<?php
/**
 * Trims the contents of element data from whitespace at start and end
 * @package Diggin_HTMLSax
 * @access protected
 */

namespace Diggin\HTMLSax;

class Trim
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
     * Trims the data
     * @param Diggin_HTMLSax
     * @param string element data
     * @access protected
     */
    function trimData($parser, $data)
    {
        $data = trim($data);
        if ($data != '') {
            $this->orig_obj->{$this->orig_method}($parser, $data);
        }
    }
}
