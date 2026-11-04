<?php

/**
 * Updates from the plugin's GitHub releases through the normal WordPress update screens.
 */
defined('ABSPATH') || exit;

/**
 * Every GitHub release that carries a "leads-collector.zip" asset becomes a WordPress
 * update: "Update now" on the Plugins screen, and auto-updates when switched on.
 */
final class Leads_Collector_Updater
{
    const REPOSITORY = 'laravelhero/leads-collector';

    const SLUG = 'leads-collector';

    const ASSET = 'leads-collector.zip';

    const CACHE_KEY = 'leads_collector_release';

    /**
     * How long a release lookup is reused; GitHub allows 60 anonymous lookups an hour per IP.
     */
    const CACHE_SECONDS = 6 * HOUR_IN_SECONDS;

    const RETRY_SECONDS = HOUR_IN_SECONDS;

    public static function init(): void
    {
        // WordPress asks this filter because the plugin header has "Update URI: https://github.com/...".
        add_filter('update_plugins_github.com', [__CLASS__, 'check'], 10, 3);
        add_filter('plugins_api', [__CLASS__, 'details'], 10, 3);
        add_action('load-update-core.php', [__CLASS__, 'forget_on_force_check']);
        add_action('upgrader_process_complete', [__CLASS__, 'forget'], 10, 0);
    }

    /**
     * @param  mixed  $update  Update data from an earlier filter, or false.
     * @param  array<string, string>  $plugin_data  Plugin headers.
     * @param  string  $plugin_file  Plugin basename.
     * @return mixed
     */
    public static function check($update, $plugin_data, $plugin_file)
    {
        if ($plugin_file !== plugin_basename(LEADS_COLLECTOR_FILE)) {
            return $update;
        }

        $release = self::latest_release();

        if (! $release) {
            return $update;
        }

        return [
            'id' => 'https://github.com/'.self::REPOSITORY,
            'slug' => self::SLUG,
            'plugin' => $plugin_file,
            'version' => $release['version'],
            'new_version' => $release['version'],
            'url' => $release['url'],
            'package' => $release['package'],
            'requires' => '6.3',
            'requires_php' => '7.4',
        ];
    }

    /**
     * The "View details" window on the Plugins and Updates screens.
     *
     * @param  mixed  $result  Result from an earlier filter.
     * @param  string  $action  Requested information.
     * @param  mixed  $args  Request arguments.
     * @return mixed
     */
    public static function details($result, $action, $args)
    {
        if ($action !== 'plugin_information' || ! is_object($args) || ! isset($args->slug) || $args->slug !== self::SLUG) {
            return $result;
        }

        $release = self::latest_release();

        if (! $release) {
            return $result;
        }

        return (object) [
            'name' => 'Leads Collector',
            'slug' => self::SLUG,
            'version' => $release['version'],
            'author' => '<a href="https://github.com/laravelhero">Abu Taher Hero</a>',
            'homepage' => 'https://github.com/'.self::REPOSITORY,
            'requires' => '6.3',
            'requires_php' => '7.4',
            'last_updated' => $release['published_at'],
            'download_link' => $release['package'],
            'sections' => [
                'description' => esc_html__('Modern lead forms and call tracking for Lead Manager.', 'leads-collector'),
                'changelog' => wpautop(esc_html($release['notes'])),
            ],
        ];
    }

    public static function forget_on_force_check(): void
    {
        if (isset($_GET['force-check'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flag set by WordPress.
            self::forget();
        }
    }

    public static function forget(): void
    {
        delete_site_transient(self::CACHE_KEY);
    }

    /**
     * The newest published release, cached.
     *
     * @return array{version: string, package: string, url: string, notes: string, published_at: string}|null
     */
    public static function latest_release(): ?array
    {
        $cached = get_site_transient(self::CACHE_KEY);

        if (is_array($cached)) {
            return isset($cached['version']) ? $cached : null;
        }

        $release = self::fetch_release();

        // An empty array remembers a failed lookup so GitHub is not asked on every page load.
        set_site_transient(self::CACHE_KEY, $release ? $release : [], $release ? self::CACHE_SECONDS : self::RETRY_SECONDS);

        return $release;
    }

    /**
     * @return array{version: string, package: string, url: string, notes: string, published_at: string}|null
     */
    private static function fetch_release(): ?array
    {
        $response = wp_remote_get(
            'https://api.github.com/repos/'.self::REPOSITORY.'/releases/latest',
            [
                'timeout' => 10,
                'headers' => [
                    'Accept' => 'application/vnd.github+json',
                    'User-Agent' => 'Leads-Collector/'.LEADS_COLLECTOR_VERSION.'; '.home_url('/'),
                ],
            ]
        );

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (! is_array($body) || empty($body['tag_name']) || ! empty($body['draft']) || ! empty($body['prerelease'])) {
            return null;
        }

        $version = ltrim((string) $body['tag_name'], 'vV');

        if (! preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            return null;
        }

        $package = '';
        $assets = isset($body['assets']) && is_array($body['assets']) ? $body['assets'] : [];

        foreach ($assets as $asset) {
            if (is_array($asset) && isset($asset['name'], $asset['browser_download_url']) && $asset['name'] === self::ASSET) {
                $package = (string) $asset['browser_download_url'];
            }
        }

        // Only ever install files published in this repository's releases.
        if (strpos($package, 'https://github.com/'.self::REPOSITORY.'/releases/download/') !== 0) {
            return null;
        }

        return [
            'version' => $version,
            'package' => $package,
            'url' => isset($body['html_url']) ? esc_url_raw((string) $body['html_url']) : 'https://github.com/'.self::REPOSITORY,
            'notes' => isset($body['body']) ? (string) $body['body'] : '',
            'published_at' => isset($body['published_at']) ? (string) $body['published_at'] : '',
        ];
    }
}
