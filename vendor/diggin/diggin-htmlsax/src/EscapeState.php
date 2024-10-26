<?php

namespace Diggin\HTMLSax;

/**
 * Deals with Diggin escapes handling comments and CDATA correctly
 * @package Diggin_HTMLSax
 * @access protected
 */
class EscapeState
{
    /**
     * @access protected
     */
    function parse(StateParser $context) : int
    {
        $char = $context->ScanCharacter();
        if ($char == '-') {
            $char = $context->ScanCharacter();
            if ($char == '-') {
                $context->unscanCharacter();
                $context->unscanCharacter();
                $text = $context->scanUntilString('-->');
                $text .= $context->scanCharacter();
                $text .= $context->scanCharacter();
            } else {
                $context->unscanCharacter();
                $text = $context->scanUntilString('>');
            }
        } elseif ($char == '[') {
            $context->unscanCharacter();
            $text = $context->scanUntilString(']>');
            $text .= $context->scanCharacter();
        } else {
            $context->unscanCharacter();
            $text = $context->scanUntilString('>');
        }

        $context->IgnoreCharacter();
        if ($text != '') {
            $context->handler_object_escape->
            {$context->handler_method_escape}($context->htmlsax, $text);
        }
        return StateInterface::STATE_START;
    }
}
