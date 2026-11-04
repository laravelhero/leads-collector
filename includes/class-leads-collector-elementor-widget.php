<?php

/**
 * The "Leads Collector Form" Elementor widget. Loaded only when Elementor registers widgets.
 */
defined('ABSPATH') || exit;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

/**
 * Lets Elementor users place and style a lead form from the panel.
 */
class Leads_Collector_Elementor_Widget extends Widget_Base
{
    public function get_name(): string
    {
        return 'leads_collector_form';
    }

    public function get_title(): string
    {
        return __('Leads Collector Form', 'leads-collector');
    }

    public function get_icon(): string
    {
        return 'eicon-form-horizontal';
    }

    /**
     * @return string[]
     */
    public function get_categories(): array
    {
        return ['general'];
    }

    /**
     * @return string[]
     */
    public function get_keywords(): array
    {
        return ['form', 'lead', 'contact', 'quote', 'leads collector'];
    }

    protected function register_controls(): void
    {
        $settings = Leads_Collector_Settings::all();
        $labels = Leads_Collector_Settings::field_labels();
        $optional = array_diff_key($labels, array_flip(Leads_Collector_Settings::ALWAYS_REQUIRED));

        $this->start_controls_section(
            'section_form',
            [
                'label' => __('Form', 'leads-collector'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        if (! Leads_Collector_Settings::is_connected()) {
            $this->add_control(
                'setup_notice',
                [
                    'type' => Controls_Manager::RAW_HTML,
                    'raw' => esc_html__('Add your site key in Settings → Leads Collector to show this form on the site.', 'leads-collector'),
                    'content_classes' => 'elementor-panel-alert elementor-panel-alert-warning',
                ]
            );
        }

        $this->add_control(
            'fields',
            [
                'label' => __('Extra fields', 'leads-collector'),
                'description' => __('Name and phone are always included.', 'leads-collector'),
                'type' => Controls_Manager::SELECT2,
                'multiple' => true,
                'options' => $optional,
                'default' => array_values(array_intersect(array_keys($optional), $settings['fields'])),
                'label_block' => true,
            ]
        );

        $this->add_control(
            'required',
            [
                'label' => __('Required extra fields', 'leads-collector'),
                'type' => Controls_Manager::SELECT2,
                'multiple' => true,
                'options' => $optional,
                'default' => array_values(array_intersect(array_keys($optional), $settings['required'])),
                'label_block' => true,
            ]
        );

        $this->add_control(
            'columns',
            [
                'label' => __('Columns', 'leads-collector'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    '1' => '1',
                    '2' => '2',
                    '3' => '3',
                ],
                'default' => (string) $settings['columns'],
            ]
        );

        $this->add_control(
            'color',
            [
                'label' => __('Button and accent color', 'leads-collector'),
                'type' => Controls_Manager::COLOR,
                'default' => $settings['color'],
                'alpha' => false,
                'global' => ['active' => false],
            ]
        );

        $this->add_control(
            'button_text',
            [
                'label' => __('Button text', 'leads-collector'),
                'type' => Controls_Manager::TEXT,
                'default' => $settings['button_text'],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_after',
            [
                'label' => __('After sending', 'leads-collector'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'success_message',
            [
                'label' => __('Message', 'leads-collector'),
                'type' => Controls_Manager::TEXT,
                'default' => $settings['success_message'],
            ]
        );

        $this->add_control(
            'redirect',
            [
                'label' => __('Go to this page (optional)', 'leads-collector'),
                'type' => Controls_Manager::URL,
                'options' => false,
                'placeholder' => home_url('/thank-you/'),
            ]
        );

        $this->add_control(
            'label',
            [
                'label' => __('Form name in Lead Manager', 'leads-collector'),
                'type' => Controls_Manager::TEXT,
                'default' => $settings['form_label'],
                'separator' => 'before',
                'label_block' => true,
            ]
        );

        $this->add_control(
            'source',
            [
                'label' => __('Lead source', 'leads-collector'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    '' => __('Detect automatically', 'leads-collector'),
                    'page' => __('Page', 'leads-collector'),
                    'post' => __('Post', 'leads-collector'),
                    'popup' => __('Popup', 'leads-collector'),
                    'other' => __('Other', 'leads-collector'),
                ],
                'default' => '',
            ]
        );

        $this->end_controls_section();
    }

    protected function render(): void
    {
        Leads_Collector_Guard::run([$this, 'render_form']);
    }

    /**
     * Public so the guard can call it; Elementor only calls render().
     */
    public function render_form(): void
    {
        $settings = $this->get_settings_for_display();
        $redirect = isset($settings['redirect']['url']) ? $settings['redirect']['url'] : '';

        // Escaped inside the renderer.
        echo Leads_Collector_Form::render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            [
                'label' => isset($settings['label']) ? $settings['label'] : '',
                'fields' => array_merge(Leads_Collector_Settings::ALWAYS_REQUIRED, (array) (isset($settings['fields']) ? $settings['fields'] : [])),
                'required' => array_merge(Leads_Collector_Settings::ALWAYS_REQUIRED, (array) (isset($settings['required']) ? $settings['required'] : [])),
                'columns' => isset($settings['columns']) ? $settings['columns'] : '',
                'color' => isset($settings['color']) ? $settings['color'] : '',
                'button_text' => isset($settings['button_text']) ? $settings['button_text'] : '',
                'success_message' => isset($settings['success_message']) ? $settings['success_message'] : '',
                'redirect' => $redirect,
                'source' => isset($settings['source']) ? $settings['source'] : '',
            ]
        );
    }
}
