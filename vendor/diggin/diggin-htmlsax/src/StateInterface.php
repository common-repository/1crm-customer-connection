<?php

namespace Diggin\HTMLSax;

interface StateInterface
{
    const STATE_STOP = 0;
    const STATE_START = 1;
    const STATE_TAG = 2;
    const STATE_OPENING_TAG = 3;
    const STATE_CLOSING_TAG = 4;
    const STATE_ESCAPE = 6;
    const STATE_JASP = 7;
    const STATE_PI = 8;
}
