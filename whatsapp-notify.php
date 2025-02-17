<?php
/*
Plugin Name: WhatsApp Notify
Description: Sends WhatsApp notifications to your clients for order status changes in WooCommerce.
Author: Wacto Solutions
Version: 1.0
Text Domain: whatsapp-notify
*/

// Exit if accessed directly
if (!defined('ABSPATH'))
    exit;

// Define constants for the plugin
define('WHATSAPP_NOTIFY_VERSION', '1.0');
define('WHATSAPP_NOTIFY_TEXT_DOMAIN', 'whatsapp-notify');
define('WHATSAPP_NOTIFY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WHATSAPP_NOTIFY_PLUGIN_URL', plugin_dir_url(__FILE__));


// Required files
// require_once WHATSAPP_NOTIFY_PLUGIN_DIR . 'api-functions.php';
// require_once WHATSAPP_NOTIFY_PLUGIN_DIR . 'otp-functions.php';

if (is_admin()) {
    require_once plugin_dir_path(__FILE__) . 'settings-page.php';
}



// Ensure WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', 'whatsapp_require_woocommerce');
    deactivate_plugins(plugin_basename(__FILE__));
    return;
}

function whatsapp_require_woocommerce()
{
    echo '<div class="error"><p><strong>' . __('WhatsApp Notify:', WHATSAPP_NOTIFY_TEXT_DOMAIN) . '</strong> ' . __('WooCommerce is required for this plugin to function. Please activate WooCommerce.', WHATSAPP_NOTIFY_TEXT_DOMAIN) . '</p></div>';
}

// Include core plugin functionality
include_once WHATSAPP_NOTIFY_PLUGIN_DIR . 'plugin-core.php';

// Handle plugin activation
register_activation_hook(__FILE__, 'whatsapp_notify_activate');
function whatsapp_notify_activate()
{
    // Ensure WooCommerce is active before activation
    if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        wp_die(__('WooCommerce must be active to use WhatsApp Notify.', WHATSAPP_NOTIFY_TEXT_DOMAIN));
    }

    // Perform plugin activation tasks (e.g., set default settings, create database tables)
    whatsapp_create_db_table();
}

// Create a database table for notifications
function whatsapp_create_db_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'whatsapp_notifications';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        phone_number VARCHAR(15) NOT NULL,
        order_id BIGINT(20) UNSIGNED NOT NULL,
        notification_status VARCHAR(20) NOT NULL,
        sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

// Handle plugin deactivation
register_deactivation_hook(__FILE__, 'whatsapp_notify_deactivate');
function whatsapp_notify_deactivate()
{
    // Clean up scheduled events
    if (wp_next_scheduled('whatsapp_cron_hook')) {
        $timestamp = wp_next_scheduled('whatsapp_cron_hook');
        wp_unschedule_event($timestamp, 'whatsapp_cron_hook');
    }
}

// Handle plugin uninstallation
register_uninstall_hook(__FILE__, 'whatsapp_notify_uninstall');
function whatsapp_notify_uninstall()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'whatsapp_notifications';

    // Delete the plugin settings
    delete_option('whatsapp_settings');

    // Drop the custom table
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
}

// Schedule a custom cron job on activation
register_activation_hook(__FILE__, 'whatsapp_notify_schedule_cron');
function whatsapp_notify_schedule_cron()
{
    if (!wp_next_scheduled('whatsapp_cron_hook')) {
        wp_schedule_event(time(), 'hourly', 'whatsapp_cron_hook');
    }
}

// Add a custom cron interval (if needed)
add_filter('cron_schedules', 'whatsapp_add_cron_interval');
function whatsapp_add_cron_interval($schedules)
{
    if (!isset($schedules['hourly'])) {
        $schedules['hourly'] = [
            'interval' => 3600,
            'display' => __('Once Hourly', WHATSAPP_NOTIFY_TEXT_DOMAIN),
        ];
    }
    return $schedules;
}
add_action('admin_menu', function () {
    error_log('admin_menu hook is working!');
});

// Handle cron jobs
add_action('whatsapp_cron_hook', 'whatsapp_handle_cron');
function whatsapp_handle_cron()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'whatsapp_notifications';

    // Fetch unsent notifications (example logic)
    $unsent_notifications = $wpdb->get_results("SELECT * FROM $table_name WHERE notification_status = 'pending'");

    foreach ($unsent_notifications as $notification) {
        $phone = $notification->phone_number;
        $order_id = $notification->order_id;

        // Fetch order details
        $order = wc_get_order($order_id);
        $first_name = $order->get_billing_first_name();
        $status = $order->get_status();

        // Fetch settings and send message
        $settings = get_option('whatsapp_settings', []);
        $message_template = isset($settings['message_template']) ? $settings['message_template'] : __('Hi %name%, your order #%order_id% is now %status%.', WHATSAPP_NOTIFY_TEXT_DOMAIN);
        $header_text = __('Order Update', WHATSAPP_NOTIFY_TEXT_DOMAIN);
        $body_text = str_replace(
            ['%name%', '%order_id%', '%status%'],
            [$first_name, $order_id, $status],
            $message_template
        );

        // Determine message category and select template based on it
        $category = determine_message_category($status);
        $templates = get_message_templates();
        $selected_template = isset($templates[$category]) ? $templates[$category] : 'default-template';

        // Use the API utility function to send the message
        $sent = whatsapp_send_message_via_api($phone, $selected_template, 'en_US', $header_text, $body_text);

        // Update the notification status in the database
        $wpdb->update(
            $table_name,
            ['notification_status' => $sent ? 'sent' : 'failed'],
            ['id' => $notification->id],
            ['%s'],
            ['%d']
        );
    }
}
?>