# WhatsApp Plugin for WooCommerce

A powerful plugin to integrate WhatsApp functionality with your WooCommerce store. This plugin allows store owners to send OTPs for login and registration, communicate with customers via WhatsApp templates, and more.

---

## Features

- **OTP Integration:**
  - Enable or disable OTP functionality for login and registration.
  - OTP expiration management.

- **WhatsApp Messaging:**
  - Send messages via WhatsApp using pre-configured templates.
  - Dynamically populate templates with user-specific data.

- **WooCommerce Compatibility:**
  - Add phone number and OTP fields to WooCommerce registration and login forms.
  - Validate OTPs during registration and login.
  - Store customer phone numbers in WooCommerce database.

- **Admin Settings:**
  - API configuration for WhatsApp messaging.
  - Enable or disable OTP features.
  - Configure message templates and channels.

---

## Requirements

- WordPress 5.0 or higher
- WooCommerce 4.0 or higher
- PHP 7.2 or higher
- WhatsApp Business API credentials

---

## Installation

1. Download the plugin ZIP file.
2. Log in to your WordPress admin dashboard.
3. Navigate to `Plugins > Add New > Upload Plugin`.
4. Upload the ZIP file and click `Install Now`.
5. Activate the plugin.

---

## Configuration

1. Go to `Settings > WhatsApp Config` in your WordPress admin dashboard.
2. Configure the following:
   - **API Key**: Your WhatsApp Business API key.
   - **API URL**: The URL for the WhatsApp Business API.
   - **Enable OTP Feature**: Toggle to enable or disable OTP functionality.
   - **Template Configuration**:
     - Select a category.
     - Choose a template name and channel ID.
     - Configure message body and parameters.

---

## How to Use with WooCommerce

### Registration

1. **Enable OTP for Registration**:
   - Ensure the "Enable OTP Feature" option is toggled on in the plugin settings.

2. **User Registration Flow**:
   - Users are prompted to enter their phone number and OTP during WooCommerce registration.
   - OTPs are sent via WhatsApp to the provided phone number.
   - Users must validate the OTP before registration is completed.

3. **Store Customer Phone Numbers**:
   - Customer phone numbers are stored in WooCommerce's database for future reference.

### Login

1. **Enable OTP for Login**:
   - Ensure the "Enable OTP Feature" option is toggled on in the plugin settings.

2. **User Login Flow**:
   - Users are prompted to enter their phone number and OTP during login.
   - OTPs are sent via WhatsApp to the phone number associated with the account.
   - OTP validation is required to complete the login process.

### Messaging

1. **Send Messages to Customers**:
   - Use the WhatsApp API to send order updates, promotional messages, or OTPs.
   - Configure message templates in the plugin settings.

---

## Shortcode Usage

You can use the following shortcode to display the OTP form anywhere on your site:

```php
[whatsapp_otp_form]
```

---

## Hooks and Filters

### Available Hooks

- **`woocommerce_register_post`**: Validate phone number and OTP during registration.
- **`woocommerce_login_form`**: Add OTP field to the WooCommerce login form.
- **`wp_authenticate`**: Validate OTP during login.

### Filters

- **`whatsapp_plugin_validate_otp`**: Customize OTP validation logic.
- **`whatsapp_plugin_send_message`**: Modify the WhatsApp API message payload.

---

## Troubleshooting

### Common Issues

1. **OTP Not Being Sent**:
   - Verify your API Key and URL in the plugin settings.
   - Check server logs for errors.

2. **Templates Not Loading**:
   - Ensure templates are correctly configured in your WhatsApp Business API.
   - Verify that the API key has the required permissions.

3. **Login/Registration OTP Validation Failing**:
   - Check if the OTP session is active.
   - Verify the OTP expiration time in the plugin settings.

---

## Support

For support, please contact our team via the [support page](https://example.com/support).

---

## Changelog

### Version 1.0.0
- Initial release with OTP and WhatsApp messaging features.
- WooCommerce integration for login and registration.

---

## License

This plugin is licensed under the [GNU General Public License v2.0](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html).

---

## Credits

Developed by [Abhishek Jha].
