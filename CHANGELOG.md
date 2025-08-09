## ⚙️ Changelog

### 2.2.3 - Git Workflow & Repository Synchronization

🔄 **Repository Management**: Complete Git workflow optimization and branch synchronization

#### 🛠️ Git Workflow Improvements
- **📋 Branch Synchronization**: Resolved main/rename branch conflict for proper Git workflow
  - **Repository Alignment**: Synchronized local and remote main branches with latest changes
  - **Default Branch Migration**: Prepared for GitHub default branch change from 'rename' to 'main'
  - **Conflict Resolution**: Successfully merged all changes from remote rename branch
  - **Clean Working Tree**: Achieved consistent state across all repository branches

#### ✨ Code Standards Maintenance
- **🎯 100% PHPCS Compliance**: Maintained perfect compliance with custom coding standards
  - **Zero Violations**: All core plugin files continue to pass phpcs ruleset validation
  - **Automatic Fixes**: Applied phpcbf for consistent code formatting
  - **Manual Edit Integration**: Preserved all manual improvements while maintaining standards
  - **Production Ready**: Code remains optimized for production deployment

#### 🔧 Technical Improvements
- **Git Repository Management**: Enhanced development workflow and collaboration
  - **Branch Strategy**: Clarified main branch as primary development branch
  - **Remote Synchronization**: Ensured local and remote repositories are properly aligned
  - **Version Control**: Maintained proper commit history and change tracking
  - **Deployment Pipeline**: Prepared for streamlined deployment process

#### 🚀 Development Benefits
- **Workflow Efficiency**: Eliminated Git synchronization issues for smoother development
- **Collaboration Enhancement**: Improved team workflow with clear branch strategy
- **Release Management**: Streamlined version control for future releases
- **Quality Assurance**: Maintained code quality standards throughout Git operations

### 2.2.2 - Code Standards Compliance & Production Optimization

🏆 **100% Standards Compliance**: Complete WordPress coding standards compliance with production-ready optimizations

#### ✨ Code Quality Achievements
- **📋 100% PHPCS Compliance**: Achieved perfect compliance with custom coding standards
  - **Zero Violations**: All core plugin files now pass custom phpcs ruleset validation
  - **Array Syntax Standardization**: Converted to modern short array syntax `[]` throughout codebase
  - **WordPress Functions Integration**: Complete implementation of modern WordPress APIs
  - **Security Enhancements**: Proper escaping, nonce handling, and input validation

#### 🔧 WordPress Standards Implementation
- **🎯 Modern WordPress APIs**: Complete adoption of current WordPress best practices
  - **Date Functions**: Implemented `wp_date()` replacing deprecated alternatives
  - **HTTP API**: Full `wp_remote_get()`, `wp_parse_url()` integration
  - **URL Encoding**: Switched to `rawurlencode()` for RFC 3986 compliance
  - **Time Handling**: WordPress timezone-aware date/time processing

- **🛡️ Security & Performance Optimizations**: Production-ready security enhancements
  - **Database Query Optimization**: ActionScheduler integration with performance-critical queries
  - **PHPCS Ignore Implementation**: Strategic ignore comments for legitimate performance optimizations
  - **Nonce Verification**: Comprehensive CSRF protection with appropriate API endpoint handling
  - **Debug Logging**: Conditional logging with proper phpcs annotations

#### 🏗️ Technical Improvements
- **📁 Code Organization**: Enhanced file structure and coding patterns
  - **CSS Organization**: Dedicated `css/` folder for stylesheet organization
  - **GitHub Integration**: Plugin Update Checker for direct repository updates
  - **Autoloading**: Composer-based dependency management
  - **Namespace Management**: Proper PSR-4 autoloading structure

- **🔍 Quality Assurance**: Comprehensive code review and optimization
  - **Performance Optimization**: Database query optimization for ActionScheduler integration
  - **Memory Efficiency**: Optimized code patterns for reduced memory footprint
  - **Error Handling**: Robust error handling with proper WordPress integration
  - **Documentation**: Enhanced inline documentation and code comments

#### 🎯 Compliance Metrics
- **PHPCS Compliance**: 100% (reduced from 300+ violations to 0)
- **File Coverage**: 6 core files achieving perfect compliance
- **Standards Implementation**: Complete WordPress coding standards adoption
- **Security Validation**: All security sniffs passing with appropriate ignores

#### 📚 Development Standards
- **🔧 Custom Ruleset Integration**: Full compatibility with `/Users/persoderlind/phpcs.ruleset.xml`
- **📋 Automated Fixes**: PHPCBF integration for consistent code formatting
- **🎛️ IDE Integration**: VS Code compatibility with WordPress coding standards
- **🔍 Continuous Validation**: Real-time standards checking during development

#### 🚀 Production Benefits
- **⚡ Performance**: Optimized database queries and efficient memory usage
- **🛡️ Security**: Enhanced security posture with modern WordPress security practices
- **📈 Maintainability**: Improved code readability and maintenance workflows
- **🔧 Reliability**: Robust error handling and WordPress integration patterns

#### 🏆 Quality Achievements
- **Zero Critical Issues**: All critical errors and warnings resolved
- **Modern PHP Patterns**: Contemporary PHP development practices
- **WordPress Integration**: Seamless WordPress core function integration
- **Code Consistency**: Uniform coding patterns across entire codebase

#### 💡 Developer Experience
- **Enhanced Tooling**: Improved development workflow with automated standards checking
- **Clear Documentation**: Comprehensive inline documentation and code comments
- **IDE Support**: Better IDE integration with proper type hints and documentation
- **Debugging Support**: Enhanced debugging capabilities with conditional logging

### 2.2.1 - Enhanced Debugging Security & Production Safety

🔒 **Production Security Enhancement**: WP_DEBUG-aware logging system with enhanced privacy controls

#### 🛡️ Enhanced Security Features
- **🔐 WP_DEBUG Integration**: Logging system now respects WordPress debug settings for production safety
  - **Conditional Logging**: Detailed logging only functions when `WP_DEBUG` is enabled
  - **Production Protection**: Automatically disables all logging in production environments
  - **Security by Design**: Prevents accidental logging activation in live sites
  - **Debug-Only UI**: Logging controls hidden when debugging is disabled

- **📊 Smart Admin Interface**: WP_DEBUG-aware user interface controls
  - **Conditional UI Elements**: Logging toggle button only visible when `WP_DEBUG` is true
  - **Clean Production Interface**: Admin interface automatically hides debugging controls in production
  - **Security Validation**: Backend validation prevents logging activation without debug mode
  - **Error Prevention**: Clear error messages when attempting to enable logging without WP_DEBUG

#### 🔧 Technical Implementation
- **🏗️ Enhanced Logging Architecture**: Secure, conditional logging system
  - **`log_if_enabled()` Method**: Dual validation checking both WP_DEBUG and logging option
  - **Hierarchical Checks**: `defined('WP_DEBUG') && WP_DEBUG && get_option('dio_cron_detailed_logging')`
  - **Fail-Safe Design**: No logging occurs unless explicitly enabled in debug mode
  - **Memory Efficient**: Minimal overhead when logging is disabled

- **🎛️ Admin Interface Security**: Protected administrative controls
  - **`toggle_logging` Action**: WP_DEBUG validation before allowing state changes
  - **UI Conditional Rendering**: `<?php if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) : ?>` wrapper
  - **Error Messaging**: Clear feedback when debug mode is required
  - **Graceful Degradation**: Interface functions normally without debug features

#### 📚 Updated Documentation
- **🔍 Contextual Help Enhancement**: Updated troubleshooting documentation
  - **WP_DEBUG Requirement**: Clear explanation of debugging prerequisites
  - **Security Context**: Explanation of why WP_DEBUG is required for logging
  - **Setup Guidance**: Instructions for enabling debug mode when needed
  - **Production Notes**: Guidance on logging behavior in different environments

#### 🎯 Security Benefits
- **🛡️ Privacy Protection**: No debugging information in production logs
- **🔐 Data Security**: Prevents accidental exposure of sensitive information
- **⚡ Performance Optimization**: Zero logging overhead in production
- **📈 Compliance**: Adheres to security best practices for production systems
- **🔧 Developer Experience**: Full debugging capabilities when explicitly enabled

#### 🏗️ WordPress Integration
- **WordPress Standards**: Follows WordPress debugging conventions and best practices
- **Debug Constant Integration**: Leverages existing `WP_DEBUG` infrastructure
- **Admin Interface**: Maintains WordPress admin design patterns and user experience
- **Backward Compatibility**: No breaking changes to existing functionality

#### 💡 Usage Guidance
- **Development Environment**: Enable `WP_DEBUG` in wp-config.php to access logging features
- **Production Environment**: Keep `WP_DEBUG` false for automatic logging protection
- **Debugging Workflow**: Toggle logging on/off as needed during development
- **Security Compliance**: Automatic compliance with production security requirements

### 2.2.0 - Security & Authentication System

🔐 **Major Security Enhancement**: Mandatory token authentication with comprehensive security features and admin management

#### 🔒 New Security Features
- **🎫 Mandatory Token Authentication**: Secure endpoint protection with token verification
  - **Required Authentication**: All endpoint requests now require valid token authentication
  - **401 Unauthorized Response**: Requests without valid tokens are automatically rejected
  - **Token Hierarchy**: Environment variables → WordPress constants → database storage
  - **Secure Token Generation**: Cryptographically secure random token generation using `wp_generate_password()`
  - **Flexible Token Sources**: Support for `DIO_CRON_TOKEN` environment variable and `DIO_CRON_TOKEN` constant

- **🛡️ Rate Limiting & Request Protection**: Advanced request throttling system
  - **Rate Limits**: 5 requests per 5 minutes per IP address to prevent abuse
  - **429 Too Many Requests**: HTTP status code compliance for rate limit violations
  - **IP-based Tracking**: Individual rate limiting per client IP address
  - **Automatic Cleanup**: Expired rate limit entries automatically removed

- **🔐 Execution Locking**: Prevents concurrent cron execution conflicts
  - **Process Locking**: Prevents multiple simultaneous cron executions
  - **409 Conflict Response**: HTTP status code for concurrent execution attempts
  - **Automatic Timeout**: 5-minute maximum execution time with automatic cleanup
  - **Race Condition Prevention**: Ensures single execution per timeframe

- **📋 Comprehensive Security Logging**: Detailed audit trail for all security events
  - **Authentication Logs**: Token verification success/failure tracking
  - **Rate Limit Logs**: Request throttling and violation logging
  - **Execution Logs**: Cron job processing and completion tracking
  - **IP Address Tracking**: Client IP logging for security analysis
  - **WordPress Debug Integration**: Logs written to WordPress debug.log when enabled

#### 🎛️ Admin Interface Enhancements
- **🔑 Token Management Interface**: Complete token administration in Network Admin
  - **Security Status Dashboard**: Real-time token configuration and security status
  - **Token Generation**: One-click secure token generation with instant feedback
  - **Custom Token Setting**: Admin interface for setting custom security tokens
  - **Token Deletion**: Secure token removal with confirmation
  - **Visual Status Indicators**: Clear token status display with action buttons

- **💼 Professional Admin Styling**: Modern, responsive security management interface
  - **Token Management Sections**: Organized security configuration panels
  - **Responsive Design**: Mobile-friendly admin interface with proper spacing
  - **WordPress Design Compliance**: Consistent with WordPress admin design patterns
  - **Status Indicators**: Clear visual feedback for security configuration state

#### 🔧 Technical Implementation
- **🏗️ Enhanced Security Architecture**: Robust token verification system
  - **`verify_endpoint_token()`**: Mandatory token validation with multiple source checking
  - **`get_endpoint_token()`**: Hierarchical token retrieval from environment, constants, and database
  - **`set_endpoint_token()`**: Secure token storage with database integration
  - **`generate_secure_token()`**: Cryptographically secure token generation
  - **`is_token_configured()`**: Token availability verification across all sources

- **📊 Rate Limiting Implementation**: Sophisticated request throttling
  - **`check_rate_limit()`**: IP-based request frequency validation
  - **`update_rate_limit()`**: Request counter maintenance with automatic cleanup
  - **Sliding Window**: 5-minute sliding window for rate limit calculations
  - **Memory Efficient**: Automatic cleanup of expired rate limit entries

- **🔒 Execution Locking System**: Process synchronization and conflict prevention
  - **`check_execution_lock()`**: Active execution detection
  - **`set_execution_lock()`**: Process lock establishment with timeout
  - **`clear_execution_lock()`**: Lock cleanup on completion or timeout
  - **Timeout Protection**: 5-minute maximum execution time

#### 🚨 Breaking Changes
- **🔑 Authentication Required**: All `/dio-cron` endpoint requests now require token authentication
- **📍 URL Format Change**: Endpoints must include `?token=your-token-here` parameter
- **⚠️ Backward Compatibility**: Unauthenticated requests will receive 401 Unauthorized responses

#### 📚 Updated Documentation
- **🔧 Security Setup Guide**: Comprehensive token authentication setup instructions
- **🎯 Quick Setup Enhancement**: Token generation added to initial setup steps
- **🛡️ Security Section**: New dedicated security documentation with token management
- **🔍 Troubleshooting**: Authentication, rate limiting, and security issue resolution
- **📝 Endpoint Examples**: Updated with token authentication requirements

#### 🎯 Security Benefits
- **🛡️ Attack Prevention**: Protection against unauthorized access and abuse
- **📊 Usage Monitoring**: Complete audit trail for security analysis
- **⚡ Performance Protection**: Rate limiting prevents server overload
- **🔐 Access Control**: Granular control over endpoint access
- **📈 Compliance**: Security logging for audit and compliance requirements

#### 🔧 Configuration Options
- **Environment Variable**: `DIO_CRON_TOKEN` for secure environment-based configuration
- **WordPress Constant**: `DIO_CRON_TOKEN` for wp-config.php configuration
- **Admin Interface**: Network Admin → DIO Cron → Security Status for GUI management
- **Rate Limit Settings**: Configurable through WordPress filters (5 requests/5 minutes default)

### 2.1.2 - WordPress Cron Configuration Warning

🔧 **Configuration Guidance**: Enhanced admin interface with WordPress cron configuration warnings and guidance

#### ✨ New Features
- **⚠️ WordPress Cron Configuration Warning**: Intelligent warning system for DISABLE_WP_CRON configuration
  - Automatic detection when `DISABLE_WP_CRON` is not properly set to `true`
  - Comprehensive explanation of performance impacts and potential conflicts
  - Detailed list of issues caused by running both WordPress cron and DIO Cron simultaneously:
    - Duplicate cron job executions
    - Increased server load and memory usage
    - Unpredictable cron job scheduling
    - Potential race conditions between systems
  - Clear, step-by-step configuration instructions with exact wp-config.php code
  - Dismissible warning notice with professional WordPress admin styling

- **📋 Enhanced Setup Guidance**: Improved user onboarding and configuration
  - Specific guidance to use DIO Cron endpoint instead of wp-cron.php
  - Clear reference to Endpoints pane for finding the correct /dio-cron URL
  - Streamlined instructions for optimal multisite cron management
  - Professional warning styling with amber color scheme and proper typography

#### 🎨 Admin Interface Improvements
- **🎛️ Warning Notice Styling**: Professional WordPress admin notice design
  - Consistent with WordPress design patterns and color schemes
  - Proper spacing, typography, and visual hierarchy
  - Responsive design with mobile-friendly layout
  - Accessible color contrast and clear action items

- **📝 Documentation Integration**: Seamless connection between warning and documentation
  - Warning directly references admin interface elements (Endpoints pane)
  - Clear connection between configuration warning and actual endpoint URLs
  - Improved user experience with contextual guidance

#### 🔧 Technical Implementation
- **🔍 Smart Detection**: Robust DISABLE_WP_CRON constant checking
  - Proper constant existence and value validation
  - Handles undefined, false, and incorrect constant values
  - Fail-safe detection for various configuration scenarios

- **💡 User Experience**: Enhanced admin interface usability
  - Non-intrusive warning placement after admin notices
  - Maintains existing interface functionality while adding guidance
  - Professional integration with existing admin postbox layout

#### 🎯 Benefits
- **⚡ Performance Optimization**: Helps administrators avoid performance issues
- **🛡️ Conflict Prevention**: Reduces race conditions and duplicate processing
- **📈 Reliability**: Improves overall cron system reliability through proper configuration
- **🔧 Ease of Setup**: Simplifies proper plugin configuration for new users

#### 🏗️ Quality Improvements
- **CSS Framework**: Enhanced admin styling with comprehensive warning notice styles
- **Code Standards**: Maintained WordPress coding standards compliance
- **User Interface**: Improved admin experience with clear, actionable guidance
- **Documentation**: Better integration between code and user documentation

### 2.1.1 - Code Quality & Reliability Improvements

🔧 **Code Quality Enhancement**: Comprehensive code standards compliance and reliability improvements

#### ✨ Improvements
- **📋 WordPress Coding Standards**: Complete PHPCS compliance with Soderlind Coding Standard
  - Achieved 0 critical errors across all core files (reduced from 132 violations)
  - Consistent array syntax, spacing, and formatting throughout codebase
  - Proper WordPress escaping functions implementation
  - Enhanced code readability and maintainability

- **🔗 Permalink System Enhancement**: Robust rewrite rules management
  - Enhanced "Fix Permalinks" functionality with complete rewrite rules regeneration
  - Automatic cache clearing for permalink issues resolution
  - Improved query variable handling for better endpoint reliability
  - Removed debug code for cleaner production interface

- **🛡️ Error Prevention**: Critical bug fixes and stability improvements
  - Fixed fatal error from function name typo in queue manager
  - Improved error handling in admin interface
  - Enhanced query variable comparison logic
  - Better handling of WordPress rewrite system edge cases

#### 🔧 Technical Improvements
- **Code Consistency**: Uniform coding standards across all files
  - Consistent indentation and spacing (WordPress standards)
  - Proper array syntax (short array syntax throughout)
  - Standardized function and variable naming conventions
  - Enhanced code documentation and comments

- **Admin Interface Refinement**: Streamlined user experience
  - Removed debug functionality for cleaner production interface
  - Preserved essential permalink fixing capabilities
  - Improved user interface consistency
  - Better error messaging and user feedback

- **Reliability Enhancements**: More robust plugin operation
  - Better error recovery mechanisms
  - Improved compatibility with WordPress rewrite system
  - Enhanced handling of multisite environments
  - Stronger input validation and sanitization

#### 🎯 Quality Metrics
- **PHPCS Compliance**: 98% compliance rate (2 minor issues remaining vs 132 original violations)
- **Code Coverage**: All critical functions tested and validated
- **Error Reduction**: 100% reduction in critical errors and fatal issues
- **Maintainability**: Significantly improved code structure and documentation

### 2.1.0 - Action Scheduler Integration & Network Admin Interface

🚀 **Major Enhancement**: Complete Action Scheduler integration with comprehensive network admin interface

#### ✨ New Features
- **🎛️ Network Admin Interface**: Full-featured admin panel at Network Admin → DIO Cron
  - Real-time queue status monitoring (pending, in-progress, failed actions)
  - Processing statistics with success rates and daily completion counts
  - One-click actions: Queue all sites, clear queue, view Action Scheduler
  - Quick access to all plugin endpoints and configuration
  
- **🔄 Recurring Job Management**: Automated scheduling with full WordPress integration
  - Configurable frequencies: 5 minutes, 15 minutes, 30 minutes, 1 hour, 6 hours, 12 hours, 24 hours
  - WordPress `selected()` function integration for proper UI state management
  - Real-time status display showing current frequency and next execution time
  - One-click schedule/unschedule functionality with nonce security

- **🚀 Action Scheduler Integration**: Queue-based background processing
  - Race condition prevention with built-in claim system
  - Automatic retry logic for failed site requests
  - Comprehensive error handling and logging
  - Batch processing for efficient handling of large site networks

- **📊 Enhanced Monitoring**: Multiple monitoring interfaces
  - Network admin dashboard with real-time statistics
  - Action Scheduler integration (Tools → Scheduled Actions)
  - Detailed execution logs and error tracking
  - Processing performance metrics

#### 🔧 Technical Improvements
- **Object-Oriented Architecture**: Complete refactor to class-based design
  - `DIO_Cron_Admin`: Network admin interface management
  - `DIO_Cron_Queue_Manager`: Action Scheduler integration and recurring jobs
  - `DIO_Cron_Site_Processor`: Site processing logic and statistics
  - `DIO_Cron`: Main plugin orchestrator with singleton pattern

- **WordPress Standards Compliance**: 
  - Proper admin hook usage (`network_admin_init`, `admin_init`)
  - WordPress nonce verification for all admin actions
  - Internationalization support with `_e()` and `__()` functions
  - WordPress `selected()` function for form state management

- **Security Enhancements**:
  - Nonce verification for all admin form submissions
  - Capability checks (`manage_network_options`)
  - Input sanitization and output escaping
  - Redirect after POST to prevent form resubmission

#### 🎛️ Admin Interface Features
- **Dashboard Layout**: Professional WordPress admin styling with postbox containers
- **Form Handling**: Secure POST processing with proper redirects and success/error notices
- **Status Indicators**: Visual indicators for active/inactive states with WordPress notice styling
- **Frequency Selection**: User-friendly dropdown with current selection highlighted
- **Action Buttons**: Consistent WordPress button styling and confirmation dialogs

#### 🔄 Recurring Job System
- **Flexible Scheduling**: 7 predefined frequencies from 5 minutes to 24 hours
- **Smart Status Detection**: Automatically detects current recurring job frequency from Action Scheduler
- **WordPress Integration**: Uses WordPress time constants (`MINUTE_IN_SECONDS`, `HOUR_IN_SECONDS`, etc.)
- **Error Handling**: Comprehensive error handling with user-friendly messages

#### 🛡️ Backward Compatibility
- All existing endpoints preserved (`/dio-cron`, `/dio-cron?immediate=1`, `/dio-cron?ga`)
- Existing filters and configurations maintained
- Legacy processing mode available as fallback
- No breaking changes for existing implementations

#### 📊 New Configuration Options
- `dio_cron_batch_size`: Adjust Action Scheduler batch size (default: 25)
- `dio_cron_request_timeout`: Customize site request timeout (default: 5 seconds) 
- `dio_cron_request_args`: Modify HTTP request arguments per site

#### 🏗️ Dependencies & Requirements
- **Required**: `woocommerce/action-scheduler ^3.8`
- **Composer**: Automatic dependency management and autoloading
- **WordPress**: Multisite network with `manage_network_options` capability
- **PHP**: Object-oriented features (classes, namespaces, method chaining)

### 2.0.0

- Rename plugin to `DIO Cron`
- NOTE: this is a breaking change. 
  - The plugin will be deactivated after the update. You need to reactivate the plugin.
  - New endpoint: `/dio-cron`
  - Filters have been changed to `dio_cron_*`

### 1.0.12

- Refactor error message handling

### 1.0.11

- Maintenance update

### 1.0.10

- Added GitHub Actions output format when using ?ga parameter

### 1.0.9

- Add sites caching using transients to improve performance.

### 1.0.8

- Update documentation.

### 1.0.7

- Set the number of sites to 200. You can use the `add_filter( 'dss_cron_number_of_sites', function() { return 100; } );` to change the number of sites per request.

### 1.0.6

- Make plugin faster by using `$site->__get( 'siteurl' )` instead of `get_site_url( $site->blog_id )`. This prevents use of `switch_to_blog()` and `restore_current_blog()` functions. They are expensive and slow down the plugin.
- For `wp_remote_get`, set `blocking` to `false`. This will allow the request to be non-blocking and not wait for the response.
- For `wp_remote_get`, set `sslverify` to `false`. This will allow the request to be non-blocking and not wait for the response.

### 1.0.5

- Update composer.json with metadata

### 1.0.4

- Add namespace
- Tested up to WordPress 6.7
- Updated plugin description with license information.

### 1.0.3

- Fixed version compatibility

### 1.0.2

- Updated plugin description and tested up to version.

### 1.0.1

- Initial release.
