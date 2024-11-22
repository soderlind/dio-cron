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
 * Version: 1.0.12
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
 * 
 * @return void
 */
function dss_cron_init(): void {
	add_rewrite_rule( '^dss-cron/?$', 'index.php?dss_cron=1', 'top' );
	add_rewrite_rule( '^dss-cron/?\?ga', 'index.php?dss_cron=1&ga=1', 'top' );
	add_rewrite_tag( '%dss_cron%', '1' );
	add_rewrite_tag( '%ga%', '1' );
}

/**
 * Check for the custom query variable and run the cron job if it is set.
 * 
 * @return void
 */
function dss_cron_template_redirect(): void {
	if ( get_query_var( 'dss_cron' ) == 1 ) {
		$result = dss_run_cron_on_all_sites();
		if ( isset( $_GET[ 'ga' ] ) ) {
			if ( ! $result[ 'success' ] ) {
				echo "::error::{$result[ 'message' ]}\n";
			} else {
				echo "::notice::Running wp-cron on {$result[ 'count' ]} sites\n";
			}
		}
		exit;
	}
}

/**
 * Run wp-cron on all public sites in the multisite network.
 * 
 * @return array
 */
function dss_run_cron_on_all_sites(): array {
	if ( ! is_multisite() ) {
		return create_error_response( __( 'This plugin requires WordPress Multisite', 'dss-cron' ) );
	}

	$sites = get_site_transient( 'dss_cron_sites' );
	if ( false === $sites ) {
		$sites = get_sites( [ 
			'public'   => 1,
			'archived' => 0,
			'deleted'  => 0,
			'spam'     => 0,
			'number'   => apply_filters( 'dss_cron_number_of_sites', 200 ),
		] );
		set_site_transient( 'dss_cron_sites', $sites, apply_filters( 'dss_cron_sites_transient', HOUR_IN_SECONDS ) );
	}

	if ( empty( $sites ) ) {
		return create_error_response( __( 'No public sites found in the network', 'dss-cron' ) );
	}

	$errors = [];
	foreach ( (array) $sites as $site ) {
		$url      = $site->__get( 'siteurl' );
		$response = wp_remote_get( $url . '/wp-cron.php?doing_wp_cron', [ 
			'blocking'  => false,
			'sslverify' => false,
			'timeout'   => 5,
		] );

		if ( is_wp_error( $response ) ) {
			$errors[] = sprintf( __( 'Error for %s: %s', 'dss-cron' ), $url, $response->get_error_message() );
		}
	}

	if ( ! empty( $errors ) ) {
		return create_error_response( implode( "\n", $errors ) );
	}

	return [ 
		'success' => true,
		'message' => '',
		'count'   => count( (array) $sites ),
	];
}

/**
 * Create an error response.
 *
 * @param string $error_message
 * @return array
 */
function create_error_response( $error_message ): array {
	$response = [ 
		[ 
			'success' => false,
			'message' => $error_message,
		],
	];


	return $response;
}

/**
 * Flush rewrite rules on plugin activation.
 * 
 * @return void
 */
function dss_cron_activation(): void {
	dss_cron_init();
	flush_rewrite_rules();
}

/**
 * Flush rewrite rules on plugin deactivation.
 * 
 * @return void
 */
function dss_cron_deactivation(): void {
	flush_rewrite_rules();
}

