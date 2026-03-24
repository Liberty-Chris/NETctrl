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
                'checkin_type' => array(
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'has_announcement' => array(
                    'required' => false,
                ),
                'has_traffic' => array(
                    'required' => false,
                ),
                'announcement_details' => array(
                    'required' => false,
                    'sanitize_callback' => 'sanitize_textarea_field',
                ),
                'traffic_details' => array(
                    'required' => false,
                    'sanitize_callback' => 'sanitize_textarea_field',
                ),
            ),
        ),
    ));

    register_rest_route('netctrl/v1', '/entries/(?P<id>\d+)', array(
        array(
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => 'netctrl_rest_update_entry',
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
                'checkin_type' => array(
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'has_announcement' => array(
                    'required' => false,
                ),
                'has_traffic' => array(
                    'required' => false,
                ),
                'announcement_details' => array(
                    'required' => false,
                    'sanitize_callback' => 'sanitize_textarea_field',
                ),
                'traffic_details' => array(
                    'required' => false,
                    'sanitize_callback' => 'sanitize_textarea_field',
                ),
            ),
        ),
        array(
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => 'netctrl_rest_delete_entry',
            'permission_callback' => 'netctrl_rest_require_auth',
        ),
    ));

    register_rest_route('netctrl/v1', '/sessions/(?P<id>\d+)/pdf', array(
        array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'netctrl_rest_download_pdf',
            'permission_callback' => 'netctrl_rest_require_auth',
        ),
    ));

    register_rest_route('netctrl/v1', '/lookup/callsign', array(
        array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'netctrl_rest_lookup_callsign',
            'permission_callback' => 'netctrl_rest_require_auth',
            'args' => array(
                'callsign' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ),
    ));
}

function netctrl_rest_require_auth()
{
    return current_user_can('run_net');
}

function netctrl_rest_list_sessions()
{
    $sessions = array_map('netctrl_prepare_session_for_response', netctrl_get_sessions());

    return rest_ensure_response($sessions);
}

function netctrl_rest_get_session(WP_REST_Request $request)
{
    $session = netctrl_get_session((int) $request['id']);
    if (!$session) {
        return new WP_Error('netctrl_not_found', __('Session not found.', 'netctrl'), array('status' => 404));
    }

    return rest_ensure_response(netctrl_prepare_session_for_response($session));
}

function netctrl_rest_create_session(WP_REST_Request $request)
{
    $net_name = $request->get_param('net_name');
    $session_id = netctrl_create_session($net_name);

    if (is_wp_error($session_id)) {
        return $session_id;
    }

    return rest_ensure_response(array(
        'id' => $session_id,
        'session' => netctrl_prepare_session_for_response(netctrl_get_session($session_id)),
    ));
}

function netctrl_rest_close_session(WP_REST_Request $request)
{
    $session_id = (int) $request['id'];
    netctrl_close_session($session_id);

    return rest_ensure_response(netctrl_prepare_session_for_response(netctrl_get_session($session_id)));
}

function netctrl_rest_list_entries(WP_REST_Request $request)
{
    $session_id = (int) $request['id'];

    return rest_ensure_response(array_map('netctrl_prepare_entry_for_response', netctrl_get_entries($session_id)));
}

function netctrl_rest_create_entry(WP_REST_Request $request)
{
    $session_id = (int) $request['id'];
    $entry = netctrl_rest_prepare_entry_payload($request);
    $entry_id = netctrl_add_entry($session_id, $entry);

    return rest_ensure_response(array(
        'id' => $entry_id,
        'entry' => netctrl_prepare_entry_for_response(netctrl_get_entry($entry_id)),
    ));
}

function netctrl_rest_update_entry(WP_REST_Request $request)
{
    $entry_id = (int) $request['id'];
    $existing_entry = netctrl_get_entry($entry_id);

    if (!$existing_entry) {
        return new WP_Error('netctrl_not_found', __('Entry not found.', 'netctrl'), array('status' => 404));
    }

    $entry = netctrl_rest_prepare_entry_payload($request);
    netctrl_update_entry($entry_id, $entry);

    return rest_ensure_response(array(
        'id' => $entry_id,
        'entry' => netctrl_prepare_entry_for_response(netctrl_get_entry($entry_id)),
    ));
}

function netctrl_rest_delete_entry(WP_REST_Request $request)
{
    $entry_id = (int) $request['id'];
    $existing_entry = netctrl_get_entry($entry_id);

    if (!$existing_entry) {
        return new WP_Error('netctrl_not_found', __('Entry not found.', 'netctrl'), array('status' => 404));
    }

    netctrl_delete_entry($entry_id);

    return rest_ensure_response(array(
        'deleted' => true,
        'id' => $entry_id,
        'session_id' => (int) $existing_entry['session_id'],
    ));
}

function netctrl_rest_lookup_callsign(WP_REST_Request $request)
{
    return rest_ensure_response(netctrl_lookup_callsign_data($request->get_param('callsign')));
}

function netctrl_rest_prepare_entry_payload(WP_REST_Request $request)
{
    $callsign = netctrl_normalize_callsign($request->get_param('callsign'));
    $checkin_type = netctrl_normalize_checkin_type($request->get_param('checkin_type'));
    $has_announcement = rest_sanitize_boolean($request->get_param('has_announcement'));
    $has_traffic = rest_sanitize_boolean($request->get_param('has_traffic'));
    $announcement_details = trim((string) $request->get_param('announcement_details'));
    $traffic_details = trim((string) $request->get_param('traffic_details'));

    if ($checkin_type !== 'regular') {
        $has_announcement = false;
        $has_traffic = false;
        $announcement_details = '';
        $traffic_details = '';
    } else {
        if (!$has_announcement) {
            $announcement_details = '';
        }

        if (!$has_traffic) {
            $traffic_details = '';
        }
    }

    $entry = array(
        'callsign' => $callsign,
        'name' => $request->get_param('name'),
        'location' => $request->get_param('location'),
        'comments' => $request->get_param('comments'),
        'checkin_type' => $checkin_type,
        'has_announcement' => $has_announcement,
        'has_traffic' => $has_traffic,
        'announcement_details' => $announcement_details,
        'traffic_details' => $traffic_details,
    );

    if (empty($entry['name']) || empty($entry['location'])) {
        $lookup = netctrl_lookup_callsign_data($callsign);

        if (!empty($lookup['found'])) {
            if (empty($entry['name'])) {
                $entry['name'] = netctrl_join_name_parts($lookup['first_name'] ?? '', $lookup['last_name'] ?? '');
            }

            if (empty($entry['location'])) {
                $entry['location'] = $lookup['location'] ?? '';
            }
        }
    }

    return $entry;
}

function netctrl_rest_download_pdf(WP_REST_Request $request)
{
    $session = netctrl_get_session((int) $request['id']);

    if (!$session) {
        return new WP_Error('netctrl_not_found', __('Session not found.', 'netctrl'), array('status' => 404));
    }

    if (($session['status'] ?? '') !== 'closed') {
        return new WP_Error('netctrl_session_open', __('PDF downloads are only available for closed sessions.', 'netctrl'), array('status' => 400));
    }

    return new WP_REST_Response(netctrl_generate_session_pdf((int) $session['id']), 200, array(
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'attachment; filename="netctrl-session-' . (int) $session['id'] . '.pdf"',
    ));
}
