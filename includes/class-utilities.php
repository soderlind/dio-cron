<?php
/**
 * DIO Cron Utilities Class
 *
 * @package DIO_Cron
 */

namespace Soderlind\Multisite\DioCron;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared utilities class for common functionality across DIO Cron classes
 */
class DIO_Cron_Utilities {
	/**
	 * Common cache keys and groups (DRY)
	 */
	private const CACHE_GROUP       = 'dio_cron';
	private const NETWORK_STATS_KEY = 'dio_cron_network_stats';
	private const CACHED_SITES_KEY  = 'dio_cron_sites';
	private const LEGACY_SITES_KEY  = 'dss_cron_sites';
	private const EXEC_LOCK_KEY     = 'dio_cron_execution_lock';
	private const LAST_RUN_KEY      = 'dio_cron_last_run';
	private const CURRENT_RUN_KEY   = 'dio_cron_current_run';

	/**
	 * Common filter names and groups (DRY)
	 */
	private const FILTER_SITES_LIMIT     = 'dio_cron_number_of_sites';
	private const FILTER_SITES_TTL       = 'dio_cron_sites_transient';
	private const FILTER_RL_MAX_REQUESTS = 'dio_cron_rate_limit_max_requests';
	private const FILTER_RL_TIME_WINDOW  = 'dio_cron_rate_limit_time_window';

	/**
	 * Action Scheduler group used by this plugin
	 */
	private const AS_GROUP = 'dio-cron';

	/**
	 * Public accessor for the Action Scheduler group slug (DRY across classes)
	 *
	 * @return string
	 */
	public static function get_action_scheduler_group(): string {
		return self::AS_GROUP;
	}

	/**
	 * Default structure for network stats (single source of truth)
	 */
	private static function network_stats_defaults(): array {
		return [ 
			'total_runs'               => 0,
			'last_run'                 => 0,
			'total_sites_processed'    => 0,
			'sites_processed_last_run' => 0,
		];
	}

	/**
	 * Generate a unique run identifier for a batch
	 *
	 * @return string
	 */
	public static function generate_run_id(): string {
		// Prefer random_bytes when available for better entropy, fallback to uniqid.
		try {
			if ( function_exists( 'random_bytes' ) ) {
				return 'run_' . bin2hex( random_bytes( 8 ) ) . '_' . dechex( (int) ( microtime( true ) * 1000 ) );
			}
		} catch (\Throwable $e) {
			// Fallback below.
		}
		return 'run_' . uniqid( '', true );
	}

	/**
	 * Start tracking a new run (batch) of enqueued site cron actions
	 *
	 * @param string $run_id   Unique run identifier.
	 * @param int    $expected Number of actions expected to complete (success or fail).
	 * @return void
	 */
	public static function start_new_run( string $run_id, int $expected ): void {
		$state = [ 
			'run_id'     => $run_id,
			'expected'   => max( 0, $expected ),
			'processed'  => 0,
			'started_at' => time(),
		];
		// Keep state for up to 6 hours.
		self::site_cache_set( self::CURRENT_RUN_KEY, $state, 6 * HOUR_IN_SECONDS, self::CACHE_GROUP );
	}

	/**
	 * Increment processed counter for the current run if run_id matches.
	 *
	 * @param string|null $run_id Run id from the processed action (optional but recommended).
	 * @return array|null Updated state or null if no active run.
	 */
	public static function increment_run_processed( ?string $run_id ): ?array {
		$state = self::site_cache_get( self::CURRENT_RUN_KEY, self::CACHE_GROUP );
		if ( ! is_array( $state ) ) {
			return null;
		}
		// If a run_id is provided, make sure it matches. If not provided, assume current run.
		if ( ! empty( $run_id ) && isset( $state[ 'run_id' ] ) && $state[ 'run_id' ] !== $run_id ) {
			return $state; // Ignore increments for other runs.
		}
		$state[ 'processed' ] = (int) ( $state[ 'processed' ] ?? 0 ) + 1;
		self::site_cache_set( self::CURRENT_RUN_KEY, $state, 6 * HOUR_IN_SECONDS, self::CACHE_GROUP );
		return $state;
	}

	/**
	 * Finalize the current run if processed >= expected. Updates network stats and clears run state.
	 *
	 * @return array|null Finalized data including processed count, or null if not finalized.
	 */
	public static function finalize_run_if_complete(): ?array {
		$state = self::site_cache_get( self::CURRENT_RUN_KEY, self::CACHE_GROUP );
		if ( ! is_array( $state ) ) {
			return null;
		}
		$expected  = (int) ( $state[ 'expected' ] ?? 0 );
		$processed = (int) ( $state[ 'processed' ] ?? 0 );
		if ( $expected > 0 && $processed >= $expected ) {
			// Update network-wide stats with the processed count for this run.
			self::update_network_stats( $processed );
			// Clear current run state.
			self::site_cache_delete( self::CURRENT_RUN_KEY, self::CACHE_GROUP );
			return [ 
				'finalized' => true,
				'processed' => $processed,
				'expected'  => $expected,
				'run_id'    => $state[ 'run_id' ] ?? '',
			];
		}
		return null;
	}

	/**
	 * Site-level cache get helper (uses object cache when available, otherwise site transients)
	 *
	 * @param string $key Cache key.
	 * @param string $group Cache group (object cache only).
	 * @param mixed  $default Default value on cache miss.
	 * @return mixed
	 */
	private static function site_cache_get( string $key, string $group = self::CACHE_GROUP, $default = false ) {
		if ( wp_using_ext_object_cache() ) {
			$val = wp_cache_get( $key, $group );
			return ( false === $val ) ? $default : $val;
		}
		$val = get_site_transient( $key );
		return ( false === $val ) ? $default : $val;
	}

	/**
	 * Site-level cache set helper
	 *
	 * @param string $key Cache key.
	 * @param mixed  $value Value to store.
	 * @param int    $expiration Expiration in seconds.
	 * @param string $group Cache group (object cache only).
	 * @return bool
	 */
	private static function site_cache_set( string $key, $value, int $expiration, string $group = self::CACHE_GROUP ): bool {
		if ( wp_using_ext_object_cache() ) {
			return (bool) wp_cache_set( $key, $value, $group, $expiration );
		}
		return (bool) set_site_transient( $key, $value, $expiration );
	}

	/**
	 * Site-level cache delete helper
	 *
	 * @param string $key Cache key.
	 * @param string $group Cache group (object cache only).
	 * @return bool
	 */
	private static function site_cache_delete( string $key, string $group = self::CACHE_GROUP ): bool {
		$ok = true;
		if ( wp_using_ext_object_cache() ) {
			$ok = (bool) wp_cache_delete( $key, $group );
		}
		// Always delete transient as fallback to ensure cleanup across environments.
		$ok = (bool) ( $ok & delete_site_transient( $key ) );
		return $ok;
	}

	/**
	 * Build a rate-limit cache key for an IP
	 *
	 * @param string $ip Client IP.
	 * @return string
	 */
	private static function build_rate_limit_key( string $ip ): string {
		return 'dio_cron_rate_limit_' . md5( $ip );
	}
	/**
	 * Update network-wide stats for DIO Cron runs.
	 *
	 * @param int $sites_processed Number of sites processed in this run.
	 * @return void
	 */
	public static function update_network_stats( int $sites_processed ) {
		$stats = self::site_cache_get( self::NETWORK_STATS_KEY, self::CACHE_GROUP, [] );
		if ( ! is_array( $stats ) ) {
			$stats = self::network_stats_defaults();
		} else {
			// Ensure we have all expected keys.
			$stats = array_merge( self::network_stats_defaults(), $stats );
		}
		$stats[ 'total_runs' ]++;
		$stats[ 'last_run' ]                 = time();
		$stats[ 'total_sites_processed' ] += $sites_processed;
		$stats[ 'sites_processed_last_run' ] = $sites_processed;
		self::site_cache_set( self::NETWORK_STATS_KEY, $stats, DAY_IN_SECONDS, self::CACHE_GROUP );
	}

	/**
	 * Get network-wide stats for DIO Cron runs.
	 *
	 * @return array
	 */
	public static function get_network_stats() {
		$stats = self::site_cache_get( self::NETWORK_STATS_KEY, self::CACHE_GROUP, [] );
		if ( ! is_array( $stats ) ) {
			return self::network_stats_defaults();
		}
		// Merge to ensure forward/backward compatibility when new keys are added.
		return array_merge( self::network_stats_defaults(), $stats );
	}

	/**
	 * Check if Action Scheduler is available
	 *
	 * @return bool
	 */
	public static function is_action_scheduler_available() {
		return function_exists( 'as_get_scheduled_actions' );
	}

	/**
	 * Get "Action Scheduler not available" error response
	 *
	 * @return array
	 */
	public static function get_action_scheduler_error() {
		return [ 
			'error' => esc_html__( 'Action Scheduler is not available', 'dio-cron' ),
		];
	}

	/**
	 * Create Action Scheduler not available WP_Error
	 *
	 * @return \WP_Error
	 */
	public static function create_action_scheduler_error() {
		return new \WP_Error( 'action_scheduler_missing', esc_html__( 'Action Scheduler is not available', 'dio-cron' ) );
	}

	/**
	 * Check if a specific Action Scheduler function exists
	 *
	 * @param string $function_name Function name to check.
	 * @return bool
	 */
	public static function action_scheduler_function_exists( string $function_name ): bool {
		return function_exists( $function_name );
	}

	/**
	 * Create a standardized error response array
	 *
	 * @param string $message Error message.
	 * @param int    $count Optional count value.
	 * @return array
	 */
	public static function create_error_response( string $message, int $count = 0 ): array {
		return [ 
			'success' => false,
			'message' => $message,
			'count'   => $count,
		];
	}

	/**
	 * Create a standardized success response array
	 *
	 * @param string $message Success message.
	 * @param int    $count Count value.
	 * @param float  $execution_time Optional execution time.
	 * @return array
	 */
	public static function create_success_response( string $message, int $count, ?float $execution_time = null ): array {
		$response = [ 
			'success' => true,
			'message' => $message,
			'count'   => $count,
		];

		if ( null !== $execution_time ) {
			$response[ 'execution_time' ] = $execution_time;
		}

		return $response;
	}

	/**
	 * Validate site object for cron processing
	 *
	 * @param object $site Site object.
	 * @return bool|\WP_Error
	 */
	public static function validate_site_for_cron( $site ) {
		if ( ! is_object( $site ) ) {
			return new \WP_Error( 'invalid_site', esc_html__( 'Invalid site object', 'dio-cron' ) );
		}

		if ( empty( $site->siteurl ) ) {
			return new \WP_Error( 'missing_site_url', esc_html__( 'Site URL is missing', 'dio-cron' ) );
		}

		$site_status_checks = [ 
			'public'   => [ 'site_not_public', esc_html__( 'Site is not public', 'dio-cron' ) ],
			'archived' => [ 'site_archived', esc_html__( 'Site is archived', 'dio-cron' ) ],
			'deleted'  => [ 'site_deleted', esc_html__( 'Site is deleted', 'dio-cron' ) ],
			'spam'     => [ 'site_spam', esc_html__( 'Site is marked as spam', 'dio-cron' ) ],
		];

		foreach ( $site_status_checks as $property => $error_info ) {
			if ( 'public' === $property ) {
				// Public should be 1 (true).
				if ( isset( $site->$property ) && ! $site->$property ) {
					return new \WP_Error( $error_info[ 0 ], $error_info[ 1 ] );
				}
			} elseif ( isset( $site->$property ) && $site->$property ) {
				// Other properties should be 0 (false) or not set.
				return new \WP_Error( $error_info[ 0 ], $error_info[ 1 ] );
			}
		}

		return true;
	}

	/**
	 * Format error message with site context
	 *
	 * @param string $site_url Site URL.
	 * @param string $message_template Message template with placeholders.
	 * @param string $error_message Error message.
	 * @return string
	 */
	public static function format_site_error( string $site_url, string $message_template, string $error_message ): string {
		return sprintf(
			$message_template,
			esc_url( $site_url ),
			esc_html( $error_message )
		);
	}

	/**
	 * Calculate execution time between two microtime values
	 *
	 * @param float $start_time Start time from microtime(true).
	 * @param float $end_time End time from microtime(true).
	 * @param int   $precision Number of decimal places.
	 * @return string
	 */
	public static function calculate_execution_time( float $start_time, float $end_time, int $precision = 2 ): string {
		return number_format( $end_time - $start_time, $precision );
	}

	/**
	 * Get default per-page for Action Scheduler queries (centralized)
	 *
	 * @param int $default Default value.
	 * @return int
	 */
	public static function get_default_actions_per_page( int $default = 25 ): int {
		return (int) apply_filters( 'dio_cron_actions_per_page', $default );
	}

	/**
	 * Get batch size for manual processing (centralized)
	 *
	 * @param int $default Default batch size.
	 * @return int
	 */
	public static function get_default_batch_size( int $default = 25 ): int {
		return (int) apply_filters( 'dio_cron_batch_size', $default );
	}

	/**
	 * Get common Action Scheduler query arguments
	 *
	 * @param string $hook Hook name.
	 * @param string $status Status (pending, complete, failed, etc.).
	 * @param int    $per_page Number per page.
	 * @return array
	 */
	public static function get_action_scheduler_query_args( string $hook, string $status, int $per_page = 25 ): array {
		return [ 
			'hook'     => $hook,
			'status'   => $status,
			'group'    => self::AS_GROUP,
			'per_page' => $per_page,
		];
	}

	/**
	 * Create a multisite requirement error
	 *
	 * @return array
	 */
	public static function create_multisite_error() {
		return self::create_error_response( esc_html__( 'This plugin requires WordPress Multisite', 'dio-cron' ) );
	}

	/**
	 * Create "no sites found" error
	 *
	 * @return array
	 */
	public static function create_no_sites_error() {
		return self::create_error_response( esc_html__( 'No public sites found in the network', 'dio-cron' ) );
	}

	/**
	 * Create "no sites provided" error for batch operations
	 *
	 * @return array
	 */
	public static function create_no_sites_provided_error() {
		return [ 
			'success'   => false,
			'message'   => esc_html__( 'No sites provided for processing', 'dio-cron' ),
			'processed' => 0,
			'errors'    => [],
		];
	}

	/**
	 * Sanitize and validate admin action
	 *
	 * @param array $request Request data ($_POST or $_REQUEST).
	 * @return array|false Returns array with action and nonce, or false if invalid.
	 */
	public static function validate_admin_action( array $request ) {
		if ( ! isset( $request[ 'action' ] ) ) {
			return false;
		}

		if ( ! current_user_can( 'manage_network_options' ) ) {
			return false;
		}

		$action = sanitize_text_field( wp_unslash( $request[ 'action' ] ?? '' ) );
		$nonce  = sanitize_text_field( wp_unslash( $request[ '_wpnonce' ] ?? '' ) );

		if ( ! wp_verify_nonce( $nonce, 'dio_cron_admin' ) ) {
			return false;
		}

		return [ 
			'action' => $action,
			'nonce'  => $nonce,
		];
	}

	/**
	 * Create admin notice HTML
	 *
	 * @param string $type Notice type (success, error, warning, info).
	 * @param string $message Notice message.
	 * @return string
	 */
	public static function create_admin_notice_html( string $type, string $message ): string {
		return sprintf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( $type ),
			esc_html( $message )
		);
	}

	/**
	 * Add admin notice for WordPress admin interface
	 *
	 * @param string $type The type of notice (success, error, warning, info).
	 * @param string $message The message to display in the notice.
	 * @return void
	 */
	public static function add_admin_notice( string $type, string $message ): void {
		add_action(
			'admin_notices',
			function () use ($type, $message) {
				echo wp_kses_post( self::create_admin_notice_html( $type, $message ) );
			}
		);
	}

	/**
	 * Get all public sites in the multisite network with transient caching
	 *
	 * Supports both transient and object caching for optimal performance:
	 * - Uses object cache when available for persistent caching
	 * - Falls back to transients for standard WordPress installations
	 * - Automatically cleans up old cache keys during upgrades
	 *
	 * @param int $number Maximum number of sites to retrieve.
	 * @return array|\WP_Error
	 */
	public static function get_cached_sites( ?int $number = null ) {
		// Try cache first (object cache or site transient depending on environment).
		$sites = self::site_cache_get( self::CACHED_SITES_KEY, self::CACHE_GROUP );
		if ( false !== $sites && null !== $sites ) {
			return $sites;
		}

		// Remove transient from previous version during cache miss.
		if ( false !== get_site_transient( self::LEGACY_SITES_KEY ) ) {
			delete_site_transient( self::LEGACY_SITES_KEY );
		}

		// Get all public sites in the network.
		$sites = get_sites(
			[ 
				'public'   => 1,
				'archived' => 0,
				'deleted'  => 0,
				'spam'     => 0,
				'number'   => $number ?? apply_filters( self::FILTER_SITES_LIMIT, 200 ),
			]
		);

		if ( is_wp_error( $sites ) ) {
			return $sites;
		}

		$cache_duration = apply_filters( self::FILTER_SITES_TTL, HOUR_IN_SECONDS );
		self::site_cache_set( self::CACHED_SITES_KEY, $sites, $cache_duration, self::CACHE_GROUP );

		return $sites;
	}

	/**
	 * Clear the cached sites from both object cache and transients
	 *
	 * @return bool
	 */
	public static function clear_sites_cache(): bool {
		return self::site_cache_delete( self::CACHED_SITES_KEY, self::CACHE_GROUP );
	}

	/**
	 * Get the secret token for endpoint authentication
	 *
	 * Uses hierarchical token retrieval for maximum flexibility:
	 * 1. Environment variable (DIO_CRON_TOKEN) - for containerized deployments
	 * 2. WordPress constant (DIO_CRON_TOKEN) - for wp-config.php configuration
	 * 3. Database option (dio_cron_endpoint_token) - for admin interface management
	 *
	 * @return string
	 */
	public static function get_endpoint_token(): string {
		// Check environment variable first.
		$token = getenv( 'DIO_CRON_TOKEN' );

		if ( ! empty( $token ) ) {
			return $token;
		}

		// Fall back to WordPress constant.
		if ( defined( 'DIO_CRON_TOKEN' ) ) {
			return DIO_CRON_TOKEN;
		}

		// Finally check database option.
		return get_site_option( 'dio_cron_endpoint_token', '' );
	}

	/**
	 * Set the endpoint token in database
	 *
	 * @param string $token The token to set.
	 * @return bool
	 */
	public static function set_endpoint_token( string $token ): bool {
		if ( empty( $token ) ) {
			return delete_site_option( 'dio_cron_endpoint_token' );
		}

		return update_site_option( 'dio_cron_endpoint_token', sanitize_text_field( $token ) );
	}

	/**
	 * Generate a secure random token
	 *
	 * @param int $length Token length in bytes (will be hex encoded, so actual string is 2x this).
	 * @return string
	 * @throws \Exception When both random_bytes() and wp_generate_password() fail.
	 */
	public static function generate_secure_token( int $length = 32 ): string {
		if ( function_exists( 'random_bytes' ) ) {
			try {
				return bin2hex( random_bytes( $length ) );
			} catch (\Exception $e) {
				// Fall back to wp_generate_password if random_bytes fails.
				// Intentionally empty - fallback is handled below.
				unset( $e ); // Suppress unused variable warning.
			}
		}

		// Fallback using WordPress function.
		return wp_generate_password( $length * 2, true, true );
	}

	/**
	 * Check if a valid token is configured
	 *
	 * @return bool
	 */
	public static function is_token_configured() {
		$token = self::get_endpoint_token();
		return ! empty( $token ) && strlen( $token ) >= 16; // Minimum 16 characters.
	}

	/**
	 * Get client IP address
	 *
	 * @return string
	 */
	public static function get_client_ip() {
		$ip_keys = [ 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR' ];

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) );
				$ip  = trim( $ips[ 0 ] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
			}
		}

		return sanitize_text_field( wp_unslash( $_SERVER[ 'REMOTE_ADDR' ] ?? '' ) );
	}

	/**
	 * Check rate limit for endpoint requests
	 *
	 * Implements sliding window rate limiting per IP address:
	 * - Tracks request timestamps in site transients
	 * - Automatically cleans expired entries outside time window
	 * - Uses MD5 hash of IP to create unique cache keys
	 * - Prevents abuse while allowing legitimate usage patterns
	 *
	 * @param int $max_requests Maximum requests allowed.
	 * @param int $time_window Time window in seconds.
	 * @return bool
	 */
	public static function check_rate_limit( int $max_requests = 5, int $time_window = 5 * MINUTE_IN_SECONDS ): bool {
		// Allow customization through filters while keeping method parameters as defaults.
		$max_requests = (int) apply_filters( self::FILTER_RL_MAX_REQUESTS, $max_requests );
		$time_window  = (int) apply_filters( self::FILTER_RL_TIME_WINDOW, $time_window );

		$client_ip = self::get_client_ip();
		$cache_key = self::build_rate_limit_key( $client_ip );

		$requests = self::site_cache_get( $cache_key, self::CACHE_GROUP, [] );
		if ( false === $requests || ! is_array( $requests ) ) {
			$requests = [];
		}

		// Clean old requests outside time window.
		$current_time = time();
		$requests     = array_filter(
			$requests,
			function ($timestamp) use ($current_time, $time_window) {
				return ( $current_time - $timestamp ) < $time_window;
			}
		);

		// Check if limit exceeded.
		if ( count( $requests ) >= $max_requests ) {
			return false; // Rate limit exceeded.
		}

		// Add current request.
		$requests[] = $current_time;
		self::site_cache_set( $cache_key, $requests, $time_window, self::CACHE_GROUP );

		return true;
	}

	/**
	 * Verify endpoint token authentication
	 *
	 * @return bool
	 */
	public static function verify_endpoint_token() {
		$secret = self::get_endpoint_token();

		// Token is now mandatory - must be configured.
		if ( empty( $secret ) ) {
			return false; // No token configured - access denied.
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended -- Token-based API endpoint, not form processing
		$provided_token = sanitize_text_field( wp_unslash( $_GET[ 'token' ] ?? $_POST[ 'token' ] ?? '' ) );

		if ( empty( $provided_token ) ) {
			return false; // No token provided - access denied.
		}

		return hash_equals( $secret, $provided_token );
	}

	/**
	 * Acquire a network-wide execution lock using site transients.
	 * Prevents concurrent runs across all servers in a multisite/cluster environment.
	 * Also prevents rapid re-entry (double-run) within a short window.
	 *
	 * @param int $timeout Lock timeout in seconds.
	 * @param int $min_interval Minimum seconds between runs (last-run protection).
	 * @return bool True if lock acquired, false if already locked or ran too recently.
	 */
	public static function acquire_execution_lock( int $timeout = 300, int $min_interval = 60 ): bool {
		$now      = time();
		$last_run = (int) self::site_cache_get( self::LAST_RUN_KEY, self::CACHE_GROUP, 0 );
		if ( $last_run && ( $now - $last_run ) < $min_interval ) {
			return false; // Ran too recently.
		}
		$lock = self::site_cache_get( self::EXEC_LOCK_KEY, self::CACHE_GROUP );
		if ( $lock && isset( $lock[ 'expires' ] ) && $lock[ 'expires' ] > $now ) {
			return false; // Already locked.
		}
		$lock_data = [ 
			'server'    => gethostname(),
			'pid'       => function_exists( 'getmypid' ) ? getmypid() : null,
			'timestamp' => $now,
			'expires'   => $now + $timeout,
		];
		self::site_cache_set( self::EXEC_LOCK_KEY, $lock_data, $timeout, self::CACHE_GROUP );
		// Double-check we got the lock (race protection)
		$verify = self::site_cache_get( self::EXEC_LOCK_KEY, self::CACHE_GROUP );
		if ( $verify && $verify[ 'timestamp' ] === $now ) {
			self::site_cache_set( self::LAST_RUN_KEY, $now, $timeout, self::CACHE_GROUP );
			return true;
		}
		return false;
	}

	/**
	 * Release the network-wide execution lock.
	 *
	 * @return bool
	 */
	public static function release_execution_lock() {
		return self::site_cache_delete( self::EXEC_LOCK_KEY, self::CACHE_GROUP );
	}

	/**
	 * Check if execution is currently locked (network-wide).
	 *
	 * @return bool
	 */
	public static function is_execution_locked() {
		$lock = self::site_cache_get( self::EXEC_LOCK_KEY, self::CACHE_GROUP );
		return ( $lock && isset( $lock[ 'expires' ] ) && $lock[ 'expires' ] > time() );
	}

	/**
	 * Get info about the current lock holder (for debugging/logging).
	 *
	 * @return array|null
	 */
	public static function get_execution_lock_info() {
		$lock = self::site_cache_get( self::EXEC_LOCK_KEY, self::CACHE_GROUP );
		if ( $lock && isset( $lock[ 'expires' ] ) && $lock[ 'expires' ] > time() ) {
			return $lock;
		}
		return null;
	}

	/**
	 * Log security event
	 *
	 * @param string $event Event type.
	 * @param string $message Event message.
	 * @param array  $context Additional context.
	 * @return void
	 */
	public static function log_security_event( string $event, string $message, array $context = [] ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$log_entry = sprintf(
				'[DIO Cron Security] %s: %s | IP: %s | Context: %s',
				$event,
				$message,
				self::get_client_ip(),
				wp_json_encode( $context )
			);

			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging when WP_DEBUG is enabled
			error_log( $log_entry );
		}
	}

	/**
	 * Common Action Scheduler hook names
	 *
	 * These constants provide centralized hook management for Action Scheduler integration.
	 * Using constants prevents typos and makes it easier to update hook names globally.
	 */

	/**
	 * Hook name for processing individual sites
	 *
	 * @var string
	 */
	const PROCESS_SITE_HOOK = 'dio_cron_process_site';

	/**
	 * Hook name for triggering all sites processing
	 *
	 * @var string
	 */
	const RUN_ALL_SITES_HOOK = 'dio_cron_run_all_sites';
}
