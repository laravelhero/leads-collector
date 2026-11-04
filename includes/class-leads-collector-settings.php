<?php

/**
 * Stored settings: the Lead Manager connection and the default form design.
 */
defined('ABSPATH') || exit;

/**
 * Reads, validates and cleans the plugin settings.
 */
final class Leads_Collector_Settings
{
    const OPTION = 'leads_collector_settings';

    const DEFAULT_SERVER = 'https://leads.websolutiontx.com';

    /**
     * Every field the built-in form can show, in display order.
     */
    const FIELDS = ['name', 'email', 'phone', 'model_number', 'message'];

    /**
     * Fields Lead Manager always needs, so they are always shown and required.
     */
    const ALWAYS_REQUIRED = ['name', 'phone'];

    /**
     * Fields that always take a whole row (the message box is too tall to share one).
     */
    const ALWAYS_FULL = ['message'];

    const SOURCES = ['page', 'post', 'popup', 'other'];

    /**
     * Translated field labels keyed by field name.
     *
     * @return array<string, string>
     */
    public static function field_labels(): array
    {
        return [
            'name' => __('Name', 'leads-collector'),
            'email' => __('Email', 'leads-collector'),
            'phone' => __('Phone', 'leads-collector'),
            'model_number' => __('Model number', 'leads-collector'),
            'message' => __('Message', 'leads-collector'),
        ];
    }

    /**
     * Settings used before anything is saved. A connection.php file shipped with
     * a pre-configured download fills in the server and site key.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        $defaults = [
            'server_url' => self::DEFAULT_SERVER,
            'site_key' => '',
            'form_label' => 'Website form',
            'fields' => ['name', 'email', 'phone', 'message'],
            'required' => ['name', 'email', 'phone'],
            'columns' => 2,
            'full' => ['name', 'model_number', 'message'],
            'color' => '#2563eb',
            'button_text' => 'Send',
            'success_message' => 'Thanks! We will contact you shortly.',
        ];

        $preset_file = LEADS_COLLECTOR_DIR.'connection.php';

        if (is_readable($preset_file)) {
            $preset = include $preset_file;

            if (is_array($preset)) {
                $server_url = self::sanitize_server_url(isset($preset['server_url']) ? $preset['server_url'] : '');

                $defaults['server_url'] = $server_url !== '' ? $server_url : $defaults['server_url'];
                $defaults['site_key'] = self::sanitize_site_key(isset($preset['site_key']) ? $preset['site_key'] : '');
            }
        }

        return $defaults;
    }

    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        $saved = get_option(self::OPTION, []);

        return array_merge(self::defaults(), is_array($saved) ? $saved : []);
    }

    /**
     * @return mixed
     */
    public static function get(string $key)
    {
        $settings = self::all();

        return isset($settings[$key]) ? $settings[$key] : null;
    }

    /**
     * Copy a pre-filled connection.php into the saved settings, so the connection
     * survives plugin updates (an update replaces the plugin folder).
     */
    public static function import_connection_file(): void
    {
        $saved = get_option(self::OPTION, []);

        if (is_array($saved) && ! empty($saved['site_key'])) {
            return;
        }

        if (! is_readable(LEADS_COLLECTOR_DIR.'connection.php')) {
            return;
        }

        $settings = self::all();

        if ($settings['site_key'] !== '') {
            update_option(self::OPTION, $settings);
        }
    }

    public static function is_connected(): bool
    {
        return (string) self::get('site_key') !== '' && (string) self::get('server_url') !== '';
    }

    /**
     * Reduce any URL to "https://host[:port]". Plain http is kept only for local development hosts.
     *
     * @param  mixed  $value  Raw input.
     */
    public static function sanitize_server_url($value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        if (! preg_match('#^https?://#i', $value)) {
            $value = 'https://'.$value;
        }

        $parts = wp_parse_url($value);

        if (empty($parts['host'])) {
            return '';
        }

        $host = strtolower($parts['host']);

        if (! preg_match('/^[a-z0-9.-]+$/', $host)) {
            return '';
        }

        $is_local = in_array($host, ['localhost', '127.0.0.1'], true) || preg_match('/\.(test|localhost)$/', $host);
        $scheme = (strtolower(isset($parts['scheme']) ? $parts['scheme'] : '') === 'http' && $is_local) ? 'http' : 'https';
        $port = isset($parts['port']) ? ':'.(int) $parts['port'] : '';

        return $scheme.'://'.$host.$port;
    }

    /**
     * Accept a bare site key or a pasted script snippet that contains one.
     *
     * @param  mixed  $value  Raw input.
     */
    public static function sanitize_site_key($value): string
    {
        return preg_match('/site_[A-Za-z0-9]{16,64}/', (string) $value, $match) ? $match[0] : '';
    }

    /**
     * Keep only known fields, in display order, always including the fields Lead Manager needs.
     *
     * @param  mixed  $value  Array or comma-separated string.
     * @return string[]
     */
    public static function sanitize_fields($value): array
    {
        $list = is_array($value) ? $value : explode(',', (string) $value);
        $list = array_map('sanitize_key', array_map('trim', array_map('strval', $list)));

        return array_values(array_intersect(self::FIELDS, array_merge($list, self::ALWAYS_REQUIRED)));
    }

    /**
     * Full-width fields, in display order, always including the ones that never share a row.
     *
     * @param  mixed  $value  Array or comma-separated string.
     * @return string[]
     */
    public static function sanitize_full($value): array
    {
        $list = is_array($value) ? $value : explode(',', (string) $value);
        $list = array_map('sanitize_key', array_map('trim', array_map('strval', $list)));

        return array_values(array_intersect(self::FIELDS, array_merge($list, self::ALWAYS_FULL)));
    }

    /**
     * Read the per-field width choices from the settings page: field => "half" or "full".
     *
     * @param  mixed  $value  Raw input.
     * @return string[]
     */
    public static function full_from_widths($value): array
    {
        $widths = is_array($value) ? $value : [];
        $full = [];

        foreach (self::FIELDS as $field) {
            if (isset($widths[$field]) && $widths[$field] === 'full') {
                $full[] = $field;
            }
        }

        return self::sanitize_full($full);
    }

    /**
     * @param  mixed  $value  Raw input.
     */
    public static function sanitize_columns($value): int
    {
        return max(1, min(3, (int) $value));
    }

    /**
     * Read the site key and server from what was typed: a bare key, or the whole
     * <script src="https://server/js/lead-capture.js" data-site-key="..."> snippet.
     *
     * @param  mixed  $raw_key  Site key field as typed.
     * @param  mixed  $raw_server  Lead Manager address field as typed.
     * @return array{server_url: string, site_key: string}
     */
    public static function parse_connection($raw_key, $raw_server): array
    {
        $raw_key = (string) $raw_key;
        $server_url = self::sanitize_server_url($raw_server);

        if (preg_match('/src=["\']([^"\']+)["\']/i', $raw_key, $src)) {
            $from_snippet = self::sanitize_server_url($src[1]);

            if ($from_snippet !== '') {
                $server_url = $from_snippet;
            }
        }

        return [
            'server_url' => $server_url,
            'site_key' => self::sanitize_site_key($raw_key),
        ];
    }

    /**
     * Clean everything submitted from the settings page.
     *
     * @param  mixed  $input  Raw option value.
     * @return array<string, mixed>
     */
    public static function sanitize($input): array
    {
        $input = is_array($input) ? wp_unslash($input) : [];
        $defaults = self::defaults();
        $raw_key = isset($input['site_key']) ? (string) $input['site_key'] : '';

        $connection = self::parse_connection($raw_key, isset($input['server_url']) ? $input['server_url'] : '');

        $clean = [
            'server_url' => $connection['server_url'] !== '' ? $connection['server_url'] : self::DEFAULT_SERVER,
            'site_key' => $connection['site_key'],
            'form_label' => self::text($input, 'form_label', $defaults['form_label'], 100),
            'fields' => self::sanitize_fields(isset($input['fields']) ? $input['fields'] : []),
            'columns' => self::sanitize_columns(isset($input['columns']) ? $input['columns'] : 2),
            'full' => isset($input['width']) ? self::full_from_widths($input['width']) : self::sanitize_full(isset($input['full']) ? $input['full'] : $defaults['full']),
            'color' => sanitize_hex_color(isset($input['color']) ? $input['color'] : ''),
            'button_text' => self::text($input, 'button_text', $defaults['button_text'], 60),
            'success_message' => self::text($input, 'success_message', $defaults['success_message'], 255),
        ];

        if (! $clean['color']) {
            $clean['color'] = $defaults['color'];
        }

        $required = self::sanitize_fields(isset($input['required']) ? $input['required'] : []);
        $clean['required'] = array_values(array_intersect($required, $clean['fields']));

        if (trim($raw_key) !== '' && $clean['site_key'] === '') {
            add_settings_error(self::OPTION, 'site_key', __('That does not look like a site key. Copy it from the website page in Lead Manager; it starts with "site_".', 'leads-collector'));
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $input  Submitted values.
     */
    private static function text(array $input, string $key, string $fallback, int $max_length): string
    {
        $value = isset($input[$key]) ? sanitize_text_field((string) $input[$key]) : '';

        return $value === '' ? $fallback : mb_substr($value, 0, $max_length);
    }
}
