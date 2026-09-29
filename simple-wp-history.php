<?php
/**
 * Plugin Name: Simple WP History
 * Plugin URI: https://khairulazhar.com/my-plugins-and-tools/
 * Description: Lightweight WordPress activity history and audit log with dashboard summary, filters, CSV export, retention controls, safe admin click tracking, and security hardening.
 * Version: 1.1
 * Requires at least: 5.8
 * Tested up to: 6.1.1
 * Requires PHP: 7.4
 * Author: Khairul Azhar
 * Author URI: https://khairulazhar.com/
 * License: GPL-2.0-or-later
 * Text Domain: simple-wp-history
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/trait-core.php';
require_once __DIR__ . '/includes/trait-events.php';
require_once __DIR__ . '/includes/trait-tracker-updater.php';
require_once __DIR__ . '/includes/trait-advanced.php';
require_once __DIR__ . '/includes/trait-admin-data.php';
require_once __DIR__ . '/includes/trait-admin-ui.php';

final class SWH_Simple_WP_History {

	const VERSION    = '1.1';
	const DB_VERSION = '1.0';

	private static $instance = null;
	private $pre_update_posts = array();
	private $table_exists_cache = null;

	use SWH_Core_Trait, SWH_Events_Trait, SWH_Tracker_Updater_Trait, SWH_Advanced_Trait, SWH_Admin_Data_Trait, SWH_Admin_UI_Trait;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		register_activation_hook( __FILE__, array( $this, 'activate' ) );

		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ) );
		add_action( 'admin_init', array( $this, 'maybe_cleanup_old_logs' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_tracker' ) );

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_github_update' ) );
		add_filter( 'plugins_api', array( $this, 'github_plugin_information' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'normalize_github_update_source' ), 10, 4 );

		add_action( 'admin_post_swh_clear_logs', array( $this, 'handle_clear_logs' ) );
		add_action( 'admin_post_swh_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_swh_export_csv', array( $this, 'handle_export_csv' ) );
		add_action( 'admin_post_swh_export_json', array( $this, 'handle_export_json' ) );
		add_action( 'admin_post_swh_cleanup_logs', array( $this, 'handle_cleanup_logs' ) );
		add_action( 'wp_ajax_swh_log_admin_click', array( $this, 'ajax_log_admin_click' ) );

		add_action( 'wp_login', array( $this, 'log_login' ), 10, 2 );
		add_action( 'wp_login_failed', array( $this, 'log_failed_login' ) );
		add_action( 'wp_logout', array( $this, 'log_logout' ), 10, 1 );

		add_action( 'pre_post_update', array( $this, 'capture_pre_post_update' ), 10, 2 );
		add_action( 'save_post', array( $this, 'log_post_save' ), 10, 3 );
		add_action( 'before_delete_post', array( $this, 'log_post_delete' ) );
		add_action( 'trashed_post', array( $this, 'log_post_trashed' ) );
		add_action( 'untrashed_post', array( $this, 'log_post_untrashed' ) );

		add_action( 'add_attachment', array( $this, 'log_media_added' ) );
		add_action( 'edit_attachment', array( $this, 'log_media_updated' ) );
		add_action( 'delete_attachment', array( $this, 'log_media_deleted' ) );

		add_action( 'user_register', array( $this, 'log_user_created' ) );
		add_action( 'profile_update', array( $this, 'log_user_updated' ), 10, 2 );
		add_action( 'delete_user', array( $this, 'log_user_deleted' ) );

		add_action( 'activated_plugin', array( $this, 'log_plugin_activated' ), 10, 2 );
		add_action( 'deactivated_plugin', array( $this, 'log_plugin_deactivated' ), 10, 2 );
		add_action( 'switch_theme', array( $this, 'log_theme_switched' ), 10, 3 );
		add_action( 'upgrader_process_complete', array( $this, 'log_upgrade' ), 10, 2 );

		add_action( 'updated_option', array( $this, 'log_option_updated' ), 10, 3 );
		add_action( 'added_option', array( $this, 'log_option_added' ), 10, 2 );
		add_action( 'deleted_option', array( $this, 'log_option_deleted' ) );

		add_action( 'woocommerce_new_order', array( $this, 'log_wc_new_order' ) );
		add_action( 'woocommerce_order_status_changed', array( $this, 'log_wc_order_status_changed' ), 10, 4 );
		add_action( 'woocommerce_update_product', array( $this, 'log_wc_product_updated' ) );
		add_action( 'transition_post_status', array( $this, 'log_wc_product_status' ), 10, 3 );
		add_action( 'save_post_shop_coupon', array( $this, 'log_wc_coupon_save' ), 10, 3 );
		add_action( 'before_delete_post', array( $this, 'log_wc_coupon_deleted' ), 20 );
	}
}

SWH_Simple_WP_History::instance();
