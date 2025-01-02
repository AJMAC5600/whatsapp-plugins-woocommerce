<?php

if (!defined('ABSPATH')) {
    exit; // Prevent direct access
}

require 'vendor/autoload.php'; // Ensure Guzzle is autoloaded
use GuzzleHttp\Client;

// Fetch saved WhatsApp settings
if (!function_exists('whatsapp_get_value')) {
    function whatsapp_get_value($key)
    {
        $options = get_option('whatsapp_settings', []);
        return isset($options[$key]) ? $options[$key] : '';
    }
}

// Fetch template payload
function fetch_template_payload()
{
    // error_log('Fetching template payload');
    // Check if template and channel number are provided
    if (empty($_POST['template_name']) || empty($_POST['channel_number'])) {
        wp_send_json_error(['message' => 'Missing required parameters'], 400);
    }

    $api_key = whatsapp_get_value('api_key');
    $api_url = whatsapp_get_value('api_url');
    $template_name = sanitize_text_field($_POST['template_name']);
    $channel_number = sanitize_text_field($_POST['channel_number']);
    // error_log('Template Name: ' . $template_name);
    // error_log('Channel Number: ' . $channel_number);
    if (!$api_key || !$api_url) {
        wp_send_json_error(['message' => 'API key or URL is missing'], 400);
    }

    try {
        $client = new Client();
        $headers = ['Authorization' => 'Bearer ' . $api_key];
        $endpoint = $api_url . '/api/v1.0/template-payload/' . $channel_number. '/' .$template_name;

        $response = $client->request('GET', $endpoint, ['headers' => $headers]);
        $body = json_decode($response->getBody(), true);
        // error_log(print_r($body, true));
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Error decoding API response: ' . json_last_error_msg());
        }

        wp_send_json_success($body);
    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()], 500);
    }
}
add_action('wp_ajax_fetch_template_payload', 'fetch_template_payload');

// Fetch templates and channels if API Key and URL are set
function fetch_templates_and_channels()
{
    $templates = [];
    $channels = [];
    $api_key = whatsapp_get_value('api_key');
    $api_url = whatsapp_get_value('api_url');

    if ($api_key && $api_url) {
        try {
            $client = new Client();
            $headers = ['Authorization' => 'Bearer ' . $api_key];

            // Fetch templates
            $template_response = $client->get($api_url . '/api/v1.0/templates', ['headers' => $headers]);
            $templates = json_decode($template_response->getBody(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Error decoding templates response: ' . json_last_error_msg());
            }

            // Fetch channels
            $channel_response = $client->get($api_url . '/api/v1.0/channels', ['headers' => $headers]);
            $channels = json_decode($channel_response->getBody(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Error decoding channels response: ' . json_last_error_msg());
            }
        } catch (Exception $e) {
            // error_log('[API Error]: ' . $e->getMessage());
        }
    }
    return ['templates' => $templates, 'channels' => $channels];
}
function fetch_single_channel()
{
    $channel = null;
    $api_key = whatsapp_get_value('api_key');
    $api_url = whatsapp_get_value('api_url');

    if ($api_key && $api_url) {
        try {
            $client = new Client();
            $headers = ['Authorization' => 'Bearer ' . $api_key];

            // Fetch channels
            $channel_response = $client->get($api_url . '/api/v1.0/channels', ['headers' => $headers]);
            $channels = json_decode($channel_response->getBody(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Error decoding channels response: ' . json_last_error_msg());
            }

            // Get the first channel from the response
            if (!empty($channels)) {
                $channel = $channels[0]; // Get the first channel only
            }
        } catch (Exception $e) {
            // error_log('[API Error]: ' . $e->getMessage());
        }
    }

    return $channel;
}


// Register settings page
if (!function_exists('register_whatsapp_config_page')) {
    function register_whatsapp_config_page()
    {
        add_menu_page(
            'WhatsApp Config',
            'WhatsApp Config',
            'manage_options',
            'manage-whatsapp-config',
            'display_whatsapp_config_page',
            'dashicons-admin-generic',
            90
        );
    }
    add_action('admin_menu', 'register_whatsapp_config_page');
}


// Render the settings page
function display_whatsapp_config_page()
{
    $single_channel = fetch_single_channel(); // Fetch a single channel dynamically
    $templates = fetch_templates_and_channels()['templates']; // Assuming this function still fetches templates
    $sections = ['order_book', 'order_cancellation', 'order_status_change', 'order_success'];
    ?>

    <div class="wrap">
        <h1><?php esc_html_e('WhatsApp Configuration', 'whatsapp-plugin'); ?></h1>

        <form method="post" action="options.php">
            <?php
            settings_fields('whatsapp_settings_group');
            do_settings_sections('whatsapp-settings');
            submit_button();
            ?>
              <tr>
                        <th><label for="<?php echo esc_attr($section . 'channel_id'); ?>"><?php esc_html_e('Channel', 'whatsapp-plugin'); ?></label></th>
                        <td>
                            <select id="<?php echo esc_attr($section . 'channel_id'); ?>"
                                    name="whatsapp_settings[<?php echo esc_attr($section); ?>channel_id]"
                                    class="channel-dropdown">
                                <option value="<?php echo esc_attr($single_channel['Number']); ?>" selected>
                                    <?php echo esc_html($single_channel['Number']); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
            <!-- Dynamic Sections -->
            <?php foreach ($sections as $section): ?>
                <h3 class="title"><?php echo esc_html(ucfirst(str_replace('_', ' ', $section))); ?></h3>
                <table class="form-table">
                    <!-- Template Dropdown -->
                    <!-- Channel Dropdown -->
                  

                    <!-- Template Dropdown -->
                    <tr>
                        <th><label for="<?php echo esc_attr($section . '_template'); ?>"><?php esc_html_e('Template', 'whatsapp-plugin'); ?></label></th>
                        <td>
                            <select id="<?php echo esc_attr($section . '_template'); ?>"
                                    name="whatsapp_settings[<?php echo esc_attr($section); ?>_template]">
                                <option value=""><?php esc_html_e('Select Template', 'whatsapp-plugin'); ?></option>
                                <?php foreach ($templates as $template): ?>
                                    <option value="<?php echo esc_attr($template['name']); ?>"
                                        <?php selected(whatsapp_get_value($section . '_template'), $template['name']); ?>>
                                        <?php echo esc_html($template['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>

                    <!-- Message Textarea -->
                    <tr>
                        <th><label for="<?php echo esc_attr($section . '_message'); ?>"><?php esc_html_e('Message', 'whatsapp-plugin'); ?></label></th>
                        <td>
                            <textarea id="<?php echo esc_attr($section . '_message'); ?>"
                                      name="whatsapp_settings[<?php echo esc_attr($section); ?>_message]"
                                      rows="8" cols="50" class="message-textarea">
                                <?php echo esc_textarea(whatsapp_get_value($section . '_message')); ?>
                            </textarea>
                        </td>
                    </tr>
                </table>
            <?php endforeach; ?>
        </form>
    </div>
    <?php
}


function whatsapp_enqueue_scripts($hook)
{
    if ($hook !== 'toplevel_page_manage-whatsapp-config') {
        return;
    }

    // Enqueue the script
    wp_enqueue_script(
        'whatsapp-admin',
        plugin_dir_url(__FILE__) . 'script.js',
        ['jquery'],
        null,
        true
    );

    // Fetch templates dynamically or pass an empty array if not available
    $options = get_option('whatsapp_settings', []);
    $templates = [];
    $api_key = $options['api_key'] ?? '';
    $api_url = $options['api_url'] ?? '';

    if (!empty($api_key) && !empty($api_url)) {
        try {
            $client = new \GuzzleHttp\Client();
            $headers = ['Authorization' => 'Bearer ' . $api_key];

            // Fetch templates
            $response = $client->get($api_url . '/api/v1.0/templates', ['headers' => $headers]);
            $templates = json_decode($response->getBody(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Error decoding templates response: ' . json_last_error_msg());
            }
        } catch (Exception $e) {
            // error_log('Failed to fetch templates: ' . $e->getMessage());
        }
    }

    // Localize the script with AJAX URL, nonce, and templates
    wp_localize_script('whatsapp-admin', 'custom_ajax_object', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('fetch_template_nonce'),
        'templates' => $templates,
    ]);
}
add_action('admin_enqueue_scripts', 'whatsapp_enqueue_scripts');


// Register settings
function whatsapp_register_settings()
{
    register_setting('whatsapp_settings_group', 'whatsapp_settings');

    add_settings_section(
        'whatsapp_main_settings', // Section ID
        __('Main Settings', 'whatsapp-plugin'), // Section title
        function () {
            echo '<p>' . esc_html__('Configure your WhatsApp settings below.', 'whatsapp-plugin') . '</p>';
        },
        'whatsapp-settings' // Page slug
    );

    add_settings_field(
        'api_key',
        __('API Key', 'whatsapp-plugin'),
        function () {
            $options = get_option('whatsapp_settings');
            ?>
            <input type="text" id="api_key" name="whatsapp_settings[api_key]" value="<?php echo esc_attr($options['api_key'] ?? ''); ?>" />
            <?php
        },
        'whatsapp-settings',
        'whatsapp_main_settings'
    );

    add_settings_field(
        'api_url',
        __('API URL', 'whatsapp-plugin'),
        function () {
            $options = get_option('whatsapp_settings');
            ?>
            <input type="text" id="api_url" name="whatsapp_settings[api_url]" value="<?php echo esc_attr($options['api_url'] ?? ''); ?>" />
            <?php
        },
        'whatsapp-settings',
        'whatsapp_main_settings'
    );

    add_settings_field(
        'enable_otp',
        __('Enable OTP Feature', 'whatsapp-plugin'),
        function () {
            $options = get_option('whatsapp_settings');
            ?>
            <input type="checkbox" name="whatsapp_settings[enable_otp]" value="1" <?php checked($options['enable_otp'] ?? '', '1'); ?> />
            <span><?php esc_html_e('Check to enable OTP feature.', 'whatsapp-plugin'); ?></span>
            <?php
        },
        'whatsapp-settings',
        'whatsapp_main_settings'
    );
}
add_action('admin_init', 'whatsapp_register_settings');
