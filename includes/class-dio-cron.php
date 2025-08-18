<?php
/**
 * Main DIO Cron Plugin Class
 *
 * @package DIO_Cron
 */

namespace Soderlind\Multisite\DioCron;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class that orchestrates the DIO Cron functionality
 */
class DIO_Cron {

	/**
	 * Plugin version
	 *
	 * @var string VERSION Plugin version
	 */
	const VERSION = '2.3.0';    /**
			* Instance of the queue manager
			*
			* @var DIO_Cron_Queue_Manager
			*/
	private $queue_manager;

	/**
	 * Instance of the site processor
	 *
	 * @var DIO_Cron_Site_Processor
	 */
	private $site_processor;

	/**
	 * Instance of the admin interface
	 *
	 * @var DIO_Cron_Admin
	 */
	private $admin;

	/**
	 * Single instance of the class
	 *
	 * @var DIO_Cron
	 */
	private static $instance = null;

	/**
	 * Get single instance of the class
	 *
	 * @return DIO_Cron
	 */
	public static function get_instance(): DIO_Cron {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->init();
	}

	/**
	 * Initialize the plugin
	 *
	 * @return void
	 */
	private function init() {
		// Ensure Action Scheduler plugin is present; show admin notice if missing.
		$this->init_action_scheduler();

		// Load dependencies.
		$this->load_dependencies();

		// Initialize components.
		$this->queue_manager  = new DIO_Cron_Queue_Manager();
		$this->site_processor = new DIO_Cron_Site_Processor();

		// Setup hooks.
		$this->setup_hooks();
	}

	/**
	 * Ensure Action Scheduler is available. The plugin now requires
	 * the Action Scheduler plugin; no bundled/composer loading.
	 *
	 * @return void
	 */
	private function init_action_scheduler() {
		if ( ! DIO_Cron_Utilities::is_action_scheduler_available() ) {
			// Surface an admin notice if somehow activated without dependency.
			add_action( 'admin_notices', function () {
				if ( current_user_can( 'manage_network_options' ) ) {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'DIO Cron requires the Action Scheduler plugin. Please install and activate Action Scheduler.', 'dio-cron' ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
			} );
		}
	}

	/**
	 * Load required dependencies
	 *
	 * @return void
	 */
	private function load_dependencies() {
		require_once plugin_dir_path( __FILE__ ) . 'class-utilities.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-queue-manager.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-site-processor.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-admin.php';
	}

	/**
	 * Setup WordPress hooks
	 *
	 * @return void
	 */
	private function setup_hooks() {
		// Initialize rewrite rules.
		add_action( 'init', [ $this, 'init_rewrite_rules' ] );

		// Handle template redirect.
		add_action( 'template_redirect', [ $this, 'handle_template_redirect' ] );

		// Register custom action hooks for Action Scheduler.
		add_action( DIO_Cron_Utilities::PROCESS_SITE_HOOK, [ $this->site_processor, 'process_single_site' ] );
		add_action( DIO_Cron_Utilities::RUN_ALL_SITES_HOOK, [ $this->queue_manager, 'enqueue_all_sites' ] );

		// Initialize admin interface for network admin.
		add_action( 'init', [ $this, 'init_admin_interface' ] );
	}

	/**
	 * Initialize rewrite rules for the custom endpoint
	 *
	 * @return void
	 */
	public function init_rewrite_rules() {
		add_rewrite_rule( '^dio-cron/?$', 'index.php?dio_cron=1', 'top' );
		add_rewrite_rule( '^dio-cron/?\?ga', 'index.php?dio_cron=1&ga=1', 'top' );
		add_rewrite_rule( '^dio-cron/?\?immediate=1', 'index.php?dio_cron=1&immediate=1', 'top' );
		add_rewrite_tag( '%dio_cron%', '1' );
		add_rewrite_tag( '%ga%', '1' );
		add_rewrite_tag( '%immediate%', '1' );
	}

	/**
	 * Initialize admin interface for network admin
	 *
	 * @return void
	 */
	public function init_admin_interface() {
		// Only initialize admin interface in multisite network admin.
		if ( is_multisite() && is_network_admin() ) {
			$this->admin = new DIO_Cron_Admin();
		}
	}

	/**
	 * Handle the custom endpoint request
	 *
	 * This method processes incoming requests to the /dio-cron endpoint.
	 * It implements a comprehensive security and execution flow:
	 * 1. Verifies endpoint security (rate limiting + token authentication)
	 * 2. Prevents concurrent execution using locks
	 * 3. Processes the request (queue-based or immediate)
	 * 4. Logs execution results for monitoring
	 * 5. Returns appropriate response format (JSON or GitHub Actions)
	 *
	 * @return void
	 */
	public function handle_template_redirect(): void {
		if ( get_query_var( 'dio_cron' ) ) {
			// Security checks - Mandatory authentication and rate limiting.
			if ( ! $this->verify_endpoint_security() ) {
				return; // Exit handled in verify_endpoint_security().
			}

			// Prevent concurrent execution - Only one cron job should run at a time.
			if ( ! DIO_Cron_Utilities::acquire_execution_lock() ) {
				DIO_Cron_Utilities::log_security_event(
					'CONCURRENT_EXECUTION',
					'Attempt to run while already executing'
				);
				status_header( 409 ); // Conflict.
				$this->output_error_response( esc_html__( 'Cron job already running', 'dio-cron' ) );
				exit;
			}

			try {
				// Parse request parameters to determine processing mode.
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- API endpoint parameters, not form processing
				$immediate = isset( $_GET[ 'immediate' ] ) && '1' === $_GET[ 'immediate' ];
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- API endpoint parameters, not form processing
				$ga_output = isset( $_GET[ 'ga' ] );

				if ( $immediate ) {
					// Legacy immediate processing - synchronous execution for backward compatibility.
					$result = $this->run_immediate_cron();
				} else {
					// New Action Scheduler queue processing - asynchronous, reliable execution.
					$result = $this->queue_manager->enqueue_all_sites();
				}

				// Update network-wide statistics.
				$sites_count = (int) ( $result[ 'count' ] ?? 0 );
				if ( $sites_count > 0 ) {
					DIO_Cron_Utilities::update_network_stats( $sites_count );
				}

				// Log successful execution.
				DIO_Cron_Utilities::log_security_event(
					'SUCCESSFUL_EXECUTION',
					sprintf( 'Cron executed successfully for %d sites', $result[ 'count' ] ?? 0 )
				);

				// Output results.
				if ( $ga_output ) {
					$this->output_github_actions_format( $result );
				} else {
					$this->output_standard_format( $result );
				}
			} finally {
				// Always release the lock.
				DIO_Cron_Utilities::release_execution_lock();
			}

			exit;
		}
	}

	/**
	 * Verify endpoint security (rate limiting, token auth)
	 *
	 * @return bool
	 */
	private function verify_endpoint_security(): bool {
		// Check rate limiting.
		$max_requests = apply_filters( 'dio_cron_rate_limit_max_requests', 5 );
		$time_window  = apply_filters( 'dio_cron_rate_limit_time_window', 5 * MINUTE_IN_SECONDS ); // 5 minutes.

		if ( ! DIO_Cron_Utilities::check_rate_limit( $max_requests, $time_window ) ) {
			DIO_Cron_Utilities::log_security_event(
				'RATE_LIMIT_EXCEEDED',
				'Rate limit exceeded'
			);
			status_header( 429 ); // Too Many Requests.
			$this->output_error_response( esc_html__( 'Rate limit exceeded. Please try again later.', 'dio-cron' ) );
			exit;
		}

		// Check token authentication - now mandatory.
		if ( ! DIO_Cron_Utilities::verify_endpoint_token() ) {
			DIO_Cron_Utilities::log_security_event(
				'AUTHENTICATION_FAILED',
				'Invalid or missing token'
			);
			status_header( 401 ); // Unauthorized.
			$this->output_error_response( esc_html__( 'Authentication required. Configure token in DIO Cron admin.', 'dio-cron' ) );
			exit;
		}

		DIO_Cron_Utilities::log_security_event(
			'AUTHENTICATION_SUCCESS',
			'Valid token provided'
		);

		return true;
	}

	/**
	 * Output error response in appropriate format
	 *
	 * @param string $message Error message.
	 * @return void
	 */
	private function output_error_response( string $message ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- API endpoint parameters, not form processing
		$ga_output = isset( $_GET[ 'ga' ] );

		if ( $ga_output ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- GitHub Actions command format.
			echo "::error::{$message}\n";
		} else {
			header( 'Content-Type: application/json' );
			echo wp_json_encode( DIO_Cron_Utilities::create_error_response( $message ) );
		}
	}

	/**
	 * Run immediate cron processing (legacy behavior)
	 *
	 * @return array
	 */
	private function run_immediate_cron() {
		// This maintains the original synchronous behavior.
		return dio_run_cron_on_all_sites();
	}

	/**
	 * Output results in GitHub Actions format
	 *
	 * @param array $result The result array with success, message, count, and execution_time.
	 * @return void
	 */
	private function output_github_actions_format( array $result ): void {
		if ( ! $result[ 'success' ] ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- GitHub Actions command format.
			echo "::error::{$result[ 'message' ]}\n";
		} else {
			$count          = $result[ 'count' ] ?? 0;
			$execution_time = $result[ 'execution_time' ] ?? 'N/A';
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- GitHub Actions command format.
			echo "::notice::Queued wp-cron for {$count} sites (execution time: {$execution_time}s)\n";
		}
	}

	/**
	 * Output results in standard format
	 *
	 * @param array $result The result array to output as JSON.
	 * @return void
	 */
	private function output_standard_format( array $result ): void {
		header( 'Content-Type: application/json' );
		echo wp_json_encode( $result );
	}

	/**
	 * Get the queue manager instance
	 *
	 * @return DIO_Cron_Queue_Manager
	 */
	public function get_queue_manager(): DIO_Cron_Queue_Manager {
		return $this->queue_manager;
	}

	/**
	 * Get the site processor instance
	 *
	 * @return DIO_Cron_Site_Processor
	 */
	public function get_site_processor(): DIO_Cron_Site_Processor {
		return $this->site_processor;
	}

	/**
	 * Plugin activation hook
	 *
	 * @return void
	 */
	public static function activate() {
		// Initialize rewrite rules.
		$instance = self::get_instance();
		$instance->init_rewrite_rules();
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation hook
	 *
	 * @return void
	 */
	public static function deactivate() {
		// Clean up transients.
		DIO_Cron_Utilities::clear_sites_cache();

		// Cancel any pending actions.
		if ( DIO_Cron_Utilities::is_action_scheduler_available() ) {
			as_unschedule_all_actions( DIO_Cron_Utilities::PROCESS_SITE_HOOK );
			as_unschedule_all_actions( DIO_Cron_Utilities::RUN_ALL_SITES_HOOK );
		}

		// Flush rewrite rules.
		flush_rewrite_rules();
	}
}
