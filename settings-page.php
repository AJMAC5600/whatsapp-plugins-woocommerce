<?php
if (!defined('ABSPATH')) exit;

require 'vendor/autoload.php'; // Ensure Guzzle is autoloaded
use GuzzleHttp\Client;

// Fetch saved WhatsApp settings
function whatsapp_get_value($var) {
    global $whatsapp_settings;
    return isset($whatsapp_settings[$var]) ? $whatsapp_settings[$var] : '';
}

// Initialize variables
$api_key = whatsapp_get_value('api_key');
$api_url = whatsapp_get_value('api_url');
$templates = [];

// Fetch templates only if API key and URL are provided
if ($api_key && $api_url) {
    try {
        // Create a Guzzle client
        $client = new Client();
        $response = $client->request('GET', $api_url . '/api/v1.0/templates', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
            ],
        ]);

        // Decode the JSON response
        $templates = json_decode($response->getBody()->getContents(), true);

        // Check for JSON decoding issues
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "<p>Error decoding API response: " . json_last_error_msg() . "</p>";
            $templates = [];
        }
    } catch (\Exception $e) {
        // Handle API request errors
        echo "<p>Error fetching templates: " . $e->getMessage() . "</p>";
    }
}

?>

<div class="wrap woocommerce">
    <h2><?php _e('WhatsApp Configuration', 'whatsapp-plugin'); ?></h2>

    <form method="post" action="options.php" id="whatsapp-config-form">
        <?php settings_fields('whatsapp_settings_group'); ?>
        <input type="hidden" name="redirect_to" value="<?php echo esc_url(admin_url('admin.php?page=whatsapp-settings')); ?>">

        <!-- API Details Section -->
        <h3 class="title"><?php _e('API Details', 'whatsapp-plugin'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="api_key"><?php _e('API Key', 'whatsapp-plugin'); ?></label></th>
                <td>
                    <input type="text" id="api_key" name="whatsapp_settings[api_key]" value="<?php echo esc_attr($api_key); ?>" required>
                </td>
            </tr>
            <tr>
                <th><label for="api_url"><?php _e('API URL', 'whatsapp-plugin'); ?></label></th>
                <td>
                    <input type="text" id="api_url" name="whatsapp_settings[api_url]" value="<?php echo esc_attr($api_url); ?>" required>
                </td>
            </tr>
        </table>

        <!-- Template Configuration Section -->
        <h3 class="title" id="template-config-title" style="display: <?php echo ($api_key && $api_url) ? 'block' : 'none'; ?>;"><?php _e('Template Configuration', 'whatsapp-plugin'); ?></h3>
        <table class="form-table" id="template-config-table" style="display: <?php echo ($api_key && $api_url) ? 'block' : 'none'; ?>;">
            <tr>
                <th><label for="category"><?php _e('Category', 'whatsapp-plugin'); ?></label></th>
                <td>
                    <select id="category" name="whatsapp_settings[category]" required>
                        <option value=""><?php _e('Select Category', 'whatsapp-plugin'); ?></option>
                        <option value="UTILITY"><?php _e('Utility', 'whatsapp-plugin'); ?></option>
                        <option value="AUTHENTICATION"><?php _e('Authentication', 'whatsapp-plugin'); ?></option>
                        <option value="MARKETING"><?php _e('Marketing', 'whatsapp-plugin'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="template_name"><?php _e('Template Name', 'whatsapp-plugin'); ?></label></th>
                <td>
                    <select id="template_name" name="whatsapp_settings[template_name]" required>
                        <option value=""><?php _e('Select Template', 'whatsapp-plugin'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="channel_id"><?php _e('Channel ID', 'whatsapp-plugin'); ?></label></th>
                <td>
                    <select id="channel_id" name="whatsapp_settings[channel_id]" required>
                        <option value=""><?php _e('Select Channel ID', 'whatsapp-plugin'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="message"><?php _e('Message', 'whatsapp-plugin'); ?></label></th>
                <td>
                    <textarea id="message" name="whatsapp_settings[message]" rows="5" cols="50"></textarea>
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" class="button-primary" value="<?php _e('Save Configuration', 'whatsapp-plugin'); ?>">
        </p>
    </form>
</div>

<script>
jQuery(document).ready(function ($) {
    const categoryDropdown = $('#category');
    const templateNameDropdown = $('#template_name');
    const channelIdDropdown = $('#channel_id');
    const messageTextarea = $('#message');
    const templateConfigTitle = $('#template-config-title');
    const templateConfigTable = $('#template-config-table');
    
    // Templates fetched from the server
    const templates = <?php echo json_encode($templates); ?>;

    // Show/hide template configuration section based on API key and URL
    const apiKey = $('#api_key').val();
    const apiUrl = $('#api_url').val();

    if (!apiKey || !apiUrl) {
        templateConfigTitle.hide();
        templateConfigTable.hide();
    }

    // Filter templates by category and update the template name dropdown
    categoryDropdown.on('change', function () {
        const selectedCategory = $(this).val();
        templateNameDropdown.empty().append('<option value="">' + '<?php _e('Select Template', 'whatsapp-plugin'); ?>' + '</option>');

        if (selectedCategory) {
            const filteredTemplates = templates.filter(template => template.category === selectedCategory);
            filteredTemplates.forEach(template => {
                templateNameDropdown.append('<option value="' + template.name + '" data-components=\'' + JSON.stringify(template.components) + '\' data-id="' + template.Id + '">' + template.name + '</option>');
            });
        }
    });

    // Update message and channel ID when a template is selected
    templateNameDropdown.on('change', function () {
        const selectedOption = $(this).find(':selected');
        const components = selectedOption.data('components');
        const id = selectedOption.data('id');

        // Populate the message textarea
        messageTextarea.val('');
        if (components) {
            let messageBody = '';
            components.forEach(component => {
                if (component.type === 'BODY' && component.text) {
                    messageBody += component.text + "\n";
                }
            });
            messageTextarea.val(messageBody.trim());
        }

        // Populate the Channel ID dropdown
        channelIdDropdown.empty().append('<option value="">' + '<?php _e('Select Channel ID', 'whatsapp-plugin'); ?>' + '</option>');
        if (id) {
            channelIdDropdown.append('<option value="' + id + '">' + id + '</option>');
        }
    });

    // Dynamically hide/show Template Configuration section based on API key and URL inputs
    $('#api_key, #api_url').on('change', function() {
        const apiKey = $('#api_key').val();
        const apiUrl = $('#api_url').val();

        if (apiKey && apiUrl) {
            templateConfigTitle.show();
            templateConfigTable.show();
        } else {
            templateConfigTitle.hide();
            templateConfigTable.hide();
        }
    });
});
</script>
