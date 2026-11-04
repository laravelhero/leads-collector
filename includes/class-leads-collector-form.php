<?php

/**
 * Builds the form placeholder that the Lead Manager script turns into a form.
 */
defined('ABSPATH') || exit;

/**
 * Shared renderer for the shortcode, the block and the Elementor widget.
 */
final class Leads_Collector_Form
{
    /**
     * Options each placement may override. Empty values use the settings page defaults.
     */
    const OPTIONS = ['label', 'fields', 'required', 'columns', 'full', 'color', 'button_text', 'success_message', 'redirect', 'source'];

    /**
     * [leads_collector_form label="Quote form" fields="name,email,phone,message" columns="2" color="#2563eb"]
     *
     * @param  array<string, string>|string  $atts  Shortcode attributes.
     */
    public static function shortcode($atts): string
    {
        return self::render(shortcode_atts(array_fill_keys(self::OPTIONS, ''), is_array($atts) ? $atts : [], 'leads_collector_form'));
    }

    /**
     * @param  array<string, mixed>  $options  Placement options; see OPTIONS.
     */
    public static function render(array $options): string
    {
        if (! Leads_Collector_Settings::is_connected()) {
            return self::setup_notice();
        }

        $settings = Leads_Collector_Settings::all();
        $value = static function (string $key) use ($options) {
            return isset($options[$key]) && $options[$key] !== '' && $options[$key] !== [] ? $options[$key] : null;
        };

        $fields = Leads_Collector_Settings::sanitize_fields($value('fields') !== null ? $value('fields') : $settings['fields']);
        $required = Leads_Collector_Settings::sanitize_fields($value('required') !== null ? $value('required') : $settings['required']);
        $full = $value('full') !== null ? array_values(array_intersect(Leads_Collector_Settings::FIELDS, self::listed($value('full')))) : [];
        $color = sanitize_hex_color((string) ($value('color') !== null ? $value('color') : $settings['color']));
        $source = sanitize_key((string) $value('source'));
        $redirect = $value('redirect') !== null ? esc_url_raw((string) $value('redirect')) : '';

        $attributes = [
            'class' => 'leads-collector-form',
            'data-lead-form' => '',
            'data-label' => self::text($value('label') !== null ? $value('label') : $settings['form_label']),
            'data-fields' => implode(',', $fields),
            'data-required' => implode(',', array_intersect($required, $fields)),
            'data-columns' => (string) Leads_Collector_Settings::sanitize_columns($value('columns') !== null ? $value('columns') : $settings['columns']),
            'data-full' => implode(',', array_merge(['message'], $full)),
            'data-color' => $color ? $color : '',
            'data-button-text' => self::text($value('button_text') !== null ? $value('button_text') : $settings['button_text']),
            'data-success-message' => self::text($value('success_message') !== null ? $value('success_message') : $settings['success_message']),
            'data-redirect' => $redirect,
            'data-lead-source' => in_array($source, Leads_Collector_Settings::SOURCES, true) ? $source : '',
        ];

        $html = '';

        foreach ($attributes as $name => $attribute_value) {
            if ($attribute_value === '' && $name !== 'data-lead-form') {
                continue;
            }

            $html .= $attribute_value === '' ? ' '.$name : sprintf(' %s="%s"', $name, esc_attr($attribute_value));
        }

        return '<div'.$html.'></div>';
    }

    /**
     * Admins see how to finish setup; visitors see nothing.
     */
    private static function setup_notice(): string
    {
        if (! current_user_can('manage_options')) {
            return '';
        }

        return sprintf(
            '<div class="leads-collector-notice" style="padding:12px 16px;border:1px dashed #f59e0b;border-radius:8px;background:#fffbeb;color:#92400e;">%s <a href="%s">%s</a></div>',
            esc_html__('Leads Collector: add your site key to show this form.', 'leads-collector'),
            esc_url(admin_url('options-general.php?page=leads-collector')),
            esc_html__('Open settings', 'leads-collector')
        );
    }

    /**
     * @param  mixed  $value  Raw text.
     */
    private static function text($value): string
    {
        return sanitize_text_field((string) $value);
    }

    /**
     * Field names exactly as the placement listed them.
     *
     * @param  mixed  $value  Array or comma-separated string.
     * @return string[]
     */
    private static function listed($value): array
    {
        $list = is_array($value) ? $value : explode(',', (string) $value);

        return array_map('sanitize_key', array_map('trim', array_map('strval', $list)));
    }
}
