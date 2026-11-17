<?php
/** Event series and recurrence for the NES calendar. */
defined('ABSPATH') || exit;
require_once __DIR__ . '/includes/class-engine.php';
require_once __DIR__ . '/includes/class-service.php';
require_once __DIR__ . '/includes/class-meetup.php';
require_once __DIR__ . '/includes/class-series.php';
define('NESCAL_VERSION', NESCM_VERSION);

final class NES_Calendar_Settings {
    public static function get() {
        return ['recurring' => !class_exists('Tribe__Events__Pro__Main'), 'horizon' => 24];
    }
}

add_action('plugins_loaded', static function () {
    if (!function_exists('tribe_events')) { return; }
    $service = new NES_Calendar_Recurrence_Service();
    new NES_Calendar_Meetup($service);
    if (NES_Calendar_Settings::get()['recurring'] && function_exists('tribe_events')) { new NES_Calendar_Series(); }
}, 40);

// These two existing Elementor placements keep their saved settings and page IDs.
add_action('elementor/widgets/register', static function ($manager) {
    if (class_exists('Tribe__Events__Pro__Main') || !class_exists('Tribe\Events\Views\V2\Widgets\Widget_List')) {
        return;
    }
    require_once __DIR__ . '/includes/class-list-widget.php';
    $manager->register(new NES_Calendar_List_Widget());
}, 100);

add_action('tribe_events_date_display', static function ($id, $classic = true) {
    if ($classic && NES_Calendar_Settings::get()['recurring'] && !NES_Calendar_Series::is_backup($id)) {
        echo '<tr><th scope="row" class="tribe-datetime-label">Event Series</th><td>';
        nescal_event_box(get_post($id ?: get_the_ID()));
        echo '</td></tr>';
    }
}, 12, 2);
add_action('add_meta_boxes_tribe_events', static function ($post) {
    if (NES_Calendar_Settings::get()['recurring'] && use_block_editor_for_post($post) && !NES_Calendar_Series::is_backup($post->ID)) {
        add_meta_box('nescal-repeat', 'Event Series', 'nescal_event_box', 'tribe_events', 'normal', 'high');
    }
});
function nescal_event_box($post) {
    static $rendered = false;
    if ($rendered || !$post) { return; }
    $rendered = true;
    $series = (int) get_post_meta($post->ID, '_nescal_series', true);
    $definition = $series ? (array) get_post_meta($series, '_nescal_definition', true) : [];
    $definition['enabled'] = $series && !get_post_meta($series, '_nescal_paused', true);
    $definition['start'] = $definition['start'] ?? get_post_meta($post->ID, '_EventStartDate', true);
    $definition['timezone'] = $definition['timezone'] ?? (get_post_meta($post->ID, '_EventTimezone', true) ?: wp_timezone_string());
    wp_nonce_field('nescal_event', 'nescal_event_nonce');
    $json = esc_attr(wp_json_encode($definition));
    if ($series) {
        echo '<p><label for="nescal-scope">Apply changes to </label><select id="nescal-scope" name="nescal_scope"><option value="single">This event</option><option value="future">This and following events</option><option value="all">All events in this schedule</option></select></p>';
        $error = get_post_meta($series, '_nescal_error', true);
        if ($error) { echo '<p>' . esc_html($error) . '</p>'; }
    } else {
        echo '<input type="hidden" name="nescal_scope" value="all">';
    }
    printf('<div class="nescal-recurrence" data-event="%d" data-existing="%d" data-schedule="%s"></div>', $post->ID, (int) (bool) $series, $json);
    printf('<input type="hidden" id="nescal-schedule" name="nescal_schedule" value="%s">', $json);
    echo '<noscript>Enable JavaScript to change the repeat schedule.</noscript>';
    $error = get_transient('nescal_error_' . get_current_user_id());
    if ($error) { echo '<p>' . esc_html($error) . '</p>'; }
}

add_action('admin_enqueue_scripts', static function ($hook) {
    $screen = get_current_screen();
    if (!$screen || !in_array($screen->post_type, ['tribe_events', NES_Calendar_Series::TYPE], true) || !in_array($hook, ['post.php','post-new.php'], true) || !NES_Calendar_Settings::get()['recurring']) {
        return;
    }
    wp_enqueue_script('nescal-series', plugins_url('assets/series.js', __FILE__), [], NESCAL_VERSION, true);
    wp_localize_script('nescal-series', 'NESCalendarAdmin', [
        'ajax' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('nescal_admin'), 'timezone' => wp_timezone_string(),
    ]);
    if ($screen->post_type === 'tribe_events') {
        wp_enqueue_script('nescal-repeat', plugins_url('assets/recurrence.js', __FILE__), ['nescal-series'], NESCAL_VERSION, true);
    }
    wp_enqueue_style('nescal-repeat', plugins_url('assets/recurrence.css', __FILE__), [], NESCAL_VERSION);
});

add_action('wp_ajax_nescal_preview', static function () {
    check_ajax_referer('nescal_admin', 'nonce');
    $id = absint($_POST['event'] ?? 0);
    if (!current_user_can('edit_posts') || ($id && !current_user_can('edit_post', $id))) {
        wp_send_json_error(['message' => 'Editing permission is required.'], 403);
    }
    try {
        $data = json_decode(wp_unslash($_POST['schedule'] ?? ''), true);
        if (!is_array($data)) { throw new InvalidArgumentException('Choose a repeat schedule.'); }
        if (isset($_POST['event_fields']) && function_exists('tribe_get_date_format')) {
            $fields = json_decode(wp_unslash($_POST['event_fields']), true);
            if (is_array($fields) && !empty($fields['date'])) {
                $format = Tribe__Date_Utils::datepicker_formats(tribe_get_option('datepickerFormat', 1));
                $parse = static function ($value) use ($format) {
                    $value = trim((string) $value);
                    foreach (array_unique(['Y-m-d', is_string($format) ? $format : 'Y-m-d']) as $pattern) {
                        $date = DateTimeImmutable::createFromFormat('!' . $pattern, $value);
                        if ($date && $date->format($pattern) === $value) { return $date->format('Y-m-d'); }
                    }
                    throw new InvalidArgumentException('Check the event date in Time & Date.');
                };
                $clock = static function ($value) {
                    $time = strtolower(trim((string) $value));
                    if (!preg_match('/^(?:[01]?\d|2[0-3]):[0-5]\d(?::[0-5]\d)?(?:\s*[ap]\.?m\.?)?$/iD', $time)) { throw new InvalidArgumentException('Check the event time in Time & Date.'); }
                    return (new DateTimeImmutable(str_replace('.', '', $time), new DateTimeZone('UTC')))->format('H:i:s');
                };
                $start = $parse($fields['date']) . ' ' . (!empty($fields['allDay']) ? '00:00:00' : $clock($fields['time'] ?? ''));
                $end = $parse($fields['endDate'] ?? $fields['date']) . ' ' . (!empty($fields['allDay']) ? '23:59:59' : $clock($fields['endTime'] ?? ''));
                $data['duration'] = strtotime($end . ' UTC') - strtotime($start . ' UTC');
                if ($data['duration'] < 1) { throw new InvalidArgumentException('The end must follow the start.'); }
                $data['start'] = $start;
                $data['timezone'] = Tribe__Timezones::get_valid_timezone(($fields['zone'] ?? '') ?: wp_timezone_string());
            }
        }
        if (empty($data['start']) && $id) { $data = NES_Calendar_Recurrence_Service::definition($id, $data); }
        $from = substr($data['start'], 0, 10);
        if ($id && ($_POST['scope'] ?? '') === 'all') {
            $schedule = (int) get_post_meta($id, '_nescal_series', true);
            if ($schedule) { $data = NES_Calendar_Recurrence_Service::anchor($id, $schedule, $data); }
        }
        $data = NES_Calendar_Recurrence_Engine::normalize($data);
        if ($id && ($_POST['scope'] ?? '') === 'future') {
            $schedule = (int) get_post_meta($id, '_nescal_series', true);
            if ($schedule) { $data = NES_Calendar_Recurrence_Service::remaining($id, $schedule, $data); }
        }
        $through = (new DateTimeImmutable($from))->modify('+2 years')->format('Y-m-d');
        wp_send_json_success(['dates' => NES_Calendar_Recurrence_Engine::occurrences($data, $from, $through, 16), 'timezone' => $data['timezone']]);
    } catch (Throwable $e) { wp_send_json_error(['message' => $e->getMessage()], 400); }
});

function nescal_deactivate_calendar() {
    wp_clear_scheduled_hook('nescal_generate_events');
    delete_option('nescal_series_rewrites');
    NES_Calendar_Meetup::restore();
}
