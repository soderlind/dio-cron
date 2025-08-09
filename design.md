# Action Scheduler Integration Design

## Project Analysis

### Current Implementation
The DIO Cron plugin currently:
- Uses WordPress built-in `wp-cron` system triggered via HTTP endpoints
- Runs synchronously when `/dio-cron` endpoint is accessed
- Processes all sites in a single request using `wp_remote_get()` with `blocking => false`
- Uses transients to cache site lists for 1 hour
- Supports up to 200 sites per request (filterable)
- Provides GitHub Actions compatible output format

### Current Pain Points
1. **Race Conditions**: Multiple simultaneous requests could trigger overlapping executions
2. **Memory/Time Limits**: Processing large numbers of sites in a single request
3. **Error Handling**: Limited retry mechanisms for failed site requests
4. **Scalability**: No built-in batching or queue management
5. **Reliability**: Depends on external triggers (Pingdom, cron, GitHub Actions)

## Action Scheduler Integration Strategy

### Why Action Scheduler?
Action Scheduler addresses the current limitations by providing:
- **Robust Queue Management**: Built-in batching and concurrency control
- **Retry Logic**: Automatic retry for failed actions
- **Scalability**: Processes large queues efficiently
- **Race Condition Prevention**: Built-in claim system prevents overlapping
- **Logging**: Comprehensive action tracking and debugging
- **Background Processing**: Automatic async processing via loopback requests

### Design Goals
1. **Backward Compatibility**: Maintain existing `/dio-cron` endpoint functionality
2. **Enhanced Reliability**: Leverage Action Scheduler's queue system for better error handling
3. **Improved Performance**: Use batching to process sites more efficiently
4. **Better Monitoring**: Utilize Action Scheduler's logging for better visibility
5. **Flexible Scheduling**: Support both manual triggers and scheduled recurring jobs

## Implementation Plan

### Phase 1: Installation and Setup
1. **Add Action Scheduler Dependency**
   - Update `composer.json` to include Action Scheduler
   - Add vendor autoload inclusion
   - Ensure Action Scheduler tables are created on activation

2. **Plugin Structure Updates**
   - Create new class-based architecture for better organization
   - Separate concerns: endpoint handling, queue management, site processing

### Phase 2: Core Integration
1. **Queue Management System**
   - Create `DIO_Cron_Queue_Manager` class
   - Implement methods for enqueueing site cron jobs
   - Add batch processing logic

2. **Action Handlers**
   - Create `dio_cron_process_site` action hook
   - Implement individual site processing logic
   - Add error handling and retry mechanisms

3. **Enhanced Endpoint**
   - Modify existing endpoint to use Action Scheduler
   - Add option to process immediately vs. queue for background processing
   - Maintain GitHub Actions output compatibility

### Phase 3: Advanced Features
1. **Scheduled Recurring Jobs**
   - Add option for automatic recurring cron runs
   - Implement admin interface for schedule management
   - Support multiple schedule frequencies

2. **Monitoring and Logging**
   - Integrate with Action Scheduler's admin interface
   - Add custom logging for DIO Cron specific events
   - Create status dashboard for queue monitoring

### Phase 4: Admin Interface (Optional)
1. **Settings Page**
   - Queue configuration options
   - Schedule management
   - Batch size settings
   - Monitoring dashboard

## Technical Implementation Details

### New File Structure
```
dio-cron/
├── dio-cron.php (main plugin file)
├── includes/
│   ├── class-dio-cron.php (main plugin class)
│   ├── class-queue-manager.php (Action Scheduler integration)
│   ├── class-site-processor.php (individual site processing)
│   └── class-admin.php (admin interface - optional)
├── assets/ (existing)
└── vendor/ (Action Scheduler)
```

### Key Classes and Methods

#### DIO_Cron_Queue_Manager
```php
class DIO_Cron_Queue_Manager {
    public function enqueue_site_cron_jobs();
    public function process_single_site($site_id);
    public function get_queue_status();
    public function schedule_recurring_job();
}
```

#### DIO_Cron_Site_Processor
```php
class DIO_Cron_Site_Processor {
    public function process_site_cron($site_id);
    public function validate_site($site);
    public function handle_cron_request($site_url);
}
```

### Action Scheduler Integration Points

1. **Action Registration**
   ```php
   // Register custom action hook
   add_action('dio_cron_process_site', [$this, 'process_single_site']);
   ```

2. **Enqueueing Jobs**
   ```php
   // Queue individual site processing
   as_enqueue_async_action('dio_cron_process_site', ['site_id' => $site_id]);
   ```

3. **Batch Processing**
   ```php
   // Schedule batch of sites
   foreach ($sites as $site) {
       as_enqueue_async_action('dio_cron_process_site', ['site_id' => $site->blog_id]);
   }
   ```

4. **Recurring Jobs**
   ```php
   // Schedule recurring cron job
   as_schedule_recurring_action(
       time(),
       HOUR_IN_SECONDS,
       'dio_cron_run_all_sites'
   );
   ```

### Configuration Options

1. **Batch Size**: Number of sites to process per Action Scheduler batch
2. **Processing Mode**: Immediate vs. Background queue processing
3. **Retry Logic**: Number of retry attempts for failed sites
4. **Schedule Frequency**: For recurring automatic runs
5. **Timeout Settings**: Per-site timeout configurations

### Backward Compatibility Strategy

1. **Endpoint Behavior**
   - `/dio-cron` - Enhanced with Action Scheduler (background processing)
   - `/dio-cron?immediate=1` - Legacy behavior (synchronous processing)
   - `/dio-cron?ga` - GitHub Actions compatible output (maintained)

2. **Filter Compatibility**
   - Maintain existing `dio_cron_number_of_sites` filter
   - Maintain existing `dio_cron_sites_transient` filter
   - Add new filters for Action Scheduler specific options

3. **Database Changes**
   - Action Scheduler will create its own tables
   - No changes to existing plugin data structure
   - Graceful fallback if Action Scheduler fails to initialize

### Error Handling and Monitoring

1. **Action Scheduler Logging**
   - Automatic logging of all queued actions
   - Failed action tracking with error messages
   - Processing time and performance metrics

2. **Custom Logging**
   - DIO Cron specific log entries
   - Site-level success/failure tracking
   - Performance benchmarking

3. **Admin Interface Integration**
   - View Action Scheduler logs in WordPress admin
   - Custom DIO Cron status dashboard
   - Queue management tools

## Risk Assessment

### Low Risk
- Action Scheduler is battle-tested (used by WooCommerce)
- Maintains backward compatibility
- Gradual rollout possible

### Medium Risk
- Additional dependency increases complexity
- Database overhead from Action Scheduler tables
- Learning curve for troubleshooting queued actions

### Mitigation Strategies
- Extensive testing in staging environments
- Fallback to legacy processing if Action Scheduler fails
- Comprehensive documentation and monitoring
- Gradual feature rollout with feature flags

## Success Metrics

1. **Performance**: Reduced memory usage and execution time
2. **Reliability**: Fewer failed cron executions
3. **Scalability**: Support for larger numbers of sites
4. **Observability**: Better error tracking and debugging
5. **User Experience**: Simplified setup and maintenance

## Next Steps

1. **Review and Approval**: Get stakeholder approval for design approach
2. **Development Environment**: Set up testing environment with multiple sites
3. **Phase 1 Implementation**: Begin with Action Scheduler installation
4. **Testing**: Comprehensive testing of each phase
5. **Documentation**: Update README and create migration guide

---

This design provides a roadmap for integrating Action Scheduler while maintaining the plugin's core functionality and improving its reliability and scalability.
