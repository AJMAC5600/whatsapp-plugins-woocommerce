<?php
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

// Helper function to fetch WhatsApp settings
function whatsapp_field($key) {
    $settings = get_option('whatsapp_settings');
    return isset($settings[$key]) ? $settings[$key] : '';
}

// Generate and send OTP via WhatsApp
function generate_and_send_otp($phone_number) {
    // Fetch WhatsApp API credentials
    $api_key = whatsapp_field('api_key');
    $api_url = whatsapp_field('api_url');
    $channel_id = whatsapp_field('channel_id');

    if (empty($api_key) || empty($api_url) || empty($channel_id)) {
        return new WP_Error('missing_credentials', __('WhatsApp API credentials are missing.', 'whatsapp-plugin'));
    }

    // Generate a 6-digit OTP
    $otp = rand(100000, 999999);

    // Save the OTP in the user session or user meta
    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        update_user_meta($user_id, 'otp_code', $otp);
    } else {
        WC()->session->set('otp_code', $otp);
    }

    // Prepare the WhatsApp API request
    $body = [
        'messaging_product' => 'whatsapp',
        'recipient_type' => 'individual',
        'to' => $phone_number,
        'type' => 'template',
        'template' => [
            'name' => 'test_auth', // Replace with your template name
            'language' => ['code' => 'en'],
            'components' => [
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $otp],
                    ],
                ],
            ],
        ],
    ];

    // Send the OTP via WhatsApp API
    $response = wp_remote_post("$api_url/api/v1.0/messages/send-template/$channel_id", [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ],
        'body' => json_encode($body),
        'timeout' => 45,
    ]);

    // Handle API response
    if (is_wp_error($response)) {
        return new WP_Error('api_error', $response->get_error_message());
    }

    $response_body = wp_remote_retrieve_body($response);
    $decoded_response = json_decode($response_body, true);

    if (isset($decoded_response['error'])) {
        return new WP_Error('api_error', $decoded_response['error']['message']);
    }

    return true;
}

// Verify OTP
function verify_otp($entered_otp) {
    $stored_otp = is_user_logged_in()
        ? get_user_meta(get_current_user_id(), 'otp_code', true)
        : WC()->session->get('otp_code');

    if ($entered_otp == $stored_otp) {
        if (is_user_logged_in()) {
            update_user_meta(get_current_user_id(), 'otp_verified', true);
        } else {
            WC()->session->set('otp_verified', true);
        }
        return true;
    }

    return false;
}

// Hook to WooCommerce registration to send OTP
add_action('woocommerce_created_customer', 'send_otp_on_registration');
function send_otp_on_registration($customer_id) {
    $phone_number = isset($_POST['phone_number']) ? sanitize_text_field($_POST['phone_number']) : '';

    if (!$phone_number) {
        wc_add_notice(__('Phone number is required for OTP verification.', 'whatsapp-plugin'), 'error');
        return;
    }

    $result = generate_and_send_otp($phone_number);

    if (is_wp_error($result)) {
        wc_add_notice($result->get_error_message(), 'error');
    } else {
        update_user_meta($customer_id, 'phone_number', $phone_number);
    }
}

// Add OTP field to My Account page
add_action('woocommerce_edit_account_form', 'add_otp_verification_field');
function add_otp_verification_field() {
    $user_id = get_current_user_id();
    $otp_verified = get_user_meta($user_id, 'otp_verified', true);

    if (!$otp_verified) {
        echo '<p>
            <label for="otp_code">' . __('Enter OTP', 'whatsapp-plugin') . '</label>
            <input type="text" name="otp_code" id="otp_code" value="" required />
        </p>';
    }
}

// Handle OTP verification on My Account page
add_action('woocommerce_save_account_details', 'handle_otp_verification');
function handle_otp_verification($user_id) {
    if (isset($_POST['otp_code'])) {
        $entered_otp = sanitize_text_field($_POST['otp_code']);

        if (!verify_otp($entered_otp)) {
            wc_add_notice(__('Invalid OTP. Please try again.', 'whatsapp-plugin'), 'error');
        } else {
            wc_add_notice(__('OTP verified successfully!', 'whatsapp-plugin'), 'success');
        }
    }
}

// Block login if OTP is not verified
add_action('wp_login', 'block_login_unless_otp_verified', 10, 2);
function block_login_unless_otp_verified($user_login, $user) {
    $otp_verified = get_user_meta($user->ID, 'otp_verified', true);

    if (!$otp_verified) {
        wp_logout();
        wp_redirect(wc_get_page_permalink('myaccount'));
        wc_add_notice(__('Please verify your OTP to login.', 'whatsapp-plugin'), 'error');
        exit;
    }
}
