<?php

if (!defined('ABSPATH')) {
    exit;
}

function netctrl_register_capabilities()
{
    $role = get_role('net_control');

    if (!$role) {
        $role = add_role('net_control', __('Net Control', 'netctrl'), array('read' => true));
    }

    if ($role && !$role->has_cap('run_net')) {
        $role->add_cap('run_net');
    }

    $admin = get_role('administrator');
    if ($admin && !$admin->has_cap('run_net')) {
        $admin->add_cap('run_net');
    }
}

function netctrl_remove_capabilities()
{
    $role = get_role('net_control');
    if ($role) {
        $role->remove_cap('run_net');
    }

    $admin = get_role('administrator');
    if ($admin) {
        $admin->remove_cap('run_net');
    }
}
