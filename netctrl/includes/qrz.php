<?php

if (!defined('ABSPATH')) {
    exit;
}

function netctrl_qrz_lookup($callsign)
{
    $callsign = strtoupper(trim($callsign));
    if (empty($callsign)) {
        return null;
    }

    $api_key = get_option('netctrl_qrz_api_key');
    if (empty($api_key)) {
        return null;
    }

    $url = add_query_arg(
        array(
            's' => $api_key,
            'callsign' => $callsign,
            'format' => 'json',
        ),
        'https://xmldata.qrz.com/xml/current/'
    );

    $response = wp_remote_get($url, array('timeout' => 10));
    if (is_wp_error($response)) {
        return null;
    }

    $body = wp_remote_retrieve_body($response);
    if (empty($body)) {
        return null;
    }

    $data = json_decode($body, true);
    if (!is_array($data)) {
        return null;
    }

    if (!isset($data['QRZDatabase']['Callsign'])) {
        return null;
    }

    $callsign_data = $data['QRZDatabase']['Callsign'];

    return array(
        'name' => $callsign_data['fname'] ?? '',
        'location' => $callsign_data['addr2'] ?? '',
    );
}
