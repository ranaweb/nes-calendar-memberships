<?php
defined('ABSPATH') || exit;

/** A named Series groups independent events and repeating schedules. */
final class NES_Calendar_Series {
    const TYPE = 'tribe_event_series';
    const GROUP = '_nescal_collection';

    public function __construct() {
        add_action('init', [self::class, 'register'], 21);
        add_action('admin_init', [$this, 'upgrade']);
        add_action('add_meta_boxes_' . self::TYPE, [$this, 'series_box']);
        add_action('add_meta_boxes_tribe_events', [$this, 'event_box']);
        add_action('save_post_' . self::TYPE, [$this, 'save_series'], 50);
        add_action('save_post_tribe_events', [$this, 'save_event'], 85);
        add_action('wp_ajax_nescal_find_events', [$this, 'search']);
        add_filter('manage_' . self::TYPE . '_posts_columns', [$this, 'columns']);
        add_action('manage_' . self::TYPE . '_posts_custom_column', [$this, 'column'], 10, 2);
        add_filter('the_content', [$this, 'public_content'], 12);
        add_action('tribe_events_single_event_before_the_content', [$this, 'event_link']);
        add_filter('elementor/widget/render_content', [$this, 'elementor_event_link'], 12, 2);
    }

    public static function register() {
        if (post_type_exists(self::TYPE)) { return; }
        register_post_type(self::TYPE, [
            'labels' => [
                'name' => 'Series', 'singular_name' => 'Series', 'menu_name' => 'Series',
                'add_new' => 'Add New Series', 'add_new_item' => 'Add New Series', 'edit_item' => 'Edit Series',
                'new_item' => 'New Series', 'view_item' => 'View Series', 'search_items' => 'Search Series',
                'not_found' => 'No series found.', 'not_found_in_trash' => 'No series found in Trash.',
                'all_items' => 'Series', 'item_published' => 'Series published.', 'item_updated' => 'Series updated.',
            ],
            'public' => true, 'show_in_rest' => false, 'show_in_menu' => 'edit.php?post_type=tribe_events',
            'supports' => ['title', 'editor', 'author'], 'has_archive' => false,
            'rewrite' => ['slug' => 'series', 'with_front' => false],
        ]);
    }

    private static function valid($id) {
        return $id && get_post_type($id) === self::TYPE && !in_array(get_post_status($id), ['trash','auto-draft'], true);
    }

    private static function owner($event) {
        return (int) get_post_meta($event, '_nescal_series', true) ?: (int) $event;
    }

    public static function for_event($event) {
        $id = (int) get_post_meta(self::owner($event), self::GROUP, true);
        return self::valid($id) ? $id : 0;
    }

    // Do not expose the retained Pro backup in the everyday event picker.
    public static function is_backup($event) {
        $conversion = get_option('nescal_meetup_conversion', []);
        return !empty($conversion['active']) && (int) ($conversion['source'] ?? 0) === (int) $event;
    }

    public static function attach_schedule($schedule, $source, $previous = 0) {
        if (metadata_exists('post', $schedule, self::GROUP)) { return; }
        $group = (int) get_post_meta($source, self::GROUP, true);
        if (!$group && $previous) { $group = (int) get_post_meta($previous, self::GROUP, true); }
        $state = get_option('nescal_meetup_conversion', []);
        if (!$group && !empty($state['source']) && ((int) ($state['series'] ?? 0) === (int) $schedule || (int) get_post_meta($source, '_nescal_original', true) === (int) $state['source'])) {
            $group = (int) get_post_meta($state['source'], self::GROUP, true);
            $original = get_page_by_path('nes-monthly-meetups', OBJECT, self::TYPE);
            if (!$group && $original) { $group = $original->ID; }
        }
        if (!self::valid($group)) {
            // This marker makes a retried upgrade recover the same post after an interrupted write.
            $found = get_posts(['post_type' => self::TYPE, 'post_status' => ['draft','publish','pending','private'],
                'meta_key' => '_nescal_created_for', 'meta_value' => $schedule, 'fields' => 'ids', 'numberposts' => 1]);
            $group = $found ? (int) $found[0] : wp_insert_post(wp_slash([
                'post_type' => self::TYPE, 'post_status' => 'draft', 'post_title' => get_post_field('post_title', $source, 'raw'),
                'post_author' => get_post_field('post_author', $source), 'meta_input' => ['_nescal_created_for' => $schedule],
            ]), true);
            if (!$group || is_wp_error($group)) { throw new RuntimeException('The series could not be created.'); }
        }
        update_post_meta($schedule, self::GROUP, $group);
        // On conversion a one-off membership moves to the repeating schedule.
        delete_post_meta($source, self::GROUP);
    }

    public function upgrade() {
        if (!current_user_can('manage_options')) { return; }
        if (get_option('nescal_series_schema') === '1.1') {
            if (!get_option('nescal_series_rewrites')) { flush_rewrite_rules(false); update_option('nescal_series_rewrites', 1, false); }
            return;
        }
        $lock = 'nescal_series_upgrade_lock';
        if ((int) get_option($lock) && (int) get_option($lock) < time() - 300) { delete_option($lock); }
        if (!add_option($lock, time(), '', false)) { return; }
        try {
            global $wpdb;
            $table = $wpdb->prefix . 'tec_series_relationships';
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table) {
                $rows = $wpdb->get_results("SELECT event_post_id, series_post_id FROM `$table` ORDER BY series_post_id");
                foreach ($rows as $row) {
                    if (get_post_type($row->event_post_id) === 'tribe_events' && self::valid($row->series_post_id)) {
                        add_post_meta($row->event_post_id, self::GROUP, (int) $row->series_post_id, true);
                    }
                }
            }
            $schedules = get_posts(['post_type' => 'nescal_series', 'post_status' => 'private',
                'numberposts' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC']);
            foreach ($schedules as $id) {
                $source = (int) get_post_meta($id, '_nescal_source', true);
                if (get_post_type($source) === 'tribe_events') { self::attach_schedule($id, $source, (int) get_post_meta($id, '_nescal_family', true)); }
            }
            flush_rewrite_rules(false);
            update_option('nescal_series_rewrites', 1, false);
            update_option('nescal_series_schema', '1.1', false);
        } catch (Throwable $error) {
            add_action('admin_notices', static function () use ($error) {
                echo '<div class="notice notice-error"><p>' . esc_html($error->getMessage()) . '</p></div>';
            });
        } finally { delete_option($lock); }
    }

    private static function events($group, $public = false) {
        $schedules = get_posts(['post_type' => 'nescal_series', 'post_status' => 'private', 'fields' => 'ids',
            'numberposts' => -1, 'meta_key' => self::GROUP, 'meta_value' => $group]);
        $membership = ['relation' => 'OR', ['key' => self::GROUP, 'value' => $group]];
        if ($schedules) { $membership[] = ['key' => '_nescal_series', 'value' => $schedules, 'compare' => 'IN']; }
        $events = get_posts([
            'post_type' => 'tribe_events', 'post_status' => $public ? 'publish' : ['publish','private','draft','pending','future'],
            'numberposts' => -1, 'meta_query' => [$membership], 'meta_key' => '_EventStartDateUTC', 'orderby' => ['meta_value' => 'ASC', 'ID' => 'ASC'],
            'tec_events_ignore' => true, 'tribe_suppress_query_filters' => true, 'suppress_filters' => !$public,
        ]);
        return array_values(array_filter($events, static function ($event) use ($public, $group) {
            if (self::is_backup($event->ID) || get_post_meta($event->ID, '_nescal_held_status', true) || self::for_event($event->ID) !== (int) $group) { return false; }
            if (!$public) { return current_user_can('edit_post', $event->ID); }
            return self::visible($event) && !tribe_is_truthy(get_post_meta($event->ID, '_EventHideFromUpcoming', true));
        }));
    }

    private static function visible($post) {
        if (!$post || $post->post_status !== 'publish' || post_password_required($post)) { return false; }
        return !is_callable(['MeprRule','is_locked']) || !MeprRule::is_locked($post);
    }

    private static function date_label($id) {
        return tribe_get_start_date($id, !tribe_event_is_all_day($id), tribe_event_is_all_day($id) ? 'M j, Y' : 'M j, Y, g:i a');
    }

    public function event_box($post) {
        if (self::is_backup($post->ID)) { return; }
        add_meta_box('nescal-series', 'Series', [$this, 'event_picker'], 'tribe_events', 'side', 'default');
    }

    public function event_picker($post) {
        $current = self::for_event($post->ID);
        $groups = get_posts(['post_type' => self::TYPE, 'post_status' => ['publish','draft','pending','private','future'],
            'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC']);
        wp_nonce_field('nescal_group_' . $post->ID, 'nescal_group_nonce');
        echo '<p><label for="nescal-group">Add this event to a series</label></p><select class="widefat" id="nescal-group" name="nescal_group">';
        if (!$current || self::owner($post->ID) === $post->ID) { echo '<option value="0">No series</option>'; }
        foreach ($groups as $group) {
            if ($group->ID !== $current && !current_user_can('edit_post', $group->ID)) { continue; }
            printf('<option value="%d" %s>%s</option>', $group->ID, selected($current, $group->ID, false), esc_html($group->post_title . ($group->post_status === 'publish' ? '' : ' (' . get_post_status_object($group->post_status)->label . ')')));
        }
        if (current_user_can(get_post_type_object(self::TYPE)->cap->create_posts)) { echo '<option value="new">Create a new series…</option>'; }
        echo '</select><p id="nescal-new-group" hidden><label for="nescal-group-name">New series name</label><input class="widefat" name="nescal_group_name" id="nescal-group-name"><small>Saved as a draft.</small></p>';
        if ($current && get_edit_post_link($current)) { printf('<p><a href="%s">Edit series</a></p>', esc_url(get_edit_post_link($current))); }
        if (self::owner($post->ID) !== $post->ID) { echo '<p class="description">All dates in this repeating event share this series.</p>'; }
    }

    private static function permitted($id) {
        if (get_post_type($id) !== 'tribe_events' || self::is_backup($id) || !current_user_can('edit_post', $id)) { return false; }
        $owner = self::owner($id);
        $source = $owner === $id ? $id : (int) get_post_meta($owner, '_nescal_source', true);
        $previous = self::for_event($id);
        return $source && current_user_can('edit_post', $source) && (!$previous || current_user_can('edit_post', $previous));
    }

    private $new_groups = [];
    public function save_event($id) {
        if (NES_Calendar_Recurrence_Service::$writing || wp_is_post_autosave($id) || wp_is_post_revision($id) ||
            !isset($_POST['nescal_group_nonce'], $_POST['nescal_group']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nescal_group_nonce'])), 'nescal_group_' . $id) || !self::permitted($id)) { return; }
        $choice = sanitize_text_field(wp_unslash($_POST['nescal_group']));
        $owner = self::owner($id);
        if ($choice === 'new') {
            $name = sanitize_text_field(wp_unslash($_POST['nescal_group_name'] ?? ''));
            if (!$name || !current_user_can(get_post_type_object(self::TYPE)->cap->create_posts)) { return; }
            $group = $this->new_groups[$id][$name] ?? wp_insert_post(wp_slash(['post_type' => self::TYPE, 'post_title' => $name, 'post_status' => 'draft']), true);
            if (!$group || is_wp_error($group)) { return; }
            $this->new_groups[$id][$name] = $group;
        } else { $group = absint($choice); }
        if ($group && self::valid($group) && current_user_can('edit_post', $group)) { update_post_meta($owner, self::GROUP, $group); }
        elseif ($choice === '0' && $owner === $id) { delete_post_meta($id, self::GROUP); }
    }

    public function series_box() { add_meta_box('nescal-members', 'Events in this series', [$this, 'members'], self::TYPE, 'normal', 'high'); }

    public function members($post) {
        wp_nonce_field('nescal_members_' . $post->ID, 'nescal_members_nonce');
        echo '<label for="nescal-search">Add an existing event</label><p><input type="search" class="regular-text" id="nescal-search" placeholder="Search event titles" autocomplete="off"> <button type="button" class="button" id="nescal-find">Find events</button></p>';
        echo '<div class="nescal-search-results" data-group="' . (int) $post->ID . '" aria-live="polite"></div>';
        $events = self::events($post->ID);
        if (!$events) { echo '<p>No events in this series yet.</p>'; return; }
        $sets = [];
        foreach ($events as $event) { $sets[self::owner($event->ID)][] = $event; }
        echo '<table class="widefat striped"><thead><tr><th>Event</th><th>Dates</th><th>First date</th><th>Membership</th></tr></thead><tbody>';
        foreach ($sets as $owner => $items) {
            $event = $items[0];
            $source = (int) get_post_meta($owner, '_nescal_source', true);
            $editor = $source && current_user_can('edit_post', $source) ? $source : $event->ID;
            printf('<tr><td><a href="%s">%s</a></td><td>%s</td><td>%s</td><td>', esc_url(get_edit_post_link($editor)), esc_html($event->post_title), esc_html(count($items) === 1 ? '1 date' : count($items) . ' dates'), esc_html(self::date_label($event->ID)));
            if (!$source) { printf('<label><input type="checkbox" name="nescal_remove[]" value="%d"> Remove</label>', $event->ID); }
            else { echo '<a href="' . esc_url(get_edit_post_link($editor) . '#nescal-series') . '">Change series</a>'; }
            echo '</td></tr>';
        }
        echo '</tbody></table>';
    }

    public function search() {
        check_ajax_referer('nescal_admin', 'nonce');
        $group = absint($_POST['group'] ?? 0);
        if (get_post_type($group) !== self::TYPE || !current_user_can('edit_post', $group)) { wp_send_json_error(null, 403); }
        $term = sanitize_text_field(wp_unslash($_POST['term'] ?? ''));
        if ($term === '') { wp_send_json_success([]); }
        $results = get_posts(['post_type' => 'tribe_events', 's' => $term, 'search_columns' => ['post_title'],
            'post_status' => ['draft','publish','future','private','pending'], 'numberposts' => -1,
            'tec_events_ignore' => true, 'tribe_suppress_query_filters' => true, 'orderby' => 'title', 'order' => 'ASC']);
        $choices = [];
        foreach ($results as $event) {
            $owner = self::owner($event->ID);
            if (isset($choices[$owner]) || self::for_event($event->ID) || !self::permitted($event->ID)) { continue; }
            $choices[$owner] = ['id' => $event->ID, 'label' => $event->post_title . ' · ' . self::date_label($event->ID)];
            if (count($choices) >= 30) { break; }
        }
        wp_send_json_success(array_values($choices));
    }

    public function save_series($id) {
        if (wp_is_post_autosave($id) || wp_is_post_revision($id) || !isset($_POST['nescal_members_nonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nescal_members_nonce'])), 'nescal_members_' . $id) || !current_user_can('edit_post', $id)) { return; }
        foreach (array_map('absint', (array) ($_POST['nescal_add'] ?? [])) as $event) {
            if (self::permitted($event) && !self::for_event($event)) { update_post_meta(self::owner($event), self::GROUP, $id); }
        }
        foreach (array_map('absint', (array) ($_POST['nescal_remove'] ?? [])) as $event) {
            if (self::permitted($event) && self::owner($event) === $event) { delete_post_meta($event, self::GROUP, $id); }
        }
    }

    public function columns($columns) {
        unset($columns['date']);
        return $columns + ['nescal_start' => 'Start date', 'nescal_events' => 'Event dates'];
    }
    private $listed = [];
    public function column($column, $id) {
        if (!in_array($column, ['nescal_start','nescal_events'], true)) { return; }
        $events = $this->listed[$id] ??= self::events($id);
        echo esc_html($column === 'nescal_events' ? (string) count($events) : ($events ? self::date_label($events[0]->ID) : '—'));
    }

    public function public_content($content) {
        if (!is_singular(self::TYPE) || !in_the_loop() || !is_main_query() || post_password_required() || !self::visible(get_post())) { return $content; }
        $group = get_the_ID();
        $past = isset($_GET['series_dates']) && $_GET['series_dates'] === 'past';
        $now = gmdate('Y-m-d H:i:s');
        $events = array_filter(self::events($group, true), static function ($event) use ($now, $past) {
            return (get_post_meta($event->ID, '_EventEndDateUTC', true) < $now) === $past;
        });
        $url = get_permalink($group);
        $html = '<section class="nescal-series-events"><h2>' . ($past ? 'Past events' : 'Upcoming events') . '</h2>';
        if ($events) {
            $html .= '<ul>';
            foreach ($past ? array_reverse($events) : $events as $event) {
                $html .= '<li><a href="' . esc_url(get_permalink($event)) . '">' . esc_html($event->post_title) . '</a> · ' . esc_html(self::date_label($event->ID)) . '</li>';
            }
            $html .= '</ul>';
        } else { $html .= $past ? '<p>No past events in this series.</p>' : '<p>No upcoming events in this series.</p>'; }
        $html .= '<p><a href="' . esc_url($past ? $url : add_query_arg('series_dates', 'past', $url)) . '">' . ($past ? 'View upcoming events' : 'View past events') . '</a></p></section>';
        return $content . $html;
    }

    private function event_link_markup($event) {
        $group = self::for_event($event);
        if (!$group || !self::visible(get_post($group)) || !self::visible(get_post($event))) { return ''; }
        return sprintf('<p class="nescal-series-link">Part of the series <a href="%s">%s</a></p>', esc_url(get_permalink($group)), esc_html(get_the_title($group)));
    }

    public function event_link() {
        echo $this->event_link_markup(get_the_ID());
    }

    public function elementor_event_link($content, $widget) {
        if (!is_singular('tribe_events') || $widget->get_name() !== 'theme-post-content' || str_contains($content, 'class="nescal-series-link"')) { return $content; }
        // Elementor's Single Event template displays Post Content without the TEC single-event hook.
        return $this->event_link_markup(get_queried_object_id()) . $content;
    }
}
