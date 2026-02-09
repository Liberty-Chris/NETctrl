<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', 'netctrl_register_routes');

function netctrl_register_routes()
{
    register_rest_route('netctrl/v1', '/sessions', array(
        array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'netctrl_rest_list_sessions',
            'permission_callback' => 'netctrl_rest_require_auth',
        ),
        array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'netctrl_rest_create_session',
            'permission_callback' => 'netctrl_rest_require_auth',
            'args' => array(
                'net_name' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ),
    ));

    register_rest_route('netctrl/v1', '/sessions/(?P<id>\d+)', array(
        array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'netctrl_rest_get_session',
            'permission_callback' => 'netctrl_rest_require_auth',
        ),
    ));

    register_rest_route('netctrl/v1', '/sessions/(?P<id>\d+)/close', array(
        array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'netctrl_rest_close_session',
            'permission_callback' => 'netctrl_rest_require_auth',
        ),
    ));

    register_rest_route('netctrl/v1', '/sessions/(?P<id>\d+)/entries', array(
        array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'netctrl_rest_list_entries',
            'permission_callback' => 'netctrl_rest_require_auth',
        ),
        array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'netctrl_rest_create_entry',
            'permission_callback' => 'netctrl_rest_require_auth',
            'args' => array(
                'callsign' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'name' => array(
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'location' => array(
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'comments' => array(
                    'required' => false,
                    'sanitize_callback' => 'sanitize_textarea_field',
                ),
            ),
        ),
    ));

    register_rest_route('netctrl/v1', '/sessions/(?P<id>\d+)/pdf', array(
        array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'netctrl_rest_download_pdf',
            'permission_callback' => 'netctrl_rest_require_auth',
        ),
    ));
}

function netctrl_rest_require_auth()
{
    return current_user_can('run_net');
}

function netctrl_rest_list_sessions()
{
    return rest_ensure_response(netctrl_get_sessions());
}

function netctrl_rest_get_session(WP_REST_Request $request)
{
    $session = netctrl_get_session((int) $request['id']);
    if (!$session) {
        return new WP_Error('netctrl_not_found', __('Session not found.', 'netctrl'), array('status' => 404));
    }

    return rest_ensure_response($session);
}

function netctrl_rest_create_session(WP_REST_Request $request)
{
    $net_name = $request->get_param('net_name');
    $session_id = netctrl_create_session($net_name);

    return rest_ensure_response(array(
        'id' => $session_id,
        'session' => netctrl_get_session($session_id),
    ));
}

function netctrl_rest_close_session(WP_REST_Request $request)
{
    $session_id = (int) $request['id'];
    netctrl_close_session($session_id);

    return rest_ensure_response(netctrl_get_session($session_id));
}

function netctrl_rest_list_entries(WP_REST_Request $request)
{
    $session_id = (int) $request['id'];

    return rest_ensure_response(netctrl_get_entries($session_id));
}

function netctrl_rest_create_entry(WP_REST_Request $request)
{
    $session_id = (int) $request['id'];
    $callsign = $request->get_param('callsign');

    $entry = array(
        'callsign' => $callsign,
        'name' => $request->get_param('name'),
        'location' => $request->get_param('location'),
        'comments' => $request->get_param('comments'),
    );

    if (empty($entry['name']) || empty($entry['location'])) {
        $lookup = netctrl_qrz_lookup($callsign);
        if ($lookup) {
            if (empty($entry['name'])) {
                $entry['name'] = $lookup['name'];
            }
            if (empty($entry['location'])) {
                $entry['location'] = $lookup['location'];
            }
        }
    }

    $entry_id = netctrl_add_entry($session_id, $entry);

    return rest_ensure_response(array(
        'id' => $entry_id,
        'entry' => $entry,
    ));
}

function netctrl_rest_download_pdf(WP_REST_Request $request)
{
    $session_id = (int) $request['id'];
    $entries = netctrl_get_entries($session_id);

    $content = "NETctrl Session {$session_id}\n";
    foreach ($entries as $entry) {
        $content .= sprintf(
            "%s - %s %s (%s)\n",
            $entry['created_at'],
            $entry['callsign'],
            $entry['name'],
            $entry['location']
        );
    }

    return new WP_REST_Response($content, 200, array(
        'Content-Type' => 'application/octet-stream',
        'Content-Disposition' => 'attachment; filename=\"netctrl-session-' . $session_id . '.txt\"',
    ));
}
