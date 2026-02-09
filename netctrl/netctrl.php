<?php
/**
 * Plugin Name: NETctrl
 * Description: Amateur radio net control logging.
 * Version: 0.1.0
 * Author: NETctrl
 * Text Domain: netctrl
 */

if (!defined('ABSPATH')) {
    exit;
}

define('NETCTRL_VERSION', '0.1.0');

define('NETCTRL_PATH', plugin_dir_path(__FILE__));
define('NETCTRL_URL', plugin_dir_url(__FILE__));

define('NETCTRL_DB_VERSION', '1.0');

require_once NETCTRL_PATH . 'includes/db.php';
require_once NETCTRL_PATH . 'includes/capabilities.php';
require_once NETCTRL_PATH . 'includes/helpers.php';
require_once NETCTRL_PATH . 'includes/qrz.php';
require_once NETCTRL_PATH . 'includes/pdf.php';
require_once NETCTRL_PATH . 'includes/rest.php';
require_once NETCTRL_PATH . 'admin/console-page.php';
require_once NETCTRL_PATH . 'public/shortcode-log.php';

register_activation_hook(__FILE__, 'netctrl_activate_plugin');
register_deactivation_hook(__FILE__, 'netctrl_deactivate_plugin');

function netctrl_activate_plugin()
{
    netctrl_install_tables();
    netctrl_register_capabilities();
}

function netctrl_deactivate_plugin()
{
    netctrl_remove_capabilities();
}
