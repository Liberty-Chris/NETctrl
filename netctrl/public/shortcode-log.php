<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_enqueue_scripts', 'netctrl_register_public_assets');
add_action('wp_ajax_netctrl_public_sessions', 'netctrl_ajax_public_sessions');
add_action('wp_ajax_nopriv_netctrl_public_sessions', 'netctrl_ajax_public_sessions');
add_action('wp_ajax_netctrl_public_session', 'netctrl_ajax_public_session');
add_action('wp_ajax_nopriv_netctrl_public_session', 'netctrl_ajax_public_session');
add_shortcode('netctrl_log', 'netctrl_render_log_shortcode');
add_shortcode('netctrl_public_sessions', 'netctrl_render_public_sessions_shortcode');

function netctrl_register_public_assets()
{
    wp_register_style('netctrl-console', NETCTRL_URL . 'assets/css/console.css', array(), NETCTRL_VERSION);
    wp_register_script('netctrl-public-sessions', NETCTRL_URL . 'assets/js/public-sessions.js', array(), NETCTRL_VERSION, true);
}

function netctrl_enqueue_public_assets()
{
    netctrl_register_public_assets();
    wp_enqueue_style('netctrl-console');
    wp_enqueue_script('netctrl-public-sessions');

    wp_localize_script('netctrl-public-sessions', 'netctrlPublic', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'pollInterval' => 5000,
        'strings' => array(
            'live' => __('Live', 'netctrl'),
            'closed' => __('Closed', 'netctrl'),
            'noOpenSessions' => __('No live sessions right now.', 'netctrl'),
            'noClosedSessions' => __('No closed sessions available yet.', 'netctrl'),
            'entriesHeading' => __('Entries', 'netctrl'),
            'downloadPdf' => __('Download PDF', 'netctrl'),
            'status' => __('Status', 'netctrl'),
            'created' => __('Created', 'netctrl'),
            'closedAt' => __('Closed', 'netctrl'),
            'commentsFallback' => __('No comments', 'netctrl'),
            'liveUpdates' => __('Live updates refresh automatically every few seconds.', 'netctrl'),
            'sessionUnavailable' => __('Session unavailable.', 'netctrl'),
            'expand' => __('Expand', 'netctrl'),
            'collapse' => __('Collapse', 'netctrl'),
            'noEntries' => __('No entries recorded yet.', 'netctrl'),
        ),
    ));
}

function netctrl_ajax_public_sessions()
{
    wp_send_json_success(netctrl_get_public_sessions_payload(5));
}

function netctrl_ajax_public_session()
{
    $session_id = isset($_GET['session_id']) ? absint(wp_unslash($_GET['session_id'])) : 0;
    $session = $session_id ? netctrl_get_session($session_id) : null;

    if (!$session) {
        wp_send_json_error(array('message' => __('Session unavailable.', 'netctrl')), 404);
    }

    wp_send_json_success(array(
        'session' => netctrl_prepare_public_session_payload($session),
    ));
}

function netctrl_render_log_shortcode($atts)
{
    $atts = shortcode_atts(
        array(
            'id' => 0,
        ),
        $atts,
        'netctrl_log'
    );

    $session_id = absint($atts['id']);
    if (!$session_id) {
        return '';
    }

    $session = netctrl_get_session($session_id);
    if (!$session) {
        return '';
    }

    netctrl_enqueue_public_assets();
    $payload = netctrl_prepare_public_session_payload($session);

    ob_start();
    ?>
    <div class="netctrl-public-log" data-netctrl-public-log data-session-id="<?php echo esc_attr($session_id); ?>">
        <?php netctrl_render_public_session_card($payload, true); ?>
    </div>
    <?php
    return ob_get_clean();
}

function netctrl_render_public_sessions_shortcode()
{
    netctrl_enqueue_public_assets();
    $payload = netctrl_get_public_sessions_payload(5);

    ob_start();
    ?>
    <div class="netctrl-public-sessions" data-netctrl-public-sessions>
        <div class="netctrl-public-sessions__grid">
            <section class="netctrl-panel netctrl-public-section">
                <div class="netctrl-panel__heading">
                    <h2><?php esc_html_e('Live Sessions', 'netctrl'); ?></h2>
                    <span class="netctrl-status-badge netctrl-status-badge--live"><?php esc_html_e('Live', 'netctrl'); ?></span>
                </div>
                <p class="netctrl-public-section__note"><?php esc_html_e('Live updates refresh automatically every few seconds.', 'netctrl'); ?></p>
                <div data-netctrl-open-sessions>
                    <?php
                    if ($payload['open_sessions']) {
                        foreach ($payload['open_sessions'] as $session) {
                            netctrl_render_public_session_card($session);
                        }
                    } else {
                        echo '<p class="netctrl-public-empty">' . esc_html__('No live sessions right now.', 'netctrl') . '</p>';
                    }
                    ?>
                </div>
            </section>

            <section class="netctrl-panel netctrl-public-section">
                <div class="netctrl-panel__heading">
                    <h2><?php esc_html_e('Recent Closed Sessions', 'netctrl'); ?></h2>
                    <span class="netctrl-status-badge netctrl-status-badge--closed"><?php esc_html_e('Closed', 'netctrl'); ?></span>
                </div>
                <div data-netctrl-closed-sessions>
                    <?php
                    if ($payload['closed_sessions']) {
                        foreach ($payload['closed_sessions'] as $session) {
                            netctrl_render_public_session_card($session);
                        }
                    } else {
                        echo '<p class="netctrl-public-empty">' . esc_html__('No closed sessions available yet.', 'netctrl') . '</p>';
                    }
                    ?>
                </div>
            </section>
        </div>
    </div>
    <?php

    return ob_get_clean();
}

function netctrl_render_public_session_card(array $session, $single = false)
{
    $status_class = ($session['status'] ?? '') === 'open' ? 'live' : 'closed';
    ?>
    <article class="netctrl-public-session netctrl-public-session--<?php echo esc_attr($status_class); ?><?php echo $single ? ' netctrl-public-session--single' : ''; ?>" data-session-id="<?php echo esc_attr($session['id']); ?>">
        <div class="netctrl-public-session__header">
            <div>
                <h3><?php echo esc_html($session['net_name']); ?></h3>
                <div class="netctrl-public-session__meta">
                    <span><?php esc_html_e('Status', 'netctrl'); ?>: <?php echo esc_html($session['status_label']); ?></span>
                    <?php if (!empty($session['created_at'])) : ?>
                        <span><?php esc_html_e('Created', 'netctrl'); ?>: <?php echo esc_html($session['created_at']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($session['closed_at'])) : ?>
                        <span><?php esc_html_e('Closed', 'netctrl'); ?>: <?php echo esc_html($session['closed_at']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <span class="netctrl-status-badge netctrl-status-badge--<?php echo esc_attr($status_class); ?>">
                <?php echo esc_html($session['status_label']); ?>
            </span>
        </div>

        <div class="netctrl-public-session__actions">
            <?php if (($session['status'] ?? '') === 'closed' && !empty($session['pdf_url'])) : ?>
                <a class="button button-secondary netctrl-public-session__pdf" href="<?php echo esc_url($session['pdf_url']); ?>">
                    <?php esc_html_e('Download PDF', 'netctrl'); ?>
                </a>
            <?php endif; ?>
            <button
                type="button"
                class="button button-secondary netctrl-public-session__toggle"
                data-netctrl-session-toggle
                aria-expanded="false"
            >
                <?php esc_html_e('Expand', 'netctrl'); ?>
            </button>
        </div>

        <div class="netctrl-public-session__body" hidden>
            <div class="netctrl-public-session__description"><?php echo esc_html($session['status_description']); ?></div>
            <ul class="netctrl-public-session__entries">
                <?php if (!empty($session['entries'])) : ?>
                    <?php foreach ($session['entries'] as $entry) : ?>
                        <li>
                            <strong><?php echo esc_html($entry['callsign']); ?></strong>
                            <span><?php echo esc_html($entry['name'] ?: '—'); ?></span>
                            <span><?php echo esc_html($entry['location'] ?: '—'); ?></span>
                            <span><?php echo esc_html($entry['comments'] ?: '—'); ?></span>
                            <span><?php echo esc_html($entry['created_at'] ?: '—'); ?></span>
                        </li>
                    <?php endforeach; ?>
                <?php else : ?>
                    <li class="netctrl-public-empty"><?php esc_html_e('No entries recorded yet.', 'netctrl'); ?></li>
                <?php endif; ?>
            </ul>
        </div>
    </article>
    <?php
}
