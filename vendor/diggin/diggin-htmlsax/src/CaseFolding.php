<?php
/**
 * Coverts tag names to upper case
 * @package Diggin_HTMLSax
 * @access protected
 */

namespace Diggin\HTMLSax;

class CaseFolding
{
    /**
     * Original handler object
     * @var object
     * @access private
     */
    var $orig_obj;
    /**
     * Original open handler method
     * @var string
     * @access private
     */
    var $orig_open_method;
    /**
     * Original close handler method
     * @var string
     * @access private
     */
    var $orig_close_method;
    /**
     * Constructs Diggin_HTMLSax_CaseFolding
     * @param object handler object being decorated
     * @param string original open handler method
     * @param string original close handler method
     * @access protected
     */
    function __construct($orig_obj, $orig_open_method, $orig_close_method)
    {
        $this->orig_obj = $orig_obj;
        $this->orig_open_method = $orig_open_method;
        $this->orig_close_method = $orig_close_method;
    }
    /**
     * Folds up open tag callbacks
     * @param Diggin_HTMLSax
     * @param string tag name
     * @param array tag attributes
     * @access protected
     */
    function foldOpen($parser, $tag, $attrs = [], $empty = false)
    {
        $this->orig_obj->{$this->orig_open_method}($parser, strtoupper($tag), $attrs, $empty);
    }
    /**
     * Folds up close tag callbacks
     * @param Diggin_HTMLSax
     * @param string tag name
     * @access protected
     */
    function foldClose($parser, $tag, $empty = false)
    {
        $this->orig_obj->{$this->orig_close_method}($parser, strtoupper($tag), $empty);
    }
}
