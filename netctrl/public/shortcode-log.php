<?php

if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('netctrl_log', 'netctrl_render_log_shortcode');

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

    $entries = netctrl_get_entries($session_id);
    ob_start();
    ?>
    <div class="netctrl-log">
        <h3><?php echo esc_html($session['net_name']); ?></h3>
        <p>
            <?php esc_html_e('Status:', 'netctrl'); ?>
            <?php echo esc_html($session['status']); ?>
        </p>
        <ul>
            <?php foreach ($entries as $entry) : ?>
                <li>
                    <?php echo esc_html($entry['created_at']); ?> -
                    <?php echo esc_html($entry['callsign']); ?>
                    <?php echo esc_html($entry['name']); ?>
                    <?php echo esc_html($entry['location']); ?>
                    <?php echo esc_html($entry['comments']); ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if (current_user_can('run_net')) : ?>
            <?php $pdf_url = add_query_arg('_wpnonce', wp_create_nonce('wp_rest'), netctrl_get_pdf_url($session_id)); ?>
            <a href="<?php echo esc_url($pdf_url); ?>">
                <?php esc_html_e('Download PDF', 'netctrl'); ?>
            </a>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
