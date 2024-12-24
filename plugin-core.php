<?php
if (!defined('ABSPATH')) exit;

// Section 1: Load Text Domain for Localization
add_action('plugins_loaded', 'whatsapp_plugin_load_textdomain');
function whatsapp_plugin_load_textdomain() {
    load_plugin_textdomain('whatsapp-plugin', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

// Section 2: Add Settings Page to Admin Menu
add_action('admin_menu', 'whatsapp_admin_menu', 20);
function whatsapp_admin_menu() {
    function whatsapp_tab() {
        include 'settings-page.php'; // Load the settings page
    }

    add_menu_page(
        __('WhatsApp Config', 'whatsapp-plugin'), // Page title
        __('WhatsApp Config', 'whatsapp-plugin'), // Menu title
        'manage_options',
        'manage-whatsapp-config',
        'whatsapp_tab',
        '',
        8
    );
}

// Section 3: Register Settings Groups
add_action('admin_init', 'register_whatsapp_settings');
function register_whatsapp_settings() {
    register_setting('whatsapp_settings_group', 'whatsapp_settings');
    register_setting('whatsapp_template_settings_group', 'whatsapp_template_settings', [
        'type' => 'array',
        'sanitize_callback' => 'sanitize_whatsapp_template_settings',
    ]);
}

// Sanitization callback for template settings
function sanitize_whatsapp_template_settings($input) {
    $output = [];
    if (isset($input['channel_id'])) {
        $output['channel_id'] = sanitize_text_field($input['channel_id']);
    }
    if (isset($input['selected_category'])) {
        $output['selected_category'] = sanitize_text_field($input['selected_category']);
    }
    if (isset($input['template_name'])) {
        $output['template_name'] = sanitize_text_field($input['template_name']);
    }
    if (isset($input['message'])) {
        $output['message'] = sanitize_textarea_field($input['message']);
    }
    return $output;
}

// Section 4: Utility Function to Fetch WhatsApp Field
$whatsapp_settings = get_option('whatsapp_settings');
function whatsapp_field($key) {
    global $whatsapp_settings;
    return isset($whatsapp_settings[$key]) ? $whatsapp_settings[$key] : '';
}

// Section 5: WhatsApp API Integration Functions
function whatsapp_send_message_via_api($phone, $template_name, $language_code, $header_text, $body_text) {
    $api_key = whatsapp_field('api_key');
    $channel_id = whatsapp_field('channel_id');
    $api_url = whatsapp_field('api_url');

    if (empty($api_key) || empty($channel_id) || empty($api_url)) {
        error_log(__('WhatsApp API credentials or domain missing.', 'whatsapp-plugin'));
        return false;
    }

    $url = "$api_url/api/v1.0/messages/send-template/$channel_id";

    $body = [
        'MessagingProduct' => 'whatsapp',
        'RecipientType' => 'individual',
        'to' => $phone,
        'Type' => 'template',
        'Template' => [
            'Name' => $template_name,
            'Language' => [
                'Code' => $language_code,
            ],
            'components' => [
                [
                    'type' => 'header',
                    'parameters' => [
                        [
                            'type' => 'text',
                            'text' => $header_text,
                        ],
                    ],
                ],
                [
                    'type' => 'body',
                    'parameters' => [
                        [
                            'type' => 'text',
                            'text' => $body_text,
                        ],
                    ],
                ],
            ],
        ],
    ];

    $response = wp_remote_post($url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ],
        'body' => json_encode($body),
        'timeout' => 45,
    ]);

    if (is_wp_error($response)) {
        error_log(__('WhatsApp API Error: ', 'whatsapp-plugin') . $response->get_error_message());
        return false;
    }

    $response_body = wp_remote_retrieve_body($response);
    $decoded_body = json_decode($response_body, true);

    if (isset($decoded_body['error'])) {
        error_log(__('WhatsApp API Error: ', 'whatsapp-plugin') . $decoded_body['error']['message']);
        return false;
    }

    return true;
}


// Section 6: WooCommerce Event Handlers
// Order Placement
add_action('woocommerce_thankyou', 'send_order_confirmation_whatsapp', 10, 1);
function send_order_confirmation_whatsapp($order_id) {
    if (!$order_id) return;

    $order = wc_get_order($order_id);

    whatsapp_send_message_via_api(
        $order->get_billing_phone(),
        'test_vikas',
        'en',
        __('Order Confirmation', 'whatsapp-plugin'),
        sprintf(__('Thank you for your order #%s. We will process it soon.', 'whatsapp-plugin'), $order->get_order_number())
    );
}

// Order Cancellation
add_action('woocommerce_order_status_cancelled', 'send_order_cancellation_whatsapp', 10, 1);
function send_order_cancellation_whatsapp($order_id) {
    if (!$order_id) return;

    $order = wc_get_order($order_id);

    whatsapp_send_message_via_api(
        $order->get_billing_phone(),
        'order_cancellation',
        'en',
        __('Order Cancellation', 'whatsapp-plugin'),
        sprintf(__('We regret to inform you that your order #%s has been canceled.', 'whatsapp-plugin'), $order->get_order_number())
    );
}

// Order Status Changes
add_action('woocommerce_order_status_changed', 'send_order_status_update_whatsapp', 10, 4);
function send_order_status_update_whatsapp($order_id, $old_status, $new_status, $order) {
    if (!$order_id) return;

    $phone = $order->get_billing_phone();
    $status_messages = [
        'processing' => __('Your order is now being processed. We will notify you when it is shipped.', 'whatsapp-plugin'),
        'completed'  => __('Your order has been completed. Thank you for shopping with us!', 'whatsapp-plugin'),
        'on-hold'    => __('Your order is on hold. Please contact us for more details.', 'whatsapp-plugin'),
        'refunded'   => __('Your order has been refunded. Please check your payment method for updates.', 'whatsapp-plugin'),
    ];

    if (isset($status_messages[$new_status])) {
        whatsapp_send_message_via_api(
            $phone,
            'order_status_update',
            'en',
            __('Order Status Update', 'whatsapp-plugin'),
            sprintf(__('Order #%s: %s', 'whatsapp-plugin'), $order->get_order_number(), $status_messages[$new_status])
        );
    }
}

// Custom Admin Notification
add_action('woocommerce_order_status_completed', 'notify_admin_order_completed', 10, 1);
function notify_admin_order_completed($order_id) {
    if (!$order_id) return;

    $order = wc_get_order($order_id);

    whatsapp_send_message_via_api(
        'admin_phone_number',
        'admin_order_notification',
        'en',
        __('Order Completed Notification', 'whatsapp-plugin'),
        sprintf(__('Order #%s has been marked as completed.', 'whatsapp-plugin'), $order->get_order_number())
    );
}

// Section 7: Fetch Plugin Settings with AJAX
add_action('wp_ajax_fetch_whatsapp_channels', 'fetch_whatsapp_channels');
function fetch_whatsapp_channels() {
    if (!isset($_POST['api_key']) || !isset($_POST['api_url'])) {
        wp_send_json_error(['message' => __('Invalid API credentials.', 'whatsapp-plugin')]);
        return;
    }

    $api_key = sanitize_text_field($_POST['api_key']);
    $api_url = sanitize_text_field($_POST['api_url']);

    $response = wp_remote_get("$api_url/v1/channels", [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ],
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error(['message' => __('Failed to fetch channels.', 'whatsapp-plugin')]);
        return;
    }

    $channels = json_decode(wp_remote_retrieve_body($response), true);

    if (isset($channels['error'])) {
        wp_send_json_error(['message' => __('API Error: ', 'whatsapp-plugin') . $channels['error']['message']]);
        return;
    }

    wp_send_json_success(['channels' => $channels]);
}
?>
