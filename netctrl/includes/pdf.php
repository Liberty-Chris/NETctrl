<?php

if (!defined('ABSPATH')) {
    exit;
}

function netctrl_get_pdf_url($session_id)
{
    return rest_url('netctrl/v1/sessions/' . absint($session_id) . '/pdf');
}
