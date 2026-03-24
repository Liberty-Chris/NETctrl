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
    $roster_table = netctrl_get_table('roster');

    $sql_sessions = "CREATE TABLE {$sessions_table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        net_name VARCHAR(190) NOT NULL,
        started_at DATETIME NOT NULL,
        created_at DATETIME NOT NULL,
        created_by BIGINT UNSIGNED NULL,
        closed_at DATETIME NULL,
        closed_by BIGINT UNSIGNED NULL,
        updated_at DATETIME NULL,
        updated_by BIGINT UNSIGNED NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'open',
        PRIMARY KEY  (id)
    ) {$charset_collate};";

    $sql_entries = "CREATE TABLE {$entries_table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        session_id BIGINT UNSIGNED NOT NULL,
        callsign VARCHAR(50) NOT NULL,
        name VARCHAR(190) NULL,
        location VARCHAR(190) NULL,
        checkin_type VARCHAR(30) NOT NULL DEFAULT 'short_time_no_traffic',
        has_announcement TINYINT(1) NOT NULL DEFAULT 0,
        has_traffic TINYINT(1) NOT NULL DEFAULT 0,
        announcement_details TEXT NULL,
        traffic_details TEXT NULL,
        comments TEXT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        KEY session_id (session_id)
    ) {$charset_collate};";

    $sql_roster = "CREATE TABLE {$roster_table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        callsign VARCHAR(50) NOT NULL,
        name VARCHAR(190) NULL,
        location VARCHAR(190) NULL,
        license_class VARCHAR(50) NULL,
        is_member TINYINT(1) NOT NULL DEFAULT 0,
        is_officer TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY callsign (callsign)
    ) {$charset_collate};";

    dbDelta($sql_sessions);
    dbDelta($sql_entries);
    dbDelta($sql_roster);

    $wpdb->query("UPDATE {$sessions_table} SET created_at = started_at WHERE created_at IS NULL OR created_at = '0000-00-00 00:00:00'");

    update_option('netctrl_db_version', NETCTRL_DB_VERSION);
}

function netctrl_maybe_upgrade_db()
{
    $installed_version = get_option('netctrl_db_version');

    if ($installed_version !== NETCTRL_DB_VERSION) {
        netctrl_install_tables();
    }
}

function netctrl_normalize_callsign($callsign)
{
    return strtoupper(trim((string) $callsign));
}

function netctrl_normalize_roster_flag($value)
{
    if (is_bool($value)) {
        return $value ? 1 : 0;
    }

    if (is_int($value)) {
        return $value === 1 ? 1 : 0;
    }

    if (is_float($value)) {
        return (float) $value === 1.0 ? 1 : 0;
    }

    $normalized = strtolower(trim((string) $value));

    if ($normalized === '') {
        return 0;
    }

    return in_array($normalized, array('1', 'yes', 'y', 'true', 'on', 'member', 'officer', 'active', 'x'), true) ? 1 : 0;
}

function netctrl_normalize_license_class($value)
{
    $normalized = strtoupper(trim((string) $value));

    if ($normalized === '') {
        return '';
    }

    $aliases = array(
        'T' => 'T',
        'TECH' => 'T',
        'TECHNICIAN' => 'T',
        'G' => 'G',
        'GEN' => 'G',
        'GENERAL' => 'G',
        'E' => 'E',
        'EX' => 'E',
        'EXTRA' => 'E',
        'AMATEUR EXTRA' => 'E',
        'A' => 'A',
        'ADVANCED' => 'A',
        'N' => 'N',
        'NOVICE' => 'N',
    );

    return $aliases[$normalized] ?? sanitize_text_field($value);
}

function netctrl_normalize_roster_csv_column($column)
{
    $column = strtolower(trim((string) $column));
    $column = preg_replace('/^\x{feff}/u', '', $column);
    $column = preg_replace('/[^a-z0-9]+/', '_', $column);
    $column = trim((string) $column, '_');

    $aliases = array(
        'call_sign' => 'callsign',
        'call' => 'callsign',
        'member' => 'is_member',
        'members' => 'is_member',
        'officer' => 'is_officer',
        'officers' => 'is_officer',
        'license' => 'license_class',
        'licenseclass' => 'license_class',
        'licence_class' => 'license_class',
        'class' => 'license_class',
    );

    return $aliases[$column] ?? $column;
}

function netctrl_prepare_roster_entry(array $entry)
{
    return array(
        'callsign' => netctrl_normalize_callsign($entry['callsign'] ?? ''),
        'name' => sanitize_text_field($entry['name'] ?? ''),
        'location' => sanitize_text_field($entry['location'] ?? ''),
        'license_class' => netctrl_normalize_license_class($entry['license_class'] ?? ''),
        'is_member' => netctrl_normalize_roster_flag($entry['is_member'] ?? 0),
        'is_officer' => netctrl_normalize_roster_flag($entry['is_officer'] ?? 0),
    );
}

function netctrl_upsert_roster_entry(array $entry)
{
    global $wpdb;

    $table = netctrl_get_table('roster');
    $prepared = netctrl_prepare_roster_entry($entry);

    if ($prepared['callsign'] === '') {
        return false;
    }

    $existing = netctrl_get_roster_entry_by_callsign($prepared['callsign']);
    $timestamp = current_time('mysql');

    if ($existing) {
        $updated = $wpdb->update(
            $table,
            array(
                'name' => $prepared['name'],
                'location' => $prepared['location'],
                'license_class' => $prepared['license_class'],
                'is_member' => $prepared['is_member'],
                'is_officer' => $prepared['is_officer'],
                'updated_at' => $timestamp,
            ),
            array('id' => $existing['id']),
            array('%s', '%s', '%s', '%d', '%d', '%s'),
            array('%d')
        );

        return $updated === false ? false : (int) $existing['id'];
    }

    $inserted = $wpdb->insert(
        $table,
        array(
            'callsign' => $prepared['callsign'],
            'name' => $prepared['name'],
            'location' => $prepared['location'],
            'license_class' => $prepared['license_class'],
            'is_member' => $prepared['is_member'],
            'is_officer' => $prepared['is_officer'],
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ),
        array('%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s')
    );

    return $inserted ? (int) $wpdb->insert_id : false;
}

function netctrl_import_roster_csv($file_path)
{
    $handle = fopen($file_path, 'r');

    if (!$handle) {
        return new WP_Error('netctrl_roster_csv_open_failed', __('Unable to open the uploaded CSV file.', 'netctrl'));
    }

    $header = fgetcsv($handle);
    if (!$header) {
        fclose($handle);
        return new WP_Error('netctrl_roster_csv_empty', __('The uploaded CSV file is empty.', 'netctrl'));
    }

    $columns = array_map('netctrl_normalize_roster_csv_column', $header);

    $required_callsign_column = array_search('callsign', $columns, true);
    if ($required_callsign_column === false) {
        fclose($handle);
        return new WP_Error('netctrl_roster_csv_invalid', __('The CSV file must include a callsign column.', 'netctrl'));
    }

    $result = array(
        'processed' => 0,
        'saved' => 0,
        'skipped' => 0,
    );

    while (($row = fgetcsv($handle)) !== false) {
        if ($row === array(null) || $row === false) {
            continue;
        }

        $mapped = array();
        foreach ($columns as $index => $column) {
            if ($column === '') {
                continue;
            }

            $mapped[$column] = $row[$index] ?? '';
        }

        $result['processed']++;

        if (netctrl_upsert_roster_entry($mapped) === false) {
            $result['skipped']++;
            continue;
        }

        $result['saved']++;
    }

    fclose($handle);

    return $result;
}

function netctrl_get_roster_entries()
{
    global $wpdb;
    $table = netctrl_get_table('roster');

    return $wpdb->get_results("SELECT * FROM {$table} ORDER BY callsign ASC", ARRAY_A);
}

function netctrl_get_roster_entry_by_callsign($callsign)
{
    global $wpdb;

    $table = netctrl_get_table('roster');
    $normalized_callsign = netctrl_normalize_callsign($callsign);

    if ($normalized_callsign === '') {
        return null;
    }

    return $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$table} WHERE callsign = %s", $normalized_callsign),
        ARRAY_A
    );
}

function netctrl_get_roster_entry($roster_id)
{
    global $wpdb;

    $table = netctrl_get_table('roster');
    $roster_id = (int) $roster_id;

    if ($roster_id <= 0) {
        return null;
    }

    return $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $roster_id),
        ARRAY_A
    );
}

function netctrl_create_roster_entry(array $entry)
{
    global $wpdb;

    $table = netctrl_get_table('roster');
    $prepared = netctrl_prepare_roster_entry($entry);

    if ($prepared['callsign'] === '') {
        return new WP_Error('netctrl_roster_callsign_required', __('Callsign is required.', 'netctrl'));
    }

    if (netctrl_get_roster_entry_by_callsign($prepared['callsign'])) {
        return new WP_Error('netctrl_roster_duplicate_callsign', __('A roster entry with that callsign already exists.', 'netctrl'));
    }

    $timestamp = current_time('mysql');
    $inserted = $wpdb->insert(
        $table,
        array(
            'callsign' => $prepared['callsign'],
            'name' => $prepared['name'],
            'location' => $prepared['location'],
            'license_class' => $prepared['license_class'],
            'is_member' => $prepared['is_member'],
            'is_officer' => $prepared['is_officer'],
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ),
        array('%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s')
    );

    if (!$inserted) {
        return new WP_Error('netctrl_roster_insert_failed', __('Unable to save the roster entry.', 'netctrl'));
    }

    return (int) $wpdb->insert_id;
}

function netctrl_update_roster_entry($roster_id, array $entry)
{
    global $wpdb;

    $table = netctrl_get_table('roster');
    $roster_id = (int) $roster_id;
    $existing = netctrl_get_roster_entry($roster_id);

    if (!$existing) {
        return new WP_Error('netctrl_roster_not_found', __('Roster entry not found.', 'netctrl'));
    }

    $prepared = netctrl_prepare_roster_entry($entry);

    if ($prepared['callsign'] === '') {
        return new WP_Error('netctrl_roster_callsign_required', __('Callsign is required.', 'netctrl'));
    }

    $duplicate = netctrl_get_roster_entry_by_callsign($prepared['callsign']);
    if ($duplicate && (int) $duplicate['id'] !== $roster_id) {
        return new WP_Error('netctrl_roster_duplicate_callsign', __('A roster entry with that callsign already exists.', 'netctrl'));
    }

    $updated = $wpdb->update(
        $table,
        array(
            'callsign' => $prepared['callsign'],
            'name' => $prepared['name'],
            'location' => $prepared['location'],
            'license_class' => $prepared['license_class'],
            'is_member' => $prepared['is_member'],
            'is_officer' => $prepared['is_officer'],
            'updated_at' => current_time('mysql'),
        ),
        array('id' => $roster_id),
        array('%s', '%s', '%s', '%s', '%d', '%d', '%s'),
        array('%d')
    );

    if ($updated === false) {
        return new WP_Error('netctrl_roster_update_failed', __('Unable to update the roster entry.', 'netctrl'));
    }

    return $roster_id;
}

function netctrl_delete_roster_entry($roster_id)
{
    global $wpdb;
    $table = netctrl_get_table('roster');

    return $wpdb->delete(
        $table,
        array('id' => $roster_id),
        array('%d')
    );
}

function netctrl_get_current_open_session()
{
    $sessions = netctrl_get_sessions('open');

    return $sessions ? $sessions[0] : null;
}

function netctrl_create_session($net_name)
{
    global $wpdb;
    $table = netctrl_get_table('sessions');
    $timestamp = current_time('mysql');
    $user_id = get_current_user_id();
    $existing_open_session = netctrl_get_current_open_session();

    if ($existing_open_session) {
        return new WP_Error('netctrl_session_already_open', __('A live session is already in progress.', 'netctrl'), array('status' => 409));
    }

    $inserted = $wpdb->insert(
        $table,
        array(
            'net_name' => sanitize_text_field($net_name),
            'started_at' => $timestamp,
            'created_at' => $timestamp,
            'created_by' => $user_id ?: null,
            'status' => 'open',
        ),
        array('%s', '%s', '%s', '%d', '%s')
    );

    if (!$inserted) {
        return new WP_Error('netctrl_session_create_failed', __('Unable to start the session.', 'netctrl'));
    }

    return (int) $wpdb->insert_id;
}

function netctrl_close_session($session_id)
{
    global $wpdb;
    $table = netctrl_get_table('sessions');
    $timestamp = current_time('mysql');
    $user_id = get_current_user_id();

    return $wpdb->update(
        $table,
        array(
            'status' => 'closed',
            'closed_at' => $timestamp,
            'closed_by' => $user_id ?: null,
            'updated_at' => $timestamp,
            'updated_by' => $user_id ?: null,
        ),
        array('id' => $session_id),
        array('%s', '%s', '%d', '%s', '%d'),
        array('%d')
    );
}

function netctrl_touch_session($session_id)
{
    global $wpdb;

    $table = netctrl_get_table('sessions');
    $timestamp = current_time('mysql');
    $user_id = get_current_user_id();

    return $wpdb->update(
        $table,
        array(
            'updated_at' => $timestamp,
            'updated_by' => $user_id ?: null,
        ),
        array('id' => $session_id),
        array('%s', '%d'),
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

function netctrl_get_recent_closed_sessions($limit = 5)
{
    global $wpdb;
    $table = netctrl_get_table('sessions');
    $limit = max(1, (int) $limit);

    return $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM {$table} WHERE status = %s ORDER BY closed_at DESC, started_at DESC LIMIT %d", 'closed', $limit),
        ARRAY_A
    );
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
            'checkin_type' => $entry['checkin_type'],
            'has_announcement' => !empty($entry['has_announcement']) ? 1 : 0,
            'has_traffic' => !empty($entry['has_traffic']) ? 1 : 0,
            'announcement_details' => $entry['announcement_details'],
            'traffic_details' => $entry['traffic_details'],
            'comments' => $entry['comments'],
            'created_at' => current_time('mysql'),
        ),
        array('%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s')
    );

    if ($wpdb->insert_id) {
        netctrl_touch_session($session_id);
    }

    return (int) $wpdb->insert_id;
}

function netctrl_update_entry($entry_id, array $entry)
{
    global $wpdb;
    $table = netctrl_get_table('entries');
    $existing = netctrl_get_entry($entry_id);

    $updated = $wpdb->update(
        $table,
        array(
            'callsign' => $entry['callsign'],
            'name' => $entry['name'],
            'location' => $entry['location'],
            'checkin_type' => $entry['checkin_type'],
            'has_announcement' => !empty($entry['has_announcement']) ? 1 : 0,
            'has_traffic' => !empty($entry['has_traffic']) ? 1 : 0,
            'announcement_details' => $entry['announcement_details'],
            'traffic_details' => $entry['traffic_details'],
            'comments' => $entry['comments'],
        ),
        array('id' => $entry_id),
        array('%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s'),
        array('%d')
    );

    if ($updated !== false && $existing) {
        netctrl_touch_session((int) $existing['session_id']);
    }

    return $updated;
}

function netctrl_delete_entry($entry_id)
{
    global $wpdb;
    $table = netctrl_get_table('entries');
    $existing = netctrl_get_entry($entry_id);

    $deleted = $wpdb->delete(
        $table,
        array('id' => $entry_id),
        array('%d')
    );

    if ($deleted && $existing) {
        netctrl_touch_session((int) $existing['session_id']);
    }

    return $deleted;
}

function netctrl_get_entry($entry_id)
{
    global $wpdb;
    $table = netctrl_get_table('entries');

    return $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $entry_id),
        ARRAY_A
    );
}

function netctrl_get_entries($session_id)
{
    global $wpdb;
    $table = netctrl_get_table('entries');

    return $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM {$table} WHERE session_id = %d ORDER BY created_at ASC, id ASC", $session_id),
        ARRAY_A
    );
}
