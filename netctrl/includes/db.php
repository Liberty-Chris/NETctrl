<?php

if (!defined('ABSPATH')) {
    exit;
}

function netctrl_get_table($name)
{
    global $wpdb;
    return $wpdb->prefix . 'netctrl_' . $name;
}

function netctrl_install_tables()
{
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();
    $sessions_table = netctrl_get_table('sessions');
    $entries_table = netctrl_get_table('entries');

    $sql_sessions = "CREATE TABLE {$sessions_table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        net_name VARCHAR(190) NOT NULL,
        started_at DATETIME NOT NULL,
        closed_at DATETIME NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'open',
        PRIMARY KEY  (id)
    ) {$charset_collate};";

    $sql_entries = "CREATE TABLE {$entries_table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        session_id BIGINT UNSIGNED NOT NULL,
        callsign VARCHAR(50) NOT NULL,
        name VARCHAR(190) NULL,
        location VARCHAR(190) NULL,
        comments TEXT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        KEY session_id (session_id)
    ) {$charset_collate};";

    dbDelta($sql_sessions);
    dbDelta($sql_entries);

    update_option('netctrl_db_version', NETCTRL_DB_VERSION);
}

function netctrl_create_session($net_name)
{
    global $wpdb;
    $table = netctrl_get_table('sessions');

    $wpdb->insert(
        $table,
        array(
            'net_name' => $net_name,
            'started_at' => current_time('mysql'),
            'status' => 'open',
        ),
        array('%s', '%s', '%s')
    );

    return $wpdb->insert_id;
}

function netctrl_close_session($session_id)
{
    global $wpdb;
    $table = netctrl_get_table('sessions');

    return $wpdb->update(
        $table,
        array(
            'status' => 'closed',
            'closed_at' => current_time('mysql'),
        ),
        array('id' => $session_id),
        array('%s', '%s'),
        array('%d')
    );
}

function netctrl_get_sessions($status = null)
{
    global $wpdb;
    $table = netctrl_get_table('sessions');

    if ($status) {
        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} WHERE status = %s ORDER BY started_at DESC", $status),
            ARRAY_A
        );
    }

    return $wpdb->get_results("SELECT * FROM {$table} ORDER BY started_at DESC", ARRAY_A);
}

function netctrl_get_session($session_id)
{
    global $wpdb;
    $table = netctrl_get_table('sessions');

    return $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $session_id),
        ARRAY_A
    );
}

function netctrl_add_entry($session_id, array $entry)
{
    global $wpdb;
    $table = netctrl_get_table('entries');

    $wpdb->insert(
        $table,
        array(
            'session_id' => $session_id,
            'callsign' => $entry['callsign'],
            'name' => $entry['name'],
            'location' => $entry['location'],
            'comments' => $entry['comments'],
            'created_at' => current_time('mysql'),
        ),
        array('%d', '%s', '%s', '%s', '%s', '%s')
    );

    return $wpdb->insert_id;
}

function netctrl_get_entries($session_id)
{
    global $wpdb;
    $table = netctrl_get_table('entries');

    return $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM {$table} WHERE session_id = %d ORDER BY created_at ASC", $session_id),
        ARRAY_A
    );
}
