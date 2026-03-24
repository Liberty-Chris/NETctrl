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

function netctrl_get_user_login_label($user_id)
{
    $user_id = (int) $user_id;

    if ($user_id <= 0) {
        return '';
    }

    $user = get_userdata($user_id);

    if (!$user) {
        return '';
    }

    return (string) $user->user_login;
}

function netctrl_format_display_timestamp($datetime)
{
    $datetime = trim((string) $datetime);

    if ($datetime === '' || $datetime === '0000-00-00 00:00:00') {
        return '';
    }

    $timestamp = mysql2date('U', $datetime, true);

    if (!$timestamp) {
        return '';
    }

    if (wp_date('Y-m-d', $timestamp) === wp_date('Y-m-d')) {
        return wp_date('g:i A', $timestamp);
    }

    return wp_date('m/d/y g:i A', $timestamp);
}

function netctrl_format_recent_session_timestamp($datetime)
{
    return netctrl_format_display_timestamp($datetime);
}

function netctrl_build_recent_session_audit_lines(array $session)
{
    $audit_lines = array();
    $events = array(
        array(
            'label' => __('created by', 'netctrl'),
            'user_id' => $session['created_by'] ?? 0,
            'timestamp' => $session['created_at'] ?? '',
        ),
        array(
            'label' => __('closed by', 'netctrl'),
            'user_id' => $session['closed_by'] ?? 0,
            'timestamp' => $session['closed_at'] ?? '',
        ),
        array(
            'label' => __('last edited by', 'netctrl'),
            'user_id' => $session['updated_by'] ?? 0,
            'timestamp' => $session['updated_at'] ?? '',
        ),
    );

    foreach ($events as $event) {
        $username = netctrl_get_user_login_label($event['user_id']);
        $formatted_timestamp = netctrl_format_recent_session_timestamp($event['timestamp']);

        if ($username === '' || $formatted_timestamp === '') {
            continue;
        }

        $audit_lines[] = sprintf('%s %s %s', $event['label'], $username, $formatted_timestamp);
    }

    return $audit_lines;
}

function netctrl_get_status_label($status)
{
    return $status === 'open' ? __('Live', 'netctrl') : __('Closed', 'netctrl');
}

function netctrl_get_status_description(array $session)
{
    if (($session['status'] ?? '') === 'open') {
        return __('Live session in progress', 'netctrl');
    }

    if (!empty($session['closed_at'])) {
        return sprintf(
            __('Closed %s', 'netctrl'),
            netctrl_format_display_timestamp($session['closed_at'])
        );
    }

    return __('Session complete', 'netctrl');
}

function netctrl_prepare_entry_for_response(array $entry)
{
    $checkin_type = netctrl_normalize_checkin_type($entry['checkin_type'] ?? '');
    $has_announcement = !empty($entry['has_announcement']);
    $has_traffic = !empty($entry['has_traffic']);
    $announcement_details = trim((string) ($entry['announcement_details'] ?? ''));
    $traffic_details = trim((string) ($entry['traffic_details'] ?? ''));

    if ($checkin_type !== 'regular') {
        $has_announcement = false;
        $has_traffic = false;
        $announcement_details = '';
        $traffic_details = '';
    }

    if (!$has_announcement) {
        $announcement_details = '';
    }

    if (!$has_traffic) {
        $traffic_details = '';
    }

    $entry['checkin_type'] = $checkin_type;
    $entry['has_announcement'] = $has_announcement;
    $entry['has_traffic'] = $has_traffic;
    $entry['announcement_details'] = $announcement_details;
    $entry['traffic_details'] = $traffic_details;
    $entry['legacy_comments'] = trim((string) ($entry['comments'] ?? ''));
    $entry['created_at_raw'] = $entry['created_at'] ?? '';
    $entry['updated_at_raw'] = $entry['updated_at'] ?? '';

    if (!empty($entry['created_at'])) {
        $entry['created_at'] = netctrl_format_display_timestamp($entry['created_at']);
    }

    if (!empty($entry['updated_at'])) {
        $entry['updated_at'] = netctrl_format_display_timestamp($entry['updated_at']);
    }

    return $entry;
}

function netctrl_normalize_checkin_type($value)
{
    $normalized = strtolower(trim((string) $value));

    if (in_array($normalized, array('regular', 'full', 'standard'), true)) {
        return 'regular';
    }

    return 'short_time_no_traffic';
}

function netctrl_prepare_session_for_response(array $session)
{
    if (empty($session['created_at']) && !empty($session['started_at'])) {
        $session['created_at'] = $session['started_at'];
    }

    $session['started_at_raw'] = $session['started_at'] ?? '';
    $session['created_at_raw'] = $session['created_at'] ?? '';
    $session['closed_at_raw'] = $session['closed_at'] ?? '';
    $session['updated_at_raw'] = $session['updated_at'] ?? '';
    $session['recent_session_audit'] = netctrl_build_recent_session_audit_lines($session);
    $session['status_label'] = netctrl_get_status_label($session['status'] ?? 'closed');
    $session['status_description'] = netctrl_get_status_description($session);

    foreach (array('started_at', 'created_at', 'closed_at', 'updated_at') as $field) {
        if (!empty($session[$field])) {
            $session[$field] = netctrl_format_display_timestamp($session[$field]);
        }
    }

    return $session;
}

function netctrl_prepare_public_session_payload(array $session)
{
    $prepared = netctrl_prepare_session_for_response($session);
    $prepared['entries'] = array_map('netctrl_prepare_entry_for_response', netctrl_get_entries((int) $session['id']));
    $prepared['pdf_url'] = ($session['status'] ?? '') === 'closed' ? netctrl_get_pdf_url((int) $session['id']) : '';

    return $prepared;
}

function netctrl_get_public_sessions_payload($closed_limit = 5)
{
    $open_sessions = array_map('netctrl_prepare_public_session_payload', netctrl_get_sessions('open'));
    $closed_sessions = array_map('netctrl_prepare_public_session_payload', netctrl_get_recent_closed_sessions((int) $closed_limit));

    return array(
        'open_sessions' => $open_sessions,
        'closed_sessions' => $closed_sessions,
        'generated_at' => current_time('mysql'),
    );
}
