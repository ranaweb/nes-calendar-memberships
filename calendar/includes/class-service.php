<?php
if (!defined('ABSPATH')) { exit; }
class NES_Calendar_Recurrence_Service {
    public static $writing=false;
    private $pending = [];
    public function __construct() {
        add_filter('post_password_required',[self::class,'password_required'],10,2);
        add_action('init', function() {
            register_post_type('nescal_series',['public'=>false,'show_ui'=>false,'supports'=>['title'],'can_export'=>false]);
            $enabled=!empty(NES_Calendar_Settings::get()['recurring']);
            if ($enabled && !wp_next_scheduled('nescal_generate_events')) { wp_schedule_event(time()+120,'hourly','nescal_generate_events'); }
            if (!$enabled) { wp_clear_scheduled_hook('nescal_generate_events'); }
        });
        add_action('nescal_generate_events', [$this,'cron']);
        add_action('save_post_tribe_events', [$this,'save'],100,3);
        add_action('before_delete_post', [$this,'remember_removal']);
        add_action('wp_trash_post', [$this,'remember_removal']);
    }
    public function remember_removal($id) {
        if (self::$writing) { return; }
        $series=(int)get_post_meta($id,'_nescal_series',true);
        $key=get_post_meta($id,'_nescal_key',true);
        if ($series && $key) {
            $removed=(array)get_post_meta($series,'_nescal_removed',true); $removed[$key]=true;
            update_post_meta($series,'_nescal_removed',$removed);
        }
    }
    public static function definition($id,$input=[]) {
        $start=get_post_meta($id,'_EventStartDate',true);
        $end=get_post_meta($id,'_EventEndDate',true);
        $zone=Tribe__Timezones::get_valid_timezone(get_post_meta($id,'_EventTimezone',true) ?: wp_timezone_string());
        if (!$start || !$end) { throw new InvalidArgumentException('Save a valid start and end time in The Events Calendar first.'); }
        $duration=strtotime($end.' UTC')-strtotime($start.' UTC');
        if ($duration<1) { throw new InvalidArgumentException('The event must end after it starts.'); }
        return NES_Calendar_Recurrence_Engine::normalize(array_replace($input,['start'=>$start,'timezone'=>$zone,'duration'=>$duration]));
    }
    public function save($id,$post,$update) {
        if (self::$writing || wp_is_post_autosave($id) || wp_is_post_revision($id) || !current_user_can('edit_post',$id)) { return; }
        $series=(int)get_post_meta($id,'_nescal_series',true);
        // Quick edits, API updates and manual reschedules become protected occurrence overrides.
        if ($series) { update_post_meta($id,'_nescal_override',1); }
        if (empty(NES_Calendar_Settings::get()['recurring']) || !isset($_POST['nescal_event_nonce'],$_POST['nescal_schedule']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nescal_event_nonce'])),'nescal_event')) { return; }
        $raw=json_decode(wp_unslash($_POST['nescal_schedule']),true);
        if (!is_array($raw)) { set_transient('nescal_error_'.get_current_user_id(),'The recurrence settings could not be read. Please reload the editor and try again.',300); return; }
        $scope=sanitize_key(wp_unslash($_POST['nescal_scope'] ?? 'single'));
        if ($series && $scope==='single') { return; }
        if (empty($raw['enabled'])) {
            if ($series) { update_post_meta($series,'_nescal_paused',1); }
            return;
        }
        if (isset($this->pending[$id])) { return; }
        $this->pending[$id] = true;
        add_action('shutdown',function()use($id,$raw,$scope,$series) {
            try { $definition=self::definition($id,$raw); $this->configure($id,$definition,$scope,$series); }
            catch (Throwable $e) { set_transient('nescal_error_'.get_current_user_id(),$e->getMessage(),300); }
        },5);
    }
    public static function template($id) {
        $post=get_post($id);
        $meta=[];
        foreach (['_EventVenueID','_EventCost','_EventCurrencySymbol','_EventCurrencyCode','_EventCurrencyPosition','_EventURL','_EventShowMap','_EventShowMapLink','_EventAllDay','_EventHideFromUpcoming','_tribe_events_status','_tribe_events_status_reason','_thumbnail_id'] as $key) { $meta[$key]=get_post_meta($id,$key,true); }
        $tax=[];
        foreach (get_object_taxonomies('tribe_events') as $taxonomy) { $terms=wp_get_object_terms($id,$taxonomy,['fields'=>'ids']); if (!is_wp_error($terms)) { $tax[$taxonomy]=$terms; } }
        return ['title'=>$post->post_title,'content'=>$post->post_content,'excerpt'=>$post->post_excerpt,'status'=>$post->post_status,'author'=>$post->post_author,'password'=>$post->post_password,'meta'=>$meta,'tax'=>$tax,'organizers'=>array_values(array_filter(array_map('absint',get_post_meta($id,'_EventOrganizerID',false))))];
    }
    public static function series_password($series,$template) {
        if (is_array($template) && array_key_exists('password',$template)) { return (string)$template['password']; }
        $source_id=(int)get_post_meta($series,'_nescal_source',true);
        $source=$source_id ? get_post($source_id) : null;
        // Old snapshots did not retain passwords. Never assume public access when recovery is ambiguous.
        if (!$source || $source->post_type!=='tribe_events' || (int)get_post_meta($source->ID,'_nescal_series',true)!==(int)$series) { return null; }
        return (string)$source->post_password;
    }
    public static function effective_password($post) {
        $post=get_post($post);
        if (!$post) { return null; }
        if ($post->post_type!=='tribe_events' || $post->post_password!=='' || get_post_meta($post->ID,'_nescal_override',true)) { return (string)$post->post_password; }
        $series=(int)get_post_meta($post->ID,'_nescal_series',true);
        return $series ? self::series_password($series,get_post_meta($series,'_nescal_template',true)) : '';
    }
    public static function password_required($required,$post) {
        if ($required || $post->post_type!=='tribe_events' || $post->post_password!=='') { return $required; }
        $password=self::effective_password($post);
        if ($password===null) { return true; }
        if ($password==='') { return $required; }
        $protected=clone $post;
        $protected->post_password=$password;
        // The nonempty password stops re-entry into this fallback; WordPress verifies its password cookie.
        return post_password_required($protected);
    }
    /** Keep a finite schedule and its interval anchored when editing a later occurrence. */
    public static function anchor($id, $series, $definition) {
        $old = get_post_meta($series, '_nescal_definition', true);
        $key = get_post_meta($id, '_nescal_key', true);
        if (empty($old['start']) || !$key || empty($definition['start'])) { return $definition; }
        $old_date = new DateTimeImmutable(substr($old['start'], 0, 10));
        $selected = new DateTimeImmutable(substr($key, 0, 10));
        $edited = new DateTimeImmutable(substr($definition['start'], 0, 10));
        $shift = $selected->diff($edited)->format('%r%a');
        $definition['start'] = $old_date->modify($shift . ' days')->format('Y-m-d') . substr($definition['start'], 10);
        return $definition;
    }

    public static function remaining($id, $series, $definition) {
        $old = get_post_meta($series, '_nescal_definition', true);
        $cutoff = get_post_meta($id, '_nescal_key', true);
        if (!$old || !$cutoff) { return $definition; }
        foreach (['rules', 'exclude_rules'] as $type) {
            foreach ($definition[$type] as $index => $rule) {
                $prior = $old[$type][$index] ?? [];
                if (($prior['end'] ?? '') !== 'count' || $rule['end'] !== 'count' || $rule['count'] !== $prior['count']) { continue; }
                $pattern = array_flip(['frequency','interval','weekdays','ordinal','monthdays','months']);
                if (array_intersect_key($prior, $pattern) !== array_intersect_key($rule, $pattern)) { continue; }
                $left = $prior['count'] - NES_Calendar_Recurrence_Engine::matches_before($prior, $cutoff);
                if ($left > 0) { $definition[$type][$index]['count'] = $left; }
                else { unset($definition[$type][$index]); }
            }
            $definition[$type] = array_values($definition[$type]);
        }
        return $definition;
    }

    public function configure($id,$definition,$scope='all',$series=0) {
        if (!function_exists('tribe_events')) { throw new RuntimeException('Activate The Events Calendar to generate events.'); }
        if (class_exists('Tribe__Events__Pro__Main')) { throw new RuntimeException('Deactivate Events Calendar Pro before enabling this recurrence.'); }
        $previous = (int) $series;
        $whole = $series && $scope === 'all';
        if ($whole) { $definition = self::anchor($id, $series, $definition); }
        $definition=NES_Calendar_Recurrence_Engine::normalize($definition);
        $adopt=[]; $removed=[];
        $family = $series ? (int) get_post_meta($series, '_nescal_family', true) : 0;
        $meetup = get_option('nescal_meetup_conversion', []);
        if (!$family && $series && (int) ($meetup['series'] ?? 0) === $series) { $family = $series; }
        if ($series && $scope==='future') {
            $definition = self::remaining($id, $series, $definition);
            $cutoff=get_post_meta($id,'_nescal_key',true) ?: $definition['start'];
            foreach ($this->children($series) as $p) {
                if ($p->ID!==$id && get_post_meta($p->ID,'_nescal_key',true)>=$cutoff) { $adopt[]=$p->ID; }
            }
            foreach ((array)get_post_meta($series,'_nescal_removed',true) as $key=>$value) { if ($key>=$cutoff) { $removed[$key]=true; } }
            $old_cutoff = get_post_meta($series, '_nescal_cutoff', true);
            $family = $family ?: $series;
            $series=0;
        }
        if (!$series) {
            $series=wp_insert_post(['post_type'=>'nescal_series','post_status'=>'private','post_title'=>get_the_title($id)],true);
            if (is_wp_error($series)) { throw new RuntimeException($series->get_error_message()); }
            if ($family) { update_post_meta($series, '_nescal_family', $family); }
            if (isset($cutoff)) {
                update_post_meta($previous, '_nescal_cutoff', $cutoff);
                if ($old_cutoff) { update_post_meta($series, '_nescal_cutoff', $old_cutoff); }
            }
        }
        update_post_meta($series,'_nescal_definition',$definition);
        update_post_meta($series,'_nescal_template',wp_slash(self::template($id)));
        update_post_meta($series,'_nescal_source',$id);
        foreach ($adopt as $occurrence) { update_post_meta($occurrence,'_nescal_series',$series); }
        if ($removed) { update_post_meta($series,'_nescal_removed',$removed); }
        delete_post_meta($series,'_nescal_paused');
        update_post_meta($id,'_nescal_series',$series);
        NES_Calendar_Series::attach_schedule($series, $id, $previous);
        if (!get_post_meta($id,'_nescal_key',true) || $scope==='future') { update_post_meta($id,'_nescal_key',$definition['start']); }
        // The edited occurrence stays exactly as saved; series propagation skips overrides.
        update_post_meta($id,'_nescal_override',1);
        $this->generate($series, true, $whole || isset($cutoff) ? substr($definition['start'], 0, 10) : null);
        return $series;
    }
    private function children($series) {
        // This is an internal inventory, not a visitor-facing calendar query. TEC's
        // custom-table replacement re-enables filters and can hide existing dates
        // from a background request, causing the generator to create them again.
        return get_posts(['post_type'=>'tribe_events','post_status'=>['publish','future','draft','pending','private','trash'],'numberposts'=>-1,'meta_key'=>'_nescal_series','meta_value'=>$series,'orderby'=>'ID','order'=>'ASC','suppress_filters'=>true,'tribe_suppress_query_filters'=>true,'tec_events_ignore'=>true]);
    }
    public function generate($series,$reconcile=false,$archive_from=null) {
        if (empty(NES_Calendar_Settings::get()['recurring']) || !function_exists('tribe_events') || class_exists('Tribe__Events__Pro__Main') || get_post_meta($series,'_nescal_paused',true)) { return 0; }
        $lock='nescal_lock_'.$series;
        $stamp=(int)get_option($lock,0);
        if ($stamp && $stamp<time()-600) { delete_option($lock); }
        if (!add_option($lock,time(),'','no')) { return 0; }
        self::$writing=true;
        try {
            $definition=get_post_meta($series,'_nescal_definition',true);
            $template=get_post_meta($series,'_nescal_template',true);
            if (!$definition || !$template) { return 0; }
            $password=self::series_password($series,$template);
            if ($password===null) { throw new RuntimeException('The original event password could not be recovered. Review and save this series from its source event before generating more dates.'); }
            if (!array_key_exists('password',$template)) { $template['password']=$password; update_post_meta($series,'_nescal_template',wp_slash($template)); }
            $zone=new DateTimeZone($definition['timezone']);
            $now=new DateTimeImmutable('now',$zone);
            $from=$now->format('Y-m-d');
            $through=$now->modify('+'.(int)NES_Calendar_Settings::get()['horizon'].' months')->format('Y-m-d');
            // A one-time NES conversion may restore the original public dates. Scheduled runs only add upcoming dates.
            if ($archive_from !== null && NES_Calendar_Recurrence_Engine::date($archive_from)) {
                $from = max($archive_from, substr($definition['start'], 0, 10));
            }
            $slots=NES_Calendar_Recurrence_Engine::occurrences($definition,$from,$through,10000);
            $cutoff=get_post_meta($series,'_nescal_cutoff',true);
            $removed=(array)get_post_meta($series,'_nescal_removed',true);
            $existing=[]; $actual_starts=[];
            foreach ($this->children($series) as $p) {
                $key=get_post_meta($p->ID,'_nescal_key',true);
                // Prefer the oldest surviving occurrence over a trashed duplicate.
                if (!isset($existing[$key]) || ($existing[$key]->post_status==='trash' && $p->post_status!=='trash')) { $existing[$key]=$p; }
                if ($p->post_status!=='trash') { $actual_starts[get_post_meta($p->ID,'_EventStartDate',true)]=true; }
            }
            // A time-only edit on a day with one occurrence keeps its post ID, tickets and incoming links.
            if ($reconcile) {
                $old_days = []; $new_days = [];
                foreach ($existing as $key => $p) { if ($p->post_status !== 'trash') { $old_days[substr($key, 0, 10)][] = $key; } }
                foreach ($slots as $slot) { $new_days[substr($slot['key'], 0, 10)][] = $slot['key']; }
                foreach ($new_days as $day => $keys) {
                    if (count($keys) !== 1 || count($old_days[$day] ?? []) !== 1 || isset($existing[$keys[0]]) || isset($removed[$keys[0]])) { continue; }
                    $old_key = $old_days[$day][0];
                    $existing[$keys[0]] = $existing[$old_key];
                    update_post_meta($existing[$old_key]->ID, '_nescal_key', $keys[0]);
                    unset($existing[$old_key]);
                }
            }
            $wanted=[]; $created=0;
            foreach ($slots as $slot) {
                $post=$existing[$slot['key']] ?? null;
                // A removed duplicate must not cancel its surviving original.
                if (($cutoff && $slot['key']>=$cutoff) || (isset($removed[$slot['key']]) && (!$post || $post->post_status==='trash'))) { continue; }
                $wanted[$slot['key']]=true;
                if ($archive_from === null && $slot['utc']<gmdate('Y-m-d H:i:s')) { continue; }
                if ($post && $post->post_status==='trash' && $reconcile && get_post_meta($post->ID,'_nescal_retired',true)) {
                    wp_untrash_post($post->ID); delete_post_meta($post->ID,'_nescal_retired'); $post=get_post($post->ID);
                }
                if ($post && ($post->post_status==='trash' || get_post_meta($post->ID,'_nescal_override',true))) { continue; }
                if (!$post && isset($actual_starts[$slot['start']])) { continue; }
                if ($post && !$reconcile) { continue; }
                if (!$post && $created>=100) { continue; }
                $args=['title'=>$template['title'],'description'=>$template['content'],'excerpt'=>$template['excerpt'],'status'=>$post ? $template['status'] : 'draft','author'=>$template['author'],'start_date'=>$slot['start'],'end_date'=>$slot['end'],'timezone'=>$definition['timezone'],'all_day'=>$template['meta']['_EventAllDay']==='yes','_nescal_series'=>$series,'_nescal_key'=>$slot['key']];
                // Explicit series updates propagate password changes; individual overrides were skipped above.
                $args['post_password']=wp_slash($password);
                $args['organizers']=$template['organizers'] ?? [];
                $args['venue']=(int)($template['meta']['_EventVenueID'] ?? 0);
                $result=$post ? tribe_events()->where('id',$post->ID)->set_args($args)->save() : tribe_events()->set_args($args)->create();
                $event_id=$post ? $post->ID : ($result instanceof WP_Post ? $result->ID : 0);
                if (!$result || !$event_id || is_wp_error($result)) { throw new RuntimeException('The calendar could not save an occurrence. Existing events were preserved.'); }
                update_post_meta($event_id,'_nescal_series',$series);
                update_post_meta($event_id,'_nescal_key',$slot['key']);
                foreach ($template['meta'] as $key=>$value) { update_post_meta($event_id,$key,$value); }
                foreach ($template['tax'] as $taxonomy=>$terms) { wp_set_object_terms($event_id,array_map('intval',$terms),$taxonomy); }
                // Publish only after access categories and private metadata are in place.
                if (!$post && $template['status']!=='draft') {
                    $published = wp_update_post([
                        'ID' => $event_id,
                        'post_status' => $template['status'],
                        'post_name' => sanitize_title($template['title'] . '-' . substr($slot['start'], 0, 10)),
                    ], true);
                    if (is_wp_error($published)) { throw new RuntimeException($published->get_error_message()); }
                }
                if (!$post) { ++$created; }
            }
            if ($reconcile) {
                foreach ($existing as $key=>$p) {
                    if (!isset($wanted[$key]) && substr($key,0,10)<=$through && ($archive_from !== null || get_post_meta($p->ID,'_EventStartDateUTC',true)>=gmdate('Y-m-d H:i:s')) && !get_post_meta($p->ID,'_nescal_override',true) && $p->post_status!=='trash') {
                        if (is_callable(['Tribe__Tickets__Tickets', 'get_all_event_tickets']) && Tribe__Tickets__Tickets::get_all_event_tickets($p->ID)) { update_post_meta($p->ID, '_nescal_override', 1); continue; }
                        update_post_meta($p->ID,'_nescal_retired',1); wp_trash_post($p->ID);
                    }
                }
            }
            update_post_meta($series,'_nescal_last_run',gmdate('c'));
            delete_post_meta($series,'_nescal_error');
            return $created;
        } catch (Throwable $e) { update_post_meta($series,'_nescal_error',$e->getMessage()); throw $e; }
        finally { self::$writing=false; delete_option($lock); }
    }
    public function cron() {
        foreach (get_posts(['post_type'=>'nescal_series','post_status'=>'private','numberposts'=>-1]) as $series) {
            try { $this->generate($series->ID); } catch (Throwable $e) { /* Visible on the Events settings tab; retry on the next run. */ }
        }
    }
}
