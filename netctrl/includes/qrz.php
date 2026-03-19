<?php

if (!defined('ABSPATH')) {
    exit;
}

define('NETCTRL_QRZ_SETTINGS_OPTION', 'netctrl_qrz_settings');
define('NETCTRL_QRZ_STATUS_OPTION', 'netctrl_qrz_status');
define('NETCTRL_QRZ_SESSION_TRANSIENT', 'netctrl_qrz_session_key');
define('NETCTRL_QRZ_LOOKUP_TRANSIENT_PREFIX', 'netctrl_qrz_lookup_');
define('NETCTRL_QRZ_LOOKUP_CACHE_TTL', DAY_IN_SECONDS);

function netctrl_get_qrz_settings()
{
    $settings = get_option(NETCTRL_QRZ_SETTINGS_OPTION, array());

    if (!is_array($settings)) {
        $settings = array();
    }

    return wp_parse_args($settings, array(
        'enabled' => 0,
        'username' => '',
        'password' => '',
        'agent' => 'NETctrl/1.0',
    ));
}

function netctrl_get_qrz_status()
{
    $status = get_option(NETCTRL_QRZ_STATUS_OPTION, array());

    if (!is_array($status)) {
        $status = array();
    }

    return wp_parse_args($status, array(
        'connected' => false,
        'last_error' => '',
        'subscription_expiration' => '',
        'last_successful_login' => '',
    ));
}

function netctrl_update_qrz_settings(array $settings)
{
    if (get_option(NETCTRL_QRZ_SETTINGS_OPTION, null) === null) {
        add_option(NETCTRL_QRZ_SETTINGS_OPTION, $settings, '', false);
        return;
    }

    update_option(NETCTRL_QRZ_SETTINGS_OPTION, $settings, false);
}

function netctrl_update_qrz_status(array $status)
{
    $current_status = netctrl_get_qrz_status();
    $updated_status = wp_parse_args($status, $current_status);

    if (get_option(NETCTRL_QRZ_STATUS_OPTION, null) === null) {
        add_option(NETCTRL_QRZ_STATUS_OPTION, $updated_status, '', false);
        return;
    }

    update_option(NETCTRL_QRZ_STATUS_OPTION, $updated_status, false);
}

function netctrl_qrz_is_enabled()
{
    $settings = netctrl_get_qrz_settings();

    return !empty($settings['enabled']) && $settings['username'] !== '' && $settings['password'] !== '';
}

function netctrl_split_name_parts($name)
{
    $name = trim((string) $name);

    if ($name === '') {
        return array(
            'first_name' => '',
            'last_name' => '',
        );
    }

    $parts = preg_split('/\s+/', $name, 2);

    return array(
        'first_name' => $parts[0] ?? '',
        'last_name' => $parts[1] ?? '',
    );
}

function netctrl_join_name_parts($first_name, $last_name)
{
    return trim(trim((string) $first_name) . ' ' . trim((string) $last_name));
}

function netctrl_build_lookup_location($city, $state)
{
    $city = trim((string) $city);
    $state = trim((string) $state);

    if ($city !== '' && $state !== '') {
        return sprintf('%s, %s', $city, $state);
    }

    if ($city !== '') {
        return $city;
    }

    return $state;
}

function netctrl_qrz_parse_xml_response($body)
{
    if (!is_string($body) || trim($body) === '') {
        return new WP_Error('netctrl_qrz_empty_response', __('QRZ returned an empty response.', 'netctrl'));
    }

    $previous_errors = libxml_use_internal_errors(true);
    $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);
    libxml_clear_errors();
    libxml_use_internal_errors($previous_errors);

    if ($xml === false || !isset($xml->Session)) {
        return new WP_Error('netctrl_qrz_invalid_response', __('QRZ returned an invalid XML response.', 'netctrl'));
    }

    $session = array(
        'key' => isset($xml->Session->Key) ? trim((string) $xml->Session->Key) : '',
        'error' => isset($xml->Session->Error) ? trim((string) $xml->Session->Error) : '',
        'message' => isset($xml->Session->Message) ? trim((string) $xml->Session->Message) : '',
        'subscription_expiration' => isset($xml->Session->SubExp) ? trim((string) $xml->Session->SubExp) : '',
    );

    $callsign = array();
    if (isset($xml->Callsign)) {
        $callsign = array(
            'fname' => isset($xml->Callsign->fname) ? trim((string) $xml->Callsign->fname) : '',
            'name' => isset($xml->Callsign->name) ? trim((string) $xml->Callsign->name) : '',
            'addr2' => isset($xml->Callsign->addr2) ? trim((string) $xml->Callsign->addr2) : '',
            'state' => isset($xml->Callsign->state) ? trim((string) $xml->Callsign->state) : '',
        );
    }

    return array(
        'session' => $session,
        'callsign' => $callsign,
    );
}

function netctrl_qrz_request(array $query_args)
{
    $settings = netctrl_get_qrz_settings();
    $agent = $settings['agent'] !== '' ? $settings['agent'] : 'NETctrl/1.0';

    $response = wp_remote_get(
        add_query_arg($query_args, 'https://xmldata.qrz.com/xml/current/'),
        array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => $agent,
            ),
        )
    );

    if (is_wp_error($response)) {
        return $response;
    }

    return netctrl_qrz_parse_xml_response(wp_remote_retrieve_body($response));
}

function netctrl_qrz_record_login_success(array $session)
{
    if (!empty($session['key'])) {
        set_transient(NETCTRL_QRZ_SESSION_TRANSIENT, $session['key'], 12 * HOUR_IN_SECONDS);
    }

    netctrl_update_qrz_status(array(
        'connected' => true,
        'last_error' => '',
        'subscription_expiration' => $session['subscription_expiration'] ?? '',
        'last_successful_login' => current_time('mysql'),
    ));
}

function netctrl_qrz_record_login_failure($message)
{
    delete_transient(NETCTRL_QRZ_SESSION_TRANSIENT);

    netctrl_update_qrz_status(array(
        'connected' => false,
        'last_error' => sanitize_text_field((string) $message),
    ));
}

function netctrl_qrz_login()
{
    $settings = netctrl_get_qrz_settings();

    if (empty($settings['username']) || empty($settings['password'])) {
        $error = new WP_Error('netctrl_qrz_missing_credentials', __('Enter both a QRZ username and password.', 'netctrl'));
        netctrl_qrz_record_login_failure($error->get_error_message());
        return $error;
    }

    $result = netctrl_qrz_request(array(
        'username' => $settings['username'],
        'password' => $settings['password'],
        'agent' => $settings['agent'] !== '' ? $settings['agent'] : 'NETctrl/1.0',
    ));

    if (is_wp_error($result)) {
        netctrl_qrz_record_login_failure($result->get_error_message());
        return $result;
    }

    $session = $result['session'];
    $error_message = $session['error'] ?: $session['message'];

    if (empty($session['key'])) {
        if ($error_message === '') {
            $error_message = __('QRZ did not return a session key.', 'netctrl');
        }

        $error = new WP_Error('netctrl_qrz_login_failed', $error_message);
        netctrl_qrz_record_login_failure($error_message);
        return $error;
    }

    netctrl_qrz_record_login_success($session);

    return $session;
}

function netctrl_qrz_get_session_key()
{
    $session_key = get_transient(NETCTRL_QRZ_SESSION_TRANSIENT);

    if (!empty($session_key)) {
        return $session_key;
    }

    $session = netctrl_qrz_login();

    if (is_wp_error($session)) {
        return $session;
    }

    return $session['key'];
}

function netctrl_qrz_session_error_requires_relogin($error_message)
{
    $error_message = strtolower(trim((string) $error_message));

    if ($error_message === '') {
        return false;
    }

    $needles = array(
        'session timeout',
        'invalid session key',
        'session key',
        'not found: key',
    );

    foreach ($needles as $needle) {
        if (strpos($error_message, $needle) !== false) {
            return true;
        }
    }

    return false;
}

function netctrl_qrz_fetch_callsign($callsign, $retry = true)
{
    $session_key = netctrl_qrz_get_session_key();

    if (is_wp_error($session_key)) {
        return $session_key;
    }

    $result = netctrl_qrz_request(array(
        's' => $session_key,
        'callsign' => $callsign,
    ));

    if (is_wp_error($result)) {
        return $result;
    }

    $session_error = $result['session']['error'] ?? '';
    if ($retry && netctrl_qrz_session_error_requires_relogin($session_error)) {
        delete_transient(NETCTRL_QRZ_SESSION_TRANSIENT);
        $login = netctrl_qrz_login();

        if (is_wp_error($login)) {
            return $login;
        }

        return netctrl_qrz_fetch_callsign($callsign, false);
    }

    return $result;
}

function netctrl_qrz_lookup($callsign)
{
    $callsign = netctrl_normalize_callsign($callsign);

    if ($callsign === '' || !netctrl_qrz_is_enabled()) {
        return null;
    }

    $cache_key = NETCTRL_QRZ_LOOKUP_TRANSIENT_PREFIX . md5($callsign);
    $cached = get_transient($cache_key);

    if (is_array($cached)) {
        return $cached;
    }

    $result = netctrl_qrz_fetch_callsign($callsign);

    if (is_wp_error($result)) {
        return null;
    }

    $session_error = $result['session']['error'] ?? '';
    $callsign_data = $result['callsign'];

    if ($session_error !== '' && empty($callsign_data)) {
        return null;
    }

    if (empty($callsign_data)) {
        return null;
    }

    $lookup = array(
        'first_name' => $callsign_data['fname'] ?? '',
        'last_name' => $callsign_data['name'] ?? '',
        'location' => netctrl_build_lookup_location($callsign_data['addr2'] ?? '', $callsign_data['state'] ?? ''),
    );

    if ($lookup['first_name'] === '' && $lookup['last_name'] === '' && $lookup['location'] === '') {
        return null;
    }

    set_transient($cache_key, $lookup, NETCTRL_QRZ_LOOKUP_CACHE_TTL);

    return $lookup;
}

function netctrl_lookup_callsign_data($callsign)
{
    $callsign = netctrl_normalize_callsign($callsign);

    if ($callsign === '') {
        return array(
            'found' => false,
            'callsign' => '',
        );
    }

    $roster_entry = netctrl_get_roster_entry_by_callsign($callsign);

    if ($roster_entry) {
        $name_parts = netctrl_split_name_parts($roster_entry['name'] ?? '');

        return array(
            'found' => true,
            'source' => 'roster',
            'callsign' => $callsign,
            'first_name' => $name_parts['first_name'],
            'last_name' => $name_parts['last_name'],
            'location' => $roster_entry['location'] ?? '',
        );
    }

    $lookup = netctrl_qrz_lookup($callsign);

    if ($lookup) {
        return array(
            'found' => true,
            'source' => 'qrz',
            'callsign' => $callsign,
            'first_name' => $lookup['first_name'] ?? '',
            'last_name' => $lookup['last_name'] ?? '',
            'location' => $lookup['location'] ?? '',
        );
    }

    return array(
        'found' => false,
        'callsign' => $callsign,
    );
}
