=== DIO Cron ===
Contributors: PerS
Tags: cron, multisite, wp-cron, action-scheduler, admin-interface, security
Requires at least: 6.5
Tested up to: 6.8
Stable tag: 2.4.0
Requires PHP: 8.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Run wp-cron on all public sites in a multisite network with Action Scheduler integration, security token authentication, and comprehensive network admin interface.

== Description ==

DIO Cron triggers WordPress cron jobs across all public sites in your multisite network through external endpoints. Instead of each site running its own cron independently, this plugin coordinates everything from one place using Action Scheduler for better reliability and performance.

> "Why not just use a simple cron job?" I run a cluster of WordPress sites and tried using shell scripts with WP-CLI, but ran into race condition problems. I needed a way to run cron jobs on all sites without them overlapping. This plugin solves that problem.

= Key Benefits =

* No race conditions or overlapping cron jobs
* External trigger architecture for better reliability  
* Queue-based processing with built-in retry logic
* Comprehensive security with token authentication
* Rate limiting and execution locking
* Enhanced admin interface with real-time monitoring

= Features =

* **Network Admin Interface**: Complete admin panel at Network Admin → DIO Cron with enhanced user experience
* **Action Scheduler Integration**: Queue-based background processing for better reliability  
* **Security Token System**: Comprehensive authentication with admin interface management
* **External Trigger Architecture**: Designed for server cron, Pingdom, GitHub Actions, and monitoring services
* **Enhanced Monitoring**: Real-time queue status, processing statistics, and network-wide metrics
* **Site Diagnostics**: Test individual sites for connectivity issues with detailed error reporting
* **Contextual Help System**: Four comprehensive help tabs with troubleshooting guidance
* **WordPress Standards**: Uses WordPress time constants and proper admin patterns
* **Production Security**: WP_DEBUG-aware logging system with automatic protection


== Installation ==

1. Download [`dio-cron.zip`](https://github.com/soderlind/dio-cron/releases/latest/download/dio-cron.zip)
2. Upload via  Network > Plugins > Add New > Upload Plugin
3. Activate the plugin.
4. Go to **Network Admin → DIO Cron** to manage everything
5. **Generate a security token** in the Security Status panel
6. Disable WordPress default cron in `wp-config.php`:
`
define( 'DISABLE_WP_CRON', true );
`

= Alternative Installation Methods =

**Install via Composer:**
```
composer require soderlind/dio-cron
```

**Updates:**
Automatic updates directly from GitHub:

1. The plugin will automatically check for updates from the GitHub repository
2. Updates appear in your WordPress admin under Plugins → Updates


= Requirements =

* WordPress Multisite installation
* Super Admin access (`manage_network_options` capability)
* Action Scheduler 3.8+ installed as a separate plugin (Required Plugin: action-scheduler)

= Security Setup =

**Generate Token:**
1. Go to **Network Admin → DIO Cron**
2. In the Security Status panel, click **"Generate Token"**
3. Copy the generated token
4. Add `?token=your-token-here` to all your cron URLs

**Token Management:**
* **Generate New Token** - Creates a secure random token
* **Set Custom Token** - Use your own token (minimum 16 characters)
* **Delete Token** - Removes token and disables endpoint

**Alternative Token Configuration:**
You can also set tokens via environment variables or WordPress constants:
```bash
# Environment Variable
export DIO_CRON_TOKEN="your-secure-token-here"
```
```php
// WordPress Constant (wp-config.php)
define('DIO_CRON_TOKEN', 'your-secure-token-here');
```

= Configuration =

The plugin creates multiple endpoints for different use cases. **All endpoints require a security token:**

* `/dio-cron?token=TOKEN` - Action Scheduler queue processing (recommended)
* `/dio-cron?immediate=1&token=TOKEN` - Legacy synchronous processing  
* `/dio-cron?ga&token=TOKEN` - GitHub Actions compatible output format

= Admin Interface =

Access the comprehensive admin interface at **Network Admin → DIO Cron** for:

* **Generate and manage security tokens** for endpoint protection
* **Queue Status**: View pending, in-progress, and failed actions with real-time updates
* **Processing Statistics**: Success rates, daily completion counts, and performance metrics
* **Network-Wide Statistics**: Total runs, sites processed, and last execution tracking  
* **Quick Actions**: Manual queue management and one-click site processing with immediate feedback
* **Site Diagnostics**: Test individual sites for connectivity issues with detailed error reporting
* **Enhanced Help System**: Four comprehensive help tabs (Overview, Queue & Processing, Endpoints & Security, Troubleshooting)
* **Security Status**: Token protection, rate limiting, execution lock monitoring, and IP tracking

= Security Features =

* **Token Authentication** - All endpoints require secure tokens
* **Rate Limiting** - Maximum 5 requests per 5 minutes per IP
* **Execution Locking** - Prevents concurrent cron runs
* **Security Logging** - All access attempts are logged
* **IP Tracking** - Monitor requests by IP address



= Trigger Options =

Set up one of these external systems to trigger DIO Cron automatically. **Note: All endpoints require a security token.**

1. **External Monitoring (Recommended)**
   Services like Pingdom, UptimeRobot, or monitoring tools can ping your site every few minutes:
   
   Example URL to ping: `https://example.com/dio-cron?token=your-token-here`
   
   Extra benefit: You get notifications if the site is down.

2. **Server Cron Job**
   Add this to your server's crontab (every 5 minutes):
   
   ```
   */5 * * * * curl -s "https://example.com/dio-cron?token=your-token-here"
   ```

3. **GitHub Actions**
   Create a workflow file (every 5 minutes):
   
   ```yaml
   name: DIO Cron
   on:
     schedule:
       - cron: '*/5 * * * *'
   env:
     DIO_CRON_TOKEN: ${{ secrets.DIO_CRON_TOKEN }}
   jobs:
     trigger:
       runs-on: ubuntu-latest
       steps:
         - run: curl -s "https://example.com/dio-cron?ga&token=${{ env.DIO_CRON_TOKEN }}"
   ```

**Why External Triggers?**
DIO Cron is designed to be triggered by external systems rather than self-scheduling for better reliability, predictable timing, and integration with monitoring systems.

= Customization =

Adjust how many sites to process at once:
```php
add_filter('dio_cron_number_of_sites', function($count) {
    return 100; // Default is 200
});
```

Change request timeout:
```php
add_filter('dio_cron_request_timeout', function($seconds) {
    return 10; // Default is 15 seconds
});
```

Enable/disable SSL certificate verification for HTTP requests (default: true):
```php
add_filter('dio_cron_sslverify', function($verify, $site_url) {
  // Example: disable only for local dev domains
  if (strpos($site_url, '.local') !== false) {
    return false;
  }
  return $verify;
}, 10, 2);
```

Adjust cache duration (using WordPress time constants):
```php
add_filter('dio_cron_sites_transient', function($duration) {
    return 2 * HOUR_IN_SECONDS; // Cache for 2 hours instead of 1
});
```

Configure rate limiting (using WordPress time constants):
```php
add_filter('dio_cron_rate_limit_max_requests', function($max) {
    return 10; // Allow 10 requests instead of 5
});

add_filter('dio_cron_rate_limit_time_window', function($seconds) {
    return 10 * MINUTE_IN_SECONDS; // 10 minute window instead of 5
});
```

Customize Action Scheduler batch size:
```php
add_filter('dio_cron_batch_size', function($size) {
    return 50; // Process 50 sites per batch instead of 25
});
```

Adjust recurring schedule frequency (seconds) if you use the built-in recurring job:
```php
add_filter('dio_cron_recurring_frequency', function($seconds) {
  return 15 * MINUTE_IN_SECONDS; // run every 15 minutes
});
```

= WordPress Time Constants =

DIO Cron uses WordPress time constants for better code readability and maintainability:
* `MINUTE_IN_SECONDS` (60 seconds)
* `HOUR_IN_SECONDS` (3600 seconds) 
* `DAY_IN_SECONDS` (86400 seconds)
* `WEEK_IN_SECONDS` (604800 seconds)
* `MONTH_IN_SECONDS` (2592000 seconds)
* `YEAR_IN_SECONDS` (31536000 seconds)

These constants make timing configurations more readable and prevent calculation errors.

== Frequently Asked Questions ==

= How does the plugin work? =

The plugin hooks into a custom endpoint to run the cron job. It adds a rewrite rule and tag for the endpoint `dio-cron`. When this endpoint is accessed with a valid security token, the plugin will run wp-cron on all public sites in the multisite network using Action Scheduler for queue-based processing.

= Getting 401 Unauthorized? =

Check that you've included `?token=your-token-here` in your URL. Verify the token is correct in **Network Admin → DIO Cron → Security Status** and generate a new token if needed.

= Getting 429 Too Many Requests? =

You're hitting the rate limit (5 requests per 5 minutes). Wait a few minutes and try again, or check if multiple systems are calling the endpoint.

= The /dio-cron endpoint returns 404, what should I do? =

If the endpoint is not working:
1. Go to **Network Admin → DIO Cron** and click "Fix Permalinks"
2. Alternatively, visit **Settings → Permalinks** and click "Save Changes" 
3. Ensure your `.htaccess` file is writable and contains WordPress rewrite rules

The "Fix Permalinks" button performs a complete rewrite rules regeneration for maximum effectiveness.

= Getting 409 Conflict? =

Another cron job is already running. Wait up to 5 minutes for it to complete or check **Tools → Scheduled Actions** for stuck jobs.

= How do I monitor the plugin's performance? =

Use the comprehensive Network Admin interface at **Network Admin → DIO Cron** to view:
- Real-time queue status (pending, in-progress, failed actions)
- Processing statistics with success rates and daily metrics
- Network-wide statistics (total runs, sites processed, last execution)
- Individual site diagnostic testing with detailed error reporting

You can also monitor detailed action history at **Network Admin → DIO Cron → Scheduled Actions** (filter by group: `dio-cron`) for complete job logs and performance analysis.

= How do I enable detailed debugging? =

DIO Cron includes detailed logging for debugging wp-cron triggers, but this feature is only available when `WP_DEBUG` is enabled for security.

**Enable Debug Logging:**
1. Add to your `wp-config.php`:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```
2. Go to **Network Admin → DIO Cron**
3. Use the "Enable Detailed Logging" button in Quick Actions
4. Check `/wp-content/debug.log` for detailed request logs

**Security Note:**
- Logging controls are automatically hidden in production (`WP_DEBUG = false`)
- No debugging information is logged without explicit debug mode activation
- Protects against accidental logging in live environments

== Screenshots ==

1. No screenshots available.

== Changelog ==

= 2.4.0 =
* Enhancement: Network-Wide Stats now update when processing completes (tracked via run ID), avoiding double counting at enqueue time
* UI: Safer stats rendering with localized number formatting and human "time ago" for Last Run
* i18n: Load text domain from `languages/` and include updated `dio-cron.pot`
* Internal: Action Scheduler hook accepts 3 args (`site_id`, `site_url`, `run_id`)

= 2.3.1 =
* Fix: Resolved "Cannot modify header information" warnings by removing early echo output in load hooks and gating debug comments behind WP_DEBUG. Improves reliability of redirects in admin.

* Performance: Optimized daily stats counting by replacing UNION-based query with an index-friendly DISTINCT join on logs. Reduces temporary table creation and speeds up counts for large Action Scheduler tables.
* Internal: Minor refactors in stats path; no behavioral changes.

= 2.3.0 =
* Change: Stop bundling or Composer-loading Action Scheduler. This plugin now requires the Action Scheduler plugin to be installed and active.
* Docs: Updated requirements and removed references to bundled/vendor Action Scheduler loading.

= 2.2.21 =
* Enhancement: Bundled Action Scheduler under `lib/action-scheduler/` and updated loader to prefer `lib/` over `vendor/`, while safely skipping initialization when another plugin provides Action Scheduler.
* Docs: Clarified load order and requirements to reflect bundled Action Scheduler option.

= 2.2.20 =
* Fix: Prevented "Cannot modify header information - headers already sent" warnings by removing embedded Action Scheduler UI and adding an early redirect to the native page before any output.
* Fix: Updated all admin links to Action Scheduler from `admin.php?page=action-scheduler` to `tools.php?page=action-scheduler` to resolve the "Sorry, you are not allowed to access this page." permission error.

= 2.2.19 =
* **Security & Reliability**: SSL verification enabled by default for HTTP requests, with `dio_cron_sslverify` filter to override
* **URL Validation**: Early validation of cron URL with clear errors for invalid URLs
* **Accurate Daily Stats**: Fixed timezone mismatch by converting local day boundaries to UTC for Action Scheduler `*_gmt` queries
* **Queue Counts**: More accurate pending/in-progress counts using `'return' => 'ids'` and high `per_page`
* **Scheduling Flexibility**: New `dio_cron_recurring_frequency` filter to adjust recurring job cadence
* **Internals**: Simplified Action Scheduler date extraction and safer logging of non-2xx response bodies

= 2.2.18 =
* **Timeout Optimization**: Increased admin context timeout from 5 to 10 seconds to reduce cURL timeout errors
* **Configurable Timeouts**: Added `dio_cron_admin_timeout` filter for customizing admin operation timeouts
* **Better Balance**: Maintained faster admin response while allowing sufficient time for site responses
* **Flexible Configuration**: Developers can adjust timeout per site URL or admin context as needed

= 2.2.17 =
* **Timeout Protection**: Enhanced force processing to handle site timeouts gracefully without breaking entire operation
* **Admin Context Optimization**: Reduced timeout to 5 seconds for admin operations (vs 15s for background processing) for better UX
* **Exception Handling**: Comprehensive error handling catches both Exception and Throwable types for complete coverage
* **Process Isolation**: Force processing continues even if individual sites fail, providing detailed error reporting
* **Direct Processing**: Replaced synchronous do_action calls with direct site processor usage for better error control
* **Error Resilience**: One failing site no longer breaks entire force processing operation
* **Enhanced Logging**: Improved debugging information for timeout scenarios and admin context operations

= 2.2.16 =
* **Critical Bug Fix**: Resolved "Call to undefined method ActionScheduler_Action::get_id()" fatal error in force process queue
* **Method Compatibility**: Removed invalid call to non-existent ActionScheduler_Action::get_id() method
* **Simplified Processing**: Streamlined force process queue logic to use reliable direct action triggering
* **Enhanced Messaging**: Updated admin messages to clarify that actions are triggered manually but completion is handled by Action Scheduler
* **Debug Improvements**: Better debug logging without dependency on unsupported Action Scheduler methods
* **Error Prevention**: Eliminated reliance on non-standard Action Scheduler API methods for better compatibility

= 2.2.15 =
* **Enhanced Force Process Queue**: Fixed "Force Process Queue" to actually process pending actions instead of returning 0
* **Increased Processing Capacity**: Process up to 50 pending actions at once (previously limited to 5)
* **Action Completion**: Properly mark actions as complete in Action Scheduler after manual processing
* **Dual Format Support**: Handle both old associative array and new indexed array formats for Action Scheduler arguments
* **Enhanced Error Handling**: Added exception handling and detailed error tracking with user feedback
* **Debug Logging**: Comprehensive debug logging when WP_DEBUG is enabled for troubleshooting
* **Better Admin Messages**: Show detailed success/warning messages with processing counts and error information
* **Production Ready**: Reliable batch processing with proper cleanup and backward compatibility

= 2.2.14 =
* **Critical Bug Fix**: Resolved fatal error where string was passed to process_single_site() method expecting integer
* **Type Safety**: Fixed Action Scheduler argument type mismatch in site processing
* **Admin Interface**: Enhanced manual action processing with proper site ID validation and casting
* **Queue Manager**: Corrected argument structure to pass ordered array for Action Scheduler compatibility
* **Error Prevention**: Added validation to skip processing invalid site IDs and prevent crashes
* **Code Robustness**: Improved error resistance in multi-site cron processing workflows

= 2.2.13 =
* **Namespace Refactoring**: Updated namespace from `Soderlind\Multisite\Cron` to `Soderlind\Multisite\DioCron` for better code organization
* **Code Structure**: More logical namespace structure that reflects plugin naming convention
* **Consistent Naming**: Updated all class files and references to use new DioCron namespace
* **Quality Assurance**: All syntax checks passed and code organization improved
* **Maintainability**: Enhanced code structure for future development and maintenance

= 2.2.12 =
* **Critical Bug Fix**: Resolved "Failed to open stream: No such file or directory" error for missing functions.php file
* **Action Scheduler Integration**: Fixed ActionScheduler::init() calls when another plugin provides Action Scheduler  
* **Plugin Compatibility**: Enhanced compatibility with plugins like multisite-exporter that bundle Action Scheduler
* **Defensive Programming**: Only initialize bundled Action Scheduler when no other version is available
* **Automatic File Creation**: Creates minimal functions.php file when initializing our bundled Action Scheduler
* **Smart Detection**: Improved logic to avoid calling ActionScheduler::init() for externally provided instances
* **Error Prevention**: Prevents fatal errors caused by Action Scheduler expecting functions.php files

= 2.2.11 =
* **Enhanced Action Scheduler Conflict Prevention**: Comprehensive multi-level checks to prevent function redeclaration errors
* **ActionScheduler_Versions Detection**: Added detection for ActionScheduler_Versions class to identify existing installations
* **Improved Plugin Coexistence**: Enhanced compatibility with plugins that register Action Scheduler versions
* **Robust Loading Strategy**: Multi-layer defensive programming to prevent conflicts in complex plugin environments
* **Version Registry Awareness**: Respects Action Scheduler's version registry system for better ecosystem compatibility
* **Initialization Safety**: Added ActionScheduler::is_initialized() checks to prevent duplicate initialization

= 2.2.10 =
* **Critical Action Scheduler Conflict Fix**: Resolved fatal error when multiple plugins include Action Scheduler
* **Enhanced Compatibility**: Added defensive checks to prevent function redeclaration conflicts
* **Plugin Coexistence**: DIO Cron now works seamlessly with other plugins that bundle Action Scheduler
* **Improved Loading Logic**: Smart detection of existing Action Scheduler installations before loading bundled version
* **Defensive Programming**: Added comprehensive checks for ActionScheduler class and function existence
* **Production Safety**: Enhanced error prevention for multi-plugin environments

= 2.2.9 =
* **Critical Bug Fix**: Fixed ActionScheduler::init() fatal error by adding required file path parameter during plugin activation
* **Code Quality Audit**: Comprehensive review of all function calls to prevent similar argument count errors
* **Error Prevention**: Enhanced defensive programming with proper parameter validation throughout codebase
* **Reliability Enhancement**: Improved Action Scheduler integration stability and error handling
* **Git Conflict Resolution**: Successfully merged fix/cluster branch changes while maintaining 2.2.8 feature set
* **Branch Management**: Clean repository state with all conflicts resolved and version consistency achieved
* **Quality Assurance**: Systematic code audit confirmed no similar function call errors exist in codebase

= 2.2.8 =
* **Performance Optimizations**: Enhanced performance for large multisite networks with improved memory usage
* **Code Quality Improvements**: Refactored code for better maintainability and reliability
* **Timeout Protection**: Better handling of long-running operations with enhanced safeguards
* **Enhanced Error Handling**: Improved error recovery and resilience for better user experience
* **Security Enhancements**: Strengthened security measures and validation throughout the plugin
* **Configuration Optimization**: Improved default settings for better out-of-box experience
* **Debugging Support**: Enhanced debugging capabilities and error reporting for troubleshooting
* **Admin Interface Refinements**: Refined admin interface for improved usability and responsiveness

= 2.2.7 =
* **Admin Interface Streamlining**: Removed confusing recurring job UI elements that didn't align with external trigger architecture
* **Enhanced Contextual Help**: Updated comprehensive help system with four tabs (Overview, Queue & Processing, Endpoints & Security, Troubleshooting)
* **Admin Notice System Fix**: Robust notice persistence through POST redirects using WordPress transients
* **Site Diagnostics Enhancement**: Improved site testing functionality with better error handling and user feedback
* **Network Statistics Consistency**: Fixed "Queue All Sites Now" to properly update Network-Wide Stats
* **External Trigger Architecture**: Interface now accurately reflects dependency on external endpoint triggering (server cron, Pingdom, GitHub Actions)
* **User Experience**: Cleaner admin interface with reduced complexity and better documentation alignment
* **Quality Assurance**: PHP syntax validation and maintained WordPress coding standards throughout

= 2.2.6 =
* **Critical Bug Fix**: Network-wide statistics now update correctly with proper data integration
* **Stats Update Integration**: Fixed missing `update_network_stats()` call in cron execution flow for both queue-based and immediate processing
* **Data Accuracy**: Total Runs, Total Sites Processed, and Last Run now reflect actual cron execution activity
* **Safe Count Validation**: Stats only update when sites are actually processed to maintain accuracy
* **Execution Flow Enhancement**: Proper positioning of stats update between execution success and output/logging
* **Type Safety**: Robust count extraction with `(int) ( $result['count'] ?? 0 )` for error prevention

= 2.2.5 =
* **Execution Locking & Concurrency Control**: Improved execution lock logic to prevent concurrent or rapid repeated runs network-wide
* **User Interface Consolidation**: Unified "DIO Cron Statistics" panel combining Queue Status, Processing Statistics, and Network-Wide stats
* **Enhanced Monitoring**: Single-pane view for all statistics with improved visual hierarchy
* **Streamlined Admin Interface**: Removed duplicate Network-Wide stats sidebar for cleaner layout
* **Responsive Design**: Better mobile compatibility with improved spacing and typography
* **Performance Optimization**: Reduced duplicate data retrieval with consolidated statistics approach
* **Visual Design Enhancement**: Clear section headers and consistent table styling throughout interface

= 2.2.4 =
* **Production File Cleanup**: Removed all test and debug files for clean deployment structure
* **Version Management**: Updated version numbers consistently across all files
* **File Structure Optimization**: Streamlined plugin directory with only essential production files
* **Code Quality Maintenance**: Maintained 100% PHPCS compliance during cleanup operations
* **Deployment Ready**: Achieved production-ready state with comprehensive file organization

= 2.2.3 =
* **Git Workflow Optimization**: Resolved main/rename branch conflict for proper repository management
* **Repository Synchronization**: Successfully merged all changes from remote rename branch to main
* **Branch Strategy**: Clarified main branch as primary development branch for improved workflow
* **Code Standards Maintenance**: Maintained 100% PHPCS compliance throughout Git operations
* **Development Workflow**: Enhanced collaboration and deployment pipeline preparation
* **Quality Assurance**: Preserved all code quality improvements during repository synchronization

= 2.2.2 =
* **100% Standards Compliance**: Achieved perfect compliance with custom phpcs coding standards
* **Modern WordPress APIs**: Complete implementation of `wp_date()`, `wp_remote_get()`, `rawurlencode()` best practices
* **Array Syntax Modernization**: Converted to short array syntax `[]` throughout entire codebase
* **Security Optimizations**: Enhanced CSRF protection and proper nonce verification handling
* **Database Performance**: ActionScheduler integration with performance-optimized queries
* **Code Organization**: CSS files moved to dedicated `css/` folder, improved file structure
* **Quality Assurance**: Reduced violations from 300+ to 0 across all core files
* **Production Ready**: Robust error handling and memory-efficient code patterns
* **Developer Experience**: Enhanced IDE integration and automated standards checking
* **GitHub Integration**: Plugin Update Checker for direct repository updates

= 2.2.1 =
* **Enhanced Production Security**: WP_DEBUG-aware logging system with automatic production protection
* **Smart Admin Interface**: Logging controls automatically hidden when debugging is disabled (`WP_DEBUG = false`)
* **Conditional Logging**: Detailed logging only functions when `WP_DEBUG` is enabled for security
* **Security Validation**: Backend validation prevents logging activation without debug mode
* **Token Authentication**: Comprehensive security token system with admin interface management
* **Rate Limiting**: Built-in protection against abuse (5 requests per 5 minutes per IP)
* **WordPress Time Constants**: Code uses `MINUTE_IN_SECONDS`, `HOUR_IN_SECONDS` for better maintainability
* **Updated Documentation**: Contextual help explains WP_DEBUG requirement and security features
* **Production Safety**: Zero logging overhead and clean admin interface in production environments
* **UI Improvements**: Better alignment and visual consistency in admin interface

= 2.1.1 =
* **Code Quality Improvements**: Complete WordPress Coding Standards compliance (98% PHPCS compliance)
* **Enhanced Permalink System**: Robust rewrite rules management with automatic cache clearing
* **Critical Bug Fixes**: Fixed fatal error from function name typo and improved error handling  
* **Production Ready**: Removed debug functionality for cleaner production interface
* **Improved Reliability**: Better query variable handling and WordPress rewrite system compatibility

= 2.1.0 =
* **Network Admin Interface**: Complete admin panel at Network Admin → DIO Cron
* **Action Scheduler Integration**: Queue-based background processing for improved reliability
* **Action Scheduler Submenu**: Direct access to Scheduled Actions from DIO Cron admin menu
* **Recurring Jobs**: Automated scheduling with configurable frequencies (5 minutes to 24 hours)
* **Enhanced Monitoring**: Real-time queue status, processing statistics, and comprehensive logging
* **WordPress Standards**: Proper admin hooks, nonce verification, and WordPress `selected()` function integration
* **Object-Oriented Architecture**: Refactored to class-based design for better maintainability
* **Security Enhancements**: Capability checks, input sanitization, and secure form handling
* **Multisite Optimizations**: Prevents Action Scheduler menu duplication in subsites
* **Backward Compatibility**: All existing endpoints and functionality preserved

= 2.0.0 =
* Rename plugin to `DIO Cron`
* NOTE: this is a breaking change. 
  * The plugin will be deactivated after the update. You need to reactivate the plugin.
  * New endpoint: `/dio-cron`
  * Filters have been changed to `dio_cron_*` 

= 1.0.12 =
* Refactor error message handling

= 1.0.11 =
* Maintenance update

= 1.0.10 =
* Added GitHub Actions output format when using ?ga parameter

= 1.0.9 =
* Add sites caching using transients to improve performance.

= 1.0.8 =
* Update documentation

= 1.0.7 =
* Set the number of sites to 200. You can use the `add_filter( 'dio_cron_number_of_sites', function() { return 100; } );` to change the number of sites per request.

= 1.0.6 =
* Make plugin faster by using `$site->__get( 'siteurl' )` instead of `get_site_url( $site->blog_id )`. This prevents use of `switch_to_blog()` and `restore_current_blog()` functions. They are expensive and slow down the plugin.
* For `wp_remote_get`, set `blocking` to `false`. This will allow the request to be non-blocking and not wait for the response.
* For `wp_remote_get, set sslverify to false. This will allow the request to be non-blocking and not wait for the response.

= 1.0.5 =
* Update composer.json with metadata

= 1.0.4 =
* Add namespace
* Tested up to WordPress 6.7
* Updated plugin description with license information.


= 1.0.3 =
* Fixed version compatibility


= 1.0.2 =
* Updated plugin description and tested up to version.

= 1.0.1 =
* Initial release.

== Frequently Asked Questions ==

= How does the plugin work? =

The plugin hooks into a custom endpoint to run the cron job. It adds a rewrite rule and tag for the endpoint `dio-cron`. When this endpoint is accessed with a valid security token, the plugin will run wp-cron on all public sites in the multisite network using Action Scheduler for queue-based processing.

= Getting 401 Unauthorized? =

Check that you've included `?token=your-token-here` in your URL. Verify the token is correct in **Network Admin → DIO Cron → Security Status** and generate a new token if needed.

= Getting 429 Too Many Requests? =

You're hitting the rate limit (5 requests per 5 minutes). Wait a few minutes and try again, or check if multiple systems are calling the endpoint.

= The /dio-cron endpoint returns 404, what should I do? =

If the endpoint is not working:
1. Go to **Network Admin → DIO Cron** and click "Fix Permalinks"
2. Alternatively, visit **Settings → Permalinks** and click "Save Changes" 
3. Ensure your `.htaccess` file is writable and contains WordPress rewrite rules

The "Fix Permalinks" button performs a complete rewrite rules regeneration for maximum effectiveness.

= Getting 409 Conflict? =

Another cron job is already running. Wait up to 5 minutes for it to complete or check **Tools → Scheduled Actions** for stuck jobs.

= How do I monitor the plugin's performance? =

Use the comprehensive Network Admin interface at **Network Admin → DIO Cron** to view:
- Real-time queue status (pending, in-progress, failed actions)
- Processing statistics with success rates and daily metrics
- Network-wide statistics (total runs, sites processed, last execution)
- Individual site diagnostic testing with detailed error reporting

You can also monitor detailed action history at **Network Admin → DIO Cron → Scheduled Actions** (filter by group: `dio-cron`) for complete job logs and performance analysis.

= How do I enable detailed debugging? =

DIO Cron includes detailed logging for debugging wp-cron triggers, but this feature is only available when `WP_DEBUG` is enabled for security.

**Enable Debug Logging:**
1. Add to your `wp-config.php`:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```
2. Go to **Network Admin → DIO Cron**
3. Use the "Enable Detailed Logging" button in Quick Actions
4. Check `/wp-content/debug.log` for detailed request logs

**Security Note:**
- Logging controls are automatically hidden in production (`WP_DEBUG = false`)
- No debugging information is logged without explicit debug mode activation
- Protects against accidental logging in live environments

== Screenshots ==

1. No screenshots available.

== License ==

This plugin is licensed under the GPL2 license. See the [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html) file for more information.
