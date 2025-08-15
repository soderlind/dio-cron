<?php
/**
 * DIO Cron Admin Interface
 *
 * @package DIO_Cron
 */

namespace Soderlind\Multisite\Cron;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Admin interface class for DIO Cron
 */
class DIO_Cron_Admin {

	/**
	 * The page hook for the main admin page
	 *
	 * @var string
	 */
	private $page_hook = '';

	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'network_admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'network_admin_init', [ $this, 'handle_admin_actions' ] );

		// Also add admin_menu hook as fallback.
		add_action( 'admin_menu', [ $this, 'add_admin_menu_fallback' ] );
		add_action( 'admin_init', [ $this, 'handle_admin_actions' ] );

		// Load admin styles and scripts.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

		// Debug screen info.
		add_action( 'admin_head', [ $this, 'debug_screen_info' ] );

		// Optimize Action Scheduler for DIO Cron usage.
		$this->setup_action_scheduler_optimizations();

		// Customize Action Scheduler admin integration for multisite.
		$this->setup_action_scheduler_admin_integration();
	}

	/**
	 * Setup Action Scheduler optimizations for DIO Cron
	 *
	 * @return void
	 */
	private function setup_action_scheduler_optimizations() {
		// Prevent concurrent processing conflicts - only allow 1 concurrent batch.
		add_filter(
			'action_scheduler_queue_runner_concurrent_batches',
			function () {
				return 1; // Default is 5.
			}
		);

		// Optimize processing time limit for cron operations.
		add_filter(
			'action_scheduler_queue_runner_time_limit',
			function () {
				return 30; // Default is 30, keeping same but being explicit.
			}
		);

		// Reduce batch size for better memory management.
		add_filter(
			'action_scheduler_queue_runner_batch_size',
			function () {
				return 5; // Default is 25, reduce for multisite.
			}
		);
	}

	/**
	 * Setup Action Scheduler admin integration for multisite
	 *
	 * @return void
	 */
	private function setup_action_scheduler_admin_integration() {
		// Prevent Action Scheduler from adding its menu in individual subsites when we're in multisite.
		if ( is_multisite() ) {
			add_action( 'admin_menu', [ $this, 'remove_action_scheduler_subsite_menu' ], 20 );
		}

		// Add Action Scheduler as submenu to DIO Cron in network admin.
		add_action( 'network_admin_menu', [ $this, 'add_action_scheduler_network_submenu' ], 15 );
	}

	/**
	 * Remove Action Scheduler menu from individual subsites in multisite
	 *
	 * @return void
	 */
	public function remove_action_scheduler_subsite_menu() {
		if ( ! is_network_admin() ) {
			remove_submenu_page( 'tools.php', 'action-scheduler' );
		}
	}

	/**
	 * Add Action Scheduler as submenu in network admin under DIO Cron
	 *
	 * @return void
	 */
	public function add_action_scheduler_network_submenu() {
		if ( class_exists( '\ActionScheduler_AdminView' ) ) {
			add_submenu_page(
				'dio-cron-status',
				esc_html__( 'Scheduled Actions', 'dio-cron' ),
				esc_html__( 'Scheduled Actions', 'dio-cron' ),
				'manage_network_options',
				'action-scheduler',
				[ $this, 'render_action_scheduler_page' ]
			);
		}
	}

	/**
	 * Render Action Scheduler page in network admin context
	 *
	 * @return void
	 */
	public function render_action_scheduler_page() {
		if ( class_exists( '\ActionScheduler_AdminView' ) ) {
			$admin_view = \ActionScheduler_AdminView::instance();
			if ( method_exists( $admin_view, 'render_admin_ui' ) ) {
				$admin_view->render_admin_ui();
			}
		}
	}

	/**
	 * Enqueue admin styles and scripts
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		// Only load on our admin pages.
		if ( strpos( $hook_suffix, 'dio-cron' ) === false ) {
			return;
		}

		// Enqueue admin styles.
		wp_enqueue_style(
			'dio-cron-admin',
			plugin_dir_url( __DIR__ ) . 'css/admin-styles.css',
			[],
			DIO_Cron::VERSION
		);
	}

	/**
	 * Get the page hook for the admin page
	 *
	 * @return string
	 */
	public function get_page_hook() {
		return $this->page_hook;
	}

	/**
	 * Add admin menu
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		// Add as a top-level menu item in network admin.
		$this->page_hook = add_menu_page(
			esc_html__( 'DIO Cron', 'dio-cron' ),
			esc_html__( 'DIO Cron', 'dio-cron' ),
			'manage_network_options',
			'dio-cron-status',
			[ $this, 'render_status_page' ],
			'dashicons-clock',
			30
		);

		// Add contextual help for this specific page.
		if ( $this->page_hook ) {
			add_action( 'load-' . $this->page_hook, [ $this, 'add_contextual_help' ] );
		}
	}

	/**
	 * Add admin menu fallback for regular admin
	 *
	 * @return void
	 */
	public function add_admin_menu_fallback() {
		// Only add if we're in a multisite environment and user can manage network.
		if ( is_multisite() && current_user_can( 'manage_network_options' ) ) {
			$hook = add_submenu_page(
				'tools.php',
				esc_html__( 'DIO Cron', 'dio-cron' ),
				esc_html__( 'DIO Cron', 'dio-cron' ),
				'manage_network_options',
				'dio-cron-status',
				[ $this, 'render_status_page' ]
			);

			// Add contextual help for the fallback page too.
			if ( $hook ) {
				add_action( 'load-' . $hook, [ $this, 'add_contextual_help' ] );
			}
		}
	}

	/**
	 * Handle admin actions
	 *
	 * @return void
	 */
	public function handle_admin_actions() {
		// Check if we're on the right page.
		if ( ! isset( $_REQUEST[ 'page' ] ) || 'dio-cron-status' !== $_REQUEST[ 'page' ] ) {
			return;
		}

		// Check if this is a POST request with an action.
		if ( ! isset( $_POST[ 'action' ] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_network_options' ) ) {
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_POST[ 'action' ] ?? '' ) );
		$nonce  = sanitize_text_field( wp_unslash( $_POST[ '_wpnonce' ] ?? '' ) );

		if ( ! wp_verify_nonce( $nonce, 'dio_cron_admin' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'dio-cron' ) );
		}

		$plugin         = DIO_Cron::get_instance();
		$queue_manager  = $plugin->get_queue_manager();
		$site_processor = $plugin->get_site_processor();

		switch ( $action ) {
			case 'queue_all_sites':
				$result = $queue_manager->enqueue_all_sites();
				$this->add_admin_notice(
					$result[ 'success' ] ? 'success' : 'error',
					$result[ 'message' ]
				);
				break;

			case 'clear_queue':
				$queue_manager->clear_queue();
				$this->add_admin_notice( 'success', esc_html__( 'Queue cleared successfully', 'dio-cron' ) );
				break;

			case 'schedule_recurring':
				$frequency = intval( $_POST[ 'frequency' ] ?? HOUR_IN_SECONDS ); // Default 1 hour.
				$result = $queue_manager->schedule_recurring_job( $frequency );
				if ( is_wp_error( $result ) ) {
					$this->add_admin_notice( 'error', $result->get_error_message() );
				} else {
					$this->add_admin_notice( 'success', esc_html__( 'Recurring job scheduled successfully', 'dio-cron' ) );
				}
				break;

			case 'unschedule_recurring':
				$queue_manager->unschedule_recurring_job();
				$this->add_admin_notice( 'success', esc_html__( 'Recurring job unscheduled successfully', 'dio-cron' ) );
				break;

			case 'flush_rewrite_rules':
				// Force a complete rewrite rules regeneration.
				delete_option( 'rewrite_rules' );
				$plugin->init_rewrite_rules();
				flush_rewrite_rules( true ); // Force hard flush.
				$this->add_admin_notice( 'success', esc_html__( 'Rewrite rules completely regenerated. Permalinks should now work.', 'dio-cron' ) );
				break;

			case 'test_individual_site':
				$site_url = sanitize_url( wp_unslash( $_POST[ 'site_url' ] ?? '' ) );
				if ( ! empty( $site_url ) ) {
					// Find the site that matches this URL from our multisite network.
					$site  = null;
					$sites = DIO_Cron_Utilities::get_cached_sites( 500 );

					if ( is_wp_error( $sites ) ) {
						/* translators: %s: Error message */
						$this->add_admin_notice( 'error', sprintf( esc_html__( 'Failed to retrieve sites: %s', 'dio-cron' ), $sites->get_error_message() ) );
						break;
					}

					foreach ( $sites as $potential_site ) {
						if ( $potential_site->siteurl === $site_url ) {
							$site = $potential_site;
							break;
						}
					}

					if ( $site ) {
						$result = $site_processor->trigger_site_cron( $site_url );
						if ( is_wp_error( $result ) ) {
							$this->add_admin_notice(
								'error',
								sprintf(
									/* translators: 1: site URL, 2: error message */
									esc_html__( 'Site %1$s test failed: %2$s', 'dio-cron' ),
									esc_url( $site_url ),
									$result->get_error_message()
								)
							);
						} else {
							$this->add_admin_notice(
								'success',
								sprintf(
									/* translators: 1: site URL, 2: response code, 3: execution time */
									esc_html__( 'Site %1$s test successful! Response code: %2$d (%3$.2fs)', 'dio-cron' ),
									esc_url( $site_url ),
									$result[ 'response_code' ] ?? 'N/A',
									$result[ 'execution_time' ] ?? 0
								)
							);
						}
					} else {
						/* translators: %s: Site URL */
						$this->add_admin_notice( 'error', sprintf( esc_html__( 'Site with URL %s not found in this multisite network.', 'dio-cron' ), esc_url( $site_url ) ) );
					}
				} else {
					$this->add_admin_notice( 'error', esc_html__( 'Please select a site URL to test.', 'dio-cron' ) );
				}
				break;

			case 'force_process_queue':
				// Manually trigger queue processing.
				if ( DIO_Cron_Utilities::is_action_scheduler_available() ) {
					// Get pending actions.
					$pending_actions = as_get_scheduled_actions(
						[ 
							'hook'     => 'dio_cron_process_site',
							'status'   => 'pending',
							'per_page' => 5, // Process just a few for testing.
						]
					);

					if ( ! empty( $pending_actions ) ) {
						$processed = 0;
						foreach ( $pending_actions as $action ) {
							// Trigger the action manually.
							do_action( 'dio_cron_process_site', $action->get_args()[ 0 ] ?? '' );
							++$processed;
						}
						/* translators: %d: Number of actions processed */
						$this->add_admin_notice( 'success', sprintf( esc_html__( 'Manually processed %d pending actions', 'dio-cron' ), $processed ) );
					} else {
						$this->add_admin_notice( 'info', esc_html__( 'No pending actions found to process', 'dio-cron' ) );
					}
				} else {
					$this->add_admin_notice( 'error', esc_html__( 'Action Scheduler not available', 'dio-cron' ) );
				}
				break;

			case 'generate_token':
				$new_token = DIO_Cron_Utilities::generate_secure_token();
				if ( DIO_Cron_Utilities::set_endpoint_token( $new_token ) ) {
					$this->add_admin_notice( 'success', esc_html__( 'New endpoint token generated successfully', 'dio-cron' ) );
				} else {
					$this->add_admin_notice( 'error', esc_html__( 'Failed to save new token', 'dio-cron' ) );
				}
				break;

			case 'update_token':
				$custom_token = sanitize_text_field( wp_unslash( $_POST[ 'custom_token' ] ?? '' ) );
				if ( ! empty( $custom_token ) ) {
					if ( strlen( $custom_token ) < 16 ) {
						$this->add_admin_notice( 'error', esc_html__( 'Token must be at least 16 characters long', 'dio-cron' ) );
					} elseif ( DIO_Cron_Utilities::set_endpoint_token( $custom_token ) ) {
						$this->add_admin_notice( 'success', esc_html__( 'Endpoint token updated successfully', 'dio-cron' ) );
					} else {
						$this->add_admin_notice( 'error', esc_html__( 'Failed to save token', 'dio-cron' ) );
					}
				} else {
					$this->add_admin_notice( 'error', esc_html__( 'Please provide a token', 'dio-cron' ) );
				}
				break;

			case 'delete_token':
				if ( DIO_Cron_Utilities::set_endpoint_token( '' ) ) {
					$this->add_admin_notice( 'warning', esc_html__( 'Endpoint token deleted. Cron endpoint is now disabled!', 'dio-cron' ) );
				} else {
					$this->add_admin_notice( 'error', esc_html__( 'Failed to delete token', 'dio-cron' ) );
				}
				break;

			case 'toggle_logging':
				// Only allow toggling if WP_DEBUG is enabled.
				if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
					$this->add_admin_notice( 'error', esc_html__( 'Detailed logging requires WP_DEBUG to be enabled in wp-config.php', 'dio-cron' ) );
					break;
				}

				$current_setting = get_option( 'dio_cron_detailed_logging', false );
				$new_setting = ! $current_setting;
				update_option( 'dio_cron_detailed_logging', $new_setting );

				if ( $new_setting ) {
					$this->add_admin_notice( 'success', esc_html__( 'Detailed logging enabled. Check your error logs for DIO Cron activity.', 'dio-cron' ) );
				} else {
					$this->add_admin_notice( 'success', esc_html__( 'Detailed logging disabled.', 'dio-cron' ) );
				}
				break;

			default:
				break;
		}

		// Redirect to prevent resubmission.
		$request_uri  = isset( $_SERVER[ 'REQUEST_URI' ] ) ? esc_url_raw( wp_unslash( $_SERVER[ 'REQUEST_URI' ] ) ) : '';
		$redirect_url = remove_query_arg( [ 'action', '_wpnonce' ], $request_uri );
		$redirect_url = add_query_arg( 'updated', '1', $redirect_url );
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Render the status page
	 *
	 * @return void
	 */
	public function render_status_page() {
		$plugin         = DIO_Cron::get_instance();
		$queue_manager  = $plugin->get_queue_manager();
		$site_processor = $plugin->get_site_processor();

		$queue_status     = $queue_manager->get_queue_status();
		$processing_stats = $site_processor->get_processing_stats();

		?>
		<div class="wrap dio-cron-admin-page">
			<h1><?php esc_html_e( 'DIO Cron', 'dio-cron' ); ?></h1>

			<?php $this->render_admin_notices(); ?>

			<?php if ( ! $this->is_wp_cron_properly_disabled() ) : ?>
				<div class="notice notice-warning is-dismissible">
					<h3><?php esc_html_e( 'WordPress Cron Configuration Warning', 'dio-cron' ); ?></h3>
					<p>
						<strong><?php esc_html_e( 'DISABLE_WP_CRON is not set to true.', 'dio-cron' ); ?></strong>
						<?php esc_html_e( 'For optimal performance and to prevent conflicts, WordPress\'s built-in cron should be disabled when using DIO Cron with Action Scheduler.', 'dio-cron' ); ?>
					</p>
					<p>
						<?php esc_html_e( 'Having both systems running can cause:', 'dio-cron' ); ?>
					</p>
					<ul style="margin-left: 20px;">
						<li><?php esc_html_e( 'Duplicate cron job executions', 'dio-cron' ); ?></li>
						<li><?php esc_html_e( 'Increased server load and memory usage', 'dio-cron' ); ?></li>
						<li><?php esc_html_e( 'Unpredictable cron job scheduling', 'dio-cron' ); ?></li>
						<li><?php esc_html_e( 'Potential race conditions between systems', 'dio-cron' ); ?></li>
					</ul>
					<p>
						<?php esc_html_e( 'To fix this, add the following line to your wp-config.php file:', 'dio-cron' ); ?>
					</p>
					<p>
						<code style="background: #f1f1f1; padding: 4px 8px; font-family: 'Courier New', monospace;">
																																																																																																				define( 'DISABLE_WP_CRON', true );
																																																																																																			</code>
					</p>
					<p>
						<strong><?php esc_html_e( 'Important:', 'dio-cron' ); ?></strong>
						<?php esc_html_e( 'After adding this constant, set up a system cron job to trigger the DIO Cron endpoint instead of wp-cron.php. Look in the Endpoints pane for the URL to /dio-cron for optimal multisite cron management.', 'dio-cron' ); ?>
					</p>
				</div>
			<?php endif; ?>

			<div class="dio-cron-main-content">
				<div class="postbox">
					<h2 class="hndle"><?php esc_html_e( 'Queue Status', 'dio-cron' ); ?></h2>
					<div class="inside">
						<?php if ( isset( $queue_status[ 'error' ] ) ) : ?>
							<p class="description dio-cron-error"><?php echo esc_html( $queue_status[ 'error' ] ); ?></p>
						<?php else : ?>
							<table class="widefat dio-cron-stats-table">
								<tbody>
									<tr>
										<td><strong><?php esc_html_e( 'Pending Actions', 'dio-cron' ); ?></strong></td>
										<td><?php echo intval( $queue_status[ 'pending' ] ); ?></td>
									</tr>
									<tr>
										<td><strong><?php esc_html_e( 'In Progress', 'dio-cron' ); ?></strong></td>
										<td><?php echo intval( $queue_status[ 'in_progress' ] ); ?></td>
									</tr>
									<tr>
										<td><strong><?php esc_html_e( 'Failed Actions', 'dio-cron' ); ?></strong></td>
										<td><?php echo intval( $queue_status[ 'failed' ] ); ?></td>
									</tr>
								</tbody>
							</table>
						<?php endif; ?>
					</div>
				</div>

				<div class="postbox">
					<h2 class="hndle"><?php esc_html_e( 'Processing Statistics (Today)', 'dio-cron' ); ?></h2>
					<div class="inside">
						<?php if ( isset( $processing_stats[ 'error' ] ) ) : ?>
							<p class="description dio-cron-error"><?php echo esc_html( $processing_stats[ 'error' ] ); ?></p>
						<?php else : ?>
							<table class="widefat dio-cron-stats-table">
								<tbody>
									<tr>
										<td><strong><?php esc_html_e( 'Completed', 'dio-cron' ); ?></strong></td>
										<td><?php echo intval( $processing_stats[ 'completed_today' ] ); ?></td>
									</tr>
									<tr>
										<td><strong><?php esc_html_e( 'Failed', 'dio-cron' ); ?></strong></td>
										<td><?php echo intval( $processing_stats[ 'failed_today' ] ); ?></td>
									</tr>
									<tr>
										<td><strong><?php esc_html_e( 'Success Rate', 'dio-cron' ); ?></strong></td>
										<td><?php echo number_format( $processing_stats[ 'success_rate' ], 1 ); ?>%</td>
									</tr>
								</tbody>
							</table>
						<?php endif; ?>
					</div>
				</div>

				<div class="postbox">
					<h2 class="hndle"><?php esc_html_e( 'Quick Actions', 'dio-cron' ); ?></h2>
					<div class="inside">
						<div class="dio-cron-actions">
							<form method="post">
								<?php wp_nonce_field( 'dio_cron_admin' ); ?>
								<input type="hidden" name="action" value="queue_all_sites">
								<input type="submit" class="button button-primary"
									value="<?php esc_html_e( 'Queue All Sites Now', 'dio-cron' ); ?>">
							</form>

							<form method="post">
								<?php wp_nonce_field( 'dio_cron_admin' ); ?>
								<input type="hidden" name="action" value="clear_queue">
								<input type="submit" class="button button-secondary"
									value="<?php esc_html_e( 'Clear Queue', 'dio-cron' ); ?>"
									onclick="return confirm('<?php esc_html_e( 'Are you sure you want to clear the queue?', 'dio-cron' ); ?>')">
							</form>

							<form method="post">
								<?php wp_nonce_field( 'dio_cron_admin' ); ?>
								<input type="hidden" name="action" value="flush_rewrite_rules">
								<input type="submit" class="button button-secondary"
									value="<?php esc_html_e( 'Fix Permalinks', 'dio-cron' ); ?>"
									title="<?php esc_html_e( 'Flush rewrite rules to fix the /dio-cron endpoint', 'dio-cron' ); ?>">
							</form>

							<form method="post">
								<?php wp_nonce_field( 'dio_cron_admin' ); ?>
								<input type="hidden" name="action" value="force_process_queue">
								<input type="submit" class="button button-secondary"
									value="<?php esc_html_e( 'Force Process Queue', 'dio-cron' ); ?>"
									title="<?php esc_html_e( 'Manually process pending queue items for testing', 'dio-cron' ); ?>">
							</form>

							<a href="<?php echo esc_url( network_admin_url( 'admin.php?page=action-scheduler' ) ); ?>"
								class="button">
								<?php esc_html_e( 'View Action Scheduler', 'dio-cron' ); ?>
							</a>

							<?php if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) : ?>
								<div style="margin-top: 10px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
									<form method="post" style="margin: 0; display: inline-block;">
										<?php wp_nonce_field( 'dio_cron_admin' ); ?>
										<input type="hidden" name="action" value="toggle_logging">
										<?php $logging_enabled = get_option( 'dio_cron_detailed_logging', false ); ?>
										<input type="submit" class="button button-secondary"
											value="<?php echo $logging_enabled ? esc_html__( 'Disable Detailed Logging', 'dio-cron' ) : esc_html__( 'Enable Detailed Logging', 'dio-cron' ); ?>"
											title="<?php esc_html_e( 'Toggle detailed logging to error log for debugging wp-cron triggers', 'dio-cron' ); ?>"
											style="vertical-align: middle;">
									</form>
									<?php $logging_enabled = get_option( 'dio_cron_detailed_logging', false ); ?>
									<?php if ( $logging_enabled ) : ?>
										<span
											style="color: #46b450; font-weight: bold; font-size: 13px; white-space: nowrap; vertical-align: middle; display: inline-block;">
											<?php esc_html_e( 'Logging: ON', 'dio-cron' ); ?>
										</span>
									<?php else : ?>
										<span
											style="color: #dc3232; font-weight: bold; font-size: 13px; white-space: nowrap; vertical-align: middle; display: inline-block;">
											<?php esc_html_e( 'Logging: OFF', 'dio-cron' ); ?>
										</span>
									<?php endif; ?>
								</div>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<div class="postbox">
					<h2 class="hndle"><?php esc_html_e( 'Site Diagnostics', 'dio-cron' ); ?></h2>
					<div class="inside">
						<p><?php esc_html_e( 'Test individual sites for connectivity issues:', 'dio-cron' ); ?></p>

						<form method="post" class="dio-cron-form-row">
							<?php wp_nonce_field( 'dio_cron_admin' ); ?>
							<input type="hidden" name="action" value="test_individual_site">
							<div class="dio-cron-form-row">
								<div class="dio-cron-field-group">
									<label for="site_url"><?php esc_html_e( 'Select Site:', 'dio-cron' ); ?></label>
									<select name="site_url" id="site_url" required class="dio-cron-select">
										<option value=""><?php esc_html_e( 'Choose a site to test...', 'dio-cron' ); ?></option>
										<?php
										$sites = DIO_Cron_Utilities::get_cached_sites( 500 ); // Get more sites for the dropdown.
										if ( is_wp_error( $sites ) ) {
											$sites = [];
										}
										foreach ( $sites as $site ) :
											$site_url  = esc_url( $site->siteurl );
											$site_name = get_blog_details( $site->blog_id )->blogname;
											$site_name = $site_name ? $site_name : wp_parse_url( $site_url, PHP_URL_HOST );
											?>
											<option value="<?php echo esc_attr( $site_url ); ?>">
												<?php echo esc_html( $site_name ); ?> (<?php echo esc_html( $site_url ); ?>)
											</option>
										<?php endforeach; ?>
									</select>
								</div>
								<input type="submit" class="button button-primary"
									value="<?php esc_html_e( 'Test Site', 'dio-cron' ); ?>">
							</div>
						</form>
					</div>
				</div>
			</div>

			<div class="dio-cron-sidebar">
				<div class="postbox">
					<h2 class="hndle"><?php esc_html_e( 'Endpoints', 'dio-cron' ); ?></h2>
					<div class="inside">
						<?php
						$current_token = DIO_Cron_Utilities::get_endpoint_token();
						$token_param   = $current_token ? '?token=' . rawurlencode( $current_token ) : '';
						?>

						<?php if ( ! $current_token ) : ?>
							<div class="notice notice-warning inline">
								<p><strong><?php esc_html_e( 'Token Required!', 'dio-cron' ); ?></strong><br>
									<?php esc_html_e( 'Generate a token in the Security Status section to enable endpoints.', 'dio-cron' ); ?>
								</p>
							</div>
						<?php endif; ?>

						<p><strong><?php esc_html_e( 'Action Scheduler Queue:', 'dio-cron' ); ?></strong></p>
						<?php if ( $current_token ) : ?>
							<a href="<?php echo esc_url( home_url( '/dio-cron' . $token_param ) ); ?>" target="_blank"
								rel="noopener noreferrer" class="dio-cron-endpoint-link">
								<?php echo esc_url( home_url( '/dio-cron' . $token_param ) ); ?>
							</a>
						<?php else : ?>
							<code class="dio-cron-disabled-endpoint">
																																																																													<?php echo esc_url( home_url( '/dio-cron?token=YOUR_TOKEN_HERE' ) ); ?>
																																																																												</code>
						<?php endif; ?>

						<p><strong><?php esc_html_e( 'Legacy Mode:', 'dio-cron' ); ?></strong></p>
						<?php if ( $current_token ) : ?>
							<a href="<?php echo esc_url( home_url( '/dio-cron?immediate=1&token=' . rawurlencode( $current_token ) ) ); ?>"
								target="_blank" rel="noopener noreferrer" class="dio-cron-endpoint-link">
								<?php echo esc_url( home_url( '/dio-cron?immediate=1&token=' . rawurlencode( $current_token ) ) ); ?>
							</a>
						<?php else : ?>
							<code class="dio-cron-disabled-endpoint">
																																																																													<?php echo esc_url( home_url( '/dio-cron?immediate=1&token=YOUR_TOKEN_HERE' ) ); ?>
																																																																												</code>
						<?php endif; ?>

						<p><strong><?php esc_html_e( 'GitHub Actions:', 'dio-cron' ); ?></strong></p>
						<?php if ( $current_token ) : ?>
							<a href="<?php echo esc_url( home_url( '/dio-cron?ga&token=' . rawurlencode( $current_token ) ) ); ?>"
								target="_blank" rel="noopener noreferrer" class="dio-cron-endpoint-link">
								<?php echo esc_url( home_url( '/dio-cron?ga&token=' . rawurlencode( $current_token ) ) ); ?>
							</a>
						<?php else : ?>
							<code class="dio-cron-disabled-endpoint">
																																																																													<?php echo esc_url( home_url( '/dio-cron?ga&token=YOUR_TOKEN_HERE' ) ); ?>
																																																																												</code>
						<?php endif; ?>
					</div>
				</div>

				<div class="postbox">
					<h2 class="hndle"><?php esc_html_e( 'Security Status', 'dio-cron' ); ?></h2>
					<div class="inside">
						<?php $this->render_security_status(); ?>
					</div>
				</div>

				<div class="postbox">
					<h2 class="hndle"><?php esc_html_e( 'Recurring Jobs', 'dio-cron' ); ?></h2>
					<div class="inside">
						<div class="dio-cron-recurring-status">
							<?php
							$recurring_status = $this->get_recurring_job_status();
							if ( $recurring_status[ 'active' ] ) :
								?>
								<div class="dio-cron-status dio-cron-status-success">
									<p><strong><?php esc_html_e( 'Recurring job is active', 'dio-cron' ); ?></strong></p>
									<p><?php esc_html_e( 'Next run:', 'dio-cron' ); ?>
										<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $recurring_status[ 'next_run' ] ) ); ?>
									</p>
									<?php if ( $recurring_status[ 'frequency' ] ) : ?>
										<div class="dio-cron-job-details">
											<p><?php esc_html_e( 'Frequency:', 'dio-cron' ); ?>
												<?php echo esc_html( $recurring_status[ 'frequency' ] ); ?>
												<?php esc_html_e( 'seconds', 'dio-cron' ); ?>
											</p>
										</div>
									<?php endif; ?>
								</div>
							<?php else : ?>
								<div class="dio-cron-status dio-cron-status-warning">
									<p><strong><?php esc_html_e( 'No recurring job scheduled', 'dio-cron' ); ?></strong></p>
								</div>
							<?php endif; ?>
						</div>

						<div class="dio-cron-form-row">
							<form method="post">
								<?php wp_nonce_field( 'dio_cron_admin' ); ?>
								<input type="hidden" name="action" value="schedule_recurring">
								<div class="dio-cron-field-group">
									<label for="frequency"><?php esc_html_e( 'Frequency:', 'dio-cron' ); ?></label>
									<select name="frequency" id="frequency" required class="dio-cron-select">
										<option value=""><?php esc_html_e( 'Select frequency...', 'dio-cron' ); ?></option>
										<option value="<?php echo esc_attr( 5 * MINUTE_IN_SECONDS ); ?>" <?php selected( $recurring_status[ 'frequency' ], 5 * MINUTE_IN_SECONDS ); ?>>
											<?php esc_html_e( '5 minutes', 'dio-cron' ); ?>
										</option>
										<option value="<?php echo esc_attr( 15 * MINUTE_IN_SECONDS ); ?>" <?php selected( $recurring_status[ 'frequency' ], 15 * MINUTE_IN_SECONDS ); ?>>
											<?php esc_html_e( '15 minutes', 'dio-cron' ); ?>
										</option>
										<option value="<?php echo esc_attr( 30 * MINUTE_IN_SECONDS ); ?>" <?php selected( $recurring_status[ 'frequency' ], 30 * MINUTE_IN_SECONDS ); ?>>
											<?php esc_html_e( '30 minutes', 'dio-cron' ); ?>
										</option>
										<option value="<?php echo esc_attr( HOUR_IN_SECONDS ); ?>" <?php selected( $recurring_status[ 'frequency' ], HOUR_IN_SECONDS ); ?>>
											<?php esc_html_e( '1 hour', 'dio-cron' ); ?>
										</option>
										<option value="<?php echo esc_attr( 6 * HOUR_IN_SECONDS ); ?>" <?php selected( $recurring_status[ 'frequency' ], 6 * HOUR_IN_SECONDS ); ?>>
											<?php esc_html_e( '6 hours', 'dio-cron' ); ?>
										</option>
										<option value="<?php echo esc_attr( 12 * HOUR_IN_SECONDS ); ?>" <?php selected( $recurring_status[ 'frequency' ], 12 * HOUR_IN_SECONDS ); ?>>
											<?php esc_html_e( '12 hours', 'dio-cron' ); ?>
										</option>
										<option value="<?php echo esc_attr( DAY_IN_SECONDS ); ?>" <?php selected( $recurring_status[ 'frequency' ], DAY_IN_SECONDS ); ?>>
											<?php esc_html_e( '24 hours', 'dio-cron' ); ?>
										</option>
									</select>
								</div>
								<p>
									<input type="submit" class="button button-primary"
										value="<?php esc_html_e( 'Schedule Recurring', 'dio-cron' ); ?>">
								</p>
							</form>

							<form method="post">
								<?php wp_nonce_field( 'dio_cron_admin' ); ?>
								<input type="hidden" name="action" value="unschedule_recurring">
								<input type="submit" class="button button-secondary"
									value="<?php esc_html_e( 'Unschedule Recurring', 'dio-cron' ); ?>">
							</form>
						</div>
					</div>
				</div>

				<div class="postbox">
					<h2 class="hndle"><?php esc_html_e( 'Network-Wide DIO Cron Stats', 'dio-cron' ); ?></h2>
					<div class="inside">
						<?php
						$network_stats = \Soderlind\Multisite\Cron\DIO_Cron_Utilities::get_network_stats();
						?>
						<table class="widefat dio-cron-stats-table">
							<tbody>
								<tr>
									<td><strong><?php esc_html_e( 'Total Runs', 'dio-cron' ); ?></strong></td>
									<td><?php echo intval( $network_stats[ 'total_runs' ] ); ?></td>
								</tr>
								<tr>
									<td><strong><?php esc_html_e( 'Total Sites Processed', 'dio-cron' ); ?></strong></td>
									<td><?php echo intval( $network_stats[ 'total_sites_processed' ] ); ?></td>
								</tr>
								<tr>
									<td><strong><?php esc_html_e( 'Last Run', 'dio-cron' ); ?></strong></td>
									<td><?php echo $network_stats[ 'last_run' ] ? esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $network_stats[ 'last_run' ] ) ) : esc_html__( 'Never', 'dio-cron' ); ?>
									</td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Add admin notice - delegated to utilities
	 *
	 * @param string $type The type of notice (success, error, warning, info).
	 * @param string $message The message to display in the notice.
	 * @return void
	 */
	private function add_admin_notice( $type, $message ) {
		DIO_Cron_Utilities::add_admin_notice( $type, $message );
	}

	/**
	 * Render admin notices
	 *
	 * @return void
	 */
	private function render_admin_notices() {
		do_action( 'admin_notices' );
	}

	/**
	 * Check if WP Cron is properly disabled for DIO Cron
	 *
	 * @return bool True if DISABLE_WP_CRON is set to true, false otherwise
	 */
	private function is_wp_cron_properly_disabled() {
		return defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON === true;
	}

	/**
	 * Get current recurring job status
	 *
	 * @return array
	 */
	private function get_recurring_job_status() {
		// Check if there's a scheduled recurring job.
		$next_scheduled = as_next_scheduled_action( 'dio_cron_run_all_sites' );

		$frequency = null;
		if ( $next_scheduled ) {
			// Try to get the frequency from the scheduled action.
			$actions = as_get_scheduled_actions(
				[ 
					'hook'     => 'dio_cron_run_all_sites',
					'status'   => 'pending',
					'per_page' => 1,
				]
			);

			if ( ! empty( $actions ) ) {
				$action = reset( $actions );
				// Calculate frequency from schedule interval if it's a recurring action.
				$schedule = $action->get_schedule();
				if ( method_exists( $schedule, 'get_recurrence' ) ) {
					$frequency = $schedule->get_recurrence();
				}
			}
		}

		if ( $next_scheduled ) {
			return [ 
				'active'    => true,
				'next_run'  => $next_scheduled,
				'frequency' => $frequency,
			];
		}

		return [ 
			'active'    => false,
			'next_run'  => null,
			'frequency' => null,
		];
	}

	/**
	 * Render security status information
	 *
	 * @return void
	 */
	private function render_security_status() {
		$token_configured = DIO_Cron_Utilities::is_token_configured();
		$current_token    = DIO_Cron_Utilities::get_endpoint_token();
		$is_locked        = DIO_Cron_Utilities::is_execution_locked();

		?>
		<div class="dio-cron-security-status">
			<table class="widefat dio-cron-stats-table">
				<tbody>
					<tr>
						<td><strong><?php esc_html_e( 'Token Protection', 'dio-cron' ); ?></strong></td>
						<td>
							<?php if ( $token_configured ) : ?>
								<span class="dio-cron-status-success">
									✓ <?php esc_html_e( 'Configured', 'dio-cron' ); ?>
								</span>
							<?php else : ?>
								<span class="dio-cron-status-error">
									⚠ <?php esc_html_e( 'Required', 'dio-cron' ); ?>
								</span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Rate Limiting', 'dio-cron' ); ?></strong></td>
						<td>
							<span class="dio-cron-status-success">
								✓ <?php esc_html_e( 'Active', 'dio-cron' ); ?>
							</span>
						</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Execution Lock', 'dio-cron' ); ?></strong></td>
						<td>
							<?php if ( $is_locked ) : ?>
								<span class="dio-cron-status-warning">
									🔒 <?php esc_html_e( 'Currently locked', 'dio-cron' ); ?>
								</span>
							<?php else : ?>
								<span class="dio-cron-status-success">
									✓ <?php esc_html_e( 'Available', 'dio-cron' ); ?>
								</span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Client IP', 'dio-cron' ); ?></strong></td>
						<td>
							<code><?php echo esc_html( DIO_Cron_Utilities::get_client_ip() ); ?></code>
						</td>
					</tr>
				</tbody>
			</table>

			<!-- Token Management Section -->
			<div class="dio-cron-token-management">
				<h4><?php esc_html_e( 'Token Management', 'dio-cron' ); ?></h4>

				<?php if ( $token_configured ) : ?>
					<div class="dio-cron-token-display">
						<p><strong><?php esc_html_e( 'Current Token:', 'dio-cron' ); ?></strong></p>
						<input type="text" readonly value="<?php echo esc_attr( $current_token ); ?>" class="dio-cron-token-field"
							onclick="this.select();">
						<small><?php esc_html_e( 'Click to select and copy', 'dio-cron' ); ?></small>
					</div>

					<div class="dio-cron-token-actions">
						<form method="post" style="display: inline-block; margin-right: 10px;">
							<?php wp_nonce_field( 'dio_cron_admin' ); ?>
							<input type="hidden" name="action" value="generate_token">
							<input type="submit" class="button button-secondary"
								value="<?php esc_html_e( 'Generate New Token', 'dio-cron' ); ?>"
								onclick="return confirm('<?php esc_html_e( 'This will invalidate the current token. Update your cron calls! Continue?', 'dio-cron' ); ?>')">
						</form>

						<form method="post" style="display: inline-block;">
							<?php wp_nonce_field( 'dio_cron_admin' ); ?>
							<input type="hidden" name="action" value="delete_token">
							<input type="submit" class="button button-link-delete"
								value="<?php esc_html_e( 'Delete Token', 'dio-cron' ); ?>"
								onclick="return confirm('<?php esc_html_e( 'This will disable the endpoint! Are you sure?', 'dio-cron' ); ?>')">
						</form>
					</div>
				<?php else : ?>
					<div class="dio-cron-no-token">
						<p class="dio-cron-status-error">
							<strong><?php esc_html_e( 'No token configured!', 'dio-cron' ); ?></strong><br>
							<?php esc_html_e( 'The cron endpoint is disabled. Generate a token to enable it.', 'dio-cron' ); ?>
						</p>

						<form method="post">
							<?php wp_nonce_field( 'dio_cron_admin' ); ?>
							<input type="hidden" name="action" value="generate_token">
							<input type="submit" class="button button-primary"
								value="<?php esc_html_e( 'Generate Token', 'dio-cron' ); ?>">
						</form>
					</div>
				<?php endif; ?>

				<!-- Custom Token Form -->
				<div class="dio-cron-custom-token">
					<h5><?php esc_html_e( 'Custom Token', 'dio-cron' ); ?></h5>
					<form method="post">
						<?php wp_nonce_field( 'dio_cron_admin' ); ?>
						<input type="hidden" name="action" value="update_token">
						<div class="dio-cron-form-row">
							<input type="text" name="custom_token"
								placeholder="<?php esc_html_e( 'Enter custom token (min 16 chars)', 'dio-cron' ); ?>"
								minlength="16" required class="dio-cron-token-input">
							<input type="submit" class="button button-secondary"
								value="<?php esc_html_e( 'Set Custom Token', 'dio-cron' ); ?>">
						</div>
						<small><?php esc_html_e( 'Use a strong, random token for security', 'dio-cron' ); ?></small>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Add contextual help tabs to the DIO Cron admin page.
	 *
	 * @return void
	 */
	public function add_contextual_help() {
		$screen = get_current_screen();

		if ( ! ( $screen instanceof \WP_Screen ) ) {
			return;
		}

		// Debug: Add comment to see what screen we have.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Debug display only, not processing form data
		if ( isset( $_GET[ 'page' ] ) && 'dio-cron-status' === $_GET[ 'page' ] ) {
			echo '<!-- DIO Cron Contextual Help Debug - Screen ID: ' . esc_html( $screen->id ) . ' -->';
		}

		// Check if this is our DIO Cron page - be more flexible with screen ID matching.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page identification only, not processing form data
		if ( ! isset( $_GET[ 'page' ] ) || 'dio-cron-status' !== $_GET[ 'page' ] ) {
			return;
		}

		// Build dynamic endpoint examples with token awareness.
		$token           = DIO_Cron_Utilities::get_endpoint_token();
		$has_token       = ! empty( $token );
		$token_display   = $has_token ? rawurlencode( $token ) : 'YOUR_TOKEN_HERE';
		$base            = home_url( '/dio-cron' );
		$endpoint_main   = $base . '?token=' . $token_display;
		$endpoint_legacy = $base . '?immediate=1&token=' . $token_display;
		$endpoint_ga     = $base . '?ga&token=' . $token_display;

		$security_note = $has_token
			? esc_html__( 'All endpoints require the token parameter.', 'dio-cron' )
			: esc_html__( 'No token configured. Generate a token in Security Status to enable endpoints.', 'dio-cron' );

		// Overview tab.
		$screen->add_help_tab(
			[ 
				'id'      => 'dio_cron_help_overview',
				'title'   => esc_html__( 'Overview', 'dio-cron' ),
				'content' =>
					'<p>' . esc_html__( 'DIO Cron coordinates multisite cron execution using Action Scheduler.', 'dio-cron' ) . '</p>' .
					'<ul>' .
					'<li>' . esc_html__( 'Queue Status: shows pending, in-progress, and failed actions.', 'dio-cron' ) . '</li>' .
					'<li>' . esc_html__( 'Processing Statistics: completion counts and success rate for today.', 'dio-cron' ) . '</li>' .
					'<li>' . esc_html__( 'Quick Actions: queue all sites, clear queue, fix permalinks.', 'dio-cron' ) . '</li>' .
					'<li>' . esc_html__( 'Site Diagnostics: test a single site for connectivity.', 'dio-cron' ) . '</li>' .
					'<li>' . esc_html__( 'Security Status: token protection, rate limiting, and execution lock.', 'dio-cron' ) . '</li>' .
					'<li>' . esc_html__( 'Recurring Jobs: schedule/unschedule automated runs.', 'dio-cron' ) . '</li>' .
					'</ul>',
			]
		);

		// Endpoints // Endpoints & Security tab Security tab.
		$screen->add_help_tab(
			[ 
				'id'      => 'dio_cron_help_endpoints',
				'title'   => esc_html__( 'Endpoints & Security', 'dio-cron' ),
				'content' =>
					'<p>' . esc_html__( 'Use these endpoints from your external scheduler. Authentication with token is mandatory.', 'dio-cron' ) . '</p>' .
					'<p><strong>' . esc_html__( 'Endpoints:', 'dio-cron' ) . '</strong></p>' .
					'<ul>' .
					'<li><code>' . esc_html( $endpoint_main ) . '</code></li>' .
					'<li><code>' . esc_html( $endpoint_legacy ) . '</code> ' . esc_html__( '(Legacy immediate mode)', 'dio-cron' ) . '</li>' .
					'<li><code>' . esc_html( $endpoint_ga ) . '</code> ' . esc_html__( '(GitHub Actions output)', 'dio-cron' ) . '</li>' .
					'</ul>' .
					'<p>' . esc_html( $security_note ) . '</p>',
			]
		);

		// Queue // Queue & Scheduling tab Scheduling tab.
		$screen->add_help_tab(
			[ 
				'id'      => 'dio_cron_help_queue',
				'title'   => esc_html__( 'Queue & Scheduling', 'dio-cron' ),
				'content' =>
					'<p>' . esc_html__( 'Jobs are queued per site and processed by Action Scheduler with built-in locking to prevent concurrency.', 'dio-cron' ) . '</p>' .
					'<ul>' .
					'<li>' . esc_html__( 'Use "Queue All Sites Now" for ad-hoc processing.', 'dio-cron' ) . '</li>' .
					'<li>' . esc_html__( 'Schedule recurring runs with your preferred frequency.', 'dio-cron' ) . '</li>' .
					'<li>' . esc_html__( 'View detailed runs under Tools → Scheduled Actions.', 'dio-cron' ) . '</li>' .
					'</ul>',
			]
		);

		// Troubleshooting tab.
		$screen->add_help_tab(
			[ 
				'id'      => 'dio_cron_help_troubleshooting',
				'title'   => esc_html__( 'Troubleshooting', 'dio-cron' ),
				'content' =>
					'<p><strong>401:</strong> ' . esc_html__( 'Add ?token=YOUR_TOKEN to the URL or generate a token in Security Status.', 'dio-cron' ) . '</p>' .
					'<p><strong>429:</strong> ' . esc_html__( 'Rate limit exceeded (5 requests/5 minutes). Wait and retry.', 'dio-cron' ) . '</p>' .
					'<p><strong>409:</strong> ' . esc_html__( 'Another run is in progress. Wait up to 5 minutes.', 'dio-cron' ) . '</p>' .
					'<p>' . esc_html__( 'If /dio-cron returns 404, use Fix Permalinks or resave Settings → Permalinks.', 'dio-cron' ) . '</p>' .
					'<p><strong>' . esc_html__( 'Debugging:', 'dio-cron' ) . '</strong> ' . esc_html__( 'Enable detailed logging to track wp-cron.php requests to each site in your error log. Logging is only available when WP_DEBUG is enabled for security.', 'dio-cron' ) . '</p>',
			]
		);

		// Help sidebar with quick links.
		$screen->set_help_sidebar(
			'<p><strong>' . esc_html__( 'Quick Links', 'dio-cron' ) . '</strong></p>' .
			'<p><a href="' . esc_url( network_admin_url( 'admin.php?page=action-scheduler' ) ) . '">' . esc_html__( 'View Action Scheduler', 'dio-cron' ) . '</a></p>' .
			'<p><a href="#">' . esc_html__( 'Security Status section', 'dio-cron' ) . '</a></p>' .
			'<p><a href="#">' . esc_html__( 'Recurring Jobs section', 'dio-cron' ) . '</a></p>'
		);
	}

	/**
	 * Debug method to show screen information
	 * This helps troubleshoot why contextual help isn't showing
	 *
	 * @return void
	 */
	public function debug_screen_info() {
		// Only show on our admin page and for administrators.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page identification only, not form processing
		if ( ! isset( $_GET[ 'page' ] ) || 'dio-cron-status' !== $_GET[ 'page' ] ) {
			return;
		}

		if ( ! current_user_can( 'manage_network_options' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( $screen ) {
			echo '<!-- DIO Cron Debug - Screen ID: ' . esc_html( $screen->id ) . ', Page Hook: ' . esc_html( $this->page_hook ) . ', Base: ' . esc_html( $screen->base ?? 'N/A' ) . ' -->';
		}
	}
}
