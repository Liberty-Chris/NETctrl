<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'netctrl_register_console_page');
add_action('admin_enqueue_scripts', 'netctrl_maybe_enqueue_admin_console_assets');
add_shortcode('netctrl_console', 'netctrl_render_console_shortcode');

function netctrl_register_console_page()
{
    add_menu_page(
        __('Net Control Console', 'netctrl'),
        __('Net Control', 'netctrl'),
        'run_net',
        'netctrl-console',
        'netctrl_render_console_page',
        'dashicons-megaphone',
        58
    );
}

function netctrl_register_console_assets()
{
    wp_register_style('netctrl-console', NETCTRL_URL . 'assets/css/console.css', array(), NETCTRL_VERSION);
    wp_register_script('netctrl-console', NETCTRL_URL . 'assets/js/console.js', array(), NETCTRL_VERSION, true);
}

function netctrl_enqueue_console_assets()
{
    netctrl_register_console_assets();

    wp_enqueue_style('netctrl-console');
    wp_enqueue_script('netctrl-console');

    wp_localize_script('netctrl-console', 'netctrlConsole', array(
        'restUrl' => esc_url_raw(rest_url('netctrl/v1')),
        'nonce' => wp_create_nonce('wp_rest'),
        'pollInterval' => 4000,
        'canDeleteSessions' => current_user_can('manage_options'),
        'strings' => array(
            'unableToLoadSessions' => __('Unable to load sessions.', 'netctrl'),
            'unableToLoadEntries' => __('Unable to load entries.', 'netctrl'),
            'requestFailed' => __('Request failed.', 'netctrl'),
            'selectSession' => __('Select or start a session to begin logging.', 'netctrl'),
            'activeSessionLabel' => __('Current session:', 'netctrl'),
            'noRecentSessions' => __('No recent sessions found.', 'netctrl'),
            'noEntries' => __('No entries recorded yet.', 'netctrl'),
            'sessionPreviewFallback' => __('Choose a session type to generate the session name.', 'netctrl'),
            'editEntry' => __('Edit entry', 'netctrl'),
            'deleteEntry' => __('Delete entry', 'netctrl'),
            'deleteEntryConfirm' => __('Delete this entry?', 'netctrl'),
            'saveEntry' => __('Save', 'netctrl'),
            'cancelEdit' => __('Cancel', 'netctrl'),
            'lookupRoster' => __('Populated from roster', 'netctrl'),
            'lookupQrz' => __('Populated from QRZ', 'netctrl'),
            'sessionActive' => __('Session active', 'netctrl'),
            'liveSessionInProgress' => __('Live session in progress', 'netctrl'),
            'startDisabled' => __('Start Session is unavailable while another live session is open.', 'netctrl'),
            'sessionClosed' => __('Session closed.', 'netctrl'),
            'sessionReopened' => __('Session reopened.', 'netctrl'),
            'monitoringLive' => __('Polling live updates every few seconds.', 'netctrl'),
            'statusLive' => __('Live', 'netctrl'),
            'statusClosed' => __('Closed', 'netctrl'),
            'reopenSession' => __('Reopen Session', 'netctrl'),
            'deleteSession' => __('Delete Session', 'netctrl'),
            'deleteSessionConfirm' => __('Delete this session and all of its entries?', 'netctrl'),
            'checkinTypeShort' => __('Short Time / No Traffic', 'netctrl'),
            'checkinTypeRegular' => __('Regular', 'netctrl'),
            'announcementLabel' => __('Announcement', 'netctrl'),
            'trafficLabel' => __('Traffic', 'netctrl'),
            'announcementDetailsLabel' => __('Announcement Details', 'netctrl'),
            'trafficDetailsLabel' => __('Traffic Details', 'netctrl'),
            'legacyCommentsLabel' => __('Legacy Comments', 'netctrl'),
        ),
    ));
}

function netctrl_maybe_enqueue_admin_console_assets($hook)
{
    if ($hook !== 'toplevel_page_netctrl-console') {
        return;
    }

    netctrl_enqueue_console_assets();
}

function netctrl_get_console_markup($is_frontend = false)
{
    ob_start();
    ?>
    <div class="netctrl-console<?php echo $is_frontend ? ' netctrl-console--frontend' : ''; ?>" data-netctrl-console-root>
        <div class="netctrl-console__messages" aria-live="polite"></div>

        <section class="netctrl-panel netctrl-panel--session-start" data-netctrl-start-panel>
            <div class="netctrl-panel__heading">
                <h2><?php esc_html_e('New Session', 'netctrl'); ?></h2>
                <span class="netctrl-status-badge netctrl-status-badge--idle" data-netctrl-start-status>
                    <?php esc_html_e('Idle', 'netctrl'); ?>
                </span>
            </div>
            <div class="netctrl-panel__body netctrl-panel__body--stacked">
                <div class="netctrl-start-session-note" data-netctrl-start-note>
                    <?php esc_html_e('Start a session to begin live logging.', 'netctrl'); ?>
                </div>
                <div class="netctrl-session-builder">
                    <div class="netctrl-session-builder__group">
                        <label for="netctrl-session-date"><?php esc_html_e('Date', 'netctrl'); ?></label>
                        <input type="text" id="netctrl-session-date" readonly />
                    </div>

                    <fieldset class="netctrl-session-builder__group netctrl-session-builder__group--types">
                        <legend><?php esc_html_e('Session Type', 'netctrl'); ?></legend>
                        <div class="netctrl-session-types" role="radiogroup" aria-label="<?php echo esc_attr__('Session Type', 'netctrl'); ?>">
                            <?php
                            $session_types = array(
                                'CN' => __('Club Net', 'netctrl'),
                                'SW' => __('SkyWarn Activation', 'netctrl'),
                                'AR' => __('ARES Net', 'netctrl'),
                                'AX' => __('AUXCOMM Net', 'netctrl'),
                                'SE' => __('Special Event', 'netctrl'),
                            );

                            foreach ($session_types as $code => $label) :
                                ?>
                                <label class="netctrl-session-types__option" for="netctrl-session-type-<?php echo esc_attr(strtolower($code)); ?>">
                                    <input
                                        type="radio"
                                        name="netctrl-session-type"
                                        id="netctrl-session-type-<?php echo esc_attr(strtolower($code)); ?>"
                                        value="<?php echo esc_attr($code); ?>"
                                    />
                                    <span><?php echo esc_html($code . ' = ' . $label); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>

                    <div class="netctrl-session-builder__group" data-netctrl-special-event hidden>
                        <label for="netctrl-event-description"><?php esc_html_e('Event Description (optional)', 'netctrl'); ?></label>
                        <input type="text" id="netctrl-event-description" maxlength="190" />
                    </div>

                    <div class="netctrl-session-builder__group">
                        <label for="netctrl-session-preview"><?php esc_html_e('Session Name Preview', 'netctrl'); ?></label>
                        <input type="text" id="netctrl-session-preview" readonly />
                    </div>
                </div>

                <button type="button" class="button button-primary" id="netctrl-start-session">
                    <?php esc_html_e('Start Session', 'netctrl'); ?>
                </button>
            </div>
        </section>

        <section class="netctrl-panel netctrl-panel--active-session" data-netctrl-active-panel>
            <div class="netctrl-panel__heading">
                <h2><?php esc_html_e('Active Session', 'netctrl'); ?></h2>
                <span class="netctrl-status-badge netctrl-status-badge--idle" id="netctrl-active-status-badge">
                    <?php esc_html_e('Idle', 'netctrl'); ?>
                </span>
            </div>
            <div class="netctrl-panel__body">
                <div id="netctrl-active-session" class="netctrl-active-session">
                    <?php esc_html_e('Select or start a session to begin logging.', 'netctrl'); ?>
                </div>
                <div class="netctrl-active-session__meta" id="netctrl-active-session-meta">
                    <?php esc_html_e('Polling live updates every few seconds.', 'netctrl'); ?>
                </div>
                <div class="netctrl-entry-form" role="group" aria-label="<?php echo esc_attr__('Entry form', 'netctrl'); ?>">
                    <input type="text" id="netctrl-callsign" placeholder="<?php echo esc_attr__('Callsign', 'netctrl'); ?>" />
                    <input type="text" id="netctrl-first-name" placeholder="<?php echo esc_attr__('First Name', 'netctrl'); ?>" />
                    <input type="text" id="netctrl-last-name" placeholder="<?php echo esc_attr__('Last Name', 'netctrl'); ?>" />
                    <input type="text" id="netctrl-location" placeholder="<?php echo esc_attr__('Location', 'netctrl'); ?>" />
                    <select id="netctrl-checkin-type" aria-label="<?php echo esc_attr__('Check-in Type', 'netctrl'); ?>">
                        <option value="short_time_no_traffic"><?php esc_html_e('Short Time / No Traffic', 'netctrl'); ?></option>
                        <option value="regular"><?php esc_html_e('Regular', 'netctrl'); ?></option>
                    </select>
                    <div class="netctrl-entry-form__regular-fields" id="netctrl-regular-fields" hidden>
                        <label class="netctrl-entry-form__checkbox">
                            <input type="checkbox" id="netctrl-has-announcement" />
                            <span><?php esc_html_e('Announcement', 'netctrl'); ?></span>
                        </label>
                        <label class="netctrl-entry-form__checkbox">
                            <input type="checkbox" id="netctrl-has-traffic" />
                            <span><?php esc_html_e('Traffic', 'netctrl'); ?></span>
                        </label>
                    </div>
                    <input type="text" id="netctrl-announcement-details" placeholder="<?php echo esc_attr__('Announcement Details', 'netctrl'); ?>" hidden />
                    <input type="text" id="netctrl-traffic-details" placeholder="<?php echo esc_attr__('Traffic Details', 'netctrl'); ?>" hidden />
                    <div class="netctrl-entry-form__lookup-note" id="netctrl-lookup-status" aria-live="polite"></div>
                    <div class="netctrl-entry-form__actions">
                        <button type="button" class="button button-primary" id="netctrl-add-entry"><?php esc_html_e('Add Entry', 'netctrl'); ?></button>
                        <button type="button" class="button" id="netctrl-reopen-session"><?php esc_html_e('Reopen Session', 'netctrl'); ?></button>
                        <button type="button" class="button" id="netctrl-close-session"><?php esc_html_e('Close Session', 'netctrl'); ?></button>
                    </div>
                </div>
                <div class="netctrl-panel__subsection">
                    <h3><?php esc_html_e('Entries', 'netctrl'); ?></h3>
                    <div class="netctrl-entries-table" role="table" aria-label="<?php echo esc_attr__('Session entries', 'netctrl'); ?>">
                        <div class="netctrl-entries-table__head" role="rowgroup">
                            <div class="netctrl-entries-table__row netctrl-entries-table__row--header" role="row">
                                <div role="columnheader"><?php esc_html_e('Callsign', 'netctrl'); ?></div>
                                <div role="columnheader"><?php esc_html_e('Name', 'netctrl'); ?></div>
                                <div role="columnheader"><?php esc_html_e('Location', 'netctrl'); ?></div>
                                <div role="columnheader"><?php esc_html_e('Check-in', 'netctrl'); ?></div>
                                <div role="columnheader"><?php esc_html_e('Actions', 'netctrl'); ?></div>
                            </div>
                        </div>
                        <ul id="netctrl-entries" class="netctrl-list netctrl-entries-table__body" role="rowgroup"></ul>
                    </div>
                </div>
            </div>
        </section>

        <section class="netctrl-panel netctrl-panel--recent-sessions">
            <h2><?php esc_html_e('Recent Sessions', 'netctrl'); ?></h2>
            <div class="netctrl-panel__body">
                <ul id="netctrl-sessions" class="netctrl-list"></ul>
            </div>
        </section>
    </div>
    <?php

    return ob_get_clean();
}

function netctrl_render_console_page()
{
    if (!current_user_can('run_net')) {
        wp_die(esc_html__('You do not have permission to access NETctrl.', 'netctrl'));
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Net Control Console', 'netctrl'); ?></h1>
        <?php echo netctrl_get_console_markup(false); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </div>
    <?php
}

function netctrl_render_console_shortcode()
{
    $frontend_wrapper_class = 'netctrl-console-frontend';

    if (!is_user_logged_in()) {
        $redirect_url = get_permalink();

        if (!$redirect_url) {
            $redirect_url = home_url(add_query_arg(array(), $GLOBALS['wp']->request ?? ''));
        }

        return sprintf(
            '<div class="%1$s"><div class="netctrl-console-message netctrl-console-message--notice"><p>%2$s</p><a class="button button-primary netctrl-login-button" href="%3$s">%4$s</a></div></div>',
            esc_attr($frontend_wrapper_class),
            esc_html__('You must log in to access NETctrl.', 'netctrl'),
            esc_url(wp_login_url($redirect_url)),
            esc_html__('Log In', 'netctrl')
        );
    }

    if (!current_user_can('run_net')) {
        return sprintf(
            '<div class="%1$s"><div class="netctrl-console-message netctrl-console-message--error">%2$s</div></div>',
            esc_attr($frontend_wrapper_class),
            esc_html__('Access denied. Your account is not permitted to use NETctrl.', 'netctrl')
        );
    }

    netctrl_enqueue_console_assets();

    return sprintf(
        '<div class="%1$s">%2$s</div>',
        esc_attr($frontend_wrapper_class),
        netctrl_get_console_markup(true)
    );
}
