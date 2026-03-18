<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'netctrl_register_console_page');
add_action('admin_enqueue_scripts', 'netctrl_maybe_enqueue_admin_console_assets');
add_shortcode('netctrl_console', 'netctrl_render_console_shortcode');

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

function netctrl_register_console_assets()
{
    wp_register_style('netctrl-console', NETCTRL_URL . 'assets/css/console.css', array(), NETCTRL_VERSION);
    wp_register_script('netctrl-console', NETCTRL_URL . 'assets/js/console.js', array(), NETCTRL_VERSION, true);
}

function netctrl_enqueue_console_assets()
{
    netctrl_register_console_assets();

    wp_enqueue_style('netctrl-console');
    wp_enqueue_script('netctrl-console');

    wp_localize_script('netctrl-console', 'netctrlConsole', array(
        'restUrl' => esc_url_raw(rest_url('netctrl/v1')),
        'nonce' => wp_create_nonce('wp_rest'),
        'strings' => array(
            'unableToLoadSessions' => __('Unable to load sessions.', 'netctrl'),
            'unableToLoadEntries' => __('Unable to load entries.', 'netctrl'),
            'requestFailed' => __('Request failed.', 'netctrl'),
            'selectSession' => __('Select or start a session to begin logging.', 'netctrl'),
            'activeSessionLabel' => __('Current session:', 'netctrl'),
            'noRecentSessions' => __('No recent sessions found.', 'netctrl'),
            'noEntries' => __('No entries recorded yet.', 'netctrl'),
        ),
    ));
}

function netctrl_maybe_enqueue_admin_console_assets($hook)
{
    if ($hook !== 'toplevel_page_netctrl-console') {
        return;
    }

    netctrl_enqueue_console_assets();
}

function netctrl_get_console_markup($is_frontend = false)
{
    ob_start();
    ?>
    <div class="netctrl-console<?php echo $is_frontend ? ' netctrl-console--frontend' : ''; ?>" data-netctrl-console-root>
        <div class="netctrl-console__messages" aria-live="polite"></div>

        <section class="netctrl-panel">
            <h2><?php esc_html_e('Start a Session', 'netctrl'); ?></h2>
            <label for="netctrl-net-name"><?php esc_html_e('Net name', 'netctrl'); ?></label>
            <input type="text" id="netctrl-net-name" />
            <button type="button" class="button button-primary" id="netctrl-start-session">
                <?php esc_html_e('Start Session', 'netctrl'); ?>
            </button>
        </section>

        <section class="netctrl-panel">
            <h2><?php esc_html_e('Active Session', 'netctrl'); ?></h2>
            <div id="netctrl-active-session" class="netctrl-active-session">
                <?php esc_html_e('Select or start a session to begin logging.', 'netctrl'); ?>
            </div>
            <div class="netctrl-entry-form">
                <input type="text" id="netctrl-callsign" placeholder="<?php echo esc_attr__('Callsign', 'netctrl'); ?>" />
                <input type="text" id="netctrl-name" placeholder="<?php echo esc_attr__('Name', 'netctrl'); ?>" />
                <input type="text" id="netctrl-location" placeholder="<?php echo esc_attr__('Location', 'netctrl'); ?>" />
                <input type="text" id="netctrl-comments" placeholder="<?php echo esc_attr__('Comments', 'netctrl'); ?>" />
                <button type="button" class="button" id="netctrl-add-entry"><?php esc_html_e('Add Entry', 'netctrl'); ?></button>
                <button type="button" class="button" id="netctrl-close-session"><?php esc_html_e('Close Session', 'netctrl'); ?></button>
            </div>
            <h3><?php esc_html_e('Entries', 'netctrl'); ?></h3>
            <ul id="netctrl-entries" class="netctrl-list"></ul>
        </section>

        <section class="netctrl-panel">
            <h2><?php esc_html_e('Recent Sessions', 'netctrl'); ?></h2>
            <ul id="netctrl-sessions" class="netctrl-list"></ul>
        </section>
    </div>
    <?php

    return ob_get_clean();
}

function netctrl_render_console_page()
{
    if (!current_user_can('run_net')) {
        wp_die(esc_html__('You do not have permission to access NETctrl.', 'netctrl'));
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Net Control Console', 'netctrl'); ?></h1>
        <?php echo netctrl_get_console_markup(false); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </div>
    <?php
}

function netctrl_render_console_shortcode()
{
    if (!is_user_logged_in()) {
        return sprintf(
            '<div class="netctrl-console-message netctrl-console-message--notice">%s</div>',
            esc_html__('You must log in to access NETctrl.', 'netctrl')
        );
    }

    if (!current_user_can('run_net')) {
        return sprintf(
            '<div class="netctrl-console-message netctrl-console-message--error">%s</div>',
            esc_html__('Access denied. Your account is not permitted to use NETctrl.', 'netctrl')
        );
    }

    netctrl_enqueue_console_assets();

    return netctrl_get_console_markup(true);
}
