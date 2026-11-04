<?php

/**
 * Keeps the website online if this plugin ever breaks.
 */
defined('ABSPATH') || exit;

/**
 * When the plugin causes an error it "pauses" itself: forms, the tracking script and
 * the editor integrations stop loading, so the site keeps working. Updates keep
 * arriving, and a new version is tried again automatically.
 */
final class Leads_Collector_Guard
{
    const OPTION = 'leads_collector_paused';

    const RESUME_ACTION = 'leads_collector_resume';

    /**
     * Error types that stop PHP and would show "There has been a critical error".
     */
    const FATAL_TYPES = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR];

    /**
     * Whether the plugin is paused for the version that is installed now.
     */
    public static function is_paused(): bool
    {
        $paused = get_option(self::OPTION);

        if (! is_array($paused)) {
            return false;
        }

        // A new version gets a fresh start.
        if (! isset($paused['version']) || $paused['version'] !== LEADS_COLLECTOR_VERSION) {
            delete_option(self::OPTION);

            return false;
        }

        return true;
    }

    /**
     * Catch fatal errors raised from the plugin's own files.
     */
    public static function watch(): void
    {
        register_shutdown_function([__CLASS__, 'on_shutdown']);
    }

    /**
     * While paused, only show the admin notice and the "Try again" handler.
     */
    public static function init_paused(): void
    {
        add_action('admin_notices', [__CLASS__, 'paused_notice']);
        add_action('admin_post_'.self::RESUME_ACTION, [__CLASS__, 'resume']);

        // Visitors see nothing instead of the raw [leads_collector_form] text.
        add_shortcode('leads_collector_form', '__return_empty_string');
    }

    /**
     * Run plugin code; if it throws, pause the plugin and return the fallback instead.
     *
     * @param  mixed  $fallback  Value returned when the callback fails.
     * @return mixed
     */
    public static function run(callable $callback, $fallback = null)
    {
        try {
            return $callback();
        } catch (Throwable $error) {
            self::caught($error);

            return $fallback;
        }
    }

    public static function caught(Throwable $error): void
    {
        self::pause($error->getMessage(), $error->getFile(), $error->getLine());
    }

    public static function on_shutdown(): void
    {
        $error = error_get_last();

        if (! is_array($error) || ! in_array($error['type'], self::FATAL_TYPES, true)) {
            return;
        }

        // Also catch errors raised inside WordPress when our code was in the stack trace.
        if (! self::is_ours($error['file']) && ! self::is_ours($error['message'])) {
            return;
        }

        self::pause($error['message'], $error['file'], $error['line']);
    }

    public static function pause(string $message, string $file, int $line): void
    {
        if (! function_exists('update_option') || get_option(self::OPTION)) {
            return;
        }

        // The first line only: stack traces can be long and include server paths.
        $message = strtok($message, "\n");

        update_option(
            self::OPTION,
            [
                'version' => LEADS_COLLECTOR_VERSION,
                'message' => mb_substr(wp_strip_all_tags((string) $message), 0, 500),
                'file' => self::relative_path($file),
                'line' => $line,
                'time' => time(),
            ]
        );
    }

    public static function paused_notice(): void
    {
        $paused = get_option(self::OPTION);

        if (! is_array($paused) || ! current_user_can('activate_plugins')) {
            return;
        }

        $resume_url = wp_nonce_url(admin_url('admin-post.php?action='.self::RESUME_ACTION), self::RESUME_ACTION);
        ?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e('Leads Collector paused itself to keep your website online.', 'leads-collector'); ?></strong>
				<?php esc_html_e('Forms and call tracking are off until it is fixed. It turns back on by itself after the next plugin update.', 'leads-collector'); ?>
			</p>
			<p>
				<code><?php echo esc_html($paused['message'].' ('.$paused['file'].':'.$paused['line'].')'); ?></code>
			</p>
			<p>
				<a class="button" href="<?php echo esc_url($resume_url); ?>"><?php esc_html_e('Try again', 'leads-collector'); ?></a>
				<a class="button" href="<?php echo esc_url(admin_url('update-core.php?force-check=1')); ?>"><?php esc_html_e('Check for updates', 'leads-collector'); ?></a>
			</p>
		</div>
		<?php
    }

    public static function resume(): void
    {
        if (! current_user_can('activate_plugins')) {
            wp_die(esc_html__('You are not allowed to do this.', 'leads-collector'), '', ['response' => 403]);
        }

        check_admin_referer(self::RESUME_ACTION);
        delete_option(self::OPTION);

        wp_safe_redirect(wp_get_referer() ? wp_get_referer() : admin_url('plugins.php'));
        exit;
    }

    private static function is_ours(string $text): bool
    {
        return strpos(wp_normalize_path($text), wp_normalize_path(LEADS_COLLECTOR_DIR)) !== false;
    }

    private static function relative_path(string $file): string
    {
        $file = wp_normalize_path($file);
        $plugin_dir = wp_normalize_path(LEADS_COLLECTOR_DIR);

        return strpos($file, $plugin_dir) === 0 ? substr($file, strlen($plugin_dir)) : basename($file);
    }
}
