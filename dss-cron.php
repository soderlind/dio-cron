<?php
/**
 * DSS Cron
 *
 * @package     DSS_Cron
 * @author      Per Soderlind
 * @copyright   2024 Per Soderlind
 * @license     GPL-2.0+
 * 
 * Plugin Name: DSS Cron
 * Plugin URI: https://github.com/soderlind/dss-cron
 * Description: Run wp-cron on all public sites in a multisite network.
 * Version: 1.0.4
 * Author: Per Soderlind
 * Author URI: https://soderlind.no
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Network: true
 */

namespace Soderlind\Multisite\Cron;

// Flush rewrite rules on plugin activation and deactivation.
register_activation_hook( __FILE__, __NAMESPACE__ . '\dss_cron_activation' );
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\dss_cron_deactivation' );


// Hook into a custom endpoint to run the cron job.
add_action( 'init', __NAMESPACE__ . '\dss_cron_init' );
// Run the cron job when the custom endpoint is hit.
add_action( 'template_redirect', __NAMESPACE__ . '\dss_cron_template_redirect' );


/**
 * Initialize the custom rewrite rule and tag for the cron endpoint.
 */
function dss_cron_init() {
	add_rewrite_rule( '^dss-cron/?', 'index.php?dss_cron=1', 'top' );
	add_rewrite_tag( '%dss_cron%', '1' );
}

/**
 * Check for the custom query variable and run the cron job if it is set.
 */
function dss_cron_template_redirect(): void {
	if ( get_query_var( 'dss_cron' ) == 1 ) {
		dss_run_cron_on_all_sites();
		exit;
	}
}

/**
 * Run wp-cron on all public sites in the multisite network.
 */
function dss_run_cron_on_all_sites(): void {
	if ( ! is_multisite() ) {
		return;
	}

	$sites = get_sites( [ 
		'public'   => 1,
		'archived' => 0,
		'deleted'  => 0,
		'spam'     => 0,

	] );
	foreach ( $sites as $site ) {
		$url      = get_site_url( $site->blog_id );
		$response = wp_remote_get( $url . '/wp-cron.php?doing_wp_cron' );
		// if (is_wp_error($response)) {
		//     ray('Failed to run wp-cron for site: ' . $url . '. Error: ' . $response->get_error_message());
		// }
	}
}

/**
 * Flush rewrite rules on plugin activation.
 */
function dss_cron_activation(): void {
	dss_cron_init();
	flush_rewrite_rules();
}

/**
 * Flush rewrite rules on plugin deactivation.
 */
function dss_cron_deactivation(): void {
	flush_rewrite_rules();
}

