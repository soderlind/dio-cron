<?php
/**
 * DIO Cron Site Processor Class
 *
 * Following established plugin naming convention for compatibility.
 *
 * @package DIO_Cron
 */

// phpcs:disable WordPress.Files.FileName.InvalidClassFileName

namespace Soderlind\Multisite\Cron;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Site Processor class for handling individual site cron processing
 */
class DIO_Cron_Site_Processor {
	// phpcs:enable WordPress.Files.FileName.InvalidClassFileName

	/**
	 * Default timeout for cron requests
	 * This can be overridden using the 'dio_cron_request_timeout' filter
	 *
	 * @var int
	 */
	const DEFAULT_TIMEOUT = 15;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Constructor is kept empty for now.
	}

	/**
	 * Helper function to log messages only when detailed logging is enabled
	 *
	 * Implements secure logging that respects both WordPress debug settings
	 * and user preferences. Only logs when:
	 * 1. WP_DEBUG is enabled (production safety)
	 * 2. User has enabled detailed logging in admin interface
	 *
	 * @param string $message The log message.
	 * @return void
	 */
	private function log_if_enabled( string $message ): void {
		// Only log if WP_DEBUG is true AND detailed logging is enabled.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && get_option( 'dio_cron_detailed_logging', false ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging when WP_DEBUG is enabled
			error_log( $message );
		}
	}

	/**
	 * Process cron for a single site (Action Scheduler callback)
	 *
	 * @param int    $site_id  Site ID.
	 * @param string $site_url Site URL.
	 * @throws \Exception When site not found or cron fails.
	 * @return void
	 */
	public function process_single_site( int $site_id, string $site_url = '' ): void {
		$start_time = microtime( true );

		// Log the start of processing.
		$this->log_if_enabled(
			sprintf(
				'DIO Cron: Starting process for site ID %d, URL: %s',
				$site_id,
				$site_url
			)
		);

		if ( empty( $site_url ) ) {
			$site = get_site( $site_id );
			if ( ! $site ) {
				$error_msg = sprintf( 'Site with ID %d not found', $site_id );
				$this->log_if_enabled( 'DIO Cron Error: ' . $error_msg );
				// translators: %d is the site ID.
				throw new \Exception( sprintf( esc_html__( 'Site with ID %d not found', 'dio-cron' ), esc_html( $site_id ) ) );
			}
			$site_url = $site->siteurl;
		}

		// Log the actual cron trigger.
		$this->log_if_enabled(
			sprintf(
				'DIO Cron: Triggering wp-cron.php for site %s (ID: %d)',
				$site_url,
				$site_id
			)
		);

		$result         = $this->trigger_site_cron( $site_url );
		$execution_time = microtime( true ) - $start_time;

		if ( is_wp_error( $result ) ) {
			// Log the error.
			$this->log_if_enabled(
				sprintf(
					'DIO Cron Error: Site %s failed - %s (%.2fs)',
					$site_url,
					$result->get_error_message(),
					$execution_time
				)
			);
			throw new \Exception(
				sprintf(
					// translators: %1$s is the site URL, %2$s is the error message.
					esc_html__( 'Cron failed for %1$s: %2$s', 'dio-cron' ),
					esc_url( $site_url ),
					esc_html( $result->get_error_message() )
				)
			);
		}

		// Log successful completion.
		$this->log_if_enabled(
			sprintf(
				'DIO Cron Success: Site %s completed - Response: %d (%.2fs)',
				$site_url,
				$result['response_code'] ?? 'N/A',
				$execution_time
			)
		);

		// Log successful processing if needed.
		do_action( 'dio_cron_site_processed', $site_id, $site_url, $result );
	}

	/**
	 * Trigger cron for a specific site
	 *
	 * @param string $site_url Site URL.
	 * @return array|\WP_Error
	 */
	public function trigger_site_cron( $site_url ) {
		$start_time = microtime( true );
		$cron_url   = trailingslashit( $site_url ) . 'wp-cron.php?doing_wp_cron';
		$timeout    = $this->get_timeout();

		// Log the exact URL being called.
		$this->log_if_enabled(
			sprintf(
				'DIO Cron: Making HTTP request to %s',
				$cron_url
			)
		);

		$args = [
			'blocking'  => true,  // We want to wait for the response in queued processing.
			'sslverify' => false,
			'timeout'   => $timeout,
			'headers'   => [
				'User-Agent' => 'DIO-Cron/' . DIO_Cron::VERSION,
			],
		];

		$args = apply_filters( 'dio_cron_request_args', $args, $site_url );

		$response  = wp_remote_get( $cron_url, $args );
		$exec_time = microtime( true ) - $start_time;

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();

			// Log HTTP errors.
			$this->log_if_enabled(
				sprintf(
					'DIO Cron HTTP Error: %s for URL %s (%.2fs)',
					$error_message,
					$cron_url,
					$exec_time
				)
			);

			// Log timeout errors specifically for debugging.
			if ( strpos( $error_message, 'cURL error 28' ) !== false || strpos( $error_message, 'timeout' ) !== false ) {
				$this->log_if_enabled(
					sprintf(
						/* translators: 1: cron URL, 2: execution time in seconds, 3: timeout limit in seconds */
						esc_html__( 'DIO Cron timeout: %1$s (took %2$.2fs, timeout was %3$ds)', 'dio-cron' ),
						$cron_url,
						$exec_time,
						$timeout
					)
				);
			}

			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		// Log detailed response information.
		$this->log_if_enabled(
			sprintf(
				'DIO Cron HTTP Response: %d for %s (%.2fs) - Body length: %d bytes',
				$response_code,
				$cron_url,
				$exec_time,
				strlen( $response_body )
			)
		);

		// Consider anything in the 200 range as success.
		if ( $response_code >= 200 && $response_code < 300 ) {
			return [
				'success'        => true,
				'response_code'  => $response_code,
				'site_url'       => $site_url,
				'execution_time' => $exec_time,
				'timeout_used'   => $timeout,
			];
		}

		// Log if response is not 200.
		$this->log_if_enabled(
			sprintf(
				'DIO Cron Warning: Non-200 response %d for %s - Body: %s',
				$response_code,
				$cron_url,
				substr( $response_body, 0, 200 ) // First 200 chars of response.
			)
		);

		return new \WP_Error(
			'cron_request_failed',
			sprintf(
				// translators: %d is the HTTP response code.
				esc_html__( 'HTTP %d: Cron request failed', 'dio-cron' ),
				intval( $response_code )
			),
			[
				'response_code'  => $response_code,
				'execution_time' => $exec_time,
				'timeout_used'   => $timeout,
			]
		);
	}

	/**
	 * Validate if a site should have cron processed
	 * Now delegated to utilities class
	 *
	 * @param object $site Site object.
	 * @return bool|\WP_Error
	 */
	public function validate_site( $site ) {
		return DIO_Cron_Utilities::validate_site_for_cron( $site );
	}

	/**
	 * Get timeout for cron requests
	 *
	 * @return int
	 */
	private function get_timeout() {
		return apply_filters( 'dio_cron_request_timeout', self::DEFAULT_TIMEOUT );
	}

	/**
	 * Batch process multiple sites (legacy method for immediate processing)
	 *
	 * @param array $sites Array of site objects.
	 * @return array
	 */
	public function process_sites_batch( $sites ) {
		if ( empty( $sites ) ) {
			return [
				'success'   => false,
				'message'   => esc_html__( 'No sites provided for processing', 'dio-cron' ),
				'processed' => 0,
				'errors'    => [],
			];
		}

		$processed  = 0;
		$errors     = [];
		$start_time = microtime( true );

		foreach ( (array) $sites as $site ) {
			$validation = $this->validate_site( $site );
			if ( is_wp_error( $validation ) ) {
				$errors[] = $this->format_site_error(
					$site->siteurl ?? 'unknown',
					'Site %1$s validation failed: %2$s',
					$validation->get_error_message()
				);
				continue;
			}

			$result = $this->trigger_site_cron( $site->siteurl );
			if ( is_wp_error( $result ) ) {
				$errors[] = $this->format_site_error(
					$site->siteurl,
					'Error for %1$s: %2$s',
					$result->get_error_message()
				);
			} else {
				++$processed;
			}
		}

		$end_time       = microtime( true );
		$execution_time = number_format( $end_time - $start_time, 2 );

		return [
			'success'        => empty( $errors ),
			'message'        => empty( $errors ) ?
				sprintf(
					// translators: %d is the number of sites processed.
					esc_html__( 'Processed %d sites successfully', 'dio-cron' ),
					intval( $processed )
				) :
				implode( "\n", $errors ),
			'processed'      => $processed,
			'errors'         => $errors,
			'execution_time' => $execution_time,
		];
	}

	/**
	 * Format site error message - delegated to utilities
	 *
	 * @param string $site_url Site URL.
	 * @param string $message_template Message template.
	 * @param string $error_message Error message.
	 * @return string
	 */
	private function format_site_error( $site_url, $message_template, $error_message ) {
		return DIO_Cron_Utilities::format_site_error( $site_url, $message_template, $error_message );
	}

	/**
	 * Get processing statistics
	 *
	 * @return array
	 */
	public function get_processing_stats() {
		if ( ! DIO_Cron_Utilities::is_action_scheduler_available() ) {
			return [
				'error' => 'Action Scheduler is not available',
			];
		}

		// Get timezone and date information.
		$date_info = $this->get_today_date_range();

		// Try direct database query first (preferred method).
		$direct_stats = $this->get_stats_via_direct_query( $date_info );
		if ( null !== $direct_stats ) {
			return $direct_stats;
		}

		// Fallback to Action Scheduler API.
		return $this->get_stats_via_action_scheduler_api( $date_info );
	}

	/**
	 * Get today's date range information
	 *
	 * @return array
	 */
	private function get_today_date_range() {
		$wp_timezone = wp_timezone_string();
		// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Legacy Action Scheduler compatibility requires timestamp format
		$current_local_time = current_time( 'timestamp', false ); // false = local time, not GMT.
		$today_start        = strtotime( 'today midnight', $current_local_time );
		$today_end          = strtotime( 'tomorrow midnight', $current_local_time ) - 1;
		$wp_current_time    = current_time( 'Y-m-d H:i:s' );

		return [
			'timezone'              => $wp_timezone,
			'current_local_time'    => $current_local_time,
			'current_time_readable' => $wp_current_time,
			'today_start'           => $today_start,
			'today_end'             => $today_end,
			'today_start_readable'  => wp_date( 'Y-m-d H:i:s', $today_start ),
			'today_end_readable'    => wp_date( 'Y-m-d H:i:s', $today_end ),
		];
	}

	/**
	 * Calculate statistics from counts
	 *
	 * @param int $completed_count Completed count.
	 * @param int $failed_count Failed count.
	 * @return array
	 */
	private function calculate_stats( $completed_count, $failed_count ) {
		$total_today  = $completed_count + $failed_count;
		$success_rate = $total_today > 0 ? ( $completed_count / $total_today ) * 100 : 0;

		return [
			'completed_today' => $completed_count,
			'failed_today'    => $failed_count,
			'success_rate'    => $success_rate,
			'total_today'     => $total_today,
		];
	}

	/**
	 * Format final statistics response
	 *
	 * @param array  $stats Statistics array.
	 * @param array  $date_info Date information.
	 * @param string $method_used Method used to get stats.
	 * @param array  $additional_debug Additional debug info.
	 * @return array
	 */
	private function format_stats_response( $stats, $date_info, $method_used, $additional_debug = [] ) {
		$debug_info = array_merge(
			[
				'current_time'          => $date_info['current_local_time'],
				'current_time_readable' => $date_info['current_time_readable'],
				'today_start'           => $date_info['today_start'],
				'today_end'             => $date_info['today_end'],
				'today_start_readable'  => $date_info['today_start_readable'],
				'today_end_readable'    => $date_info['today_end_readable'],
				'timezone'              => $date_info['timezone'],
				'total_completed'       => $stats['completed_today'],
				'total_failed'          => $stats['failed_today'],
				'filtered_completed'    => $stats['completed_today'],
				'filtered_failed'       => $stats['failed_today'],
				'method_used'           => $method_used,
				'sample_action_dates'   => [],
			],
			$additional_debug
		);

		return [
			'completed_today' => $stats['completed_today'],
			'failed_today'    => $stats['failed_today'],
			'success_rate'    => $stats['success_rate'],
			'debug_info'      => $debug_info,
		];
	}

	/**
	 * Get statistics via direct database query
	 *
	 * @param array $date_info Date information.
	 * @return array|null
	 */
	private function get_stats_via_direct_query( $date_info ) {
		$completed_count = $this->count_actions_fast( 'complete', $date_info['today_start'], $date_info['today_end'] );
		$failed_count    = $this->count_actions_fast( 'failed', $date_info['today_start'], $date_info['today_end'] );

		// If counts are non-zero (or zero but valid), return stats directly.
		if ( null !== $completed_count && null !== $failed_count ) {
			$stats = $this->calculate_stats( (int) $completed_count, (int) $failed_count );
			return $this->format_stats_response( $stats, $date_info, 'direct_query_fast' );
		}

		return null;
	}

	/**
	 * Get statistics via Action Scheduler API (fallback method)
	 *
	 * @param array $date_info Date information.
	 * @return array
	 */
	private function get_stats_via_action_scheduler_api( $date_info ) {
		// Get all completed and failed actions.
		$completed_all = as_get_scheduled_actions(
			[
				'hook'     => 'dio_cron_process_site',
				'status'   => 'complete',
				'group'    => 'dio-cron',
				'per_page' => 1000, // Get more results to ensure we don't miss any.
			]
		);

		$failed_all = as_get_scheduled_actions(
			[
				'hook'     => 'dio_cron_process_site',
				'status'   => 'failed',
				'group'    => 'dio-cron',
				'per_page' => 1000,
			]
		);

		// Filter by today's date manually.
		$completed_count = 0;
		$failed_count    = 0;
		$debug_dates     = [];

		$completed_count = $this->count_actions_in_date_range( $completed_all, $date_info, 'completed', $debug_dates );
		$failed_count    = $this->count_actions_in_date_range( $failed_all, $date_info, 'failed', $debug_dates );

		$stats = $this->calculate_stats( $completed_count, $failed_count );

		// If we still don't have any data, try a simpler approach.
		if ( 0 === $stats['total_today'] ) {
			$stats = $this->get_recent_stats_fallback( $date_info );
		}

		$additional_debug = [
			'total_completed'     => count( $completed_all ),
			'total_failed'        => count( $failed_all ),
			'method_used'         => 0 === $stats['total_today'] ? 'fallback' : 'fallback_manual_filter',
			'sample_action_dates' => $this->get_sample_action_dates( $completed_all, $failed_all ),
			'all_action_dates'    => array_slice( $debug_dates, 0, 10 ), // First 10 for debugging.
		];

		return $this->format_stats_response( $stats, $date_info, $additional_debug['method_used'], $additional_debug );
	}

	/**
	 * Count actions within date range
	 *
	 * @param array  $actions Array of actions.
	 * @param array  $date_info Date information.
	 * @param string $type Action type for debugging.
	 * @param array  $debug_dates Debug dates array (passed by reference).
	 * @return int
	 */
	private function count_actions_in_date_range( $actions, $date_info, $type, &$debug_dates ) {
		$count = 0;

		foreach ( $actions as $action ) {
			$action_date   = $this->get_action_date( $action );
			$debug_dates[] = [
				'type'     => $type,
				'date'     => $action_date,
				'readable' => $action_date ? wp_date( 'Y-m-d H:i:s', $action_date ) : 'null',
				'in_range' => $action_date && $action_date >= $date_info['today_start'] && $action_date <= $date_info['today_end'],
			];
			if ( $action_date && $action_date >= $date_info['today_start'] && $action_date <= $date_info['today_end'] ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Get recent statistics as final fallback
	 *
	 * @param array $date_info Date information.
	 * @return array
	 */
	private function get_recent_stats_fallback( $date_info ) {
		// Get recent actions without date filtering.
		$recent_completed = as_get_scheduled_actions(
			[
				'hook'     => 'dio_cron_process_site',
				'status'   => 'complete',
				'group'    => 'dio-cron',
				'per_page' => 100,
			]
		);

		$recent_failed = as_get_scheduled_actions(
			[
				'hook'     => 'dio_cron_process_site',
				'status'   => 'failed',
				'group'    => 'dio-cron',
				'per_page' => 100,
			]
		);

		$completed_count = 0;
		$failed_count    = 0;

		// Count recent actions from today.
		foreach ( $recent_completed as $action ) {
			$action_date = $this->get_action_date( $action );
			if ( $action_date && $action_date >= $date_info['today_start'] ) {
				++$completed_count;
			}
		}

		foreach ( $recent_failed as $action ) {
			$action_date = $this->get_action_date( $action );
			if ( $action_date && $action_date >= $date_info['today_start'] ) {
				++$failed_count;
			}
		}

		return $this->calculate_stats( $completed_count, $failed_count );
	}

	/**
	 * Get action date timestamp safely
	 *
	 * @param object $action Action Scheduler action object.
	 * @return int|null
	 */
	private function get_action_date( $action ) {
		// Try different methods to get the action date.
		if ( method_exists( $action, 'get_date_created' ) ) {
			$date = $action->get_date_created();
			if ( $date && is_object( $date ) && method_exists( $date, 'getTimestamp' ) ) {
				return $date->getTimestamp();
			}
		}

		if ( method_exists( $action, 'get_date' ) ) {
			$date = $action->get_date();
			if ( $date && is_object( $date ) && method_exists( $date, 'getTimestamp' ) ) {
				return $date->getTimestamp();
			}
		}

		// Try to get scheduled date.
		if ( method_exists( $action, 'get_scheduled_date' ) ) {
			$date = $action->get_scheduled_date();
			if ( $date && is_object( $date ) && method_exists( $date, 'getTimestamp' ) ) {
				return $date->getTimestamp();
			}
		}

		// Try the store method if available.
		if ( method_exists( $action, 'get_store' ) ) {
			$store = $action->get_store();
			if ( $store && method_exists( $store, 'get_date' ) ) {
				$date = $store->get_date( $action->get_id() );
				if ( $date && is_object( $date ) && method_exists( $date, 'getTimestamp' ) ) {
					return $date->getTimestamp();
				}
			}
		}

		// Try to access action ID and get date from ActionScheduler directly.
		if ( method_exists( $action, 'get_id' ) ) {
			$action_id = $action->get_id();
			if ( DIO_Cron_Utilities::action_scheduler_function_exists( 'as_get_datetime_object' ) ) {
				$date = as_get_datetime_object( $action_id );
				if ( $date && method_exists( $date, 'getTimestamp' ) ) {
					return $date->getTimestamp();
				}
			}
		}

		// Fallback: try to get from properties.
		if ( isset( $action->date_created ) ) {
			if ( is_numeric( $action->date_created ) ) {
				return $action->date_created;
			}
			$timestamp = strtotime( $action->date_created );
			return false !== $timestamp ? $timestamp : null;
		}

		// Another fallback: check for scheduled_date property.
		if ( isset( $action->scheduled_date ) ) {
			if ( is_numeric( $action->scheduled_date ) ) {
				return $action->scheduled_date;
			}
			$timestamp = strtotime( $action->scheduled_date );
			return false !== $timestamp ? $timestamp : null;
		}

		return null;
	}

	/**
	 * Get sample action dates for debugging
	 *
	 * @param array $completed_actions Completed actions.
	 * @param array $failed_actions Failed actions.
	 * @return array
	 */
	private function get_sample_action_dates( $completed_actions, $failed_actions ) {
		$samples = [];

		// Get first 5 completed action dates.
		$count = 0;
		foreach ( $completed_actions as $action ) {
			if ( $count >= 5 ) {
				break;
			}
			$date = $this->get_action_date( $action );
			if ( $date ) {
				$samples['completed'][] = [
					'timestamp'   => $date,
					'readable'    => date_i18n( 'Y-m-d H:i:s', $date ),
					'object_type' => get_class( $action ),
				];
			}
			++$count;
		}

		// Get first 5 failed action dates.
		$count = 0;
		foreach ( $failed_actions as $action ) {
			if ( $count >= 5 ) {
				break;
			}
			$date = $this->get_action_date( $action );
			if ( $date ) {
				$samples['failed'][] = [
					'timestamp'   => $date,
					'readable'    => date_i18n( 'Y-m-d H:i:s', $date ),
					'object_type' => get_class( $action ),
				];
			}
			++$count;
		}

		return $samples;
	}

	/**
	 * Get actions with dates using direct database query
	 *
	 * @param string $status Action status ('complete' or 'failed').
	 * @param int    $start_time Start timestamp.
	 * @param int    $end_time End timestamp.
	 * @return array
	 */
	private function get_actions_with_dates( $status, $start_time, $end_time ) {
		global $wpdb;

		// Tables.
		$table_actions = $wpdb->base_prefix . 'actionscheduler_actions';
		$table_logs    = $wpdb->base_prefix . 'actionscheduler_logs';

		// Normalize inputs.
		$as_status = ( 'complete' === $status ) ? 'complete' : 'failed';
		$hook      = 'dio_cron_process_site';
		$group_id  = $this->get_dio_cron_group_id();
		if ( ! $group_id ) {
			return [];
		}

		// Compare against DATETIME to keep predicates sargable (no functions on columns).
		$start_gmt = wp_date( 'Y-m-d H:i:s', (int) $start_time );
		$end_gmt   = wp_date( 'Y-m-d H:i:s', (int) $end_time );

		// Use UNION to avoid OR across different tables, which blocks index usage.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- ActionScheduler table names are safe
		$part_actions = $wpdb->prepare(
			"SELECT a.action_id
			 FROM {$table_actions} a
			 WHERE a.hook = %s
			   AND a.status = %s
			   AND a.group_id = %d
			   AND a.scheduled_date_gmt BETWEEN %s AND %s",
			$hook,
			$as_status,
			$group_id,
			$start_gmt,
			$end_gmt
		);

		$part_logs = $wpdb->prepare(
			"SELECT a.action_id
			 FROM {$table_actions} a
			 INNER JOIN {$table_logs} l ON l.action_id = a.action_id
			 WHERE a.hook = %s
			   AND a.status = %s
			   AND a.group_id = %d
			   AND l.log_date_gmt BETWEEN %s AND %s",
			$hook,
			$as_status,
			$group_id,
			$start_gmt,
			$end_gmt
		);

		$query = "{$part_actions} UNION {$part_logs} ORDER BY action_id DESC";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Query parts are already prepared above, direct query needed for ActionScheduler optimization
		$results = $wpdb->get_results( $query );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $results ? $results : [];
	}

	/**
	 * Fast count of actions completed/failed in a time window using sargable predicates.
	 *
	 * @param string $status      'complete' or 'failed'.
	 * @param int    $start_time  Start timestamp (UTC/local agnostic, converted to GMT).
	 * @param int    $end_time    End timestamp.
	 * @return int|null           Count or null on error.
	 */
	private function count_actions_fast( $status, $start_time, $end_time ) {
		global $wpdb;

		$table_actions = $wpdb->base_prefix . 'actionscheduler_actions';
		$table_logs    = $wpdb->base_prefix . 'actionscheduler_logs';

		$as_status = ( 'complete' === $status ) ? 'complete' : 'failed';
		$hook      = 'dio_cron_process_site';
		$group_id  = $this->get_dio_cron_group_id();
		if ( ! $group_id ) {
			return 0;
		}

		$start_gmt = wp_date( 'Y-m-d H:i:s', (int) $start_time );
		$end_gmt   = wp_date( 'Y-m-d H:i:s', (int) $end_time );

		// Count DISTINCT action IDs from union of (scheduled in window) and (logged in window).
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- ActionScheduler table names are safe, performance-critical queries
		$part_actions = $wpdb->prepare(
			"SELECT a.action_id
			 FROM {$table_actions} a
			 WHERE a.hook = %s
			   AND a.status = %s
			   AND a.group_id = %d
			   AND a.scheduled_date_gmt BETWEEN %s AND %s",
			$hook,
			$as_status,
			$group_id,
			$start_gmt,
			$end_gmt
		);

		$part_logs = $wpdb->prepare(
			"SELECT a.action_id
			 FROM {$table_actions} a
			 INNER JOIN {$table_logs} l ON l.action_id = a.action_id
			 WHERE a.hook = %s
			   AND a.status = %s
			   AND a.group_id = %d
			   AND l.log_date_gmt BETWEEN %s AND %s",
			$hook,
			$as_status,
			$group_id,
			$start_gmt,
			$end_gmt
		);

		$sql = "SELECT COUNT(*) AS cnt FROM (({$part_actions}) UNION ({$part_logs})) AS x";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL parts are already prepared above
		$count = $wpdb->get_var( $sql );
		return is_null( $count ) ? null : (int) $count;
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Get and cache the group_id for the 'dio-cron' Action Scheduler group.
	 *
	 * @return int|null
	 */
	private function get_dio_cron_group_id() {
		static $cached = null;
		if ( null !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$table_groups = $wpdb->base_prefix . 'actionscheduler_groups';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- ActionScheduler group lookup using base_prefix for table name
		$cached = (int) $wpdb->get_var( $wpdb->prepare( "SELECT group_id FROM {$table_groups} WHERE slug = %s LIMIT 1", 'dio-cron' ) );
		if ( $cached <= 0 ) {
			$cached = null;
		}
		return $cached;
	}
}

// phpcs:enable WordPress.Files.FileName.InvalidClassFileName
