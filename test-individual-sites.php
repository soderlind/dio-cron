<?php
/**
 * Test script for individual site cron testing
 * 
 * Usage: Place this file in your WordPress root and access via browser
 * URL: http://plugins.local/test-individual-sites.php
 */

// Load WordPress
require_once( 'wp-config.php' );

if ( ! is_multisite() ) {
	die( 'This script requires a multisite installation.' );
}

// HTML output
?>
<!DOCTYPE html>
<html>

<head>
	<title>DIO Cron - Individual Site Testing</title>
	<style>
		body {
			font-family: Arial, sans-serif;
			margin: 20px;
		}

		.success {
			color: green;
		}

		.error {
			color: red;
		}

		.warning {
			color: orange;
		}

		table {
			border-collapse: collapse;
			width: 100%;
		}

		th,
		td {
			border: 1px solid #ddd;
			padding: 8px;
			text-align: left;
		}

		th {
			background-color: #f2f2f2;
		}

		.site-url {
			font-family: monospace;
			font-size: 12px;
		}

		.test-button {
			background: #0073aa;
			color: white;
			padding: 5px 10px;
			border: none;
			cursor: pointer;
		}
	</style>
</head>

<body>
	<h1>DIO Cron - Individual Site Testing</h1>

	<?php if ( isset( $_GET[ 'test_site' ] ) && intval( $_GET[ 'test_site' ] ) > 0 ) : ?>
		<?php
		$site_id = intval( $_GET[ 'test_site' ] );
		$site    = get_site( $site_id );

		if ( $site ) {
			echo "<h2>Testing Site ID: {$site_id}</h2>";
			echo "<p><strong>Site URL:</strong> <code>" . esc_url( $site->siteurl ) . "</code></p>";

			// Initialize DIO Cron
			if ( class_exists( 'Soderlind\Multisite\Cron\DIO_Cron' ) ) {
				$plugin         = \Soderlind\Multisite\Cron\DIO_Cron::get_instance();
				$site_processor = $plugin->get_site_processor();

				$start_time = microtime( true );
				$result     = $site_processor->trigger_site_cron( $site->siteurl );
				$total_time = microtime( true ) - $start_time;

				echo "<h3>" . esc_html__( 'Test Results:', 'dio-cron' ) . "</h3>";
				echo "<p><strong>" . esc_html__( 'Execution Time:', 'dio-cron' ) . "</strong> " . number_format( $total_time, 3 ) . " " . esc_html__( 'seconds', 'dio-cron' ) . "</p>";

				if ( is_wp_error( $result ) ) {
					echo "<div class='error'>";
					echo "<p><strong>❌ Test Failed:</strong> " . esc_html( $result->get_error_message() ) . "</p>";
					echo "<p><strong>Error Code:</strong> " . esc_html( $result->get_error_code() ) . "</p>";
					$error_data = $result->get_error_data();
					if ( $error_data ) {
						echo "<p><strong>Error Data:</strong> <pre>" . print_r( $error_data, true ) . "</pre></p>";
					}
					echo "</div>";
				} else {
					echo "<div class='success'>";
					echo "<p><strong>✅ Test Successful!</strong></p>";
					echo "<p><strong>Response Code:</strong> " . intval( $result[ 'response_code' ] ?? 0 ) . "</p>";
					if ( isset( $result[ 'execution_time' ] ) ) {
						echo "<p><strong>" . esc_html__( 'Site Response Time:', 'dio-cron' ) . "</strong> " . number_format( $result[ 'execution_time' ], 3 ) . " " . esc_html__( 'seconds', 'dio-cron' ) . "</p>";
					}
					if ( isset( $result[ 'timeout_used' ] ) ) {
						echo "<p><strong>" . esc_html__( 'Timeout Setting:', 'dio-cron' ) . "</strong> " . intval( $result[ 'timeout_used' ] ) . " " . esc_html__( 'seconds', 'dio-cron' ) . "</p>";
					}
					if ( isset( $result[ 'timeout_used' ] ) ) {
						echo "<p><strong>Timeout Setting:</strong> " . intval( $result[ 'timeout_used' ] ) . " seconds</p>";
					}
					echo "</div>";
				}

				// Test the cron URL directly
				echo "<h3>Direct URL Test:</h3>";

				// Use site processor if available for consistency
				if ( class_exists( 'Soderlind\\Multisite\\Cron\\DIO_Cron_Site_Processor' ) ) {
					$site_processor = new Soderlind\Multisite\Cron\DIO_Cron_Site_Processor();
					$result         = $site_processor->trigger_site_cron( $site->siteurl );

					if ( is_wp_error( $result ) ) {
						echo "<div class='error'><p>Error: " . esc_html( $result->get_error_message() ) . "</p></div>";
					} else {
						echo "<div class='success'><p>Success! Response code: " . intval( $result[ 'response_code' ] ) . " (Execution time: " . number_format( $result[ 'execution_time' ], 2 ) . "s)</p></div>";
					}
				} else {
					// Fallback to direct URL display
					$cron_url = trailingslashit( $site->siteurl ) . 'wp-cron.php?doing_wp_cron';
					echo "<p><strong>Cron URL:</strong> <a href='" . esc_url( $cron_url ) . "' target='_blank'>" . esc_url( $cron_url ) . "</a></p>";
					echo "<p><em>Click the link above to test the URL directly in your browser.</em></p>";
				}

			} else {
				echo "<div class='error'><p>DIO Cron plugin is not loaded.</p></div>";
			}
		} else {
			echo "<div class='error'><p>Site with ID {$site_id} not found.</p></div>";
		}
		?>

		<p><a href="<?php echo $_SERVER[ 'PHP_SELF' ]; ?>">&larr; Back to site list</a></p>

	<?php else : ?>

		<h2>All Sites in Network</h2>
		<p>Click "Test" to check individual site connectivity:</p>

		<table>
			<thead>
				<tr>
					<th>Site ID</th>
					<th>Site URL</th>
					<th>Status</th>
					<th>Cron URL</th>
					<th>Action</th>
				</tr>
			</thead>
			<tbody>
				<?php
				// Use DIO Cron utilities if available for consistency
				if ( class_exists( 'Soderlind\\Multisite\\Cron\\DIO_Cron_Utilities' ) ) {
					$sites = Soderlind\Multisite\Cron\DIO_Cron_Utilities::get_cached_sites( 50 );
					if ( is_wp_error( $sites ) ) {
						$sites = get_sites( [ 'number' => 50 ] );
					}
				} else {
					$sites = get_sites( [ 'number' => 50 ] );
				}

				foreach ( $sites as $site ) {
					$cron_url = trailingslashit( $site->siteurl ) . 'wp-cron.php?doing_wp_cron';
					echo "<tr>";
					echo "<td>" . intval( $site->blog_id ) . "</td>";
					echo "<td class='site-url'>" . esc_url( $site->siteurl ) . "</td>";
					echo "<td>";
					if ( '1' === $site->public ) {
						echo "<span class='success'>✓ Public</span>";
					} else {
						echo "<span class='warning'>⚠ Not Public</span>";
					}
					if ( '1' === $site->archived ) {
						echo "<br><span class='error'>Archived</span>";
					}
					if ( '1' === $site->spam ) {
						echo "<br><span class='error'>Spam</span>";
					}
					if ( '1' === $site->deleted ) {
						echo "<br><span class='error'>Deleted</span>";
					}
					echo "</td>";
					echo "<td class='site-url'><a href='" . esc_url( $cron_url ) . "' target='_blank' style='font-size: 11px;'>" . esc_url( $cron_url ) . "</a></td>";
					echo "<td>";
					echo "<a href='?test_site=" . intval( $site->blog_id ) . "' class='test-button'>Test</a>";
					echo "</td>";
					echo "</tr>";
				}
				?>
			</tbody>
		</table>

		<h2>Timeout Configuration</h2>
		<p>Current timeout setting can be adjusted using a filter in your theme's functions.php:</p>
		<pre><code>add_filter( 'dio_cron_request_timeout', function( $timeout ) {
				return 30; // Increase to 30 seconds
			} );</code></pre>

		<h2>Common Issues & Solutions</h2>
		<ul>
			<li><strong>cURL error 28 (timeout):</strong> Sites are taking too long to respond. Try increasing the timeout
				or check local DNS/server config.</li>
			<li><strong>404 errors:</strong> Check WordPress multisite configuration and .htaccess rules.</li>
			<li><strong>Connection refused:</strong> Verify web server is running and sites are accessible.</li>
			<li><strong>DNS issues:</strong> Ensure all subdomains are properly configured in /etc/hosts (for local
				development).</li>
		</ul>

	<?php endif; ?>

</body>

</html>