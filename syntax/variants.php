<?php
/**
 * DokuWiki Plugin variants (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michael Hamann <michael@content-space.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

/**
 * The variants syntax class, defines the if/else syntax
 */
class syntax_plugin_variants_variants extends DokuWiki_Syntax_Plugin {
    /**
     * @return string The plugin type
     */
    public function getType() {
        return 'container';
    }

    /**
     * @return string The paragraph type
     */
    public function getPType() {
        return 'block';
    }

    /**
     * @return int The sort number
     */
    public function getSort() {
        return 200;
    }

    /**
     * @return array The allowed types that may be used inside the plugin
     */
    public function getAllowedTypes() {
        return array('container', 'baseonly', 'formatting', 'substition', 'protected', 'disabled', 'paragraphs');
    }

    /**
     * @param string $mode The mode that shall be tested
     * @return bool If the mode is accepted inside the plugin
     */
    public function accepts($mode) {
        // allow nesting!
        if ($mode == substr(get_class($this), 7)) return true;
        return parent::accepts($mode);
    }

    /**
     * Add the patterns
     *
     * @param int $mode The current mode
     */
    public function connectTo($mode) {
        $this->Lexer->addEntryPattern('<ifvar [^=>]+=[^>]+>',$mode,'plugin_variants_variants');
        $this->Lexer->addPattern('<else>', 'plugin_variants_variants');
    }

    /**
     * Add the exit patterns
     */
    public function postConnect() {
        $this->Lexer->addExitPattern('</ifvar>','plugin_variants_variants');
    }

    /**
     * handle a syntax match
     *
     * @param string       $match The match
     * @param int          $state The current handler state
     * @param int          $pos   The position in the page
     * @param Doku_Handler $handler The handler object
     * @return bool False, this plugin doesn't need to be added by the handler
     */
    public function handle($match, $state, $pos, Doku_Handler $handler){
        switch ($state) {
            case DOKU_LEXER_ENTER:
                // setup call writer
                $condition = substr($match, 7, -1);
                $CallWriter = new syntax_plugin_variants_callwriter($handler->CallWriter, $condition, $pos);
                $handler->CallWriter =& $CallWriter;

                break;
            case DOKU_LEXER_MATCHED:
                // start else branch
                $handler->CallWriter->startElse();
                // else
                break;
            case DOKU_LEXER_UNMATCHED:
                // store cdata
                $handler->_addCall('cdata', array($match), $pos);
                break;
            case DOKU_LEXER_EXIT:
                // end call writer
                $handler->CallWriter->process();
                $ReWriter = & $handler->CallWriter;
                $handler->CallWriter = & $ReWriter->CallWriter;
                break;
        }
        return false;
    }

    /**
     * Render the output of the variants plugin
     *
     * @param string        $mode      The output mode
     * @param Doku_Renderer $renderer  The renderer
     * @param array         $data      The data from the handler
     * @return bool If anything has been rendered
     */
    public function render($mode, Doku_Renderer $renderer, $data) {
        $renderer->nocache();
        /** @var Input $INPUT */
        global $INPUT;
        list($condition, $ifcalls, $elsecalls) = $data;

        list($key, $value) = explode('=', $condition, 2);

        $condresult = true;
        if (substr($key, -1) == '!') {
            $key = substr($key, 0, -1);
            $condresult = false;
        }

        trim($key); trim($value);

        if ($INPUT->has($key) && ($INPUT->str($key) == $value) == $condresult) {
            $renderer->nest($ifcalls);
        } else {
            $renderer->nest($elsecalls);
        }

        return true;
    }
}

/**
 * Call writer for the variants plugins
 */
class syntax_plugin_variants_callwriter {
    private $ifcalls = array();
    private $elsecalls = array();
    private $condition;
    private $in_else = false;
    private $startpos;
    /** @var Doku_Handler_CallWriter $CallWriter */
    var $CallWriter;

    /**
     * Construct a new syntax plugin variants callwriter
     *
     * @param Doku_Handler_CallWriter $CallWriter The parent handler
     * @param string                  $condition The condition
     * @param int                     $startpos The position of the start of the syntax
     */
    function __construct(& $CallWriter, $condition, $startpos) {
        $this->CallWriter = & $CallWriter;
        $this->condition = $condition;
        $this->startpos = $startpos;
    }

    function startElse() {
        $this->in_else = true;
    }

    /**
     * Adds a single call
     *
     * @param array $call The call that shall be written
     */
    function writeCall($call) {
        if ($this->in_else) {
            $this->elsecalls[] = $call;
        } else {
            $this->ifcalls[] = $call;
        }
    }

    /**
     * Adds an array of calls
     *
     * @param array $calls The calls that shall be written
     */
    function writeCalls($calls) {
        if ($this->in_else) {
            $this->elsecalls = array_merge($this->elsecalls, $calls);
        } else {
            $this->ifcalls = array_merge($this->ifcalls, $calls);
        }
    }

    function finalise() {
        $this->process();
        $this->CallWriter->finalise();
        unset($this->CallWriter);
    }

    function process() {
        // process blocks
        $B = new Doku_Handler_Block();
        $this->ifcalls = $B->process($this->ifcalls);
        $B = new Doku_Handler_Block();
        $this->elsecalls = $B->process($this->elsecalls);

        $this->CallWriter->writeCall(array('plugin',array('variants_variants', array($this->condition, $this->ifcalls, $this->elsecalls), DOKU_LEXER_SPECIAL, ''), $this->startpos));
        $this->ifcalls = array();
        $this->elsecalls = array();
    }
}

// vim:ts=4:sw=4:et:
