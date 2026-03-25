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
add_shortcode('netctrl_stats', 'netctrl_render_stats_shortcode');

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
            'checkinTypeShort' => __('Short Time / No Traffic', 'netctrl'),
            'checkinTypeRegular' => __('Regular', 'netctrl'),
            'typeLabel' => __('Type', 'netctrl'),
            'announcementLabel' => __('Announcement', 'netctrl'),
            'trafficLabel' => __('Traffic', 'netctrl'),
            'legacyCommentsLabel' => __('Legacy Comments', 'netctrl'),
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

function netctrl_render_stats_shortcode()
{
    netctrl_register_public_assets();
    wp_enqueue_style('netctrl-console');

    $scope = netctrl_get_stats_scope();
    $stats = netctrl_get_stats_data($scope);
    $labels = netctrl_get_stats_labels($stats['rows']);

    ob_start();
    ?>
    <div class="netctrl-stats">
        <section class="netctrl-panel netctrl-public-section">
            <div class="netctrl-panel__heading">
                <h2><?php esc_html_e('NETctrl Participation Stats', 'netctrl'); ?></h2>
            </div>
            <p class="netctrl-public-section__note">
                <?php esc_html_e('Weighted participation leaderboard for net check-ins, announcements, and traffic handling.', 'netctrl'); ?>
            </p>

            <form class="netctrl-stats__filters" method="get">
                <label for="netctrl-stats-scope"><?php esc_html_e('Time Range', 'netctrl'); ?></label>
                <select id="netctrl-stats-scope" name="netctrl_scope" onchange="this.form.submit()">
                    <?php foreach (netctrl_get_stats_scopes() as $key => $label) : ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($scope, $key); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
                <noscript>
                    <button type="submit" class="button button-secondary"><?php esc_html_e('Apply', 'netctrl'); ?></button>
                </noscript>
            </form>

            <div class="netctrl-stats__labels">
                <?php foreach ($labels as $label) : ?>
                    <article class="netctrl-stats__label-card">
                        <h3><?php echo esc_html($label['title']); ?></h3>
                        <p>
                            <strong><?php echo esc_html($label['callsign']); ?></strong>
                            <?php if (!empty($label['name'])) : ?>
                                <span>— <?php echo esc_html($label['name']); ?></span>
                            <?php endif; ?>
                        </p>
                        <p class="netctrl-stats__label-meta"><?php echo esc_html($label['meta']); ?></p>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="netctrl-stats__table-wrap">
                <table class="netctrl-stats__table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Rank', 'netctrl'); ?></th>
                            <th><?php esc_html_e('Callsign', 'netctrl'); ?></th>
                            <th><?php esc_html_e('Name', 'netctrl'); ?></th>
                            <th><?php esc_html_e('Score', 'netctrl'); ?></th>
                            <th><?php esc_html_e('Total Sessions', 'netctrl'); ?></th>
                            <th><?php esc_html_e('Regular Check-ins', 'netctrl'); ?></th>
                            <th><?php esc_html_e('Short Time Check-ins', 'netctrl'); ?></th>
                            <th><?php esc_html_e('Announcements', 'netctrl'); ?></th>
                            <th><?php esc_html_e('Traffic', 'netctrl'); ?></th>
                            <th><?php esc_html_e('Last Heard', 'netctrl'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($stats['rows']) : ?>
                            <?php foreach ($stats['rows'] as $index => $row) : ?>
                                <tr>
                                    <td><?php echo esc_html((string) ($index + 1)); ?></td>
                                    <td><?php echo esc_html($row['callsign']); ?></td>
                                    <td><?php echo esc_html($row['name'] !== '' ? $row['name'] : '—'); ?></td>
                                    <td><?php echo esc_html((string) $row['score']); ?></td>
                                    <td><?php echo esc_html((string) $row['total_sessions']); ?></td>
                                    <td><?php echo esc_html((string) $row['regular_checkins']); ?></td>
                                    <td><?php echo esc_html((string) $row['short_time_checkins']); ?></td>
                                    <td><?php echo esc_html((string) $row['announcements']); ?></td>
                                    <td><?php echo esc_html((string) $row['traffic']); ?></td>
                                    <td><?php echo esc_html($row['last_heard']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="10" class="netctrl-public-empty"><?php esc_html_e('No participation data found for this time range.', 'netctrl'); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
    <?php

    return ob_get_clean();
}

function netctrl_get_stats_scope()
{
    $scope = isset($_GET['netctrl_scope']) ? sanitize_key(wp_unslash($_GET['netctrl_scope'])) : 'all_time';
    $scopes = netctrl_get_stats_scopes();

    return isset($scopes[$scope]) ? $scope : 'all_time';
}

function netctrl_get_stats_scopes()
{
    return array(
        'all_time' => __('All Time', 'netctrl'),
        'this_month' => __('This Month', 'netctrl'),
        'last_30_days' => __('Last 30 Days', 'netctrl'),
    );
}

function netctrl_get_stats_data($scope)
{
    $cache_key = 'netctrl_stats_' . $scope;
    $cached = get_transient($cache_key);

    if (is_array($cached)) {
        return $cached;
    }

    global $wpdb;
    $entries_table = netctrl_get_table('entries');
    $sessions_table = netctrl_get_table('sessions');

    $where = '';
    $query_args = array();

    if ($scope === 'this_month') {
        $start_date = gmdate('Y-m-01 00:00:00');
        $where = "WHERE COALESCE(NULLIF(e.created_at, '0000-00-00 00:00:00'), s.created_at, s.started_at) >= %s";
        $query_args[] = $start_date;
    } elseif ($scope === 'last_30_days') {
        $start_date = gmdate('Y-m-d H:i:s', time() - (30 * DAY_IN_SECONDS));
        $where = "WHERE COALESCE(NULLIF(e.created_at, '0000-00-00 00:00:00'), s.created_at, s.started_at) >= %s";
        $query_args[] = $start_date;
    }

    $sql = "SELECT
            e.callsign,
            e.name,
            e.checkin_type,
            e.has_announcement,
            e.has_traffic,
            e.comments,
            COALESCE(NULLIF(e.created_at, '0000-00-00 00:00:00'), s.created_at, s.started_at) AS heard_at
        FROM {$entries_table} e
        LEFT JOIN {$sessions_table} s ON s.id = e.session_id
        {$where}
        ORDER BY heard_at DESC, e.id DESC";

    if ($query_args) {
        $rows = $wpdb->get_results($wpdb->prepare($sql, $query_args), ARRAY_A);
    } else {
        $rows = $wpdb->get_results($sql, ARRAY_A);
    }

    $grouped = array();

    foreach ($rows as $entry) {
        $callsign = netctrl_normalize_callsign($entry['callsign'] ?? '');

        if ($callsign === '') {
            continue;
        }

        if (!isset($grouped[$callsign])) {
            $grouped[$callsign] = array(
                'callsign' => $callsign,
                'name' => trim((string) ($entry['name'] ?? '')),
                'score' => 0,
                'total_sessions' => 0,
                'regular_checkins' => 0,
                'short_time_checkins' => 0,
                'announcements' => 0,
                'traffic' => 0,
                'last_heard_raw' => '',
                'last_heard' => '',
            );
        }

        if ($grouped[$callsign]['name'] === '' && !empty($entry['name'])) {
            $grouped[$callsign]['name'] = trim((string) $entry['name']);
        }

        $grouped[$callsign]['total_sessions']++;

        $checkin_type = netctrl_normalize_checkin_type($entry['checkin_type'] ?? '');
        $is_regular = $checkin_type === 'regular';

        if ($is_regular) {
            $grouped[$callsign]['regular_checkins']++;
            $grouped[$callsign]['score'] += 2;
        } else {
            $grouped[$callsign]['short_time_checkins']++;
            $grouped[$callsign]['score'] += 1;
        }

        $has_announcement = !empty($entry['has_announcement']);
        $has_traffic = !empty($entry['has_traffic']);

        // Best effort for legacy records: include common text-only signals from comments.
        if (!$has_announcement || !$has_traffic) {
            $legacy_comments = strtolower(trim((string) ($entry['comments'] ?? '')));
            if ($legacy_comments !== '') {
                if (!$has_announcement && strpos($legacy_comments, 'announcement') !== false) {
                    $has_announcement = true;
                }
                if (!$has_traffic && strpos($legacy_comments, 'traffic') !== false) {
                    $has_traffic = true;
                }
            }
        }

        if ($has_announcement) {
            $grouped[$callsign]['announcements']++;
            $grouped[$callsign]['score'] += 2;
        }

        if ($has_traffic) {
            $grouped[$callsign]['traffic']++;
            $grouped[$callsign]['score'] += 3;
        }

        $heard_at = trim((string) ($entry['heard_at'] ?? ''));
        if ($heard_at !== '' && ($grouped[$callsign]['last_heard_raw'] === '' || $heard_at > $grouped[$callsign]['last_heard_raw'])) {
            $grouped[$callsign]['last_heard_raw'] = $heard_at;
            $grouped[$callsign]['last_heard'] = netctrl_format_display_timestamp($heard_at);
        }
    }

    $leaderboard = array_values($grouped);
    usort($leaderboard, 'netctrl_sort_stats_rows');

    $result = array('rows' => $leaderboard);
    set_transient($cache_key, $result, 5 * MINUTE_IN_SECONDS);

    return $result;
}

function netctrl_sort_stats_rows(array $a, array $b)
{
    if ($a['score'] !== $b['score']) {
        return $b['score'] <=> $a['score'];
    }

    if ($a['total_sessions'] !== $b['total_sessions']) {
        return $b['total_sessions'] <=> $a['total_sessions'];
    }

    return strcmp($b['last_heard_raw'], $a['last_heard_raw']);
}

function netctrl_get_stats_labels(array $rows)
{
    $categories = array(
        'top_score' => array(
            'title' => __('Top Net Contributor', 'netctrl'),
            'field' => 'score',
            'meta_label' => __('Score', 'netctrl'),
        ),
        'top_traffic' => array(
            'title' => __('Traffic Lead', 'netctrl'),
            'field' => 'traffic',
            'meta_label' => __('Traffic', 'netctrl'),
        ),
        'top_announcement' => array(
            'title' => __('Announcement Lead', 'netctrl'),
            'field' => 'announcements',
            'meta_label' => __('Announcements', 'netctrl'),
        ),
        'most_active' => array(
            'title' => __('Most Active', 'netctrl'),
            'field' => 'total_sessions',
            'meta_label' => __('Sessions', 'netctrl'),
        ),
    );

    $labels = array();
    foreach ($categories as $category) {
        $winner = netctrl_get_stats_label_winner($rows, $category['field']);
        $labels[] = array(
            'title' => $category['title'],
            'callsign' => $winner['callsign'],
            'name' => $winner['name'],
            'meta' => sprintf('%s: %d', $category['meta_label'], (int) $winner[$category['field']]),
        );
    }

    return $labels;
}

function netctrl_get_stats_label_winner(array $rows, $field)
{
    if (!$rows) {
        return array(
            'callsign' => __('N/A', 'netctrl'),
            'name' => '',
            $field => 0,
        );
    }

    $winner = $rows[0];
    foreach ($rows as $row) {
        if ((int) $row[$field] > (int) $winner[$field]) {
            $winner = $row;
        } elseif ((int) $row[$field] === (int) $winner[$field] && netctrl_sort_stats_rows($row, $winner) < 0) {
            $winner = $row;
        }
    }

    return $winner;
}

function netctrl_render_public_session_card(array $session, $single = false)
{
    $status_class = ($session['status'] ?? '') === 'open' ? 'live' : 'closed';
    ?>
    <article class="netctrl-public-session netctrl-public-session--<?php echo esc_attr($status_class); ?><?php echo $single ? ' netctrl-public-session--single' : ''; ?>" data-session-id="<?php echo esc_attr($session['id']); ?>">
        <div class="netctrl-public-session__header">
            <div>
                <div class="netctrl-public-session__title-row">
                    <h3><?php echo esc_html($session['net_name']); ?></h3>
                    <button
                        type="button"
                        class="button button-secondary netctrl-public-session__toggle"
                        data-netctrl-session-toggle
                        aria-expanded="false"
                    >
                        <?php esc_html_e('Expand', 'netctrl'); ?>
                    </button>
                </div>
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
                            <span>
                                <strong><?php esc_html_e('Type', 'netctrl'); ?>:</strong>
                                <?php echo esc_html(($entry['checkin_type'] ?? '') === 'regular' ? __('Regular', 'netctrl') : __('Short Time / No Traffic', 'netctrl')); ?>
                                <?php if (!empty($entry['has_announcement'])) : ?>
                                    <br />
                                    <strong><?php esc_html_e('Announcement', 'netctrl'); ?>:</strong>
                                    <?php echo esc_html(!empty($entry['announcement_details']) ? $entry['announcement_details'] : __('Yes', 'netctrl')); ?>
                                <?php endif; ?>
                                <?php if (!empty($entry['has_traffic'])) : ?>
                                    <br />
                                    <strong><?php esc_html_e('Traffic', 'netctrl'); ?>:</strong>
                                    <?php echo esc_html(!empty($entry['traffic_details']) ? $entry['traffic_details'] : __('Yes', 'netctrl')); ?>
                                <?php endif; ?>
                                <?php if (!empty($entry['legacy_comments'])) : ?>
                                    <br />
                                    <strong><?php esc_html_e('Legacy Comments', 'netctrl'); ?>:</strong>
                                    <?php echo esc_html($entry['legacy_comments']); ?>
                                <?php endif; ?>
                            </span>
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
