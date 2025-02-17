<?php
/**
 * Plugin Name: Coupon Exporter for WooCommerce
 * Plugin URI: https://github.com/Reliefcreation/WordPress-plugin-rwc-coupon-exporter
 * Description: Export WooCommerce coupons to CSV file
 * Version: 1.3
 * Author: RELIEF Creation
 * Author URI: https://reliefcreation.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: rwc-coupon-exporter
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * WC requires at least: 3.0
 * WC tested up to: 8.5
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    die('Direct access not permitted.');
}

// Check if WooCommerce is active
function rwc_coupon_exporter_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>' . 
                 esc_html__('Coupon Exporter for WooCommerce requires WooCommerce to work.', 'rwc-coupon-exporter') . 
                 '</p></div>';
        });
        return false;
    }
    return true;
}

// Add menu to admin panel
function rwc_coupon_exporter_admin_menu() {
    // Only add menu if user has proper permissions
    if (current_user_can('manage_woocommerce')) {
        add_submenu_page(
            'woocommerce',
            esc_html__('Export Coupons', 'rwc-coupon-exporter'),
            esc_html__('Export Coupons', 'rwc-coupon-exporter'),
            'manage_woocommerce',
            'coupon-exporter',
            'rwc_coupon_exporter_page'
        );
    }
}
add_action('admin_menu', 'rwc_coupon_exporter_admin_menu');

// Export page
function rwc_coupon_exporter_page() {
    // Double check permissions
    if (!current_user_can('manage_woocommerce')) {
        wp_die(esc_html__('You do not have sufficient permissions', 'rwc-coupon-exporter'));
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('Export WooCommerce Coupons', 'rwc-coupon-exporter'); ?></h1>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php 
            wp_nonce_field('rwc_coupon_export_nonce', 'rwc_coupon_export_nonce'); 
            ?>
            <input type="hidden" name="action" value="rwc_export_coupons">
            <p><?php echo esc_html__('Click the button below to export all coupons to CSV format.', 'rwc-coupon-exporter'); ?></p>
            <button type="submit" class="button button-primary">
                <?php echo esc_html__('Export Coupons', 'rwc-coupon-exporter'); ?>
            </button>
        </form>
    </div>
    <?php
}

// Handle export
function rwc_coupon_exporter_process() {
    // Verify nonce first
    if (!isset($_POST['rwc_coupon_export_nonce']) || !isset($_POST['action'])) {
        wp_die(
            esc_html__('Unauthorized action', 'rwc-coupon-exporter'),
            esc_html__('Error', 'rwc-coupon-exporter'),
            array('response' => 403)
        );
    }

    // Properly sanitize and validate inputs
    $nonce = sanitize_text_field(wp_unslash($_POST['rwc_coupon_export_nonce']));
    $action = sanitize_text_field(wp_unslash($_POST['action']));
    
    // Verify nonce
    if (!wp_verify_nonce($nonce, 'rwc_coupon_export_nonce')) {
        wp_die(
            esc_html__('Unauthorized action', 'rwc-coupon-exporter'),
            esc_html__('Error', 'rwc-coupon-exporter'),
            array('response' => 403)
        );
    }
    
    // Verify action
    if ($action !== 'rwc_export_coupons') {
        return;
    }

    // Verify user permissions
    if (!current_user_can('manage_woocommerce')) {
        wp_die(
            esc_html__('You do not have sufficient permissions', 'rwc-coupon-exporter'),
            esc_html__('Error', 'rwc-coupon-exporter'),
            array('response' => 403)
        );
    }

    // Verify WooCommerce is active
    if (!rwc_coupon_exporter_check_woocommerce()) {
        wp_die(
            esc_html__('WooCommerce is required for this action', 'rwc-coupon-exporter'),
            esc_html__('Error', 'rwc-coupon-exporter'),
            array('response' => 400)
        );
    }

    // Get all coupons
    $args = array(
        'posts_per_page' => -1,
        'post_type'      => 'shop_coupon',
        'post_status'    => 'publish'
    );
    
    $coupons = get_posts($args);
    
    if (empty($coupons)) {
        wp_die(
            esc_html__('No coupons found', 'rwc-coupon-exporter'),
            esc_html__('Information', 'rwc-coupon-exporter'),
            array('response' => 200)
        );
    }

    // Prepare CSV headers
    $headers = array(
        esc_html__('Code', 'rwc-coupon-exporter'),
        esc_html__('Description', 'rwc-coupon-exporter'),
        esc_html__('Discount Type', 'rwc-coupon-exporter'),
        esc_html__('Amount', 'rwc-coupon-exporter'),
        esc_html__('Expiry Date', 'rwc-coupon-exporter'),
        esc_html__('Minimum Spend', 'rwc-coupon-exporter'),
        esc_html__('Maximum Usage', 'rwc-coupon-exporter'),
        esc_html__('Usage Per User', 'rwc-coupon-exporter'),
        esc_html__('Products', 'rwc-coupon-exporter'),
        esc_html__('Categories', 'rwc-coupon-exporter'),
        esc_html__('Email Restrictions', 'rwc-coupon-exporter')
    );

    // Sanitize filename using gmdate instead of date
    $filename = sanitize_file_name('woocommerce-coupons-' . gmdate('Y-m-d') . '.csv');
    
    // Set secure headers
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    header('Pragma: no-cache');
    header('Expires: 0');
    
    global $wp_filesystem;
    if (empty($wp_filesystem)) {
        require_once(ABSPATH . '/wp-admin/includes/file.php');
        WP_Filesystem();
    }

    $output = fopen('php://output', 'w');
    if ($output === false) {
        wp_die(
            esc_html__('Unable to create output file', 'rwc-coupon-exporter'),
            esc_html__('Error', 'rwc-coupon-exporter'),
            array('response' => 500)
        );
    }

    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    fputcsv($output, $headers);

    // Export each coupon
    foreach ($coupons as $coupon) {
        try {
            $wc_coupon = new WC_Coupon($coupon->ID);
            
            $expiry_date = '';
            if ($wc_coupon->get_date_expires()) {
                $expiry_date = gmdate('Y-m-d', $wc_coupon->get_date_expires()->getTimestamp());
            }
            
            $row = array(
                sanitize_text_field($wc_coupon->get_code()),
                sanitize_text_field($wc_coupon->get_description()),
                sanitize_text_field($wc_coupon->get_discount_type()),
                floatval($wc_coupon->get_amount()),
                sanitize_text_field($expiry_date),
                floatval($wc_coupon->get_minimum_amount()),
                intval($wc_coupon->get_usage_limit()),
                intval($wc_coupon->get_usage_limit_per_user()),
                sanitize_text_field(implode(', ', $wc_coupon->get_product_ids())),
                sanitize_text_field(implode(', ', $wc_coupon->get_product_categories())),
                sanitize_text_field(implode(', ', $wc_coupon->get_email_restrictions()))
            );
            
            fputcsv($output, $row);
        } catch (Exception $e) {
            // Store error in WordPress options
            $error_message = sprintf(
                'Coupon Exporter - Error processing coupon ID %s: %s',
                wp_privacy_anonymize_data('text', $coupon->ID),
                wp_privacy_anonymize_data('text', $e->getMessage())
            );
            
            // Store the error in options to display in admin
            $errors = get_option('rwc_coupon_exporter_errors', array());
            $errors[] = $error_message;
            update_option('rwc_coupon_exporter_errors', array_slice($errors, -10)); // Keep last 10 errors
            
            continue;
        }
    }
    
    if (is_resource($output)) {
        $wp_filesystem->close($output);
    }
    exit();
}
add_action('admin_post_rwc_export_coupons', 'rwc_coupon_exporter_process');