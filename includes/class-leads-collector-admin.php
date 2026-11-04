<?php
/**
 * Settings → Leads Collector: connection, default form design and how-to.
 */
defined('ABSPATH') || exit;

/**
 * Admin screens, notices and the connection test.
 */
final class Leads_Collector_Admin
{
    const PAGE = 'leads-collector';

    public static function init(): void
    {
        add_action('admin_menu', [__CLASS__, 'add_page']);
        add_action('admin_init', [__CLASS__, 'register_setting']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue']);
        add_action('admin_notices', [__CLASS__, 'setup_notice']);
        add_action('wp_ajax_leads_collector_test', [__CLASS__, 'test_connection']);
        add_filter('plugin_action_links_'.plugin_basename(LEADS_COLLECTOR_FILE), [__CLASS__, 'action_links']);
    }

    public static function add_page(): void
    {
        add_options_page(
            __('Leads Collector', 'leads-collector'),
            __('Leads Collector', 'leads-collector'),
            'manage_options',
            self::PAGE,
            [__CLASS__, 'render_page']
        );
    }

    public static function register_setting(): void
    {
        register_setting(
            'leads_collector',
            Leads_Collector_Settings::OPTION,
            [
                'type' => 'array',
                'sanitize_callback' => ['Leads_Collector_Settings', 'sanitize'],
                'default' => [],
            ]
        );
    }

    public static function enqueue(string $hook): void
    {
        if ('settings_page_'.self::PAGE !== $hook) {
            return;
        }

        wp_enqueue_style('leads-collector-admin', LEADS_COLLECTOR_URL.'assets/css/admin.css', [], LEADS_COLLECTOR_VERSION);
        wp_enqueue_script('leads-collector-admin', LEADS_COLLECTOR_URL.'assets/js/admin.js', [], LEADS_COLLECTOR_VERSION, true);
        wp_localize_script(
            'leads-collector-admin',
            'leadsCollectorAdmin',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('leads_collector_test'),
                'testing' => __('Testing…', 'leads-collector'),
                'copied' => __('Copied', 'leads-collector'),
                'failed' => __('The test could not run. Please try again.', 'leads-collector'),
            ]
        );
    }

    /**
     * @param  string[]  $links  Existing links.
     * @return string[]
     */
    public static function action_links(array $links): array
    {
        array_unshift(
            $links,
            sprintf('<a href="%s">%s</a>', esc_url(admin_url('options-general.php?page='.self::PAGE)), esc_html__('Settings', 'leads-collector'))
        );

        return $links;
    }

    public static function setup_notice(): void
    {
        $screen = get_current_screen();

        if (Leads_Collector_Settings::is_connected() || ! current_user_can('manage_options') || ! $screen || ! in_array($screen->id, ['dashboard', 'plugins'], true)) {
            return;
        }

        printf(
            '<div class="notice notice-warning"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
            esc_html__('Leads Collector is almost ready.', 'leads-collector'),
            esc_html__('Add your site key so forms show and calls are tracked.', 'leads-collector'),
            esc_url(admin_url('options-general.php?page='.self::PAGE)),
            esc_html__('Finish setup', 'leads-collector')
        );
    }

    /**
     * Ask Lead Manager to check the key and domain without creating a lead: the
     * phone number is deliberately invalid, so the server answers with validation
     * errors and only a site key error means the connection is wrong.
     */
    public static function test_connection(): void
    {
        check_ajax_referer('leads_collector_test');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You are not allowed to do this.', 'leads-collector')], 403);
        }

        // Both values are reduced to a strict URL and key pattern by the parser.
        $connection = Leads_Collector_Settings::parse_connection(
            isset($_POST['site_key']) ? wp_unslash($_POST['site_key']) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            isset($_POST['server_url']) ? sanitize_text_field(wp_unslash($_POST['server_url'])) : ''
        );
        $server_url = $connection['server_url'];
        $site_key = $connection['site_key'];

        if ($server_url === '' || $site_key === '') {
            wp_send_json_error(['message' => __('Enter the Lead Manager address and a site key first.', 'leads-collector')]);
        }

        $home = wp_parse_url(home_url());
        $origin = $home['scheme'].'://'.$home['host'].(isset($home['port']) ? ':'.$home['port'] : '');

        $response = wp_remote_post(
            $server_url.'/api/v1/leads',
            [
                'timeout' => 15,
                'headers' => [
                    'Accept' => 'application/json',
                    'Origin' => $origin,
                ],
                'body' => [
                    'site_key' => $site_key,
                    'name' => 'Leads Collector connection test',
                    'phone' => '0',
                    'page_url' => home_url('/'),
                ],
            ]
        );

        if (is_wp_error($response)) {
            /* translators: %s: error message from WordPress. */
            wp_send_json_error(['message' => sprintf(__('Could not reach Lead Manager: %s', 'leads-collector'), $response->get_error_message())]);
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $errors = is_array($body) && isset($body['errors']) && is_array($body['errors']) ? $body['errors'] : [];

        if ($status === 422 && empty($errors['site_key'])) {
            wp_send_json_success(['message' => __('Connected. Forms and call tracking will work on this site.', 'leads-collector')]);
        }

        if ($status === 422) {
            $reason = is_array($errors['site_key']) ? (string) reset($errors['site_key']) : (string) $errors['site_key'];
            /* translators: %s: reason given by Lead Manager. */
            wp_send_json_error(['message' => sprintf(__('Lead Manager refused this site: %s', 'leads-collector'), sanitize_text_field($reason))]);
        }

        if ($status === 429) {
            wp_send_json_error(['message' => __('Too many tests in a row. Wait a minute and try again.', 'leads-collector')]);
        }

        /* translators: %d: HTTP status code. */
        wp_send_json_error(['message' => sprintf(__('Unexpected answer from Lead Manager (HTTP %d). Check the address.', 'leads-collector'), $status)]);
    }

    public static function render_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $settings = Leads_Collector_Settings::all();
        $name = Leads_Collector_Settings::OPTION;
        $labels = Leads_Collector_Settings::field_labels();
        $locked = Leads_Collector_Settings::ALWAYS_REQUIRED;
        ?>
		<div class="wrap lc-admin">
			<header class="lc-header">
				<div>
					<h1><?php esc_html_e('Leads Collector', 'leads-collector'); ?></h1>
					<p><?php esc_html_e('Send this site’s form leads and call clicks to Lead Manager.', 'leads-collector'); ?></p>
				</div>
				<span class="lc-pill <?php echo Leads_Collector_Settings::is_connected() ? 'lc-pill-on' : 'lc-pill-off'; ?>">
					<?php echo Leads_Collector_Settings::is_connected() ? esc_html__('Active', 'leads-collector') : esc_html__('Not set up', 'leads-collector'); ?>
				</span>
			</header>

			<form method="post" action="options.php" class="lc-form-settings">
				<?php settings_fields('leads_collector'); ?>

				<section class="lc-card">
					<h2><?php esc_html_e('1. Connect', 'leads-collector'); ?></h2>
					<p class="lc-muted"><?php esc_html_e('In Lead Manager open Websites → this website and copy the site key (or paste the whole script snippet).', 'leads-collector'); ?></p>

					<div class="lc-grid">
						<label class="lc-field">
							<span><?php esc_html_e('Site key', 'leads-collector'); ?></span>
							<input type="text" id="lc-site-key" name="<?php echo esc_attr($name); ?>[site_key]" value="<?php echo esc_attr($settings['site_key']); ?>" placeholder="site_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" autocomplete="off" spellcheck="false">
						</label>
						<label class="lc-field">
							<span><?php esc_html_e('Lead Manager address', 'leads-collector'); ?></span>
							<input type="url" id="lc-server-url" name="<?php echo esc_attr($name); ?>[server_url]" value="<?php echo esc_attr($settings['server_url']); ?>" placeholder="<?php echo esc_attr(Leads_Collector_Settings::DEFAULT_SERVER); ?>">
						</label>
					</div>

					<div class="lc-test">
						<button type="button" class="button" id="lc-test"><?php esc_html_e('Test connection', 'leads-collector'); ?></button>
						<span id="lc-test-result" class="lc-test-result" role="status" aria-live="polite"></span>
					</div>
				</section>

				<section class="lc-card">
					<h2><?php esc_html_e('2. Default form design', 'leads-collector'); ?></h2>
					<p class="lc-muted"><?php esc_html_e('Every form uses these unless you change them on the block, the Elementor widget or the shortcode.', 'leads-collector'); ?></p>

					<div class="lc-grid">
						<div class="lc-field">
							<span><?php esc_html_e('Fields', 'leads-collector'); ?></span>
							<table class="lc-fields">
								<thead>
									<tr><th><?php esc_html_e('Field', 'leads-collector'); ?></th><th><?php esc_html_e('Show', 'leads-collector'); ?></th><th><?php esc_html_e('Required', 'leads-collector'); ?></th></tr>
								</thead>
								<tbody>
								<?php foreach ($labels as $field => $label) { ?>
									<?php $is_locked = in_array($field, $locked, true); ?>
									<tr>
										<td><?php echo esc_html($label); ?></td>
										<td><input type="checkbox" name="<?php echo esc_attr($name); ?>[fields][]" value="<?php echo esc_attr($field); ?>" <?php checked(in_array($field, $settings['fields'], true) || $is_locked); ?> <?php disabled($is_locked); ?> aria-label="<?php echo esc_attr(sprintf( /* translators: %s: field label. */ __('Show %s', 'leads-collector'), $label)); ?>"></td>
										<td><input type="checkbox" name="<?php echo esc_attr($name); ?>[required][]" value="<?php echo esc_attr($field); ?>" <?php checked(in_array($field, $settings['required'], true) || $is_locked); ?> <?php disabled($is_locked); ?> aria-label="<?php echo esc_attr(sprintf( /* translators: %s: field label. */ __('%s is required', 'leads-collector'), $label)); ?>"></td>
									</tr>
								<?php } ?>
								</tbody>
							</table>
							<small class="lc-muted"><?php esc_html_e('Name and phone are always shown and required: Lead Manager needs them.', 'leads-collector'); ?></small>
						</div>

						<div class="lc-stack">
							<div class="lc-field">
								<span><?php esc_html_e('Columns', 'leads-collector'); ?></span>
								<div class="lc-segmented">
									<?php foreach ([1, 2, 3] as $columns) { ?>
										<label><input type="radio" name="<?php echo esc_attr($name); ?>[columns]" value="<?php echo esc_attr((string) $columns); ?>" <?php checked((int) $settings['columns'], $columns); ?>><span><?php echo esc_html((string) $columns); ?></span></label>
									<?php } ?>
								</div>
								<small class="lc-muted"><?php esc_html_e('Phones always get one column.', 'leads-collector'); ?></small>
							</div>
							<label class="lc-field">
								<span><?php esc_html_e('Button and accent color', 'leads-collector'); ?></span>
								<input type="color" name="<?php echo esc_attr($name); ?>[color]" value="<?php echo esc_attr($settings['color']); ?>">
							</label>
							<label class="lc-field">
								<span><?php esc_html_e('Button text', 'leads-collector'); ?></span>
								<input type="text" name="<?php echo esc_attr($name); ?>[button_text]" value="<?php echo esc_attr($settings['button_text']); ?>" maxlength="60">
							</label>
							<label class="lc-field">
								<span><?php esc_html_e('Message after sending', 'leads-collector'); ?></span>
								<input type="text" name="<?php echo esc_attr($name); ?>[success_message]" value="<?php echo esc_attr($settings['success_message']); ?>" maxlength="255">
							</label>
							<label class="lc-field">
								<span><?php esc_html_e('Form name in Lead Manager', 'leads-collector'); ?></span>
								<input type="text" name="<?php echo esc_attr($name); ?>[form_label]" value="<?php echo esc_attr($settings['form_label']); ?>" maxlength="100">
							</label>
						</div>
					</div>
				</section>

				<?php submit_button(__('Save settings', 'leads-collector')); ?>
			</form>

			<section class="lc-card">
				<h2><?php esc_html_e('3. Add a form', 'leads-collector'); ?></h2>
				<ul class="lc-ways">
					<li><strong><?php esc_html_e('Block editor:', 'leads-collector'); ?></strong> <?php esc_html_e('add the “Leads Collector Form” block and change its look in the sidebar.', 'leads-collector'); ?></li>
					<li><strong><?php esc_html_e('Elementor:', 'leads-collector'); ?></strong> <?php esc_html_e('drag the “Leads Collector Form” widget onto the page; it works in popups too.', 'leads-collector'); ?></li>
					<li><strong><?php esc_html_e('Anywhere else:', 'leads-collector'); ?></strong> <?php esc_html_e('paste a shortcode.', 'leads-collector'); ?></li>
				</ul>
				<?php
                $shortcodes = [
                    '[leads_collector_form]',
                    '[leads_collector_form label="Homepage quote form" columns="2" color="#16a34a" button_text="Get my free quote"]',
                    '[leads_collector_form label="Sidebar form" fields="name,phone,message" columns="1"]',
                ];
        foreach ($shortcodes as $shortcode) {
            ?>
					<div class="lc-code"><code><?php echo esc_html($shortcode); ?></code><button type="button" class="button-link lc-copy" data-copy="<?php echo esc_attr($shortcode); ?>"><?php esc_html_e('Copy', 'leads-collector'); ?></button></div>
				<?php } ?>
				<p class="lc-muted"><?php esc_html_e('Shortcode options: label, fields, required, columns (1–3), full (fields that take a whole row), color, button_text, success_message, redirect, source (page, post, popup or other).', 'leads-collector'); ?></p>
				<p class="lc-muted"><?php esc_html_e('Call clicks: every phone link (tel:) on the site is tracked automatically. Add data-call-label="Header call button" to a link to name it.', 'leads-collector'); ?></p>
			</section>
		</div>
		<?php
    }
}
