<?php

/**
 * Loads the Lead Manager script on every public page.
 */
defined('ABSPATH') || exit;

/**
 * The script renders the forms and tracks every tel: link, so it runs on all pages.
 */
final class Leads_Collector_Frontend
{
    const HANDLE = 'leads-collector';

    public static function init(): void
    {
        add_action(
            'wp_enqueue_scripts',
            static function () {
                Leads_Collector_Guard::run([__CLASS__, 'enqueue']);
            }
        );
        add_filter(
            'script_loader_tag',
            static function ($tag, $handle) {
                return Leads_Collector_Guard::run(
                    static function () use ($tag, $handle) {
                        return self::script_tag((string) $tag, (string) $handle);
                    },
                    $tag
                );
            },
            10,
            2
        );
        add_shortcode(
            'leads_collector_form',
            static function ($atts) {
                return Leads_Collector_Guard::run(
                    static function () use ($atts) {
                        return Leads_Collector_Form::shortcode($atts);
                    },
                    ''
                );
            }
        );

        // Caching plugins must not combine or delay the script: it reads its own URL and site key.
        add_filter('rocket_exclude_js', [__CLASS__, 'exclude_from_optimizers']);
        add_filter('rocket_delay_js_exclusions', [__CLASS__, 'exclude_from_optimizers']);
    }

    public static function enqueue(): void
    {
        if (! Leads_Collector_Settings::is_connected()) {
            return;
        }

        wp_enqueue_script(
            self::HANDLE,
            Leads_Collector_Settings::get('server_url').'/js/lead-capture.js',
            [],
            null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- the server versions its own file.
            ['strategy' => 'defer']
        );
    }

    /**
     * Add the site key, plus the attributes LiteSpeed and Cloudflare Rocket Loader honour to leave the script alone.
     */
    public static function script_tag(string $tag, string $handle): string
    {
        if ($handle !== self::HANDLE) {
            return $tag;
        }

        $attributes = sprintf(
            '<script data-site-key="%s" data-no-optimize="1" data-cfasync="false" ',
            esc_attr(Leads_Collector_Settings::get('site_key'))
        );

        return preg_replace('/<script\s/', $attributes, $tag, 1);
    }

    /**
     * @param  mixed  $patterns  Existing exclusions.
     * @return array<int, string>
     */
    public static function exclude_from_optimizers($patterns): array
    {
        $patterns = is_array($patterns) ? $patterns : [];
        $patterns[] = '/js/lead-capture.js';

        return $patterns;
    }
}
