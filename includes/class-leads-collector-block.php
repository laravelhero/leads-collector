<?php

/**
 * The "Leads Collector Form" block.
 */
defined('ABSPATH') || exit;

/**
 * Registers a server-rendered block; the editor script needs no build step.
 */
final class Leads_Collector_Block
{
    const NAME = 'leads-collector/form';

    public static function init(): void
    {
        add_action('init', [__CLASS__, 'register']);
    }

    public static function register(): void
    {
        wp_register_script(
            'leads-collector-block',
            LEADS_COLLECTOR_URL.'assets/js/block.js',
            ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n'],
            LEADS_COLLECTOR_VERSION,
            true
        );

        $settings = Leads_Collector_Settings::all();

        wp_add_inline_script(
            'leads-collector-block',
            'window.leadsCollectorBlock = '.wp_json_encode(
                [
                    'connected' => Leads_Collector_Settings::is_connected(),
                    'settingsUrl' => admin_url('options-general.php?page=leads-collector'),
                    'labels' => Leads_Collector_Settings::field_labels(),
                    'locked' => Leads_Collector_Settings::ALWAYS_REQUIRED,
                    'defaults' => [
                        'label' => $settings['form_label'],
                        'fields' => $settings['fields'],
                        'required' => $settings['required'],
                        'columns' => (int) $settings['columns'],
                        'color' => $settings['color'],
                        'buttonText' => $settings['button_text'],
                        'successMessage' => $settings['success_message'],
                    ],
                ]
            ).';',
            'before'
        );

        register_block_type(
            self::NAME,
            [
                'api_version' => 3,
                'title' => __('Leads Collector Form', 'leads-collector'),
                'description' => __('A lead form that sends to Lead Manager.', 'leads-collector'),
                'category' => 'widgets',
                'icon' => 'feedback',
                'keywords' => ['form', 'lead', 'contact', 'quote'],
                'editor_script' => 'leads-collector-block',
                'render_callback' => static function ($attributes) {
                    return Leads_Collector_Guard::run(
                        static function () use ($attributes) {
                            return self::render(is_array($attributes) ? $attributes : []);
                        },
                        ''
                    );
                },
                'supports' => [
                    'html' => false,
                    'align' => ['wide', 'full'],
                ],
                'attributes' => [
                    'label' => [
                        'type' => 'string',
                        'default' => '',
                    ],
                    'fields' => [
                        'type' => 'array',
                        'default' => [],
                        'items' => ['type' => 'string'],
                    ],
                    'required' => [
                        'type' => 'array',
                        'default' => [],
                        'items' => ['type' => 'string'],
                    ],
                    'columns' => [
                        'type' => 'number',
                        'default' => 0,
                    ],
                    'color' => [
                        'type' => 'string',
                        'default' => '',
                    ],
                    'buttonText' => [
                        'type' => 'string',
                        'default' => '',
                    ],
                    'successMessage' => [
                        'type' => 'string',
                        'default' => '',
                    ],
                    'redirect' => [
                        'type' => 'string',
                        'default' => '',
                    ],
                    'source' => [
                        'type' => 'string',
                        'default' => '',
                    ],
                ],
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $attributes  Block attributes.
     */
    public static function render(array $attributes): string
    {
        $form = Leads_Collector_Form::render(
            [
                'label' => isset($attributes['label']) ? $attributes['label'] : '',
                'fields' => isset($attributes['fields']) ? $attributes['fields'] : [],
                'required' => isset($attributes['required']) ? $attributes['required'] : [],
                'columns' => empty($attributes['columns']) ? '' : $attributes['columns'],
                'color' => isset($attributes['color']) ? $attributes['color'] : '',
                'button_text' => isset($attributes['buttonText']) ? $attributes['buttonText'] : '',
                'success_message' => isset($attributes['successMessage']) ? $attributes['successMessage'] : '',
                'redirect' => isset($attributes['redirect']) ? $attributes['redirect'] : '',
                'source' => isset($attributes['source']) ? $attributes['source'] : '',
            ]
        );

        if ($form === '') {
            return '';
        }

        return '<div '.get_block_wrapper_attributes().'>'.$form.'</div>';
    }
}
