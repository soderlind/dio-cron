# DSS Cron

Run wp-cron on all public sites in a multisite network.

## Description

DSS Cron is a WordPress plugin designed to run wp-cron on all public sites in a multisite network. This ensures that scheduled tasks are executed across all sites in the network.

## Installation

1. Upload the `dss-cron` folder to the `/wp-content/plugins/` directory.
2. Network activate the plugin through the 'Network->Plugins' menu in WordPress.
3. The plugin will automatically add a custom rewrite rule and tag for the cron endpoint.

## Usage

The plugin hooks into a custom endpoint to run the cron job. It adds a rewrite rule and tag for the endpoint `dss-cron`. When this endpoint is accessed, the plugin will run wp-cron on all public sites in the multisite network.

## Changelog

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

## License

This plugin is licensed under the GPL2 license. See the [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html) file for more information.
