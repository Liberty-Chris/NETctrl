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

    $timestamp = strtotime($datetime);

    if ($timestamp === false) {
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


function netctrl_prepare_entry_for_response(array $entry)
{
    if (!empty($entry['created_at'])) {
        $entry['created_at'] = netctrl_format_display_timestamp($entry['created_at']);
    }

    if (!empty($entry['updated_at'])) {
        $entry['updated_at'] = netctrl_format_display_timestamp($entry['updated_at']);
    }

    return $entry;
}

function netctrl_prepare_session_for_response(array $session)
{
    if (empty($session['created_at']) && !empty($session['started_at'])) {
        $session['created_at'] = $session['started_at'];
    }

    $session['recent_session_audit'] = netctrl_build_recent_session_audit_lines($session);

    foreach (array('started_at', 'created_at', 'closed_at', 'updated_at') as $field) {
        if (!empty($session[$field])) {
            $session[$field] = netctrl_format_display_timestamp($session[$field]);
        }
    }

    return $session;
}
