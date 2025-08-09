<?php
/**
 * DIO Cron Queue Manager Class
 *
 * @package DIO_Cron
 */

namespace Soderlind\Multisite\Cron;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Queue Manager class for handling Action Scheduler integration
 */
class DIO_Cron_Queue_Manager {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Constructor is kept empty for now.
	}

	/**
	 * Enqueue cron jobs for all sites in the network
	 *
	 * @return array
	 */
	public function enqueue_all_sites() {
		if ( ! is_multisite() ) {
			return DIO_Cron_Utilities::create_multisite_error();
		}

		$start_time = microtime( true );
		$sites      = $this->get_sites();

		if ( is_wp_error( $sites ) ) {
			return DIO_Cron_Utilities::create_error_response( $sites->get_error_message() );
		}

		if ( empty( $sites ) ) {
			return DIO_Cron_Utilities::create_no_sites_error();
		}

		$queued_count = 0;
		$errors       = [];

		foreach ( (array) $sites as $site ) {
			$result = $this->enqueue_site_cron_job( $site );
			if ( is_wp_error( $result ) ) {
				$errors[] = DIO_Cron_Utilities::format_site_error(
					$site->siteurl,
					'Error queuing %1$s: %2$s',
					$result->get_error_message()
				);
			} else {
				++$queued_count;
			}
		}

		$end_time       = microtime( true );
		$execution_time = DIO_Cron_Utilities::calculate_execution_time( $start_time, $end_time );

		if ( ! empty( $errors ) ) {
			return DIO_Cron_Utilities::create_error_response( implode( "\n", $errors ) );
		}

		return DIO_Cron_Utilities::create_success_response(
			sprintf(
				/* translators: %d: number of sites queued for cron processing */
				esc_html__( 'Queued %d sites for cron processing', 'dio-cron' ),
				intval( $queued_count )
			),
			$queued_count,
			$execution_time
		);
	}

	/**
	 * Enqueue a cron job for a single site
	 *
	 * @param object $site Site object.
	 * @return true|\WP_Error
	 */
	public function enqueue_site_cron_job( $site ) {
		if ( ! DIO_Cron_Utilities::action_scheduler_function_exists( 'as_enqueue_async_action' ) ) {
			return DIO_Cron_Utilities::create_action_scheduler_error();
		}

		$site_data = [
			'site_id'  => $site->blog_id,
			'site_url' => $site->siteurl,
		];

		$action_id = as_enqueue_async_action(
			DIO_Cron_Utilities::PROCESS_SITE_HOOK,
			$site_data,
			'dio-cron'
		);

		if ( ! $action_id ) {
			return new \WP_Error( 'queue_failed', esc_html__( 'Failed to queue action', 'dio-cron' ) );
		}

		return true;
	}

	/**
	 * Get all sites that should have cron run
	 *
	 * @return array|\WP_Error
	 */
	private function get_sites() {
		return DIO_Cron_Utilities::get_cached_sites();
	}

	/**
	 * Schedule a recurring job to run all sites
	 *
	 * @param int $frequency Frequency in seconds.
	 * @return bool|\WP_Error
	 */
	public function schedule_recurring_job( int $frequency = HOUR_IN_SECONDS ) {
		if ( ! DIO_Cron_Utilities::action_scheduler_function_exists( 'as_schedule_recurring_action' ) ) {
			return DIO_Cron_Utilities::create_action_scheduler_error();
		}

		// Unschedule any existing recurring job.
		$this->unschedule_recurring_job();

		$action_id = as_schedule_recurring_action(
			time(),
			$frequency,
			DIO_Cron_Utilities::RUN_ALL_SITES_HOOK,
			[],
			'dio-cron'
		);

		if ( ! $action_id ) {
			return new \WP_Error( 'schedule_failed', esc_html__( 'Failed to schedule recurring action', 'dio-cron' ) );
		}

		return true;
	}

	/**
	 * Unschedule the recurring job
	 *
	 * @return void
	 */
	public function unschedule_recurring_job() {
		if ( DIO_Cron_Utilities::action_scheduler_function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( DIO_Cron_Utilities::RUN_ALL_SITES_HOOK, [], 'dio-cron' );
		}
	}

	/**
	 * Get queue status information
	 *
	 * @return array
	 */
	public function get_queue_status() {
		if ( ! DIO_Cron_Utilities::is_action_scheduler_available() ) {
			return DIO_Cron_Utilities::get_action_scheduler_error();
		}

		$pending = as_get_scheduled_actions(
			DIO_Cron_Utilities::get_action_scheduler_query_args( DIO_Cron_Utilities::PROCESS_SITE_HOOK, 'pending' )
		);

		$in_progress = as_get_scheduled_actions(
			DIO_Cron_Utilities::get_action_scheduler_query_args( DIO_Cron_Utilities::PROCESS_SITE_HOOK, 'in-progress' )
		);

		$failed = as_get_scheduled_actions(
			DIO_Cron_Utilities::get_action_scheduler_query_args( DIO_Cron_Utilities::PROCESS_SITE_HOOK, 'failed', 10 )
		);

		return [
			'pending'        => count( $pending ),
			'in_progress'    => count( $in_progress ),
			'failed'         => count( $failed ),
			'failed_actions' => $failed,
		];
	}

	/**
	 * Clear all queued actions
	 *
	 * @return bool
	 */
	public function clear_queue() {
		if ( ! DIO_Cron_Utilities::action_scheduler_function_exists( 'as_unschedule_all_actions' ) ) {
			return false;
		}

		as_unschedule_all_actions( DIO_Cron_Utilities::PROCESS_SITE_HOOK, [], 'dio-cron' );
		return true;
	}

	/**
	 * Remove the create_error_response method since we're using utilities
	 */

	/**
	 * Get batch size for processing
	 *
	 * @return int
	 */
	public function get_batch_size() {
		return apply_filters( 'dio_cron_batch_size', 25 );
	}
}
