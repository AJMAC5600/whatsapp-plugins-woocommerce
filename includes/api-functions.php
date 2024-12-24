<?php
if (!defined('ABSPATH')) exit;

/**
 * WhatsApp API Functions
 * 
 * This file contains reusable functions for integrating with the WhatsApp API.
 */

// Retrieve WhatsApp plugin settings
function whatsapp_get_option($key) {
    $options = get_option('whatsapp_settings');
    return isset($options[$key]) ? sanitize_text_field($options[$key]) : '';
}

/**
 * Send a WhatsApp message using the API.
 *
 * @param string $phone_number The recipient's phone number.
 * @param string $template_name The template name to be used for the message.
 * @param string $language_code The language code for the template.
 * @param array $components The components to customize the template.
 * 
 * @return bool|string True on success, or an error message on failure.
 */
function whatsapp_send_message($phone_number, $template_name, $language_code, $components = []) {
    $api_key = whatsapp_get_option('api_key');
    $api_url = whatsapp_get_option('api_url');
    $channel_id = whatsapp_get_option('channel_id');

    if (empty($api_key) || empty($api_url) || empty($channel_id)) {
        return __('API credentials are missing.', 'whatsapp-plugin');
    }

    $endpoint = trailingslashit($api_url) . "api/v1.0/messages/send-template/$channel_id";
    $body = [
        'messaging_product' => 'whatsapp',
        'recipient_type' => 'individual',
        'to' => $phone_number,
        'type' => 'template',
        'template' => [
            'name' => $template_name,
            'language' => ['code' => $language_code],
            'components' => $components,
        ],
    ];

    $response = wp_remote_post($endpoint, [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ],
        'body' => json_encode($body),
        'timeout' => 45,
    ]);

    if (is_wp_error($response)) {
        return __('Error connecting to WhatsApp API: ', 'whatsapp-plugin') . $response->get_error_message();
    }

    $response_body = wp_remote_retrieve_body($response);
    $response_data = json_decode($response_body, true);

    if (isset($response_data['error'])) {
        return __('WhatsApp API Error: ', 'whatsapp-plugin') . $response_data['error']['message'];
    }

    return true;
}

/**
 * Log WhatsApp API errors.
 *
 * @param string $message The error message to log.
 */
function whatsapp_log_error($message) {
    error_log('[WhatsApp Plugin Error] ' . $message);
}

/**
 * Validate phone numbers for WhatsApp.
 *
 * @param string $phone_number The phone number to validate.
 * @return string|bool The sanitized phone number or false if invalid.
 */
function whatsapp_validate_phone_number($phone_number) {
    $sanitized = preg_replace('/[^\d]/', '', $phone_number); // Remove non-numeric characters
    return strlen($sanitized) >= 10 ? $sanitized : false;
}

/**
 * Fetch available WhatsApp channels.
 *
 * @return array|WP_Error The list of channels or a WP_Error object on failure.
 */
function whatsapp_fetch_channels() {
    $api_key = whatsapp_get_option('api_key');
    $api_url = whatsapp_get_option('api_url');

    if (empty($api_key) || empty($api_url)) {
        return new WP_Error('missing_credentials', __('API credentials are missing.', 'whatsapp-plugin'));
    }

    $endpoint = trailingslashit($api_url) . 'api/v1.0/channels';
    $response = wp_remote_get($endpoint, [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ],
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $response_body = wp_remote_retrieve_body($response);
    $data = json_decode($response_body, true);

    if (isset($data['error'])) {
        return new WP_Error('api_error', $data['error']['message']);
    }

    return $data['channels'] ?? [];
}

/**
 * Create WhatsApp message components.
 *
 * @param array $parameters The parameters for the components.
 * @return array The formatted components array.
 */
function whatsapp_create_message_components($parameters) {
    $components = [];
    foreach ($parameters as $parameter) {
        if (isset($parameter['type']) && isset($parameter['text'])) {
            $components[] = [
                'type' => $parameter['type'],
                'parameters' => [
                    ['type' => 'text', 'text' => $parameter['text']],
                ],
            ];
        }
    }
    return $components;
}
