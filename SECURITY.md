# DIO Cron Security Guide

## Overview

DIO Cron now includes compreheadd_filter( 'dio_cron_rate_limit_time_window', function( $seconds ) {
    return 10 * MINUTE_IN_SECONDS; // 10 minutes in seconds
});ve endpoint protection to prevent abuse and unauthorized access. The security system includes:

- **Rate Limiting** - Prevents too many requests in a short time
- **Token Authentication** - Requires secret token for access  
- **Execution Locking** - Prevents concurrent cron runs
- **Security Logging** - Logs all access attempts for monitoring

## Quick Setup

### 1. Configure Token Authentication

**Option A: WordPress Constant (Recommended)**
Add to your `wp-config.php`:
```php
define( 'DIO_CRON_TOKEN', 'your-very-long-random-secret-key-here' );
```

**Option B: Environment Variable**
```bash
export DIO_CRON_TOKEN="your-very-long-random-secret-key-here"
```

### 2. Generate a Secure Token

```bash
# Generate a 32-character random token
openssl rand -hex 32

# Or use a UUID
uuidgen
```

### 3. Update Your Cron Calls

Add the token parameter to your endpoint calls:

**Action Scheduler (Recommended):**
```
https://yoursite.com/dio-cron?token=your-token-here
```

**Legacy Mode:**
```
https://yoursite.com/dio-cron?immediate=1&token=your-token-here
```

**GitHub Actions:**
```
https://yoursite.com/dio-cron?ga&token=your-token-here
```

## Security Features

### Rate Limiting

**Default Limits:**
- 5 requests per 5 minutes per IP address
- Configurable via filters

**Customize Rate Limits:**
```php
// Allow 10 requests per 10 minutes
add_filter( 'dio_cron_rate_limit_max_requests', function() {
    return 10;
});

add_filter( 'dio_cron_rate_limit_time_window', function() {
    return 600; // 10 minutes in seconds
});
```

### Token Authentication

**How it works:**
1. Plugin checks for `DIO_CRON_TOKEN` environment variable first
2. Falls back to `DIO_CRON_TOKEN` WordPress constant
3. If no token configured, endpoint works without authentication (logged as security issue)
4. If token configured, all requests must include `?token=your-token` parameter

### Execution Locking

**Prevents concurrent execution:**
- Only one cron job can run at a time
- 5-minute timeout (configurable)
- Automatic lock release on completion or timeout

### Security Logging

**What gets logged:**
- Rate limit violations
- Authentication failures and successes
- Concurrent execution attempts
- Successful executions with site counts
- Requests to unconfigured endpoints

**Enable logging:**
Add to `wp-config.php`:
```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

## Server Configuration

### Crontab Examples

**Basic secured call:**
```bash
*/5 * * * * curl -s "https://yoursite.com/dio-cron?token=your-token-here"
```

**With error handling:**
```bash
*/5 * * * * curl -X GET "https://yoursite.com/dio-cron?token=your-token-here" \
  --connect-timeout 10 \
  --max-time 30 \
  --retry 3 \
  --retry-delay 5 \
  --silent \
  --show-error \
  --fail \
  >> /var/log/dio-cron.log 2>&1
```

### GitHub Actions

```yaml
name: DIO Cron Job
on:
  schedule:
    - cron: '*/5 * * * *'

env:
  CRON_ENDPOINT: 'https://yoursite.com/dio-cron?ga'
  DIO_CRON_TOKEN: ${{ secrets.DIO_CRON_TOKEN }}

jobs:
  trigger_cron:
    runs-on: ubuntu-latest
    timeout-minutes: 5
    steps:
      - run: |
          curl -X GET "${{ env.CRON_ENDPOINT }}&token=${{ env.DIO_CRON_TOKEN }}" \
            --connect-timeout 10 \
            --max-time 30 \
            --retry 3 \
            --retry-delay 5 \
            --silent \
            --show-error \
            --fail
```

## Admin Interface

### Security Status Panel

View security status in **Network Admin → DIO Cron**:

- **Token Protection**: Shows if authentication is configured
- **Rate Limiting**: Always active 
- **Execution Lock**: Shows current lock status
- **Client IP**: Your current IP address

### Security Recommendations

The admin interface will show warnings when:
- No token is configured
- `DISABLE_WP_CRON` is not set to true
- Security logging is not enabled

## HTTP Response Codes

| Code | Meaning | Action |
|------|---------|---------|
| 200 | Success | Cron executed successfully |
| 401 | Unauthorized | Check token parameter |
| 409 | Conflict | Already running, try again later |
| 429 | Too Many Requests | Rate limit exceeded, slow down |

## Troubleshooting

### Token Authentication Issues

**Problem**: Getting 401 Unauthorized
```bash
# Test if token is configured
curl -v "https://yoursite.com/dio-cron?token=wrong-token"
# Should return 401

# Test without token (if configured)
curl -v "https://yoursite.com/dio-cron"
# Should return 401
```

**Solution**: Verify token is correctly set and matches what you're sending.

### Rate Limiting Issues

**Problem**: Getting 429 Too Many Requests
**Solution**: 
- Wait 5 minutes and try again
- Check if multiple systems are calling the endpoint
- Increase rate limits if needed

### Execution Lock Issues

**Problem**: Getting 409 Conflict (Already running)
**Solution**:
- Wait for current execution to finish (max 5 minutes)
- Check Action Scheduler for stuck jobs
- Clear queue if necessary

### Debug Mode

Enable detailed logging:
```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

Check logs in `/wp-content/debug.log` for:
```
[DIO Cron Security] RATE_LIMIT_EXCEEDED: Rate limit exceeded | IP: 1.2.3.4
[DIO Cron Security] AUTHENTICATION_FAILED: Invalid or missing token | IP: 1.2.3.4
[DIO Cron Security] SUCCESSFUL_EXECUTION: Cron executed successfully for 15 sites | IP: 1.2.3.4
```

## Best Practices

1. **Always use HTTPS** for token-protected endpoints
2. **Use long, random tokens** (32+ characters)
3. **Rotate tokens periodically** 
4. **Monitor security logs** for suspicious activity
5. **Set up alerting** for failed authentication attempts
6. **Use environment variables** in production environments
7. **Test endpoints** after configuration changes

## Migration from Unprotected Setup

1. **Configure token** in wp-config.php or environment
2. **Test endpoint** with token parameter
3. **Update external triggers** (crontab, GitHub Actions, etc.)
4. **Monitor logs** for authentication issues
5. **Verify functionality** with new security measures

The plugin maintains backward compatibility - if no token is configured, endpoints work without authentication but log security warnings.
