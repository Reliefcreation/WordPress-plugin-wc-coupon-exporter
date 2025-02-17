<?php
/**
 * Uninstall script for Coupon Exporter for WooCommerce
 *
 * This script runs when the plugin is uninstalled via the Plugins screen.
 */

// Exit if uninstall is not called from WordPress
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Clean up plugin options
delete_option('rwc_coupon_exporter_settings');

// Clean up any transients we may have created
delete_transient('rwc_coupon_exporter_cache');

// If we're in multisite, clean up each blog
if (is_multisite()) {
    // Get blog IDs from cache first
    $blog_ids = wp_cache_get('rwc_coupon_exporter_blog_ids');
    
    if (false === $blog_ids) {
        $blog_ids = get_sites(array('fields' => 'ids', 'number' => 0));
        wp_cache_set('rwc_coupon_exporter_blog_ids', $blog_ids);
    }
    
    if ($blog_ids) {
        foreach ($blog_ids as $blog_id) {
            switch_to_blog($blog_id);
            
            // Delete options for each site
            delete_option('rwc_coupon_exporter_settings');
            delete_transient('rwc_coupon_exporter_cache');
            
            restore_current_blog();
        }
    }
}
