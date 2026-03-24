<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_post_netctrl_download_pdf', 'netctrl_handle_pdf_download');
add_action('admin_post_nopriv_netctrl_download_pdf', 'netctrl_handle_pdf_download');

function netctrl_get_pdf_url($session_id)
{
    return add_query_arg(
        array(
            'action' => 'netctrl_download_pdf',
            'session_id' => absint($session_id),
        ),
        admin_url('admin-post.php')
    );
}

function netctrl_handle_pdf_download()
{
    $session_id = isset($_GET['session_id']) ? absint(wp_unslash($_GET['session_id'])) : 0;
    $session = $session_id ? netctrl_get_session($session_id) : null;

    if (!$session || ($session['status'] ?? '') !== 'closed') {
        wp_die(esc_html__('PDF downloads are only available for closed sessions.', 'netctrl'), 404);
    }

    nocache_headers();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="netctrl-session-' . $session_id . '.pdf"');
    echo netctrl_generate_session_pdf($session_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    exit;
}

function netctrl_generate_session_pdf($session_id)
{
    $session = netctrl_get_session($session_id);

    if (!$session) {
        return '';
    }

    $entries = netctrl_get_entries($session_id);
    $prepared_session = netctrl_prepare_session_for_response($session);
    $lines = array_merge(
        array('NETctrl / JCARA Session Log'),
        netctrl_build_pdf_kv_block(array(
            __('Session', 'netctrl') => ($prepared_session['net_name'] ?? ''),
            __('Status', 'netctrl') => ($prepared_session['status_label'] ?? ''),
            __('Created', 'netctrl') => ($prepared_session['created_at'] ?: ($prepared_session['started_at'] ?? '')),
        ))
    );

    if (!empty($prepared_session['closed_at'])) {
        $lines = array_merge($lines, netctrl_build_pdf_kv_block(array(
            __('Closed', 'netctrl') => $prepared_session['closed_at'],
        )));
    }

    $lines[] = '';
    $lines[] = __('Entries', 'netctrl');
    $lines[] = str_repeat('=', 94);

    if (!$entries) {
        $lines[] = 'No entries recorded.';
    } else {
        foreach ($entries as $index => $entry) {
            $prepared_entry = netctrl_prepare_entry_for_response($entry);
            $lines = array_merge($lines, netctrl_build_pdf_entry_card_lines($prepared_entry, $index + 1));
        }
    }

    return netctrl_build_simple_pdf($lines);
}

function netctrl_build_simple_pdf(array $lines)
{
    $lines_per_page = 54;
    $font_size = 9;
    $line_height = 12;
    $pages = array_chunk($lines, $lines_per_page);
    $objects = array();

    $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';

    $page_object_numbers = array();
    $content_object_numbers = array();

    $page_count = count($pages);
    $objects[] = '<< /Type /Pages /Kids [';

    $font_object_number = 3 + ($page_count * 2);

    for ($index = 0; $index < $page_count; $index++) {
        $page_object_numbers[$index] = 3 + ($index * 2);
        $content_object_numbers[$index] = 4 + ($index * 2);
        $objects[1] .= $page_object_numbers[$index] . ' 0 R ';
    }

    $objects[1] .= '] /Count ' . $page_count . ' >>';

    foreach ($pages as $index => $page_lines) {
        $page_stream = netctrl_build_pdf_page_stream($page_lines, $font_size, $line_height);
        $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 ' . $font_object_number . ' 0 R >> >> /Contents ' . $content_object_numbers[$index] . ' 0 R >>';
        $objects[] = '<< /Length ' . strlen($page_stream) . " >>\nstream\n" . $page_stream . "\nendstream";
    }

    $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>';

    return netctrl_render_pdf_objects($objects);
}

function netctrl_build_pdf_kv_block(array $values)
{
    $lines = array();

    foreach ($values as $label => $value) {
        $lines = array_merge($lines, netctrl_build_pdf_wrapped_field_lines((string) $label, (string) $value, 84));
    }

    return $lines;
}

function netctrl_build_pdf_entry_card_lines(array $entry, $entry_number)
{
    $title = sprintf(
        '#%d  %s  %s',
        (int) $entry_number,
        $entry['callsign'] ?: '—',
        $entry['created_at'] ?: '—'
    );
    $lines = array(str_repeat('-', 94));
    $lines = array_merge($lines, netctrl_wrap_pdf_text($title, 94));
    $lines = array_merge($lines, netctrl_build_pdf_wrapped_field_lines(__('Name', 'netctrl'), $entry['name'] ?: '—', 86));
    $lines = array_merge($lines, netctrl_build_pdf_wrapped_field_lines(__('Location', 'netctrl'), $entry['location'] ?: '—', 82));
    $lines = array_merge($lines, netctrl_build_pdf_wrapped_field_lines(__('Type', 'netctrl'), $entry['checkin_type'] === 'regular' ? __('Regular', 'netctrl') : __('Short Time / No Traffic', 'netctrl'), 86));

    if (!empty($entry['has_announcement'])) {
        $lines = array_merge($lines, netctrl_build_pdf_wrapped_field_lines(
            __('Announcement', 'netctrl'),
            (($entry['announcement_details'] ?? '') !== '' ? $entry['announcement_details'] : 'Yes'),
            78
        ));
    }

    if (!empty($entry['has_traffic'])) {
        $lines = array_merge($lines, netctrl_build_pdf_wrapped_field_lines(
            __('Traffic', 'netctrl'),
            (($entry['traffic_details'] ?? '') !== '' ? $entry['traffic_details'] : 'Yes'),
            83
        ));
    }

    if (!empty($entry['legacy_comments'])) {
        $lines = array_merge($lines, netctrl_build_pdf_wrapped_field_lines(__('Legacy Comments', 'netctrl'), $entry['legacy_comments'], 74));
    }

    return array_merge($lines, array(''));
}

function netctrl_build_pdf_wrapped_field_lines($label, $value, $first_line_width)
{
    $label = trim((string) $label);
    $value = netctrl_normalize_pdf_cell_value($value);

    $prefix = $label . ': ';
    $first_width = max(10, (int) $first_line_width);
    $next_width = 88;
    $chunks = netctrl_wrap_pdf_text($value, $first_width);
    $lines = array();

    foreach ($chunks as $index => $chunk) {
        if ($index === 0) {
            $lines[] = $prefix . $chunk;
        } else {
            foreach (netctrl_wrap_pdf_text($chunk, $next_width) as $continuation_line) {
                $lines[] = str_repeat(' ', strlen($prefix)) . $continuation_line;
            }
        }
    }

    return $lines;
}

function netctrl_normalize_pdf_cell_value($value)
{
    $text = str_replace(array("\r\n", "\r"), "\n", (string) $value);
    $lines = explode("\n", $text);
    $normalized_lines = array();

    foreach ($lines as $line) {
        $normalized_lines[] = trim((string) preg_replace('/\s+/', ' ', $line));
    }

    return trim(implode("\n", $normalized_lines));
}

function netctrl_wrap_pdf_text($text, $width)
{
    if ($width <= 1) {
        return array(substr($text, 0, 1));
    }

    if ($text === '') {
        return array('—');
    }

    $source_lines = explode("\n", (string) $text);
    $wrapped_lines = array();

    foreach ($source_lines as $source_line) {
        if ($source_line === '') {
            $wrapped_lines[] = '';
            continue;
        }

        $wrapped = wordwrap($source_line, $width, "\n", true);
        $wrapped_parts = explode("\n", (string) $wrapped);
        foreach ($wrapped_parts as $part) {
            $wrapped_lines[] = $part;
        }
    }

    return $wrapped_lines ?: array('—');
}

function netctrl_build_pdf_page_stream(array $lines, $font_size, $line_height)
{
    $escaped_lines = array_map('netctrl_escape_pdf_text', $lines);
    $stream = "BT\n/F1 {$font_size} Tf\n{$line_height} TL\n50 742 Td\n";

    foreach ($escaped_lines as $index => $line) {
        if ($index > 0) {
            $stream .= 'T*' . "\n";
        }

        $stream .= '(' . $line . ') Tj' . "\n";
    }

    $stream .= 'ET';

    return $stream;
}

function netctrl_escape_pdf_text($text)
{
    $text = wp_strip_all_tags((string) $text);
    $text = str_replace(array('\\', '(', ')'), array('\\\\', '\\(', '\\)'), $text);
    $text = preg_replace('/[^\x20-\x7E]/', '?', $text);

    return $text;
}

function netctrl_render_pdf_objects(array $objects)
{
    $pdf = "%PDF-1.4\n";
    $offsets = array(0);

    foreach ($objects as $index => $object) {
        $offsets[] = strlen($pdf);
        $object_number = $index + 1;
        $pdf .= $object_number . " 0 obj\n" . $object . "\nendobj\n";
    }

    $xref_offset = strlen($pdf);
    $pdf .= 'xref' . "\n";
    $pdf .= '0 ' . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";

    for ($index = 1; $index < count($offsets); $index++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$index]);
    }

    $pdf .= 'trailer << /Size ' . (count($objects) + 1) . ' /Root 1 0 R >>' . "\n";
    $pdf .= 'startxref' . "\n";
    $pdf .= $xref_offset . "\n";
    $pdf .= '%%EOF';

    return $pdf;
}
