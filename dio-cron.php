<?php
/**
 * Plugin Name: DIO Cron
 * Plugin URI: https://github.com/soderlind/dio-cron
 * Description: Run wp-cron on all public sites in a multisite network with Action Scheduler integration, security token authentication, and comprehensive network admin interface.
 * Version: 2.2.9
 * Author: Per Soderlind
 * Author URI: https://soderlind.no
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Network: true
 * GitHub Plugin URI: soderlind/dio-cron
 * Primary Branch: main
 *
 * @package     DIO_Cron
 * @author      Per Soderlind
 * @copyright   2024 Per Soderlind
 * @license     GPL-2.0+
 */

namespace Soderlind\Multisite\Cron;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load Composer autoloader.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

// Include the generic updater class.
if ( ! class_exists( 'Soderlind\WordPress\GitHub_Plugin_Updater' ) ) {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-github-plugin-updater.php';
}
// Initialize the updater with configuration.
$dio_cron_updater = \Soderlind\WordPress\GitHub_Plugin_Updater::create_with_assets(
	'https://github.com/soderlind/dio-cron',
	__FILE__,
	'dio-cron',
	'/dio-cron\.zip/',
	'main'
);

// Load the main plugin class.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-dio-cron.php';

// Flush rewrite rules on plugin activation and deactivation.
register_activation_hook( __FILE__, [ DIO_Cron::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ DIO_Cron::class, 'deactivate' ] );

// Initialize Action Scheduler early on plugins_loaded.
add_action( 'plugins_loaded', function () {
	$action_scheduler_path = __DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php';
	if ( file_exists( $action_scheduler_path ) ) {
		require_once $action_scheduler_path;
	}
}, 0 );

// Initialize the plugin.
add_action(
	'plugins_loaded',
	function () {
		DIO_Cron::get_instance();
	}
);

// Legacy function for backward compatibility.
if ( ! function_exists( __NAMESPACE__ . '\dio_run_cron_on_all_sites' ) ) {
	/**
	 * Run wp-cron on all public sites in the multisite network (legacy function)
	 *
	 * @return array
	 */
	function dio_run_cron_on_all_sites(): array {
		if ( ! is_multisite() ) {
			return create_error_response( __( 'This plugin requires WordPress Multisite', 'dio-cron' ) );
		}

		$start_time = microtime( true );
		$sites      = DIO_Cron_Utilities::get_cached_sites();

		if ( is_wp_error( $sites ) ) {
			return create_error_response( $sites->get_error_message() );
		}

		if ( empty( $sites ) ) {
			return create_error_response( __( 'No public sites found in the network', 'dio-cron' ) );
		}

		// Use the site processor for consistent cron processing logic.
		$instance       = DIO_Cron::get_instance();
		$site_processor = $instance->get_site_processor();
		$result         = $site_processor->process_sites_batch( $sites );

		// Convert the result format to match legacy expectations.
		if ( $result[ 'success' ] ) {
			return [ 
				'success'        => true,
				'message'        => '',
				'count'          => $result[ 'processed' ],
				'execution_time' => $result[ 'execution_time' ],
			];
		} else {
			return create_error_response( $result[ 'message' ] );
		}
	}
}

if ( ! function_exists( __NAMESPACE__ . '\create_error_response' ) ) {
	/**
	 * Create an error response (legacy function)
	 * Delegates to utilities class for consistency
	 *
	 * @param string $error_message The error message to include in the response.
	 * @return array
	 */
	function create_error_response( $error_message ): array {
		// Use utilities class for consistent error response format.
		$response = DIO_Cron_Utilities::create_error_response( $error_message );

		// Wrap in array for legacy compatibility.
		return [ $response ];
	}
}

// Legacy functions for backward compatibility.
if ( ! function_exists( __NAMESPACE__ . '\dio_cron_init' ) ) {
	/**
	 * Initialize the custom rewrite rule and tag for the cron endpoint (legacy function)
	 * Note: Rewrite rules are now handled by the main DIO_Cron class
	 *
	 * @return void
	 */
	function dio_cron_init(): void {
		// Rewrite rules are now handled by DIO_Cron class in init_rewrite_rules()
		// This function is kept for backward compatibility but delegates to the main class.
		$instance = DIO_Cron::get_instance();
		$instance->init_rewrite_rules();
	}
}

if ( ! function_exists( __NAMESPACE__ . '\dio_cron_activation' ) ) {
	/**
	 * Flush rewrite rules on plugin activation (legacy function)
	 * Delegates to main class activation method
	 *
	 * @return void
	 */
	function dio_cron_activation(): void {
		DIO_Cron::activate();
	}
}

if ( ! function_exists( __NAMESPACE__ . '\dio_cron_deactivation' ) ) {
	/**
	 * Flush rewrite rules on plugin deactivation (legacy function)
	 * Delegates to main class deactivation method
	 *
	 * @return void
	 */
	function dio_cron_deactivation(): void {
		DIO_Cron::deactivate();
	}
}
