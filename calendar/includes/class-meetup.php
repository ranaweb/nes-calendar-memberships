<?php
defined('ABSPATH') || exit;

final class NES_Calendar_Meetup {
    private const STATE = 'nescal_meetup_conversion';
    private const LOCK = 'nescal_meetup_conversion_lock';
    private const SLUG = 'nes-monthly-meetups';
    private const ZONE = 'America/New_York';
    private $service;

    public function __construct($service) {
        $this->service = $service;
        add_action('admin_menu', function () {
            add_management_page('NES Calendar Migration', 'NES Calendar Migration', 'manage_options', 'nes-calendar-extension', [$this, 'page']);
        });
        add_action('admin_post_nescal_manage_meetup', [$this, 'handle']);
        add_action('template_redirect', [$this, 'legacy_link'], 1);
        add_action('admin_init', static function () {
            if (($GLOBALS['pagenow'] ?? '') === 'edit.php' && ($_GET['page'] ?? '') === 'nes-calendar-extension' && current_user_can('manage_options')) {
                wp_safe_redirect(admin_url('tools.php?page=nes-calendar-extension')); exit;
            }
            $state = get_option(self::STATE, []);
            if (!empty($state['active']) && class_exists('Tribe__Events__Pro__Main')) { self::restore(); }
        });
    }

    private static function source() {
        return get_page_by_path(self::SLUG, OBJECT, 'tribe_events');
    }
    private static function definition($id) {
        $start = get_post_meta($id, '_EventStartDate', true);
        $end = get_post_meta($id, '_EventEndDate', true);
        if ($start !== '2026-06-08 18:30:00' || $end !== '2026-06-08 20:00:00') {
            throw new RuntimeException('The original meetup time differs from the reviewed June 8 schedule. Review the source event before converting it.');
        }
        return ['start' => $start, 'timezone' => self::ZONE, 'duration' => 5400,
            'rules' => [['frequency' => 'monthly', 'interval' => 1, 'weekdays' => [1], 'ordinal' => [2], 'end' => 'never']]];
    }
    private static function family($series) {
        if (!$series) { return []; }
        $series = (int) get_post_meta($series, '_nescal_family', true) ?: $series;
        $children = get_posts(['post_type' => 'nescal_series', 'post_status' => 'private', 'fields' => 'ids',
            'numberposts' => -1, 'meta_key' => '_nescal_family', 'meta_value' => $series]);
        return array_values(array_unique(array_map('intval', array_merge([$series], $children))));
    }

    private static function entries($series) {
        $family = self::family($series);
        if (!$family) { return []; }
        global $wpdb;
        $placeholders = implode(',', array_fill(0, count($family), '%s'));
        return array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
             WHERE p.post_type = 'tribe_events' AND m.meta_key = '_nescal_series' AND m.meta_value IN ($placeholders) ORDER BY p.ID", $family
        )));
    }

    // Keep every original Pro occurrence and rule intact. Running event save hooks on the
    // Pro parent while Pro is absent can rebuild its occurrence table as a single event.
    private static function original_status($id, $from, $to) {
        global $wpdb;
        if (get_post_status($id) === $to) { return; }
        $changed = $wpdb->update($wpdb->posts, ['post_status' => $to],
            ['ID' => $id, 'post_type' => 'tribe_events', 'post_name' => self::SLUG, 'post_status' => $from]);
        clean_post_cache($id);
        if ($changed !== 1 && get_post_status($id) !== $to) { throw new RuntimeException('The original meetup status changed. Review it before retrying.'); }
    }

    private function make_seed($source, $definition) {
        $template = NES_Calendar_Recurrence_Service::template($source->ID);
        $seed = tribe_events()->set_args([
            'title' => $source->post_title, 'description' => $source->post_content, 'excerpt' => $source->post_excerpt,
            'status' => 'draft', 'author' => $source->post_author, 'post_password' => wp_slash($source->post_password),
            'start_date' => $definition['start'], 'end_date' => get_post_meta($source->ID, '_EventEndDate', true),
            'timezone' => self::ZONE, 'all_day' => false,
            'venue' => (int) ($template['meta']['_EventVenueID'] ?? 0), 'organizers' => $template['organizers'],
            '_nescal_original' => $source->ID,
        ])->create();
        if (!($seed instanceof WP_Post)) { throw new RuntimeException('The Events Calendar could not create the new meetup.'); }
        update_post_meta($seed->ID, '_nescal_original', $source->ID);
        foreach ($template['meta'] as $key => $value) { update_post_meta($seed->ID, $key, $value); }
        foreach ($template['tax'] as $taxonomy => $ids) { wp_set_object_terms($seed->ID, array_map('intval', $ids), $taxonomy); }
        return $seed->ID;
    }

    private function enable() {
        if (class_exists('Tribe__Events__Pro__Main') || !function_exists('tribe_events')) {
            throw new RuntimeException('Activate The Events Calendar and deactivate Events Calendar Pro before migrating the meetup.');
        }
        $source = self::source();
        if (!$source || !current_user_can('edit_post', $source->ID)) { throw new RuntimeException('The original public NES Monthly Meetups event is required.'); }
        $definition = self::definition($source->ID);
        $state = get_option(self::STATE, []);
        if (empty($state['source'])) {
            if ($source->post_status !== 'publish' || $source->post_password !== '') { throw new RuntimeException('The reviewed meetup must still be publicly published.'); }
            $state = ['source' => $source->ID, 'source_status' => $source->post_status, 'active' => false];
            update_option(self::STATE, $state, false);
        }
        if ((int) $state['source'] !== $source->ID) { throw new RuntimeException('The original meetup ID has changed.'); }
        if (empty($state['seed'])) {
            $state['seed'] = $this->make_seed($source, $definition);
            update_option(self::STATE, $state, false);
        }
        $seed = (int) $state['seed'];
        if ((int) get_post_meta($seed, '_nescal_original', true) !== $source->ID || !get_post($seed)) {
            throw new RuntimeException('The retained seed event could not be verified.');
        }
        $series = (int) get_post_meta($seed, '_nescal_series', true);
        NES_Calendar_Recurrence_Service::$writing = true;
        try {
            foreach (self::entries($series) as $id) {
                $held = get_post_meta($id, '_nescal_held_status', true);
                if ($held && get_post_status($id) === 'draft') {
                    $restored = wp_update_post(['ID' => $id, 'post_status' => $held], true);
                    if (is_wp_error($restored)) { throw new RuntimeException($restored->get_error_message()); }
                    delete_post_meta($id, '_nescal_held_status');
                }
            }
        } finally { NES_Calendar_Recurrence_Service::$writing = false; }
        if (get_post_status($seed) !== 'publish') {
            wp_update_post(['ID' => $seed, 'post_status' => 'publish', 'post_name' => self::SLUG . '-2026-06-08']);
        }
        if (!$series) { $series = $this->service->configure($seed, $definition, 'all', 0); }
        $state['series'] = $series;
        update_option(self::STATE, $state, false);
        update_post_meta($series, '_nescal_family', $series);
        foreach (self::family($series) as $part) {
            if (!get_post_meta($part, '_nescal_paused_before_restore', true)) { delete_post_meta($part, '_nescal_paused'); }
            delete_post_meta($part, '_nescal_paused_before_restore');
        }
        // Fill the historical dates once. Background refreshes respect edits and exclusions.
        if (empty($state['backfilled'])) {
            $this->service->generate($series, false, substr($definition['start'], 0, 10));
            $state['backfilled'] = true;
        } else { $this->service->generate($series); }
        if (count(self::entries($series)) < 2) { throw new RuntimeException('No additional meetup dates were created.'); }
        $state['manages_original'] = true;
        update_option(self::STATE, $state, false);
        self::original_status($source->ID, $state['source_status'], 'draft');
        $state['active'] = true;
        update_option(self::STATE, $state, false);
        self::clear_pages();
    }

    public static function restore() {
        $state = get_option(self::STATE, []);
        if (empty($state['source'])) { return; }
        $series = !empty($state['seed']) ? (int) get_post_meta($state['seed'], '_nescal_series', true) : 0;
        if (!empty($state['active']) || !empty($state['manages_original'])) {
            self::original_status((int) $state['source'], 'draft', $state['source_status']);
        }
        foreach (self::family($series) as $part) {
            if (!metadata_exists('post', $part, '_nescal_paused_before_restore')) {
                update_post_meta($part, '_nescal_paused_before_restore', get_post_meta($part, '_nescal_paused', true) ? 1 : 0);
            }
            update_post_meta($part, '_nescal_paused', 1);
        }
        $ids = self::entries($series);
        if (!empty($state['seed'])) { $ids[] = (int) $state['seed']; }
        NES_Calendar_Recurrence_Service::$writing = true;
        try {
            foreach (array_unique($ids) as $id) {
                $status = get_post_status($id);
                if (!in_array($status, ['publish','future','private','pending'], true)) { continue; }
                update_post_meta($id, '_nescal_held_status', $status);
                wp_update_post(['ID' => $id, 'post_status' => 'draft']);
            }
        } finally { NES_Calendar_Recurrence_Service::$writing = false; }
        $state['active'] = false;
        $state['manages_original'] = false;
        update_option(self::STATE, $state, false);
        self::clear_pages();
    }

    private static function clear_pages() {
        if (class_exists('Tribe__Cache') && method_exists('Tribe__Cache', 'instance')) {
            $cache = Tribe__Cache::instance();
            if (method_exists($cache, 'set_last_occurrence')) { $cache->set_last_occurrence('save_post'); }
        }
        if (class_exists('Elementor\Plugin') && isset(\Elementor\Plugin::$instance->files_manager)) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
        }
    }

    public function handle() {
        if (!current_user_can('manage_options')) { wp_die('Administrator access required.', '', ['response' => 403]); }
        check_admin_referer('nescal_manage_meetup');
        $mode = sanitize_key($_POST['mode'] ?? '');
        if (!in_array($mode, ['enable','restore','refresh'], true)) { wp_die('Unknown action.'); }
        $lock = (int) get_option(self::LOCK, 0);
        if ($lock && $lock < time() - 600) { delete_option(self::LOCK); }
        if (!add_option(self::LOCK, time(), '', false)) { wp_die('Another calendar operation is in progress.'); }
        try {
            if ($mode === 'restore') { self::restore(); }
            elseif ($mode === 'refresh') {
                $state = get_option(self::STATE, []);
                if (empty($state['active'])) { throw new RuntimeException('First enable the meetup series.'); }
                $count = 0;
                foreach (self::family($state['series']) as $part) { $count += $this->service->generate($part); }
                set_transient('nescal_result_' . get_current_user_id(), sprintf('Refreshed the series. Created %d additional dates.', $count), 120);
            } else { $this->enable(); }
        } catch (Throwable $e) {
            if ($mode === 'enable') {
                try { self::restore(); } catch (Throwable $rollback) { $e = new RuntimeException($e->getMessage() . ' Restore: ' . $rollback->getMessage()); }
            }
            set_transient('nescal_result_' . get_current_user_id(), $e->getMessage(), 120);
        } finally { delete_option(self::LOCK); }
        wp_safe_redirect(admin_url('tools.php?page=nes-calendar-extension'));
        exit;
    }

    public function page() {
        if (!current_user_can('manage_options')) { return; }
        $state = get_option(self::STATE, []);
        $active = !empty($state['active']);
        $pro = class_exists('Tribe__Events__Pro__Main');
        echo '<div class="wrap"><h1>NES Calendar Migration</h1><p>For everyday editing, open <a href="' . esc_url(admin_url('edit.php?post_type=' . NES_Calendar_Series::TYPE)) . '">Events → Series</a>.</p><h2>NES Monthly Meetups</h2>';
        echo '<p>Second Monday of each month, 6:30–8:00 p.m. New York time. Source event starts June 8, 2026.</p>';
        $message = get_transient('nescal_result_' . get_current_user_id());
        if ($message) { echo '<div class="notice notice-info"><p>' . esc_html($message) . '</p></div>'; delete_transient('nescal_result_' . get_current_user_id()); }
        if ($pro) { echo '<p>Events Calendar Pro currently owns the series. The extension is on standby.</p>'; }
        elseif ($active) {
            printf('<p><strong>The calendar extension manages this series.</strong> %d events are attached.</p>', count(self::entries($state['series'])));
            echo '<p>The event editor supports changes to one date, following dates, and future dates in the series.</p>';
        } else { echo '<p>The extension will copy the reviewed series into ordinary calendar events. The original Pro event and its rules are retained for restoration.</p>'; }
        echo '<form action="' . esc_url(admin_url('admin-post.php')) . '" method="post"><input type="hidden" name="action" value="nescal_manage_meetup">';
        wp_nonce_field('nescal_manage_meetup');
        if (!$pro && !$active && self::source()) { echo '<button class="button button-primary" name="mode" value="enable">Use the extension for this meetup</button>'; }
        if ($active) { echo '<button class="button" name="mode" value="refresh">Refresh upcoming dates</button> <button class="button" name="mode" value="restore">Restore original Pro meetup</button>'; }
        echo '</form></div>';
    }

    public function legacy_link() {
        $state = get_option(self::STATE, []);
        if (empty($state['active']) || class_exists('Tribe__Events__Pro__Main') || !in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET','HEAD'], true)) { return; }
        $path = trim((string) wp_parse_url(wp_unslash($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/');
        $home = trim((string) wp_parse_url(home_url('/'), PHP_URL_PATH), '/');
        if ($home !== '' && str_starts_with($path, $home . '/')) { $path = substr($path, strlen($home) + 1); }
        $slug = self::SLUG;
        if ($path === "series/$slug" || $path === "event/$slug") {
            $group = NES_Calendar_Series::for_event((int) ($state['seed'] ?? 0));
            if ($group && get_post_status($group) === 'publish') {
                if (is_singular(NES_Calendar_Series::TYPE) && get_queried_object_id() === $group) { return; }
                $url = get_permalink($group);
            } else {
                $url = add_query_arg('tribe-bar-search', 'NES Monthly Meetups', trailingslashit(tribe_get_events_link()) . 'list/');
            }
        } elseif (preg_match('~^event/nes-monthly-meetups/(\d{4}-\d{2}-\d{2})$~D', $path, $matches)) {
            global $wpdb;
            $family = self::family($state['series']);
            if (!$family) { return; }
            $placeholders = implode(',', array_fill(0, count($family), '%s'));
            $id = $wpdb->get_var($wpdb->prepare(
                "SELECT p.ID FROM {$wpdb->posts} p
                 JOIN {$wpdb->postmeta} s ON p.ID=s.post_id AND s.meta_key='_nescal_series' AND s.meta_value IN ($placeholders)
                 JOIN {$wpdb->postmeta} k ON p.ID=k.post_id AND k.meta_key='_nescal_key'
                 WHERE p.post_type='tribe_events' AND p.post_status='publish' AND k.meta_value LIKE %s ORDER BY p.ID LIMIT 1",
                array_merge($family, [$wpdb->esc_like($matches[1] . ' ') . '%'])
            ));
            if (!$id) { return; }
            $url = get_permalink((int) $id);
            if (isset($_GET['ical']) && $_GET['ical'] === '1') { $url = add_query_arg('ical', '1', $url); }
        } else { return; }
        wp_safe_redirect($url, 302, 'NES Calendar Extension');
        exit;
    }
}
