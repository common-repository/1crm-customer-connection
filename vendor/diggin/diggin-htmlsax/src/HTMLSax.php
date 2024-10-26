<?php


namespace Diggin\HTMLSax;

/* vim: set expandtab tabstop=4 shiftwidth=4: */
//
// +----------------------------------------------------------------------+
// | PHP Version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2002 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject toversion 3.0 of the PHP license,      |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/3_0.txt.                                  |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Authors: Alexander Zhukov <alex@veresk.ru> Original port from Python |
// | Authors: Harry Fuecks <hfuecks@phppatterns.com> Port to PEAR + more  |
// | Authors: Many @ Sitepointforums Advanced PHP Forums                  |
// +----------------------------------------------------------------------+
//
// $Id: HTMLSax.php,v 1.2 2007/10/29 21:41:34 hfuecks Exp $
//
/**
 * Main parser components
 * @package Diggin_HTMLSax
 * @version $Id: HTMLSax.php,v 1.2 2007/10/29 21:41:34 hfuecks Exp $
 */



/**
 * Define parser states
 */

/**
 * User interface class. All user calls should only be made to this class
 * @package Diggin_HTMLSax
 * @access public
 */
class HTMLSax
{
    /**
     * @var StateParser
     * @access private
     */
    private $state_parser;

    /**
     * Constructs Diggin_HTMLSax selecting concrete StateParser subclass
     * depending on PHP version being used as well as setting the default
     * NullHandler for all callbacks<br />
     * <b>Example:</b>
     * <pre>
     * $myHandler = & new MyHandler();
     * $parser = new Diggin_HTMLSax();
     * $parser->set_object($myHandler);
     * $parser->set_option('XML_OPTION_CASE_FOLDING');
     * $parser->set_element_handler('myOpenHandler','myCloseHandler');
     * $parser->set_data_handler('myDataHandler');
     * $parser->parser($xml);
     * </pre>
     */
    public function __construct()
    {
        $this->state_parser = new StateParser($this);

        $nullhandler = new NullHandler();
        $this->set_object($nullhandler);
        $this->set_element_handler('DoNothing', 'DoNothing');
        $this->set_data_handler('DoNothing');
        $this->set_pi_handler('DoNothing');
        $this->set_jasp_handler('DoNothing');
        $this->set_escape_handler('DoNothing');
    }

    /**
     * Sets the user defined handler object. Returns a PEAR Error
     * if supplied argument is not an object.
     * @param object handler object containing SAX callback methods
     * @access public
     * @return mixed
     */
    function set_object($object)
    {
        if (is_object($object)) {
            $this->state_parser->handler_default = $object;
            return true;
        } else {
            throw new \InvalidArgumentException('Diggin_HTMLSax::set_object requires '.
                'an object instance');
        }
    }

    /**
     * Sets a parser option. By default all options are switched off.
     * Returns a PEAR Error if option is invalid<br />
     * <b>Available options:</b>
     * <ul>
     * <li>XML_OPTION_TRIM_DATA_NODES: trim whitespace off the beginning
     * and end of data passed to the data handler</li>
     * <li>XML_OPTION_LINEFEED_BREAK: linefeeds result in additional data
     * handler calls</li>
     * <li>XML_OPTION_TAB_BREAK: tabs result in additional data handler
     * calls</li>
     * <li>XML_OPTION_ENTITIES_UNPARSED: Diggin entities are returned as
     * seperate data handler calls in unparsed form</li>
     * <li>XML_OPTION_ENTITIES_PARSED: (PHP 4.3.0+ only) Diggin entities are
     * returned as seperate data handler calls and are parsed with
     * PHP's html_entity_decode() function</li>
     * <li>XML_OPTION_STRIP_ESCAPES: strips out the -- -- comment markers
     * or CDATA markup inside an Diggin escape, if found.</li>
     * </ul>
     * To get HTMLSax to behave in the same way as the native PHP SAX parser,
     * using it's default state, you need to switch on XML_OPTION_LINEFEED_BREAK,
     * XML_OPTION_ENTITIES_PARSED and XML_OPTION_CASE_FOLDING
     * @param string name of parser option
     * @param int (optional) 1 to switch on, 0 for off
     * @access public
     * @return boolean
     */
    function set_option($name, $value = 1)
    {
        if (array_key_exists($name, $this->state_parser->parser_options)) {
            $this->state_parser->parser_options[$name] = $value;
            return true;
        } else {
            throw new \InvalidArgumentException('Diggin\HTMLSax\HTMLSax::set_option('.$name.') illegal');
        }
    }

    /**
     * Sets the data handler method which deals with the contents of Diggin
     * elements.<br />
     * The handler method must accept two arguments, the first being an
     * instance of Diggin_HTMLSax and the second being the contents of an
     * Diggin element e.g.
     * <pre>
     * function myDataHander(& $parser,$data){}
     * </pre>
     * @param string name of method
     * @access public
     * @return void
     * @see set_object
     */
    function set_data_handler($data_method)
    {
        $this->state_parser->handler_object_data = $this->state_parser->handler_default;
        $this->state_parser->handler_method_data = $data_method;
    }

    /**
     * Sets the open and close tag handlers
     * <br />The open handler method must accept three arguments; the parser,
     * the tag name and an array of attributes e.g.
     * <pre>
     * function myOpenHander(& $parser,$tagname,$attrs=array()){}
     * </pre>
     * The close handler method must accept two arguments; the parser and
     * the tag name e.g.
     * <pre>
     * function myCloseHander(& $parser,$tagname){}
     * </pre>
     * @param string name of open method
     * @param string name of close method
     * @access public
     * @return void
     * @see set_object
     */
    function set_element_handler($opening_method, $closing_method)
    {
        $this->state_parser->handler_object_element = $this->state_parser->handler_default;
        $this->state_parser->handler_method_opening = $opening_method;
        $this->state_parser->handler_method_closing = $closing_method;
    }

    /**
     * Sets the processing instruction handler method e.g. for PHP open
     * and close tags<br />
     * The handler method must accept three arguments; the parser, the
     * PI target and data inside the PI
     * <pre>
     * function myPIHander(& $parser,$target, $data){}
     * </pre>
     * @param string name of method
     * @access public
     * @return void
     * @see set_object
     */
    function set_pi_handler($pi_method)
    {
        $this->state_parser->handler_object_pi = $this->state_parser->handler_default;
        $this->state_parser->handler_method_pi = $pi_method;
    }

    /**
     * Sets the Diggin escape handler method e.g. for comments and doctype
     * declarations<br />
     * The handler method must accept two arguments; the parser and the
     * contents of the escaped section
     * <pre>
     * function myEscapeHander(& $parser, $data){}
     * </pre>
     * @param string name of method
     * @access public
     * @return void
     * @see set_object
     */
    function set_escape_handler($escape_method)
    {
        $this->state_parser->handler_object_escape = $this->state_parser->handler_default;
        $this->state_parser->handler_method_escape = $escape_method;
    }

    /**
     * Sets the JSP/ASP markup handler<br />
     * The handler method must accept two arguments; the parser and
     * body of the JASP tag
     * <pre>
     * function myJaspHander(& $parser, $data){}
     * </pre>
     * @param string name of method
     * @access public
     * @return void
     * @see set_object
     */
    function set_jasp_handler($jasp_method)
    {
        $this->state_parser->handler_object_jasp = $this->state_parser->handler_default;
        $this->state_parser->handler_method_jasp = $jasp_method;
    }

    /**
     * Returns the current string position of the "cursor" inside the Diggin
     * document
     * <br />Intended for use from within a user defined handler called
     * via the $parser reference e.g.
     * <pre>
     * function myDataHandler(& $parser,$data) {
     *     echo( 'Current position: '.$parser->get_current_position() );
     * }
     * </pre>
     * @access public
     * @return int
     * @see get_length
     */
    function get_current_position()
    {
        return $this->state_parser->position;
    }

    /**
     * Returns the string length of the Diggin document being parsed
     * @access public
     * @return int
     */
    function get_length()
    {
        return $this->state_parser->length;
    }

    /**
     * Start parsing some Diggin
     * @param string Diggin document
     * @access public
     * @return void
     */
    function parse($data)
    {
        $this->state_parser->parse($data);
    }
}
