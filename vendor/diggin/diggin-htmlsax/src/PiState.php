<?php
namespace Diggin\HTMLSax;

/**
 * Deals with Diggin processing instructions
 * @package Diggin_HTMLSax
 * @access protected
 */
class PiState
{
    /**
     * @return int - Diggin_HTMLSax_StateInterface::STATE_START
     * @access protected
     */
    function parse(StateParser $context) : int
    {
        $target = $context->scanUntilCharacters(" \n\r\t");
        $data = $context->scanUntilString('?>');
        if ($data != '') {
            $context->handler_object_pi->
            {$context->handler_method_pi}($context->htmlsax, $target, $data);
        }
        $context->IgnoreCharacter();
        $context->IgnoreCharacter();
        return StateInterface::STATE_START;
    }
}
