<?php
if (!defined('ABSPATH'))
    exit;
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Section 1: Load Text Domain for Localization
add_action('plugins_loaded', 'whatsapp_plugin_load_textdomain');
function whatsapp_plugin_load_textdomain()
{
    load_plugin_textdomain('whatsapp-plugin', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

// Section 2: Add Settings Page to Admin Menu
add_action('admin_menu', 'whatsapp_admin_menu', 20);
function whatsapp_admin_menu()
{
    function whatsapp_tab()
    {
        include 'settings-page.php'; // Load the settings page
    }

    // add_menu_page(
    //     __('WhatsApp Config', 'whatsapp-plugin'), // Page title
    //     __('WhatsApp Config', 'whatsapp-plugin'), // Menu title
    //     'manage_options',
    //     'manage-whatsapp-config',
    //     'whatsapp_tab',
    //     '',
    //     8
    // );
}
add_action('admin_menu', function () {
    error_log('admin_menu hook is working!');
});

// Section 3: Register Settings Groups
add_action('admin_init', 'register_whatsapp_settings');
function register_whatsapp_settings()
{
    error_log('i am working');
    register_setting('whatsapp_settings_group', 'whatsapp_settings');
    register_setting('whatsapp_template_settings_group', 'whatsapp_template_settings', [
        'type' => 'array',
        'sanitize_callback' => 'sanitize_whatsapp_template_settings',
    ]);
}

function is_otp_enabled()
{
    $settings = get_option('whatsapp_settings', []);
    return isset($settings['enable_otp']) && $settings['enable_otp'] === '1';
}

// Sanitization callback for template settings
function sanitize_whatsapp_template_settings($input)
{
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
function whatsapp_field($key)
{
    global $whatsapp_settings;
    return isset($whatsapp_settings[$key]) ? $whatsapp_settings[$key] : '';
}
// error_log('whatsapp_field: ' . whatsapp_field('order_book_message'));





// Section 5: WhatsApp API Integration Functions
function whatsapp_send_message_via_api($phone, $template_name = '', $language_code = '', $header_text = '', $body_text = '', $is_otp = false, $otp_code = '', $json_data = null, $order = null)
{
    $api_key = whatsapp_field('api_key');
    $channel_id = whatsapp_field('channel_id');
    $api_url = whatsapp_field('api_url');

    if (empty($api_key) || empty($channel_id) || empty($api_url)) {
        error_log(__('WhatsApp API credentials or domain missing.', 'whatsapp-plugin'));
        return false;
    }

    // Prepare the URL for sending the message
    $url = "$api_url/api/v1.0/messages/send-template/" . $channel_id;

    $body = [];

    // If JSON data is provided, use it to construct the body
    if ($json_data !== null) {
        $decoded_data = json_decode($json_data, true);
        error_log(print_r($decoded_data, true));
        error_log("this was the json data");
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log(__('Invalid JSON data provided: ', 'whatsapp-plugin') . json_last_error_msg());
            return false;
        }

        if ($order) {
            $decoded_data = replace_variables_in_json($decoded_data, $order);
        }

        $body = $decoded_data;
        $body['to'] = '91' . $phone;
        error_log(print_r(json_encode($body), true));
    } elseif ($is_otp) {
        $body = build_otp_message_body($phone, $otp_code);
    } else {
        if ($order) {
            $body_text = replace_variables($body_text, $order);
            $header_text = replace_variables($header_text, $order);
        }
        $body = build_standard_message_body($phone, $template_name, $language_code, $header_text, $body_text);
    }

    // Send the request
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
    $decoded_response = json_decode($response_body, true);

    if (isset($decoded_response['error'])) {
        error_log(__('WhatsApp API Error: ', 'whatsapp-plugin') . $decoded_response['error']['message']);
        return false;
    }

    return true;
}


function format_price($amount, $order)
{
    return number_format($amount, 2) . ' ' . $order->get_currency();
}

function replace_variables($text, $order)
{
    // Get all items from order
    $items = $order->get_items();
    $products = [];
    $total_items = 0;

    // Process order items
    foreach ($items as $item) {
        $products[] = [
            'name' => $item->get_name(),
            'quantity' => $item->get_quantity(),
            'total' => $item->get_total()
        ];
        $total_items += $item->get_quantity();
    }

    // Build product lists
    $product_list = '';
    foreach ($products as $product) {
        $product_list .= sprintf(
            "%s (Qty: %d) - %s\n",
            $product['name'],
            $product['quantity'],
            format_price($product['total'], $order)
        );
    }

    // Define all variables
    $variables = [
        // Order details
        '%order_id%' => $order->get_id(),
        '%order_total%' => format_price($order->get_total(), $order),
        '%order_subtotal%' => format_price($order->get_subtotal(), $order),
        '%order_currency%' => $order->get_currency(),
        '{{Amount}}' => format_price($order->get_total(), $order),
        '%order_status%' => wc_get_order_status_name($order->get_status()), // Add order status
        // Customer details
        '%billing_first_name%' => $order->get_billing_first_name(),
        '%billing_last_name%' => $order->get_billing_last_name(),
        '%billing_full_name%' => $order->get_formatted_billing_full_name(),
        '%billing_email%' => $order->get_billing_email(),
        '%billing_phone%' => $order->get_billing_phone(),

        // Product details
        '%product_list%' => $product_list,
        '%total_items%' => $total_items,
        '%first_product_name%' => !empty($products) ? $products[0]['name'] : '',
        '%first_product_quantity%' => !empty($products) ? $products[0]['quantity'] : '',
        '%first_product_total%' => !empty($products) ? format_price($products[0]['total'], $order) : '',

        // Single product variables
        '%product_name%' => !empty($products) ? $products[0]['name'] : '',
        '%product_quantity%' => !empty($products) ? $products[0]['quantity'] : '',
        '%product_total%' => !empty($products) ? format_price($products[0]['total'], $order) : ''
    ];

    // Replace all variables in text
    foreach ($variables as $key => $value) {
        $text = str_replace($key, $value, $text);
    }

    return $text;
}

function replace_variables_in_json($data, $order)
{
    // Get all items from order
    $items = $order->get_items();
    $products = [];
    $total_items = 0;

    // Process order items
    foreach ($items as $item) {
        $products[] = [
            'name' => $item->get_name(),
            'quantity' => $item->get_quantity(),
            'total' => $item->get_total()
        ];
        $total_items += $item->get_quantity();
    }

    // Build product lists
    $product_list = '';
    foreach ($products as $product) {
        $product_list .= sprintf(
            "%s (Qty: %d) - %s\n",
            $product['name'],
            $product['quantity'],
            format_price($product['total'], $order)
        );
    }

    // Define all variables
    $variables = [
        // Order details
        '%order_id%' => $order->get_id(),
        '%order_total%' => format_price($order->get_total(), $order),
        '%order_subtotal%' => format_price($order->get_subtotal(), $order),
        '%order_currency%' => $order->get_currency(),
        '{{Amount}}' => format_price($order->get_total(), $order),
        '%order_status%' => wc_get_order_status_name($order->get_status()), // Add order status
        // Customer details
        '%billing_first_name%' => $order->get_billing_first_name(),
        '%billing_last_name%' => $order->get_billing_last_name(),
        '%billing_full_name%' => $order->get_formatted_billing_full_name(),
        '%billing_email%' => $order->get_billing_email(),
        '%billing_phone%' => $order->get_billing_phone(),

        // Product details
        '%product_list%' => $product_list,
        '%total_items%' => $total_items,
        '%first_product_name%' => !empty($products) ? $products[0]['name'] : '',
        '%first_product_quantity%' => !empty($products) ? $products[0]['quantity'] : '',
        '%first_product_total%' => !empty($products) ? format_price($products[0]['total'], $order) : '',

        // Single product variables
        '%product_name%' => !empty($products) ? $products[0]['name'] : '',
        '%product_quantity%' => !empty($products) ? $products[0]['quantity'] : '',
        '%product_total%' => !empty($products) ? format_price($products[0]['total'], $order) : ''
    ];

    // Replace variables in JSON data recursively
    array_walk_recursive($data, function (&$item) use ($variables) {
        if (is_string($item)) {
            foreach ($variables as $key => $value) {
                $item = str_replace($key, $value, $item);
            }
        }
    });

    return $data;
}
// Helper functions for body construction
function build_otp_message_body($phone, $otp_code)
{
    if (empty($phone) || empty($otp_code)) {
        error_log("Invalid parameters: phone or OTP code is empty");
        return false;
    }

    // Validate phone number format
    if (!preg_match('/^\d{10}$/', $phone)) {
        error_log("Invalid phone number format");
        return false;
    }

    $auth_message = whatsapp_field('auth_message');
    if (empty($auth_message)) {
        error_log("Failed to get auth message template");
        return false;
    }

    $new_body = json_decode($auth_message, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode failed: " . json_last_error_msg());
        return false;
    }

    try {
        $new_body['to'] = '91' . $phone;
        $new_body['template']['components'][0]['parameters'][0]['text'] = $otp_code;
        $new_body['template']['components'][1]['parameters'][0]['text'] = $otp_code;
    } catch (Exception $e) {
        error_log("Error building message body: " . $e->getMessage());
        return false;
    }

    return $new_body;
}

// ...existing code for build_standard_message_body...
function build_standard_message_body($phone, $template_name, $language_code, $header_text, $body_text)
{
    return [
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



// Section 6: WooCommerce Registration - Add Phone Number and OTP Fields
function add_phone_number_otp_fields_to_registration()
{
    if (!is_otp_enabled()) {
        // Skip OTP validation if the feature is disabled
        return;
    }
    wp_nonce_field('whatsapp_otp_action', 'whatsapp_otp_nonce');
    ?>
    <p class="form-row form-row-wide">
        <label for="reg_phone_number"><?php _e('Phone Number', 'whatsapp-plugin'); ?></label>
        <input type="text" class="input-text" name="phone_number" id="reg_phone_number" value="<?php if (!empty($_POST['phone_number']))
            echo esc_attr($_POST['phone_number']); ?>" required />
    </p>

    <p class="form-row form-row-wide">
        <label for="reg_otp"><?php _e('OTP Code', 'whatsapp-plugin'); ?></label>
        <input type="text" class="input-text" name="otp_code" id="reg_otp" value="" required />
        <button type="button" id="send_otp" class="button"><?php _e('Send OTP', 'whatsapp-plugin'); ?></button>
    </p>
    <?php
}

function add_phone_number_column_to_wc_customer_lookup()
{
    if (!is_otp_enabled()) {
        // Skip OTP validation if the feature is disabled
        return;
    }
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
add_action('woocommerce_created_customer', function ($customer_id) {
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
function store_phone_number_in_wc_customer_lookup($customer_id, $phone_number)
{
    if (!is_otp_enabled()) {
        // Skip OTP validation if the feature is disabled
        return;
    }
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
add_action('woocommerce_created_customer', function ($customer_id) {
    if (isset($_POST['phone_number']) && !empty($_POST['phone_number'])) {
        $phone_number = sanitize_text_field($_POST['phone_number']);
        store_phone_number_in_wc_customer_lookup($customer_id, $phone_number);
    }
});



function activate_whatsapp_plugin()
{
    add_phone_number_column_to_wc_customer_lookup();
}
register_activation_hook(__FILE__, 'activate_whatsapp_plugin');



// Section 7: Validate OTP and Phone Number during Registration
function validate_phone_number_and_otp($username, $email, $validation_errors)
{
    if (!is_otp_enabled()) {
        // Skip OTP validation if the feature is disabled
        return;
    }
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
add_action('woocommerce_created_customer', function ($customer_id) {
    if (isset($_POST['phone_number']) && !empty($_POST['phone_number'])) {
        $phone_number = sanitize_text_field($_POST['phone_number']);

        // Save the phone number in the wp_usermeta table.
        update_user_meta($customer_id, 'phone_number', $phone_number);
    }
});
// Add password fields to WooCommerce registration form
function add_password_fields_to_registration()
{
    if (!is_otp_enabled()) {
        // Skip OTP validation if the feature is disabled
        return;
    }
    ?>
    <p class="form-row form-row-wide">
        <label for="reg_password"><?php _e('Password', 'woocommerce'); ?> <span class="required">*</span></label>
        <input type="password" class="input-text" name="password" id="reg_password" required />
    </p>

    <p class="form-row form-row-wide">
        <label for="reg_password_confirm"><?php _e('Confirm Password', 'woocommerce'); ?> <span
                class="required">*</span></label>
        <input type="password" class="input-text" name="password_confirm" id="reg_password_confirm" required />
    </p>
    <?php
}
add_action('woocommerce_register_form', 'add_password_fields_to_registration');

// Validate password fields during registration
function validate_password_fields($username, $email, $validation_errors)
{
    if (!is_otp_enabled()) {
        // Skip OTP validation if the feature is disabled
        return;
    }
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
function save_password_during_registration($customer_id)
{
    if (!is_otp_enabled()) {
        // Skip OTP validation if the feature is disabled
        return;
    }
    if (isset($_POST['password'])) {
        wp_set_password($_POST['password'], $customer_id);
    }
}
add_action('woocommerce_created_customer', 'save_password_during_registration');


// Add OTP field dynamically to WooCommerce login form
// Add OTP field to WooCommerce login form
add_action('woocommerce_login_form', 'add_phone_number_otp_field_to_login');
function add_phone_number_otp_field_to_login()
{
    if (!is_otp_enabled()) {
        // Skip OTP validation if the feature is disabled
        return;
    }
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

function send_login_otp_via_ajax()
{
    if (!is_otp_enabled()) {
        // Skip OTP validation if the feature is disabled
        return;
    }
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
                    $phone_number = $phone_number;
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
function validate_login_otp($username, $password)
{
    if (!is_otp_enabled()) {
        // Skip OTP validation if the feature is disabled
        return;
    }
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
function send_otp_via_ajax()
{
    if (!is_otp_enabled()) {
        // Skip OTP validation if the feature is disabled
        return;
    }
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
function enqueue_whatsapp_otp_script()
{
    if (!is_otp_enabled()) {
        // Skip OTP validation if the feature is disabled
        return;
    }
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
function send_order_confirmation_whatsapp($order_id)
{

    // Validate the order ID
    if (!$order_id || !is_numeric($order_id)) {
        error_log(__('Invalid order ID provided.', 'whatsapp-plugin'));
        return;
    }

    // Retrieve the order
    $order = wc_get_order($order_id);
    error_log("Order ID send_order_confirmation_whatsapp is working: " . $order_id);
    // Ensure the order object is valid
    if (!$order || !is_a($order, 'WC_Order')) {
        error_log(sprintf(__('Order not found or invalid for ID %s.', 'whatsapp-plugin'), $order_id));
        return;
    }

    // Get the billing phone number
    $billing_phone = $order->get_billing_phone();
    error_log("Billing Phone: " . $billing_phone);

    // Ensure a valid phone number is available
    if (empty($billing_phone)) {
        error_log(sprintf(__('No billing phone number found for order ID %s.', 'whatsapp-plugin'), $order_id));
        return;
    }

    // Fetch the JSON data for the order confirmation
    $json_data = whatsapp_field('order_book_message');
    error_log(whatsapp_field('order_book_message'));
    // error_log("new order book json".$json_data);
    if (!empty($json_data)) {
        // Call the WhatsApp API with the provided JSON data
        $result = whatsapp_send_message_via_api(
            $billing_phone,
            null, // Template name is not needed when using JSON
            null, // Language code is not needed when using JSON
            null, // Header text is not needed when using JSON
            null, // Body text is not needed when using JSON
            false, // Not an OTP
            '',    // OTP code is not needed
            $json_data, // Pass the JSON data
            $order // Pass the order object for variable replacement
        );

        // Log the result
        if ($result) {
            error_log(sprintf(__('WhatsApp message sent successfully for order ID %s.', 'whatsapp-plugin'), $order_id));
        } else {
            error_log(sprintf(__('Failed to send WhatsApp message for order ID %s.', 'whatsapp-plugin'), $order_id));
        }
    } else {
        error_log(__('Order book message JSON is empty.', 'whatsapp-plugin'));
    }
}


// Order Cancellation
add_action('woocommerce_order_status_cancelled', 'send_order_cancellation_whatsapp', 10, 1);
function send_order_cancellation_whatsapp($order_id)
{
    // Validate the order ID
    if (!$order_id || !is_numeric($order_id)) {
        error_log(__('Invalid order ID provided.', 'whatsapp-plugin'));
        return;
    }

    // Retrieve the order
    $order = wc_get_order($order_id);

    // Ensure the order object is valid
    if (!$order || !is_a($order, 'WC_Order')) {
        error_log(sprintf(__('Order not found or invalid for ID %s.', 'whatsapp-plugin'), $order_id));
        return;
    }

    // Get the billing phone number
    $billing_phone = $order->get_billing_phone();
    error_log("Billing Phone: " . $billing_phone);

    // Ensure a valid phone number is available
    if (empty($billing_phone)) {
        error_log(sprintf(__('No billing phone number found for order ID %s.', 'whatsapp-plugin'), $order_id));
        return;
    }

    // Fetch the JSON data for the order confirmation
    $json_data = whatsapp_field('order_cancellation_message');
    error_log("cancellation json" . whatsapp_field('order_cancellation_message') . "Now ended");

    if (!empty($json_data)) {
        // Call the WhatsApp API with the provided JSON data
        $result = whatsapp_send_message_via_api(
            $billing_phone,
            null, // Template name is not needed when using JSON
            null, // Language code is not needed when using JSON
            null, // Header text is not needed when using JSON
            null, // Body text is not needed when using JSON
            false, // Not an OTP
            '',    // OTP code is not needed
            $json_data, // Pass the JSON data
            $order // Pass the order object for variable replacement
        );

        // Log the result
        if ($result) {
            error_log(sprintf(__('WhatsApp message sent successfully for order ID %s.', 'whatsapp-plugin'), $order_id));
        } else {
            error_log(sprintf(__('Failed to send WhatsApp message for order ID %s.', 'whatsapp-plugin'), $order_id));
        }
    } else {
        error_log(__('Order book message JSON is empty.', 'whatsapp-plugin'));
    }
}

// Order Status Changes
add_action('woocommerce_order_status_changed', 'send_order_status_update_whatsapp', 10, 4);
function send_order_status_update_whatsapp($order_id, $old_status, $new_status, $order)
{
    // Validate the order ID
    if (!$order_id || !is_numeric($order_id)) {
        error_log(__('Invalid order ID provided.', 'whatsapp-plugin'));
        return;
    }

    // Retrieve the order
    $order = wc_get_order($order_id);

    // Ensure the order object is valid
    if (!$order || !is_a($order, 'WC_Order')) {
        error_log(sprintf(__('Order not found or invalid for ID %s.', 'whatsapp-plugin'), $order_id));
        return;
    }

    // Get the billing phone number
    $billing_phone = $order->get_billing_phone();
    error_log("Billing Phone: " . $billing_phone);

    // Ensure a valid phone number is available
    if (empty($billing_phone)) {
        error_log(sprintf(__('No billing phone number found for order ID %s.', 'whatsapp-plugin'), $order_id));
        return;
    }

    // Fetch the JSON data for the order confirmation
    $json_data = whatsapp_field('order_status_change_message');

    if (!empty($json_data)) {
        // Call the WhatsApp API with the provided JSON data
        $result = whatsapp_send_message_via_api(
            $billing_phone,
            null, // Template name is not needed when using JSON
            null, // Language code is not needed when using JSON
            null, // Header text is not needed when using JSON
            null, // Body text is not needed when using JSON
            false, // Not an OTP
            '',    // OTP code is not needed
            $json_data, // Pass the JSON data
            $order // Pass the order object for variable replacement
        );

        // Log the result
        if ($result) {
            error_log(sprintf(__('WhatsApp message sent successfully for order ID %s.', 'whatsapp-plugin'), $order_id));
        } else {
            error_log(sprintf(__('Failed to send WhatsApp message for order ID %s.', 'whatsapp-plugin'), $order_id));
        }
    } else {
        error_log(__('Order book message JSON is empty.', 'whatsapp-plugin'));
    }
}

// Custom Admin Notification
add_action('woocommerce_order_status_completed', 'notify_admin_order_completed', 10, 1);
function notify_admin_order_completed($order_id)
{    // Validate the order ID
    if (!$order_id || !is_numeric($order_id)) {
        error_log(__('Invalid order ID provided.', 'whatsapp-plugin'));
        return;
    }

    // Retrieve the order
    $order = wc_get_order($order_id);

    // Ensure the order object is valid
    if (!$order || !is_a($order, 'WC_Order')) {
        error_log(sprintf(__('Order not found or invalid for ID %s.', 'whatsapp-plugin'), $order_id));
        return;
    }

    // Get the billing phone number
    $billing_phone = $order->get_billing_phone();
    error_log("Billing Phone: " . $billing_phone);

    // Ensure a valid phone number is available
    if (empty($billing_phone)) {
        error_log(sprintf(__('No billing phone number found for order ID %s.', 'whatsapp-plugin'), $order_id));
        return;
    }

    // Fetch the JSON data for the order confirmation
    $json_data = whatsapp_field('order_success_message');
    error_log("new order book json" . $json_data);
    if (!empty($json_data)) {
        // Call the WhatsApp API with the provided JSON data
        $result = whatsapp_send_message_via_api(
            $billing_phone,
            null, // Template name is not needed when using JSON
            null, // Language code is not needed when using JSON
            null, // Header text is not needed when using JSON
            null, // Body text is not needed when using JSON
            false, // Not an OTP
            '',    // OTP code is not needed
            $json_data, // Pass the JSON data
            $order // Pass the order object for variable replacement
        );

        // Log the result
        if ($result) {
            // error_log(sprintf(__('WhatsApp message sent successfully for order ID %s.', 'whatsapp-plugin'), $order_id));
        } else {
            // error_log(sprintf(__('Failed to send WhatsApp message for order ID %s.', 'whatsapp-plugin'), $order_id));
        }
    } else {
        error_log(__('Order book message JSON is empty.', 'whatsapp-plugin'));
    }
}

// Section 7: Fetch Plugin Settings with AJAX
add_action('wp_ajax_fetch_whatsapp_channels', 'fetch_whatsapp_channels');
function fetch_whatsapp_channels()
{
    if (!isset($_POST['api_key']) || !isset($_POST['api_url'])) {
        wp_send_json_error(['message' => __('Invalid API credentials.', 'whatsapp-plugin')]);
        return;
    }

    $api_key = sanitize_text_field($_POST['api_key']);
    $api_url = sanitize_text_field($_POST['api_url']);

    $response = wp_remote_get("$api_url/v1/channels", [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
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