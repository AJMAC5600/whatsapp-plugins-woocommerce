# WhatsApp Notify for WooCommerce

This WordPress plugin integrates WhatsApp messaging with your WooCommerce store. It allows you to automatically send notifications to customers for various order events and provides an optional OTP (One-Time Password) system for user registration and login.

## Features

-   **Automated Order Notifications**: Send WhatsApp messages to customers for key WooCommerce events:
    -   New Order Placed (`Order Book`)
    -   Order Cancelled
    -   Order Status Changed
    -   Order Completed (`Order Success`)
-   **OTP Verification (Optional)**: Enhance security by enabling WhatsApp-based OTP verification for:
    -   User registration
    -   User login
-   **Dynamic Template Configuration**:
    -   Fetches available WhatsApp message templates directly from your provider's API.
    -   Dynamically generates input fields based on the selected template's variables.
    -   Map WooCommerce data to your message templates using placeholders.
-   **Easy-to-Use Admin Interface**: Configure all settings from a dedicated "WhatsApp Config" page within the WordPress admin dashboard.

## Prerequisites

-   WordPress installation
-   WooCommerce plugin installed and activated
-   A WhatsApp Business API account with access to an API Key, API URL, and configured message templates.

## Installation

1.  Download the plugin repository as a ZIP file.
2.  In your WordPress admin dashboard, navigate to **Plugins > Add New**.
3.  Click on the **Upload Plugin** button at the top of the page.
4.  Choose the downloaded ZIP file and click **Install Now**.
5.  After the installation is complete, click **Activate Plugin**.

## Configuration

After activating the plugin, a "WhatsApp Config" menu will appear in your WordPress admin sidebar.

1.  **API Credentials**:
    -   Navigate to the **WhatsApp Config** page.
    -   Enter your **API Key** and **API URL** provided by your WhatsApp Business API provider. The other configuration options will appear once these are filled.

2.  **Channel Selection**:
    -   The plugin will automatically fetch and display your available WhatsApp channel number. Select it from the dropdown.

3.  **OTP Feature (Optional)**:
    -   Check the **Enable OTP Feature** box if you want to use WhatsApp for user registration and login verification.
    -   If enabled, a new section for "Authentication Templates" will appear. Select the template you want to use for sending OTP codes.

4.  **Event Notifications**:
    -   Under the "Event" section, choose an event you want to configure (e.g., `Order Book`).
    -   **Category**: Select the category of your message template (e.g., `UTILITY`, `MARKETING`).
    -   **Template**: Choose the specific message template for this event.
    -   **Map Variables**: The plugin will display dropdown menus for your template's header and body variables. Map these to the corresponding WooCommerce data placeholders. Available placeholders include:
        -   `%order_id%`: The order's unique ID.
        -   `%order_total%`: The total amount of the order.
        -   `%order_status%`: The current status of the order.
        -   `%billing_first_name%`: The customer's first name.
        -   `%billing_full_name%`: The customer's full name.
        -   `%product_list%`: A list of all products in the order.
        -   ...and more.
    -   Repeat this process for each event you wish to enable.

5.  **Save Changes**:
    -   Click the **Save Changes** button to store your configuration.

## How It Works

### Event Notifications

The plugin uses WooCommerce hooks (e.g., `woocommerce_thankyou`, `woocommerce_order_status_changed`) to detect order events. When an event is triggered, it retrieves the corresponding message template and variable mappings from your settings. It then replaces the placeholders (like `%order_id%`) with live data from the order and sends the formatted message to the customer's billing phone number via the configured WhatsApp API.

### OTP System

-   **Registration**: When enabled, new fields for "Phone Number" and "OTP Code" are added to the WooCommerce registration form. A user enters their phone number and clicks "Send OTP". An AJAX request sends the OTP to their number via WhatsApp. The user must enter the correct OTP to complete registration.
-   **Login**: An "OTP Code" field is added to the login form. After entering their username/email, the user clicks "Send OTP" to receive a code on their registered phone number. The correct OTP is required to log in, in addition to their password.
