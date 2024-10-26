<?php
namespace Diggin\HTMLSax;

/**
 * Deals with JASP/ASP markup
 * @package Diggin_HTMLSax
 * @access protected
 */
class JaspState
{
    /**
     * @access protected
     */
    function parse(StateParser $context) : int
    {
        $text = $context->scanUntilString('%>');
        if ($text != '') {
            $context->handler_object_jasp->
            {$context->handler_method_jasp}($context->htmlsax, $text);
        }
        $context->IgnoreCharacter();
        $context->IgnoreCharacter();
        return StateInterface::STATE_START;
    }
}
