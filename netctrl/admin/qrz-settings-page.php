<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'netctrl_register_qrz_settings_page');
add_action('admin_init', 'netctrl_handle_qrz_settings_save');

function netctrl_register_qrz_settings_page()
{
    add_submenu_page(
        'netctrl-console',
        __('NETctrl QRZ Settings', 'netctrl'),
        __('QRZ Settings', 'netctrl'),
        'manage_options',
        'netctrl-qrz-settings',
        'netctrl_render_qrz_settings_page'
    );
}

function netctrl_handle_qrz_settings_save()
{
    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }

    if (!isset($_GET['page']) || $_GET['page'] !== 'netctrl-qrz-settings') {
        return;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['netctrl_qrz_settings_save'])) {
        return;
    }

    check_admin_referer('netctrl_qrz_settings_save');

    $posted = wp_unslash($_POST);
    $existing = netctrl_get_qrz_settings();
    $settings = array(
        'enabled' => isset($posted['enabled']) ? 1 : 0,
        'username' => sanitize_text_field($posted['username'] ?? ''),
        'password' => $existing['password'],
        'agent' => sanitize_text_field($posted['agent'] ?? 'NETctrl/1.0'),
    );

    if (!empty($posted['password'])) {
        $settings['password'] = sanitize_text_field($posted['password']);
    }

    if ($settings['agent'] === '') {
        $settings['agent'] = 'NETctrl/1.0';
    }

    netctrl_update_qrz_settings($settings);
    delete_transient(NETCTRL_QRZ_SESSION_TRANSIENT);

    $status = netctrl_get_qrz_status();
    $notice = 'saved';
    $message = __('QRZ settings saved.', 'netctrl');

    if (!empty($settings['enabled'])) {
        $login = netctrl_qrz_login();

        if (is_wp_error($login)) {
            $status = netctrl_get_qrz_status();
            $notice = 'error';
            $message = $login->get_error_message();
        } else {
            $status = netctrl_get_qrz_status();
            $notice = 'connected';
            $message = __('QRZ connection verified successfully.', 'netctrl');
        }
    } else {
        netctrl_update_qrz_status(array(
            'connected' => false,
            'last_error' => '',
        ));
        $status = netctrl_get_qrz_status();
    }

    wp_safe_redirect(add_query_arg(array(
        'page' => 'netctrl-qrz-settings',
        'netctrl_qrz_notice' => $notice,
        'message' => rawurlencode($message),
    ), admin_url('admin.php')));
    exit;
}

function netctrl_render_qrz_settings_notice()
{
    if (empty($_GET['netctrl_qrz_notice'])) {
        return;
    }

    $notice = sanitize_key(wp_unslash($_GET['netctrl_qrz_notice']));
    $message = isset($_GET['message']) ? rawurldecode(wp_unslash($_GET['message'])) : __('QRZ settings updated.', 'netctrl');
    $class = 'notice notice-info';

    if ($notice === 'connected' || $notice === 'saved') {
        $class = 'notice notice-success';
    } elseif ($notice === 'error') {
        $class = 'notice notice-error';
    }
    ?>
    <div class="<?php echo esc_attr($class); ?>"><p><?php echo esc_html($message); ?></p></div>
    <?php
}

function netctrl_render_qrz_status_row($label, $value)
{
    ?>
    <tr>
        <th scope="row"><?php echo esc_html($label); ?></th>
        <td><?php echo esc_html($value !== '' ? $value : '—'); ?></td>
    </tr>
    <?php
}

function netctrl_render_qrz_settings_page()
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access QRZ settings.', 'netctrl'));
    }

    $settings = netctrl_get_qrz_settings();
    $status = netctrl_get_qrz_status();
    $connected_label = !empty($status['connected']) ? __('Connected', 'netctrl') : __('Not connected', 'netctrl');
    $last_login = '';

    if (!empty($status['last_successful_login'])) {
        $timestamp = mysql2date('U', $status['last_successful_login'], false);
        if ($timestamp) {
            $last_login = wp_date(get_option('date_format') . ' ' . get_option('time_format'), $timestamp, wp_timezone());
        }
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('NETctrl QRZ Settings', 'netctrl'); ?></h1>
        <?php netctrl_render_qrz_settings_notice(); ?>

        <div class="card" style="max-width: 960px; margin-bottom: 20px;">
            <h2><?php esc_html_e('QRZ XML Account', 'netctrl'); ?></h2>
            <form method="post">
                <?php wp_nonce_field('netctrl_qrz_settings_save'); ?>
                <input type="hidden" name="netctrl_qrz_settings_save" value="1" />
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><?php esc_html_e('Enable QRZ Lookup', 'netctrl'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="enabled" value="1" <?php checked((int) $settings['enabled'], 1); ?> />
                                    <?php esc_html_e('Use QRZ XML lookup when a callsign is not found in the local roster.', 'netctrl'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="netctrl-qrz-username"><?php esc_html_e('QRZ Username', 'netctrl'); ?></label></th>
                            <td><input id="netctrl-qrz-username" name="username" type="text" class="regular-text" value="<?php echo esc_attr($settings['username']); ?>" autocomplete="off" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="netctrl-qrz-password"><?php esc_html_e('QRZ Password', 'netctrl'); ?></label></th>
                            <td>
                                <input id="netctrl-qrz-password" name="password" type="password" class="regular-text" value="" autocomplete="new-password" />
                                <p class="description"><?php esc_html_e('Leave blank to keep the current stored password.', 'netctrl'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="netctrl-qrz-agent"><?php esc_html_e('Agent String', 'netctrl'); ?></label></th>
                            <td>
                                <input id="netctrl-qrz-agent" name="agent" type="text" class="regular-text" value="<?php echo esc_attr($settings['agent']); ?>" />
                                <p class="description"><?php esc_html_e('This identifies NETctrl to QRZ when making requests. You can leave this as the default. No changes are required.', 'netctrl'); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <?php submit_button(__('Save QRZ Settings', 'netctrl')); ?>
            </form>
        </div>

        <div class="card" style="max-width: 960px;">
            <h2><?php esc_html_e('Connection Status', 'netctrl'); ?></h2>
            <table class="widefat striped" role="presentation">
                <tbody>
                    <?php netctrl_render_qrz_status_row(__('Status', 'netctrl'), $connected_label); ?>
                    <?php netctrl_render_qrz_status_row(__('Last Error', 'netctrl'), (string) ($status['last_error'] ?? '')); ?>
                    <?php netctrl_render_qrz_status_row(__('Subscription Expiration', 'netctrl'), (string) ($status['subscription_expiration'] ?? '')); ?>
                    <?php netctrl_render_qrz_status_row(__('Last Successful Login', 'netctrl'), $last_login); ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}
