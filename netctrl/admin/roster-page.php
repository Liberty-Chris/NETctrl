<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'netctrl_register_roster_page');

function netctrl_register_roster_page()
{
    add_submenu_page(
        'netctrl-console',
        __('NETctrl Roster', 'netctrl'),
        __('Roster', 'netctrl'),
        'manage_options',
        'netctrl-roster',
        'netctrl_render_roster_page'
    );
}

function netctrl_handle_roster_actions()
{
    if (!is_admin()) {
        return;
    }

    if (!current_user_can('manage_options')) {
        return;
    }

    if (!isset($_GET['page']) || $_GET['page'] !== 'netctrl-roster') {
        return;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['netctrl_roster_import'])) {
        check_admin_referer('netctrl_roster_import');

        if (empty($_FILES['netctrl_roster_csv']['tmp_name'])) {
            netctrl_redirect_roster_page(array(
                'netctrl_roster_notice' => 'import_error',
                'message' => __('Please choose a CSV file to import.', 'netctrl'),
            ));
        }

        $result = netctrl_import_roster_csv($_FILES['netctrl_roster_csv']['tmp_name']);

        if (is_wp_error($result)) {
            netctrl_redirect_roster_page(array(
                'netctrl_roster_notice' => 'import_error',
                'message' => $result->get_error_message(),
            ));
        }

        netctrl_redirect_roster_page(array(
            'netctrl_roster_notice' => 'import_success',
            'processed' => (int) $result['processed'],
            'saved' => (int) $result['saved'],
            'skipped' => (int) $result['skipped'],
        ));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['netctrl_roster_create'])) {
        check_admin_referer('netctrl_roster_create');

        $result = netctrl_create_roster_entry(netctrl_get_roster_form_data($_POST));

        if (is_wp_error($result)) {
            netctrl_redirect_roster_page(array(
                'netctrl_roster_notice' => 'entry_error',
                'message' => $result->get_error_message(),
                'form' => 'create',
            ));
        }

        netctrl_redirect_roster_page(array(
            'netctrl_roster_notice' => 'create_success',
        ));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['netctrl_roster_update'], $_POST['roster_id'])) {
        $roster_id = (int) $_POST['roster_id'];
        check_admin_referer('netctrl_roster_update_' . $roster_id);

        $result = netctrl_update_roster_entry($roster_id, netctrl_get_roster_form_data($_POST));

        if (is_wp_error($result)) {
            netctrl_redirect_roster_page(array(
                'netctrl_roster_notice' => 'entry_error',
                'message' => $result->get_error_message(),
                'netctrl_action' => 'edit',
                'roster_id' => $roster_id,
            ));
        }

        netctrl_redirect_roster_page(array(
            'netctrl_roster_notice' => 'update_success',
        ));
    }

    if (isset($_GET['netctrl_action']) && $_GET['netctrl_action'] === 'delete' && !empty($_GET['roster_id'])) {
        check_admin_referer('netctrl_roster_delete_' . (int) $_GET['roster_id']);
        netctrl_delete_roster_entry((int) $_GET['roster_id']);

        netctrl_redirect_roster_page(array(
            'netctrl_roster_notice' => 'delete_success',
        ));
    }
}
add_action('admin_init', 'netctrl_handle_roster_actions');

function netctrl_get_roster_form_data(array $source)
{
    $source = wp_unslash($source);

    return array(
        'callsign' => $source['callsign'] ?? '',
        'name' => $source['name'] ?? '',
        'location' => $source['location'] ?? '',
        'license_class' => $source['license_class'] ?? '',
        'is_member' => isset($source['is_member']) ? $source['is_member'] : 0,
        'is_officer' => isset($source['is_officer']) ? $source['is_officer'] : 0,
    );
}

function netctrl_redirect_roster_page(array $args)
{
    wp_safe_redirect(add_query_arg($args, admin_url('admin.php?page=netctrl-roster')));
    exit;
}

function netctrl_render_roster_notice()
{
    if (empty($_GET['netctrl_roster_notice'])) {
        return;
    }

    $notice = sanitize_key(wp_unslash($_GET['netctrl_roster_notice']));
    $class = 'notice notice-info';
    $message = '';

    if ($notice === 'import_success') {
        $processed = isset($_GET['processed']) ? (int) $_GET['processed'] : 0;
        $saved = isset($_GET['saved']) ? (int) $_GET['saved'] : 0;
        $skipped = isset($_GET['skipped']) ? (int) $_GET['skipped'] : 0;
        $message = sprintf(
            /* translators: 1: processed rows, 2: saved rows, 3: skipped rows */
            __('Roster import complete. Processed %1$d row(s), saved %2$d row(s), skipped %3$d row(s).', 'netctrl'),
            $processed,
            $saved,
            $skipped
        );
        $class = 'notice notice-success';
    } elseif ($notice === 'create_success') {
        $message = __('Roster entry added.', 'netctrl');
        $class = 'notice notice-success';
    } elseif ($notice === 'update_success') {
        $message = __('Roster entry updated.', 'netctrl');
        $class = 'notice notice-success';
    } elseif ($notice === 'delete_success') {
        $message = __('Roster entry deleted.', 'netctrl');
        $class = 'notice notice-success';
    } elseif ($notice === 'import_error' || $notice === 'entry_error') {
        $message = isset($_GET['message']) ? sanitize_text_field(wp_unslash($_GET['message'])) : __('Roster request failed.', 'netctrl');
        $class = 'notice notice-error';
    }

    if ($message === '') {
        return;
    }
    ?>
    <div class="<?php echo esc_attr($class); ?>"><p><?php echo esc_html($message); ?></p></div>
    <?php
}

function netctrl_render_roster_checkbox($name, $checked)
{
    ?>
    <label>
        <input type="checkbox" name="<?php echo esc_attr($name); ?>" value="1" <?php checked((int) $checked, 1); ?> />
        <?php esc_html_e('Yes', 'netctrl'); ?>
    </label>
    <?php
}

function netctrl_render_roster_form($mode, array $entry = array())
{
    $is_edit = $mode === 'edit';
    $title = $is_edit ? __('Edit Roster Entry', 'netctrl') : __('Add Roster Entry', 'netctrl');
    $submit_label = $is_edit ? __('Save Changes', 'netctrl') : __('Add Entry', 'netctrl');
    $callsign = $entry['callsign'] ?? '';
    $name = $entry['name'] ?? '';
    $location = $entry['location'] ?? '';
    $license_class = $entry['license_class'] ?? '';
    $is_member = $entry['is_member'] ?? 0;
    $is_officer = $entry['is_officer'] ?? 0;
    ?>
    <div class="card" style="max-width: 960px; margin-bottom: 20px;">
        <h2><?php echo esc_html($title); ?></h2>
        <form method="post">
            <?php if ($is_edit) : ?>
                <?php wp_nonce_field('netctrl_roster_update_' . (int) $entry['id']); ?>
                <input type="hidden" name="netctrl_roster_update" value="1" />
                <input type="hidden" name="roster_id" value="<?php echo (int) $entry['id']; ?>" />
            <?php else : ?>
                <?php wp_nonce_field('netctrl_roster_create'); ?>
                <input type="hidden" name="netctrl_roster_create" value="1" />
            <?php endif; ?>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><label for="netctrl-roster-callsign"><?php esc_html_e('Callsign', 'netctrl'); ?></label></th>
                        <td><input id="netctrl-roster-callsign" name="callsign" type="text" class="regular-text" value="<?php echo esc_attr($callsign); ?>" required /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="netctrl-roster-name"><?php esc_html_e('Name', 'netctrl'); ?></label></th>
                        <td><input id="netctrl-roster-name" name="name" type="text" class="regular-text" value="<?php echo esc_attr($name); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="netctrl-roster-location"><?php esc_html_e('Location', 'netctrl'); ?></label></th>
                        <td><input id="netctrl-roster-location" name="location" type="text" class="regular-text" value="<?php echo esc_attr($location); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="netctrl-roster-license-class"><?php esc_html_e('License Class', 'netctrl'); ?></label></th>
                        <td><input id="netctrl-roster-license-class" name="license_class" type="text" class="regular-text" value="<?php echo esc_attr($license_class); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Member', 'netctrl'); ?></th>
                        <td><?php netctrl_render_roster_checkbox('is_member', $is_member); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Officer', 'netctrl'); ?></th>
                        <td><?php netctrl_render_roster_checkbox('is_officer', $is_officer); ?></td>
                    </tr>
                </tbody>
            </table>
            <?php submit_button($submit_label, 'primary', '', false); ?>
            <?php if ($is_edit) : ?>
                <a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=netctrl-roster')); ?>"><?php esc_html_e('Cancel', 'netctrl'); ?></a>
            <?php endif; ?>
        </form>
    </div>
    <?php
}

function netctrl_render_roster_page()
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access the NETctrl roster.', 'netctrl'));
    }

    $entries = netctrl_get_roster_entries();
    $editing_entry = null;

    if (isset($_GET['netctrl_action']) && sanitize_key(wp_unslash($_GET['netctrl_action'])) === 'edit' && !empty($_GET['roster_id'])) {
        $editing_entry = netctrl_get_roster_entry((int) $_GET['roster_id']);
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('NETctrl Roster', 'netctrl'); ?></h1>
        <?php netctrl_render_roster_notice(); ?>

        <div class="card" style="max-width: 960px; margin-bottom: 20px;">
            <h2><?php esc_html_e('Import CSV', 'netctrl'); ?></h2>
            <p><?php esc_html_e('Supported columns: callsign, name, location, license_class, is_member, is_officer. Extra columns are ignored.', 'netctrl'); ?></p>
            <p><?php esc_html_e('Truthy member and officer values accepted during import: 1, yes, y, true, on.', 'netctrl'); ?></p>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('netctrl_roster_import'); ?>
                <input type="hidden" name="netctrl_roster_import" value="1" />
                <input type="file" name="netctrl_roster_csv" accept=".csv,text/csv" required />
                <?php submit_button(__('Upload CSV', 'netctrl'), 'primary', '', false); ?>
            </form>
        </div>

        <?php netctrl_render_roster_form('create'); ?>

        <?php if ($editing_entry) : ?>
            <?php netctrl_render_roster_form('edit', $editing_entry); ?>
        <?php endif; ?>

        <h2><?php esc_html_e('Roster Entries', 'netctrl'); ?></h2>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Callsign', 'netctrl'); ?></th>
                    <th><?php esc_html_e('Name', 'netctrl'); ?></th>
                    <th><?php esc_html_e('Location', 'netctrl'); ?></th>
                    <th><?php esc_html_e('License Class', 'netctrl'); ?></th>
                    <th><?php esc_html_e('Member', 'netctrl'); ?></th>
                    <th><?php esc_html_e('Officer', 'netctrl'); ?></th>
                    <th><?php esc_html_e('Actions', 'netctrl'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($entries)) : ?>
                    <tr>
                        <td colspan="7"><?php esc_html_e('No roster entries found.', 'netctrl'); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($entries as $entry) : ?>
                        <tr>
                            <td><?php echo esc_html($entry['callsign']); ?></td>
                            <td><?php echo esc_html($entry['name']); ?></td>
                            <td><?php echo esc_html($entry['location']); ?></td>
                            <td><?php echo esc_html($entry['license_class']); ?></td>
                            <td><?php echo (int) $entry['is_member'] === 1 ? esc_html__('Yes', 'netctrl') : esc_html__('No', 'netctrl'); ?></td>
                            <td><?php echo (int) $entry['is_officer'] === 1 ? esc_html__('Yes', 'netctrl') : esc_html__('No', 'netctrl'); ?></td>
                            <td>
                                <a href="<?php echo esc_url(add_query_arg(array(
                                    'page' => 'netctrl-roster',
                                    'netctrl_action' => 'edit',
                                    'roster_id' => (int) $entry['id'],
                                ), admin_url('admin.php'))); ?>"><?php esc_html_e('Edit', 'netctrl'); ?></a>
                                |
                                <a
                                    href="<?php echo esc_url(wp_nonce_url(add_query_arg(array(
                                        'page' => 'netctrl-roster',
                                        'netctrl_action' => 'delete',
                                        'roster_id' => (int) $entry['id'],
                                    ), admin_url('admin.php')), 'netctrl_roster_delete_' . (int) $entry['id'])); ?>"
                                    onclick="return confirm('<?php echo esc_js(__('Delete this roster entry?', 'netctrl')); ?>');"
                                >
                                    <?php esc_html_e('Delete', 'netctrl'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}
