<?php
/**
 * Decides which state to move one from after StartingState
 * @package Diggin_HTMLSax
 * @access protected
 */

namespace Diggin\HTMLSax;

class TagState
{
    /**
     * @return int - constant the next state to move into
     * @access protected
     */
    function parse(StateParser $context) : int
    {
        switch ($context->ScanCharacter()) {
            case '/':
                return StateInterface::STATE_CLOSING_TAG;
                break;
            case '?':
                return StateInterface::STATE_PI;
                break;
            case '%':
                return StateInterface::STATE_JASP;
                break;
            case '!':
                return StateInterface::STATE_ESCAPE;
                break;
            default:
                $context->unscanCharacter();
                return StateInterface::STATE_OPENING_TAG;
        }
    }
}
