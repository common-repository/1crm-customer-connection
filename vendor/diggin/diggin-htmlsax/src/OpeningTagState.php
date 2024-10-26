<?php
/**
 * Dealing with opening Diggin tags
 * @package Diggin_HTMLSax
 * @access protected
 */

namespace Diggin\HTMLSax;

class OpeningTagState
{
    /**
     * Handles attributes
     * @access protected
     * @see Diggin_HTMLSax_AttributeStartState
     */
    function parseAttributes(StateParser $context) : array
    {
        $Attributes = [];

        $context->ignoreWhitespace();
        $attributename = $context->scanUntilCharacters("=/> \n\r\t");
        while ($attributename != '') {
            $attributevalue = null;
            $context->ignoreWhitespace();
            $char = $context->scanCharacter();
            if ($char == '=') {
                $context->ignoreWhitespace();
                $char = $context->ScanCharacter();
                if ($char == '"') {
                    $attributevalue = $context->scanUntilString('"');
                    $context->IgnoreCharacter();
                } elseif ($char == "'") {
                    $attributevalue = $context->scanUntilString("'");
                    $context->IgnoreCharacter();
                } else {
                    $context->unscanCharacter();
                    $attributevalue =
                        $context->scanUntilCharacters("> \n\r\t");
                }
            } elseif ($char !== null) {
                $attributevalue = null;
                $context->unscanCharacter();
            }
            $Attributes[$attributename] = $attributevalue;

            $context->ignoreWhitespace();
            $attributename = $context->scanUntilCharacters("=/> \n\r\t");
        }
        return $Attributes;
    }

    /**
     * @return int - StateInterface::STATE_START
     * @access protected
     */
    function parse(StateParser $context) : int
    {
        $tag = $context->scanUntilCharacters("/> \n\r\t");
        if ($tag != '') {
            $Attributes = $this->parseAttributes($context);
            $char = $context->scanCharacter();
            if ($char == '/') {
                $char = $context->scanCharacter();
                if ($char != '>') {
                    $context->unscanCharacter();
                }
                $context->handler_object_element->
                {$context->handler_method_opening}($context->htmlsax, $tag,
                    $Attributes, true);
                $context->handler_object_element->
                {$context->handler_method_closing}($context->htmlsax, $tag,
                    true);
            } else {
                $context->handler_object_element->
                {$context->handler_method_opening}($context->htmlsax, $tag,
                    $Attributes, false);
            }
        }
        return StateInterface::STATE_START;
    }
}
