<?php

namespace Diggin\HTMLSax;

/**
 * Dealing with closing Diggin tags
 * @package Diggin_HTMLSax
 * @access protected
 */
class ClosingTagState
{
    /**
     * @access protected
     */
    function parse(StateParser $context) : int
    {
        $tag = $context->scanUntilCharacters('/>');
        if ($tag != '') {
            $char = $context->scanCharacter();
            if ($char == '/') {
                $char = $context->scanCharacter();
                if ($char != '>') {
                    $context->unscanCharacter();
                }
            }
            $context->handler_object_element->
            {$context->handler_method_closing}($context->htmlsax, $tag, false);
        }

        return StateInterface::STATE_START;
    }
}
