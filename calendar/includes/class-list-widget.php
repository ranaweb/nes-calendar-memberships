<?php
defined('ABSPATH') || exit;

final class NES_Calendar_List_Widget extends \Elementor\Widget_Base {
    public function get_name() { return 'tec_elementor_widget_events_list_widget'; }
    public function get_title() { return 'Events List'; }
    public function get_icon() { return 'eicon-post-list'; }
    public function get_categories() { return ['general']; }
    protected function register_controls() {
        $this->start_controls_section('content_section', ['label' => 'Events']);
        $this->add_control('limit', ['label' => 'Maximum events', 'type' => \Elementor\Controls_Manager::NUMBER, 'min' => 1, 'max' => 30, 'default' => 5]);
        $this->end_controls_section();
    }
    protected function render() {
        $limit = (int) $this->get_settings_for_display('limit');
        $widget_class = \Tribe\Events\Views\V2\Widgets\Widget_List::class;
        $widget = new $widget_class();
        $widget->widget([
            'widget_id' => 'nescal-' . $this->get_id(), 'before_widget' => '', 'after_widget' => '',
            'before_title' => '', 'after_title' => '',
        ], ['title' => '', 'limit' => $limit ? min(30, max(1, $limit)) : 5]);
    }
}
