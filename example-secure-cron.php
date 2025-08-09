<?php
/**
 * Example script showing how to securely call the DIO Cron endpoint
 * 
 * This file demonstrates how to call the protected endpoint with proper authentication
 *
 * @package DIO_Cron
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Example 1: Basic token authentication
 */
function example_call_with_token() {
	$site_url = home_url();
	$token    = defined( 'DIO_CRON_TOKEN' ) ? DIO_CRON_TOKEN : getenv( 'DIO_CRON_TOKEN' );

	if ( empty( $token ) ) {
		echo "Token not configured. Please set DIO_CRON_TOKEN constant or environment variable.\n";
		return;
	}

	// Call Action Scheduler endpoint with token
	$url = $site_url . '/dio-cron?token=' . urlencode( $token );

	$response = wp_remote_get( $url, [ 
		'timeout' => 30,
		'headers' => [ 
			'User-Agent' => 'DIO-Cron-Example/1.0',
		],
	] );

	if ( is_wp_error( $response ) ) {
		echo "Error: " . $response->get_error_message() . "\n";
		return;
	}

	$body = wp_remote_retrieve_body( $response );
	$code = wp_remote_retrieve_response_code( $response );

	echo "Response code: {$code}\n";
	echo "Response body: {$body}\n";
}

/**
 * Example 2: Call with GitHub Actions format
 */
function example_call_github_actions_format() {
	$site_url = home_url();
	$token    = defined( 'DIO_CRON_TOKEN' ) ? DIO_CRON_TOKEN : getenv( 'DIO_CRON_TOKEN' );

	if ( empty( $token ) ) {
		echo "::error::Token not configured\n";
		return;
	}

	// Call with GitHub Actions output format
	$url = $site_url . '/dio-cron?ga&token=' . urlencode( $token );

	$response = wp_remote_get( $url, [ 
		'timeout' => 30,
	] );

	if ( is_wp_error( $response ) ) {
		echo "::error::" . $response->get_error_message() . "\n";
		return;
	}

	$body = wp_remote_retrieve_body( $response );
	echo $body; // Already formatted for GitHub Actions
}

/**
 * Example 3: Legacy immediate mode with token
 */
function example_call_immediate_mode() {
	$site_url = home_url();
	$token    = defined( 'DIO_CRON_TOKEN' ) ? DIO_CRON_TOKEN : getenv( 'DIO_CRON_TOKEN' );

	if ( empty( $token ) ) {
		echo "Token not configured\n";
		return;
	}

	// Call legacy immediate processing
	$url = $site_url . '/dio-cron?immediate=1&token=' . urlencode( $token );

	$response = wp_remote_get( $url, [ 
		'timeout' => 120, // Longer timeout for immediate processing
	] );

	if ( is_wp_error( $response ) ) {
		echo "Error: " . $response->get_error_message() . "\n";
		return;
	}

	$body = wp_remote_retrieve_body( $response );
	$code = wp_remote_retrieve_response_code( $response );

	echo "Response code: {$code}\n";
	echo "Response: {$body}\n";
}

/**
 * Example cron job script (save as separate file)
 */
function generate_cron_script() {
	$site_url = home_url();
	$token    = defined( 'DIO_CRON_TOKEN' ) ? DIO_CRON_TOKEN : 'YOUR_TOKEN_HERE';

	$script = <<<BASH
#!/bin/bash
# DIO Cron secure execution script
# Save this as dio-cron.sh and add to your crontab

SITE_URL="{$site_url}"
TOKEN="{$token}"

# Call the protected endpoint
curl -X GET "\${SITE_URL}/dio-cron?token=\${TOKEN}" \
  --connect-timeout 10 \
  --max-time 30 \
  --retry 3 \
  --retry-delay 5 \
  --silent \
  --show-error \
  --fail

# Check exit code
if [ \$? -eq 0 ]; then
  echo "DIO Cron executed successfully"
else
  echo "DIO Cron failed with exit code \$?"
fi
BASH;

	echo "Save this as dio-cron.sh:\n\n";
	echo $script;
	echo "\n\nThen add to crontab:\n";
	echo "*/5 * * * * /path/to/dio-cron.sh >> /var/log/dio-cron.log 2>&1\n";
}

// Example usage (remove this in production)
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	echo "=== DIO Cron Security Examples ===\n\n";

	echo "1. Basic token call:\n";
	example_call_with_token();

	echo "\n2. GitHub Actions format:\n";
	example_call_github_actions_format();

	echo "\n3. Immediate mode:\n";
	example_call_immediate_mode();

	echo "\n4. Cron script:\n";
	generate_cron_script();
}
