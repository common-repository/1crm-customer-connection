<?php
/**
 * Breaks up data by tab characters, resulting in additional
 * calls to the data handler
 * @package Diggin_HTMLSax
 * @access protected
 */

namespace Diggin\HTMLSax;

class Tab
{
    /**
     * Original handler object
     * @var object
     * @access private
     */
    var $orig_obj;
    /**
     * Original handler method
     * @var string
     * @access private
     */
    var $orig_method;
    /**
     * Constructs Diggin_HTMLSax_Tab
     * @param object handler object being decorated
     * @param string original handler method
     * @access protected
     */
    function __construct($orig_obj, $orig_method)
    {
        $this->orig_obj = $orig_obj;
        $this->orig_method = $orig_method;
    }
    /**
     * Breaks the data up by linefeeds
     * @param Diggin_HTMLSax
     * @param string element data
     * @access protected
     */
    function breakData($parser, $data)
    {
        $data = explode("\t", $data);
        foreach ($data as $chunk) {
            $this->orig_obj->{$this->orig_method}($this, $chunk);
        }
    }
}
