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

add_action('admin_enqueue_scripts', 'whatsapp_enqueue_scripts');

// Fetch template payload
function fetch_template_payload()
{
    check_ajax_referer('fetch_template_payload_nonce', 'nonce');
    // Enable error logging
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', ABSPATH . 'wp-content/debug.log');

    // Validate required parameters
    if (empty($_POST['template_name']) || empty($_POST['channel_number'])) {
        error_log('Missing required parameters');
        wp_send_json_error([
            'message' => 'Missing required parameters',
            'debug_info' => [
                'template_name' => $_POST['template_name'] ?? 'Not set',
                'channel_number' => $_POST['channel_number'] ?? 'Not set'
            ]
        ]);
        wp_die();
    }

    $api_key = whatsapp_get_value('api_key');
    $api_url = whatsapp_get_value('api_url');
    $template_name = sanitize_text_field($_POST['template_name']);
    $channel_number = sanitize_text_field($_POST['channel_number']);

    // Validate API credentials
    if (!$api_key || !$api_url) {
        error_log('API credentials missing');
        wp_send_json_error([
            'message' => 'API key or URL is missing',
            'debug_info' => [
                'api_key_set' => !empty($api_key),
                'api_url_set' => !empty($api_url)
            ]
        ]);
        wp_die();
    }

    try {
        $client = new Client([
            'timeout' => 10,  // Increased timeout
            'verify' => false // Disable SSL verification (use cautiously)
        ]);

        $headers = [
            'Authorization' => 'Bearer ' . $api_key,
            'Accept' => 'application/json'
        ];

        $endpoint = rtrim($api_url, '/') . '/api/v1.0/template-payload/' . urlencode($channel_number) . '/' . urlencode($template_name);
        
        error_log('Attempting to fetch template payload from: ' . $endpoint);

        $response = $client->request('GET', $endpoint, [
            'headers' => $headers
        ]);

        $body = json_decode($response->getBody(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Error decoding API response: ' . json_last_error_msg());
        }

        error_log('Template Payload Fetched Successfully');
        error_log(print_r($body, true));

        wp_send_json_success($body);

    } catch (GuzzleHttp\Exception\RequestException $e) {
        error_log('Guzzle Request Exception: ' . $e->getMessage());
        
        $errorDetails = [
            'message' => $e->getMessage(),
            'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : 'No response',
            'endpoint' => $endpoint,
            'request_headers' => $headers
        ];

        error_log('API Request Failed: ' . print_r($errorDetails, true));

        wp_send_json_error([
            'message' => 'API Request Failed',
            'debug_info' => $errorDetails
        ]);
    } catch (Exception $e) {
        error_log('Unexpected Error: ' . $e->getMessage());
        
        wp_send_json_error([
            'message' => 'Unexpected error occurred',
            'error_details' => $e->getMessage()
        ]);
    }

    wp_die();
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
    $api_key = whatsapp_get_value('api_key');
    $api_url = whatsapp_get_value('api_url');
    $single_channel = fetch_single_channel(); // Fetch a single channel dynamically
    $templates_and_channels = fetch_templates_and_channels();
    $templates = $templates_and_channels['templates']; // Assuming this function still fetches templates
    $channels = $templates_and_channels['channels'];
    $sections = ['order_book', 'order_cancellation', 'order_status_change', 'order_success'];

    // Extract unique categories from templates
    $categories = array_unique(array_column($templates, 'category'));
    $otp_enabled = whatsapp_get_value('enable_otp');
    $selected_event = whatsapp_get_value('event');
    ?>

    <div class="wrap">
        <h1><?php esc_html_e('WhatsApp Configuration', 'whatsapp-plugin'); ?></h1>

        <style>
            textarea {
                display: none;
            }
            .dynamic-variable{
                display: flex;
                gap: 20px;
            }
            .dynamic-variable> div{
                display: flex;
                flex-direction: column;
                gap: 10px;
                padding: 10px;
            }
            .variable-list {
                margin-top: 10px;
                padding: 10px;
                background-color: #f1f1f1;
                border: 1px solid #ccc;
            }
            .variable-list p {
                margin: 0;
                padding: 5px 0;
            }
            .hidden {
                display: none;
            }
            .underline{
                text-decoration: underline;
                cursor: pointer;
                border: 0.4px solid grey;
            }
            
        </style>

        <form method="post" action="options.php">
            
            <?php
            settings_fields('whatsapp_settings_group');
            do_settings_sections('whatsapp-settings');
            
            ?>
  <div class="underline"></div>
            <?php if (whatsapp_get_value('enable_otp')): ?>
                <h3 class="title <?php echo empty($api_key) || empty($api_url) ? 'hidden' : ''; ?>"><?php esc_html_e('Authentication Templates', 'whatsapp-plugin'); ?></h3>
                <table class="form-table <?php echo empty($api_key) || empty($api_url) ? 'hidden' : ''; ?>">
                    <tr>
                        <th><label for="auth_template"><?php esc_html_e('Template', 'whatsapp-plugin'); ?></label></th>
                        <td>
                            <select id="auth_template"
                                    name="whatsapp_settings[auth_template]">
                                <option value=""><?php esc_html_e('Select Template', 'whatsapp-plugin'); ?></option>
                                <?php 
                                // Show only authentication templates
                                foreach ($templates as $template): 
                                    if ($template['category'] === 'AUTHENTICATION'): ?>
                                        <option value="<?php echo esc_attr($template['name']); ?>"
                                            <?php selected(whatsapp_get_value('auth_template'), $template['name']); ?>>
                                            <?php echo esc_html($template['name']); ?>
                                        </option>
                                    <?php endif; 
                                endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="auth_message"><?php esc_html_e('Message', 'whatsapp-plugin'); ?></label></th>
                        <td>
                            <?php
                            $auth_message_value = whatsapp_get_value('auth_message');
                            if (empty($auth_message_value)) {
                                // Default JSON structure if no value exists
                                $auth_message_value = json_encode([
                                    'template' => [
                                        'components' => []
                                    ]
                                ]);
                            }
                            ?>
                            <textarea id="auth_message"
                                      name="whatsapp_settings[auth_message]"
                                      rows="8" cols="50" class="message-textarea"><?php echo esc_textarea(trim($auth_message_value)); ?></textarea>
                        </td>
                    </tr>
                </table>
            <?php endif; ?>
            <!-- Channel Selection -->
            <h3 class="title <?php echo empty($api_key) || empty($api_url) ? 'hidden' : ''; ?>"><?php esc_html_e('Channel', 'whatsapp-plugin'); ?></h3>
            <table class="form-table <?php echo empty($api_key) || empty($api_url) ? 'hidden' : ''; ?>">
                <tr>
                    <th><label for="channel_id"><?php esc_html_e('Channel Number', 'whatsapp-plugin'); ?></label></th>
                    <td>
                        <select id="channel_id" name="whatsapp_settings[channel_id]" class="channel-dropdown">
                            <option value="false" disabled>Select the Channel Number</option>
                            <option value="<?php echo esc_attr($single_channel['Number']); ?>" selected>
                                <?php echo esc_html($single_channel['Number']); ?>
                            </option>
                        </select>
                    </td>
                </tr>
            </table>
            <!-- Event Dropdown -->
            <h3 class="title <?php echo empty($api_key) || empty($api_url) ? 'hidden' : ''; ?>"><?php esc_html_e('Event', 'whatsapp-plugin'); ?></h3>
            <table class="form-table <?php echo empty($api_key) || empty($api_url) ? 'hidden' : ''; ?>">
                <tr>
                    <th><label for="event"><?php esc_html_e('Select Event', 'whatsapp-plugin'); ?></label></th>
                    <td>
                        <select id="event" name="whatsapp_settings[event]">
                            <option value=""><?php esc_html_e('Select Event', 'whatsapp-plugin'); ?></option>
                            <option value="order_book" <?php selected($selected_event, 'order_book'); ?>><?php esc_html_e('Order Book', 'whatsapp-plugin'); ?></option>
                            <option value="order_cancellation" <?php selected($selected_event, 'order_cancellation'); ?>><?php esc_html_e('Order Cancellation', 'whatsapp-plugin'); ?></option>
                            <option value="order_status_change" <?php selected($selected_event, 'order_status_change'); ?>><?php esc_html_e('Order Status Change', 'whatsapp-plugin'); ?></option>
                            <option value="order_success" <?php selected($selected_event, 'order_success'); ?>><?php esc_html_e('Order Success', 'whatsapp-plugin'); ?></option>
                        </select>
                    </td>
                </tr>
            </table>
            <!-- Dynamic Sections -->
            <?php foreach ($sections as $section): ?>
                <div id="section-<?php echo esc_attr($section); ?>" class="dynamic-section <?php echo ($selected_event !== $section) ? 'hidden' : ''; ?>">
                    <h3 class="title"><?php echo esc_html(ucfirst(str_replace('_', ' ', $section))); ?></h3>
                    <table class="form-table">
                        <!-- Category Dropdown -->
                        <tr>
                            <th><label for="<?php echo esc_attr($section . '_category'); ?>"><?php esc_html_e('Category', 'whatsapp-plugin'); ?></label></th>
                            <td>
                                <select id="<?php echo esc_attr($section . '_category'); ?>"
                                        name="whatsapp_settings[<?php echo esc_attr($section); ?>_category]">
                                    <option value=""><?php esc_html_e('Select Category', 'whatsapp-plugin'); ?></option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo esc_attr($category); ?>"
                                            <?php selected(whatsapp_get_value($section . '_category'), $category); ?>>
                                            <?php echo esc_html($category); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <!-- Template Dropdown -->
                        <tr>
                            <th><label for="<?php echo esc_attr($section . '_template'); ?>"><?php esc_html_e('Template', 'whatsapp-plugin'); ?></label></th>
                            <td>
                                <select id="<?php echo esc_attr($section . '_template'); ?>"
                                        name="whatsapp_settings[<?php echo esc_attr($section); ?>_template]"
                                        data-section="<?php echo esc_attr($section); ?>">
                                    <option value=""><?php esc_html_e('Select Template', 'whatsapp-plugin'); ?></option>
                                    <?php 
                                    $saved_template = whatsapp_get_value($section . '_template');
                                    foreach ($templates as $template): ?>
                                        <option value="<?php echo esc_attr($template['name']); ?>"
                                                data-category="<?php echo esc_attr($template['category']); ?>"
                                                <?php selected($saved_template, $template['name']); ?>>
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
                                <?php
                                $message_value = whatsapp_get_value($section . '_message');
                                if (empty($message_value)) {
                                    // Default JSON structure if no value exists
                                    $message_value = json_encode([
                                        'template' => [
                                            'components' => []
                                        ]
                                    ]);
                                }
                                ?>
                                <textarea id="<?php echo esc_attr($section . '_message'); ?>"
                                          name="whatsapp_settings[<?php echo esc_attr($section); ?>_message]"
                                          rows="8" cols="50" class="message-textarea"><?php echo esc_textarea(trim($message_value)); ?></textarea>
                                <div class="dynamic-variable">
                                    <div id="header-inputs-<?php echo esc_attr($section); ?>"></div>
                                    <div id="body-inputs-<?php echo esc_attr($section); ?>"></div>
                                    <div id="button-content-<?php echo esc_attr($section); ?>"></div>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
            <?php endforeach; ?>

            <?php submit_button(); ?>
        </form>
    </div>
<?php
}

function whatsapp_enqueue_scripts($hook) {
    if ($hook !== 'toplevel_page_manage-whatsapp-config') {
        return;
    }

    // Enqueue main script
    wp_enqueue_script(
        'whatsapp-admin-new',
        plugin_dir_url(__FILE__) . 'newscript.js',
        ['jquery'],  // Only depend on jQuery
        null,
        true
    );

    // Fetch templates dynamically
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
            error_log('Failed to fetch templates: ' . $e->getMessage());
        }
    }

    // Localize script with data
    wp_localize_script('whatsapp-admin-new', 'custom_ajax_object', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('fetch_template_payload_nonce'),
        'templates' => $templates,
    ]);
}

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


function fetch_templates_by_category() {
    // Verify nonce for security
    check_ajax_referer('fetch_templates_by_category_nonce', 'nonce');

    // Get the selected category
    $category = sanitize_text_field($_POST['category']);

    // Fetch saved WhatsApp settings
    $api_key = whatsapp_get_value('api_key');
    $api_url = whatsapp_get_value('api_url');

    $templates = [];

    if ($api_key && $api_url) {
        try {
            $client = new Client();
            $headers = ['Authorization' => 'Bearer ' . $api_key];

            // Fetch templates
            $template_response = $client->get($api_url . '/api/v1.0/templates', ['headers' => $headers]);
            $all_templates = json_decode($template_response->getBody(), true);

            // Filter templates by selected category
            $templates = array_filter($all_templates, function($template) use ($category) {
                return $template['category'] === $category;
            });

        } catch (Exception $e) {
            error_log('Error fetching templates by category: ' . $e->getMessage());
        }
    }

    // Send JSON response
    wp_send_json_success([
        'templates' => $templates
    ]);
}
add_action('wp_ajax_fetch_templates_by_category', 'fetch_templates_by_category');