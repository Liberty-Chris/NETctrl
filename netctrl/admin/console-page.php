<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'netctrl_register_console_page');
add_action('admin_enqueue_scripts', 'netctrl_console_assets');

function netctrl_register_console_page()
{
    add_menu_page(
        __('Net Control Console', 'netctrl'),
        __('Net Control', 'netctrl'),
        'run_net',
        'netctrl-console',
        'netctrl_render_console_page',
        'dashicons-megaphone',
        58
    );
}

function netctrl_console_assets($hook)
{
    if ($hook !== 'toplevel_page_netctrl-console') {
        return;
    }

    wp_enqueue_style('netctrl-console', NETCTRL_URL . 'assets/css/console.css', array(), NETCTRL_VERSION);
    wp_enqueue_script('netctrl-console', NETCTRL_URL . 'assets/js/console.js', array(), NETCTRL_VERSION, true);

    wp_localize_script('netctrl-console', 'netctrlConsole', array(
        'restUrl' => esc_url_raw(rest_url('netctrl/v1')),
        'nonce' => wp_create_nonce('wp_rest'),
    ));
}

function netctrl_render_console_page()
{
    if (!current_user_can('run_net')) {
        return;
    }
    ?>
    <div class="wrap netctrl-console">
        <h1><?php esc_html_e('Net Control Console', 'netctrl'); ?></h1>
        <section class="netctrl-panel">
            <h2><?php esc_html_e('Start a Session', 'netctrl'); ?></h2>
            <label for="netctrl-net-name"><?php esc_html_e('Net name', 'netctrl'); ?></label>
            <input type="text" id="netctrl-net-name" />
            <button class="button button-primary" id="netctrl-start-session">
                <?php esc_html_e('Start Session', 'netctrl'); ?>
            </button>
        </section>

        <section class="netctrl-panel">
            <h2><?php esc_html_e('Active Session', 'netctrl'); ?></h2>
            <div id="netctrl-active-session"></div>
            <div class="netctrl-entry-form">
                <input type="text" id="netctrl-callsign" placeholder="Callsign" />
                <input type="text" id="netctrl-name" placeholder="Name" />
                <input type="text" id="netctrl-location" placeholder="Location" />
                <input type="text" id="netctrl-comments" placeholder="Comments" />
                <button class="button" id="netctrl-add-entry"><?php esc_html_e('Add Entry', 'netctrl'); ?></button>
                <button class="button" id="netctrl-close-session"><?php esc_html_e('Close Session', 'netctrl'); ?></button>
            </div>
            <h3><?php esc_html_e('Entries', 'netctrl'); ?></h3>
            <ul id="netctrl-entries"></ul>
        </section>

        <section class="netctrl-panel">
            <h2><?php esc_html_e('Recent Sessions', 'netctrl'); ?></h2>
            <ul id="netctrl-sessions"></ul>
        </section>
    </div>
    <?php
}
