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
                'updateNonce' => wp_create_nonce('leads_collector_update'),
                'updatesNonce' => wp_create_nonce('updates'),
                'checking' => __('Checking…', 'leads-collector'),
                'updating' => __('Updating…', 'leads-collector'),
                'updated' => __('Updated. Reloading…', 'leads-collector'),
                'updateFailed' => __('The update did not finish. Open Dashboard → Updates to try there.', 'leads-collector'),
                'testing' => __('Testing…', 'leads-collector'),
                'copied' => __('Copied', 'leads-collector'),
                'failed' => __('The test could not run. Please try again.', 'leads-collector'),
                'unsaved' => __('You have unsaved changes', 'leads-collector'),
                'saved' => __('All changes saved', 'leads-collector'),
                'labels' => Leads_Collector_Settings::field_labels(),
                'placeholders' => Leads_Collector_Settings::field_placeholders(),
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
        $full = Leads_Collector_Settings::sanitize_full($settings['full']);
        $connected = Leads_Collector_Settings::is_connected();
        $shortcode = '[leads_collector_form]';
        ?>
		<div class="wrap lc-admin">
			<header class="lc-header">
				<div class="lc-brand">
					<span class="lc-logo dashicons dashicons-feedback" aria-hidden="true"></span>
					<div>
						<h1><?php esc_html_e('Leads Collector', 'leads-collector'); ?></h1>
						<p><?php esc_html_e('Send this site’s form leads and call clicks to Lead Manager.', 'leads-collector'); ?></p>
					</div>
				</div>
				<div class="lc-header-meta">
					<span class="lc-chip">v<?php echo esc_html(LEADS_COLLECTOR_VERSION); ?></span>
					<span class="lc-pill <?php echo $connected ? 'lc-pill-on' : 'lc-pill-off'; ?>">
						<span class="lc-dot" aria-hidden="true"></span>
						<?php echo $connected ? esc_html__('Connected', 'leads-collector') : esc_html__('Not set up', 'leads-collector'); ?>
					</span>
				</div>
			</header>

			<?php if (current_user_can('manage_options')) { ?>
				<?php $update = Leads_Collector_Updater::status(); ?>
				<section class="lc-banner lc-updates" id="lc-updates" data-plugin="<?php echo esc_attr($update['plugin']); ?>" data-slug="<?php echo esc_attr($update['slug']); ?>">
					<span class="lc-banner-icon dashicons dashicons-update" aria-hidden="true"></span>
					<div class="lc-banner-text">
						<strong><?php esc_html_e('Plugin updates', 'leads-collector'); ?></strong>
						<span id="lc-update-result" class="lc-test-result<?php echo $update['hasUpdate'] ? ' is-warn' : ''; ?>" role="status" aria-live="polite"><?php echo esc_html($update['message']); ?></span>
					</div>
					<div class="lc-updates-actions">
						<a class="lc-link" href="<?php echo esc_url($update['releaseUrl']); ?>" target="_blank" rel="noopener"><?php esc_html_e('Release notes', 'leads-collector'); ?></a>
						<button type="button" class="lc-btn lc-btn-ghost" id="lc-check-update"><?php esc_html_e('Check for updates', 'leads-collector'); ?></button>
						<?php if ($update['canInstall']) { ?>
							<button type="button" class="lc-btn lc-btn-primary" id="lc-run-update" <?php echo $update['hasUpdate'] ? '' : 'hidden'; ?>><?php esc_html_e('Update now', 'leads-collector'); ?></button>
						<?php } ?>
					</div>
				</section>
			<?php } ?>

			<form method="post" action="options.php" class="lc-form-settings" id="lc-settings">
				<?php settings_fields('leads_collector'); ?>
				<input type="hidden" name="<?php echo esc_attr($name); ?>[columns]" value="2">

				<section class="lc-card">
					<div class="lc-card-head">
						<span class="lc-step">1</span>
						<div>
							<h2><?php esc_html_e('Connect to Lead Manager', 'leads-collector'); ?></h2>
							<p class="lc-muted"><?php esc_html_e('In Lead Manager open Websites → this website and copy the site key (or paste the whole script snippet).', 'leads-collector'); ?></p>
						</div>
					</div>

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
						<button type="button" class="lc-btn lc-btn-ghost" id="lc-test"><span class="dashicons dashicons-admin-links" aria-hidden="true"></span><?php esc_html_e('Test connection', 'leads-collector'); ?></button>
						<span id="lc-test-result" class="lc-test-result" role="status" aria-live="polite"></span>
					</div>
				</section>

				<section class="lc-card">
					<div class="lc-card-head">
						<span class="lc-step">2</span>
						<div>
							<h2><?php esc_html_e('Form design', 'leads-collector'); ?></h2>
							<p class="lc-muted"><?php esc_html_e('Every form on the site uses this design. The preview updates as you change it.', 'leads-collector'); ?></p>
						</div>
					</div>

					<div class="lc-design">
						<div class="lc-design-controls">
							<h3 class="lc-label"><?php esc_html_e('Fields', 'leads-collector'); ?></h3>
							<div class="lc-rows" role="list">
								<div class="lc-row lc-row-head" aria-hidden="true">
									<span><?php esc_html_e('Field', 'leads-collector'); ?></span>
									<span><?php esc_html_e('Show', 'leads-collector'); ?></span>
									<span><?php esc_html_e('Required', 'leads-collector'); ?></span>
									<span><?php esc_html_e('Width', 'leads-collector'); ?></span>
								</div>
								<?php foreach ($labels as $field => $label) { ?>
									<?php
                                    $is_locked = in_array($field, $locked, true);
                                    $always_full = in_array($field, Leads_Collector_Settings::ALWAYS_FULL, true);
                                    $is_full = in_array($field, $full, true);
                                    $shown = in_array($field, $settings['fields'], true) || $is_locked;
                                    ?>
									<div class="lc-row<?php echo $shown ? '' : ' is-hidden'; ?>" role="listitem" data-field="<?php echo esc_attr($field); ?>">
										<span class="lc-row-name">
											<?php echo esc_html($label); ?>
											<?php if ($is_locked) { ?><span class="lc-tag"><?php esc_html_e('Always on', 'leads-collector'); ?></span><?php } ?>
										</span>
										<label class="lc-switch">
											<input type="checkbox" class="lc-show" name="<?php echo esc_attr($name); ?>[fields][]" value="<?php echo esc_attr($field); ?>" <?php checked($shown); ?> <?php disabled($is_locked); ?>>
											<span class="lc-switch-ui" aria-hidden="true"></span>
											<span class="screen-reader-text"><?php echo esc_html(sprintf( /* translators: %s: field label. */ __('Show %s', 'leads-collector'), $label)); ?></span>
										</label>
										<label class="lc-switch">
											<input type="checkbox" class="lc-required" name="<?php echo esc_attr($name); ?>[required][]" value="<?php echo esc_attr($field); ?>" <?php checked(in_array($field, $settings['required'], true) || $is_locked); ?> <?php disabled($is_locked); ?>>
											<span class="lc-switch-ui" aria-hidden="true"></span>
											<span class="screen-reader-text"><?php echo esc_html(sprintf( /* translators: %s: field label. */ __('%s is required', 'leads-collector'), $label)); ?></span>
										</label>
										<span class="lc-seg" role="radiogroup" aria-label="<?php echo esc_attr(sprintf( /* translators: %s: field label. */ __('Width of %s', 'leads-collector'), $label)); ?>">
											<label><input type="radio" class="lc-width" name="<?php echo esc_attr($name); ?>[width][<?php echo esc_attr($field); ?>]" value="half" <?php checked(! $is_full); ?> <?php disabled($always_full); ?>><span><?php esc_html_e('Half', 'leads-collector'); ?></span></label>
											<label><input type="radio" class="lc-width" name="<?php echo esc_attr($name); ?>[width][<?php echo esc_attr($field); ?>]" value="full" <?php checked($is_full); ?> <?php disabled($always_full); ?>><span><?php esc_html_e('Full', 'leads-collector'); ?></span></label>
										</span>
									</div>
								<?php } ?>
							</div>
							<p class="lc-hint"><?php esc_html_e('Name and phone are always on: Lead Manager needs them. Two "Half" fields next to each other share a line; the message box always takes the full line, and phones show one field per line.', 'leads-collector'); ?></p>

							<h3 class="lc-label"><?php esc_html_e('Style and text', 'leads-collector'); ?></h3>
							<div class="lc-grid lc-grid-tight">
								<div class="lc-field">
									<span><?php esc_html_e('Button and accent color', 'leads-collector'); ?></span>
									<div class="lc-color">
										<input type="color" id="lc-color" name="<?php echo esc_attr($name); ?>[color]" value="<?php echo esc_attr($settings['color']); ?>">
										<input type="text" id="lc-color-hex" value="<?php echo esc_attr($settings['color']); ?>" maxlength="7" spellcheck="false" aria-label="<?php esc_attr_e('Color code', 'leads-collector'); ?>">
									</div>
								</div>
								<label class="lc-field">
									<span><?php esc_html_e('Button text', 'leads-collector'); ?></span>
									<input type="text" id="lc-button-text" name="<?php echo esc_attr($name); ?>[button_text]" value="<?php echo esc_attr($settings['button_text']); ?>" maxlength="60">
								</label>
								<label class="lc-field">
									<span><?php esc_html_e('Message after sending', 'leads-collector'); ?></span>
									<input type="text" id="lc-success" name="<?php echo esc_attr($name); ?>[success_message]" value="<?php echo esc_attr($settings['success_message']); ?>" maxlength="255">
								</label>
								<label class="lc-field">
									<span><?php esc_html_e('Form name in Lead Manager', 'leads-collector'); ?></span>
									<input type="text" name="<?php echo esc_attr($name); ?>[form_label]" value="<?php echo esc_attr($settings['form_label']); ?>" maxlength="100">
								</label>
							</div>
						</div>

						<aside class="lc-preview" aria-label="<?php esc_attr_e('Form preview', 'leads-collector'); ?>">
							<div class="lc-preview-bar">
								<span class="lc-label"><?php esc_html_e('Live preview', 'leads-collector'); ?></span>
								<span class="lc-preview-dots" aria-hidden="true"><i></i><i></i><i></i></span>
							</div>
							<div class="lc-preview-body">
								<div class="lc-mock" id="lc-mock"></div>
								<p class="lc-mock-success" id="lc-mock-success"></p>
							</div>
						</aside>
					</div>
				</section>

				<div class="lc-savebar">
					<span class="lc-save-state" id="lc-save-state"><?php esc_html_e('All changes saved', 'leads-collector'); ?></span>
					<button type="submit" class="lc-btn lc-btn-primary lc-btn-lg"><?php esc_html_e('Save settings', 'leads-collector'); ?></button>
				</div>
			</form>

			<section class="lc-card">
				<div class="lc-card-head">
					<span class="lc-step">3</span>
					<div>
						<h2><?php esc_html_e('Add the form to a page', 'leads-collector'); ?></h2>
						<p class="lc-muted"><?php esc_html_e('Pick whichever fits the page. All three show the design above.', 'leads-collector'); ?></p>
					</div>
				</div>
				<div class="lc-ways">
					<div class="lc-way">
						<span class="dashicons dashicons-block-default" aria-hidden="true"></span>
						<strong><?php esc_html_e('Block editor', 'leads-collector'); ?></strong>
						<span class="lc-muted"><?php esc_html_e('Add the “Leads Collector Form” block.', 'leads-collector'); ?></span>
					</div>
					<div class="lc-way">
						<span class="dashicons dashicons-welcome-widgets-menus" aria-hidden="true"></span>
						<strong><?php esc_html_e('Elementor', 'leads-collector'); ?></strong>
						<span class="lc-muted"><?php esc_html_e('Drag in the “Leads Collector Form” widget. Works in popups too.', 'leads-collector'); ?></span>
					</div>
					<div class="lc-way">
						<span class="dashicons dashicons-shortcode" aria-hidden="true"></span>
						<strong><?php esc_html_e('Shortcode', 'leads-collector'); ?></strong>
						<span class="lc-muted"><?php esc_html_e('Paste it anywhere else.', 'leads-collector'); ?></span>
					</div>
				</div>
				<div class="lc-code"><code><?php echo esc_html($shortcode); ?></code><button type="button" class="lc-btn lc-btn-ghost lc-btn-sm lc-copy" data-copy="<?php echo esc_attr($shortcode); ?>"><?php esc_html_e('Copy', 'leads-collector'); ?></button></div>
				<p class="lc-hint"><span class="dashicons dashicons-phone" aria-hidden="true"></span> <?php esc_html_e('Call clicks: every phone link (tel:) on the site is tracked automatically. Add data-call-label="Header call button" to a link to name it.', 'leads-collector'); ?></p>
			</section>
		</div>
		<?php
    }
}
