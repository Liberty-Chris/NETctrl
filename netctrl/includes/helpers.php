<?php

if (!defined('ABSPATH')) {
    exit;
}

function netctrl_sanitize_optional($value, $callback)
{
    if ($value === null) {
        return null;
    }

    return is_callable($callback) ? call_user_func($callback, $value) : $value;
}
