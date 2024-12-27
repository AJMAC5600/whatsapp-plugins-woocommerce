<?php
if (!defined('ABSPATH')) exit;
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

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
function whatsapp_send_message_via_api($phone, $template_name, $language_code, $header_text, $body_text, $is_otp = false, $otp_code = '') {
    $api_key = whatsapp_field('api_key');
    $channel_id = whatsapp_field('channel_id');
    $api_url = whatsapp_field('api_url');

    if (empty($api_key) || empty($channel_id) || empty($api_url)) {
        error_log(__('WhatsApp API credentials or domain missing.', 'whatsapp-plugin'));
        return false;
    }
    
    // Prepare the URL for sending the message
    $url = "$api_url/api/v1.0/messages/send-template/919833533311";

    // Initialize request body
    $body = [];

    // If it's an OTP message
    if ($is_otp) {
        $body = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => '91'.$phone,
            'type' => 'template',
            'template' => [
                'name' => 'test_auth',
                'language' => [
                    'code' => 'en_US',
                ],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => [
                            [
                                'type' => 'text',
                                'text' => $otp_code
                            ],
                        ],
                    ],
                    [
                        'type' => 'button',
                        'parameters' => [
                            [
                                'type' => 'text',
                                'text' => $otp_code
                            ],
                        ],
                        'sub_type' => 'url',
                        'index' => '0',
                    ],
                ],
            ],
        ];
    } else {
        $body = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $phone,
            'type' => 'template',
            'template' => [
                'name' => $template_name,
                'language' => [
                    'code' => $language_code,
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
    }

    $response = wp_remote_post($url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ],
        'body' => json_encode($body),
        'timeout' => 30,
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

// Section 6: WooCommerce Registration - Add Phone Number and OTP Fields
function add_phone_number_otp_fields_to_registration() {
    wp_nonce_field('whatsapp_otp_action', 'whatsapp_otp_nonce');
    ?>
    <p class="form-row form-row-wide">
        <label for="reg_phone_number"><?php _e('Phone Number', 'whatsapp-plugin'); ?></label>
        <input type="text" class="input-text" name="phone_number" id="reg_phone_number" value="<?php if (!empty($_POST['phone_number'])) echo esc_attr($_POST['phone_number']); ?>" required />
    </p>

    <p class="form-row form-row-wide">
        <label for="reg_otp"><?php _e('OTP Code', 'whatsapp-plugin'); ?></label>
        <input type="text" class="input-text" name="otp_code" id="reg_otp" value="" required />
        <button type="button" id="send_otp" class="button"><?php _e('Send OTP', 'whatsapp-plugin'); ?></button>
    </p>
    <?php
}

function add_phone_number_column_to_wc_customer_lookup() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'wc_customer_lookup';
    $column_name = 'phone_number';

    // Check if the column already exists
    $column_exists = $wpdb->get_results(
        $wpdb->prepare(
            "SHOW COLUMNS FROM {$table_name} LIKE %s",
            $column_name
        )
    );

    // If the column does not exist, add it
    if (empty($column_exists)) {
        $wpdb->query(
            "ALTER TABLE {$table_name} ADD COLUMN `{$column_name}` VARCHAR(20) DEFAULT NULL"
        );
    }
}
add_action('plugins_loaded', 'add_phone_number_column_to_wc_customer_lookup');


// Hook into the WooCommerce registration process.
add_action('woocommerce_created_customer', function($customer_id) {
    if (isset($_POST['email']) && !empty($_POST['email'])) {
        $email = sanitize_email($_POST['email']);
        // Save the email in the wp_usermeta table.
        update_user_meta($customer_id, 'user_email', $email);
    }
});

add_action('woocommerce_register_form', 'add_phone_number_otp_fields_to_registration');

/**
 * Save phone number to wp_wc_customer_lookup table.
 *
 * @param int $customer_id The customer ID.
 * @param string $phone_number The phone number to save.
 */
function store_phone_number_in_wc_customer_lookup($customer_id, $phone_number) {
    global $wpdb;

    // Sanitize the input.
    $phone_number = sanitize_text_field($phone_number);

    // Define the table name.
    $table_name = $wpdb->prefix . 'wc_customer_lookup';

    // Perform the insert or update.
    $result = $wpdb->query(
        $wpdb->prepare(
            "INSERT INTO $table_name (customer_id, phone_number) 
             VALUES (%d, %s) 
             ON DUPLICATE KEY UPDATE phone_number = VALUES(phone_number)",
            $customer_id,
            $phone_number
        )
    );

    // Log the results for debugging.
    if ($result === false) {
        error_log('DB Error: ' . $wpdb->last_error);
    } else {
        error_log("Phone Number Saved Successfully for Customer ID: $customer_id. Result: $result");
    }
}

// Hook into the WooCommerce registration process.
add_action('woocommerce_created_customer', function($customer_id) {
    if (isset($_POST['phone_number']) && !empty($_POST['phone_number'])) {
        $phone_number = sanitize_text_field($_POST['phone_number']);
        store_phone_number_in_wc_customer_lookup($customer_id, $phone_number);
    }
});



function activate_whatsapp_plugin() {
    add_phone_number_column_to_wc_customer_lookup();
}
register_activation_hook(__FILE__, 'activate_whatsapp_plugin');



// Section 7: Validate OTP and Phone Number during Registration
function validate_phone_number_and_otp($username, $email, $validation_errors) {
    if (isset($_POST['phone_number']) && empty($_POST['phone_number'])) {
        $validation_errors->add('phone_number_error', __('Phone number is required.', 'whatsapp-plugin'));
    }

    if (isset($_POST['otp_code']) && empty($_POST['otp_code'])) {
        $validation_errors->add('otp_error', __('OTP is required.', 'whatsapp-plugin'));
    } elseif (isset($_POST['otp_code']) && isset($_SESSION['otp'])) {
        $otp_code = sanitize_text_field($_POST['otp_code']);
        $stored_otp = $_SESSION['otp'];

        // Trim both OTP and compare
        if (trim($otp_code) !== trim($stored_otp)) {
            $validation_errors->add('otp_invalid', __('Invalid OTP code.', 'whatsapp-plugin'));
        } else {
            // OTP expired check (10 minutes validity)
            $otp_lifetime = 10 * 60; // 10 minutes
            if (isset($_SESSION['otp_timestamp']) && (time() - $_SESSION['otp_timestamp']) > $otp_lifetime) {
                unset($_SESSION['otp']); // Clear expired OTP
                $validation_errors->add('otp_expired', __('OTP code has expired.', 'whatsapp-plugin'));
            } else {
                unset($_SESSION['otp']); // OTP validated, remove from session
            }
        }
    }

    return $validation_errors;
}
add_filter('woocommerce_register_post', 'validate_phone_number_and_otp', 10, 3);

// Hook into the WooCommerce registration process.
add_action('woocommerce_created_customer', function($customer_id) {
    if (isset($_POST['phone_number']) && !empty($_POST['phone_number'])) {
        $phone_number = sanitize_text_field($_POST['phone_number']);

        // Save the phone number in the wp_usermeta table.
        update_user_meta($customer_id, 'phone_number', $phone_number);
    }
});
// Add password fields to WooCommerce registration form
function add_password_fields_to_registration() {
    ?>
    <p class="form-row form-row-wide">
        <label for="reg_password"><?php _e('Password', 'woocommerce'); ?> <span class="required">*</span></label>
        <input type="password" class="input-text" name="password" id="reg_password" required />
    </p>

    <p class="form-row form-row-wide">
        <label for="reg_password_confirm"><?php _e('Confirm Password', 'woocommerce'); ?> <span class="required">*</span></label>
        <input type="password" class="input-text" name="password_confirm" id="reg_password_confirm" required />
    </p>
    <?php
}
add_action('woocommerce_register_form', 'add_password_fields_to_registration');

// Validate password fields during registration
function validate_password_fields($username, $email, $validation_errors) {
    if (isset($_POST['password']) && isset($_POST['password_confirm'])) {
        $password = $_POST['password'];
        $password_confirm = $_POST['password_confirm'];

        if (empty($password)) {
            $validation_errors->add('password_error', __('Password is required.', 'woocommerce'));
        }

        if ($password !== $password_confirm) {
            $validation_errors->add('password_mismatch', __('Passwords do not match.', 'woocommerce'));
        }
    } else {
        $validation_errors->add('password_missing', __('Password fields are required.', 'woocommerce'));
    }

    return $validation_errors;
}
add_filter('woocommerce_register_post', 'validate_password_fields', 10, 3);

// Save user password during registration
function save_password_during_registration($customer_id) {
    if (isset($_POST['password'])) {
        wp_set_password($_POST['password'], $customer_id);
    }
}
add_action('woocommerce_created_customer', 'save_password_during_registration');


// Add OTP field dynamically to WooCommerce login form
// Add OTP field to WooCommerce login form
add_action('woocommerce_login_form', 'add_phone_number_otp_field_to_login');
function add_phone_number_otp_field_to_login() {
    wp_nonce_field('whatsapp_login_otp_action', 'whatsapp_login_otp_nonce'); // Security nonce
    ?>
    <p class="form-row form-row-wide">
        <label for="login_otp_code"><?php _e('OTP Code', 'whatsapp-plugin'); ?></label>
        <input type="text" class="input-text" name="login_otp_code" id="login_otp_code" required />
        <button type="button" id="send_login_otp" class="button"><?php _e('Send OTP', 'whatsapp-plugin'); ?></button>
    </p>
    <?php
}


// AJAX handler to send OTP
add_action('wp_ajax_send_login_otp', 'send_login_otp_via_ajax');
add_action('wp_ajax_nopriv_send_login_otp', 'send_login_otp_via_ajax');

function send_login_otp_via_ajax() {
    check_ajax_referer('whatsapp_login_otp_action', 'security'); // Security nonce check

    if (isset($_POST['username'])) {
        $username = sanitize_text_field($_POST['username']);

        // Attempt to find user by email, login, or the `user_email` meta key
        $user = get_user_by('email', $username) ?: get_user_by('login', $username);
        if (!$user) {
            $user_query = new WP_User_Query([
                'meta_key' => 'user_email',
                'meta_value' => $username,
                'number' => 1,
            ]);

            $users = $user_query->get_results();
            $user = !empty($users) ? $users[0] : null;
        }

        if ($user) {
            $phone_number = get_user_meta($user->ID, 'phone_number', true);

            // Log the phone number for debugging
            error_log("Found Phone Number: " . ($phone_number ? $phone_number : 'None'));

            if ($phone_number) {
                // Ensure phone number has the correct prefix
                if (substr($phone_number, 0, 1) !== '+') {
                    $phone_number =  $phone_number;
                }

                error_log("Phone Number with Prefix: " . $phone_number);

                $otp = rand(100000, 999999);
                if (!session_id()) {
                    session_start();
                }
                $_SESSION['otp'] = $otp;
                $_SESSION['otp_timestamp'] = time();
                error_log("Generated OTP: " . $otp);

                // Send OTP via WhatsApp API
                whatsapp_send_message_via_api(
                    $phone_number,
                    'authentication_code_copy_code_button',
                    'en_US',
                    __('Your OTP Code', 'whatsapp-plugin'),
                    __('Use the OTP below to log in:', 'whatsapp-plugin'),
                    true,
                    $otp
                );

                wp_send_json_success(['message' => __('OTP sent successfully.', 'whatsapp-plugin')]);
            } else {
                error_log("Error: Phone number not found for user ID {$user->ID}");
                wp_send_json_error(['message' => __('Phone number not found for this user.', 'whatsapp-plugin')]);
            }
        } else {
            error_log("Error: Invalid email, username, or associated user - Input: " . $username);
            wp_send_json_error(['message' => __('Invalid email, username, or associated user.', 'whatsapp-plugin')]);
        }
    } else {
        error_log("Error: Invalid AJAX request - Missing username");
        wp_send_json_error(['message' => __('Invalid request.', 'whatsapp-plugin')]);
    }
}




// Validate OTP during login
add_action('wp_authenticate', 'validate_login_otp', 10, 2);
function validate_login_otp($username, $password) {
    if (isset($_POST['login_otp_code'])) {
        $otp_code = sanitize_text_field($_POST['login_otp_code']);
        if (!session_id()) {
            session_start();
        }

        // Verify OTP
        if (!isset($_SESSION['otp']) || trim($otp_code) !== trim($_SESSION['otp'])) {
            wp_die(__('Invalid or expired OTP code.', 'whatsapp-plugin'));
        }

        $otp_lifetime = 10 * 60; // OTP expiry time: 10 minutes
        if (isset($_SESSION['otp_timestamp']) && (time() - $_SESSION['otp_timestamp']) > $otp_lifetime) {
            unset($_SESSION['otp'], $_SESSION['otp_timestamp']);
            wp_die(__('OTP has expired. Please request a new one.', 'whatsapp-plugin'));
        }

        // Clear session data on successful validation
        unset($_SESSION['otp'], $_SESSION['otp_timestamp']);
    } else {
        wp_die(__('OTP code is required for login.', 'whatsapp-plugin'));
    }
}

// Handle OTP sending for other forms via AJAX
add_action('wp_ajax_send_otp', 'send_otp_via_ajax');
add_action('wp_ajax_nopriv_send_otp', 'send_otp_via_ajax');
function send_otp_via_ajax() {
    check_ajax_referer('whatsapp_otp_action', 'security'); // Verify nonce for security

    if (isset($_POST['phone_number'])) {
        $phone_number = sanitize_text_field($_POST['phone_number']);

        $otp = rand(100000, 999999); // Generate OTP
        if (!session_id()) {
            session_start(); // Start session if not started
        }
        $_SESSION['otp'] = $otp; // Store OTP in session
        $_SESSION['otp_timestamp'] = time(); // Timestamp for expiry

        // Call WhatsApp API to send the OTP
        whatsapp_send_message_via_api(
            $phone_number,
            'authentication_code_copy_code_button',
            'en_US',
            __('Your OTP Code', 'whatsapp-plugin'),
            __('Please use the OTP below to verify your identity.', 'whatsapp-plugin'),
            true,
            $otp
        );

        wp_send_json_success(); // Send success response
    } else {
        wp_send_json_error(['message' => __('Invalid request.', 'whatsapp-plugin')]);
    }
}


// Enqueue script on the frontend
function enqueue_whatsapp_otp_script() {
    if (is_page('my-account')) { // Replace with your registration page condition
        wp_enqueue_script(
            'whatsapp-otp-script',
            plugins_url('/whatsapp-otp.js', __FILE__), // Update path as needed
            ['jquery'],
            '1.0',
            true
        );

        wp_localize_script('whatsapp-otp-script', 'ajax_object', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('whatsapp_otp_action'),
            'login_nonce' => wp_create_nonce('whatsapp_login_otp_action'), // Added login OTP nonce
        ]);
    }
}
add_action('wp_enqueue_scripts', 'enqueue_whatsapp_otp_script');



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
    error_log(print_r($response, true)); // Log API responses
}



?>
