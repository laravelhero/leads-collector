<?php

/**
 * Plugin Name:       Leads Collector
 * Plugin URI:        https://github.com/laravelhero/leads-collector
 * Description:       Connects this site to Lead Manager: modern lead forms (shortcode, block and Elementor widget) and automatic call-click tracking.
 * Version:           1.3.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Abu Taher Hero
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       leads-collector
 * Update URI:        https://github.com/laravelhero/leads-collector
 */
defined('ABSPATH') || exit;

define('LEADS_COLLECTOR_VERSION', '1.3.0');
define('LEADS_COLLECTOR_FILE', __FILE__);
define('LEADS_COLLECTOR_DIR', plugin_dir_path(__FILE__));
define('LEADS_COLLECTOR_URL', plugin_dir_url(__FILE__));

require_once LEADS_COLLECTOR_DIR.'includes/class-leads-collector-guard.php';

// Paused after an error: keep the site online and only look for a fixed version.
if (Leads_Collector_Guard::is_paused()) {
    Leads_Collector_Guard::init_paused();
    Leads_Collector_Guard::run(
        static function () {
            require_once LEADS_COLLECTOR_DIR.'includes/class-leads-collector-updater.php';
            Leads_Collector_Updater::init();
        }
    );

    return;
}

Leads_Collector_Guard::watch();

Leads_Collector_Guard::run(
    static function () {
        require_once LEADS_COLLECTOR_DIR.'includes/class-leads-collector-settings.php';
        require_once LEADS_COLLECTOR_DIR.'includes/class-leads-collector-form.php';
        require_once LEADS_COLLECTOR_DIR.'includes/class-leads-collector-frontend.php';
        require_once LEADS_COLLECTOR_DIR.'includes/class-leads-collector-admin.php';
        require_once LEADS_COLLECTOR_DIR.'includes/class-leads-collector-block.php';
        require_once LEADS_COLLECTOR_DIR.'includes/class-leads-collector-updater.php';

        Leads_Collector_Settings::import_connection_file();
        Leads_Collector_Frontend::init();
        Leads_Collector_Block::init();
        Leads_Collector_Updater::init();

        if (is_admin()) {
            Leads_Collector_Admin::init();
        }

        add_action(
            'elementor/widgets/register',
            static function ($widgets_manager) {
                Leads_Collector_Guard::run(
                    static function () use ($widgets_manager) {
                        require_once LEADS_COLLECTOR_DIR.'includes/class-leads-collector-elementor-widget.php';
                        $widgets_manager->register(new Leads_Collector_Elementor_Widget);
                    }
                );
            }
        );
    }
);
