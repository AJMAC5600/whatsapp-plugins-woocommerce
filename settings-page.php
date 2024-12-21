<?php

if (!defined('ABSPATH')) exit;

global $whatsapp_settings;

// Fetch saved WhatsApp settings
function whatsapp_get_value($var) {
    global $whatsapp_settings;
    return isset($whatsapp_settings[$var]) ? $whatsapp_settings[$var] : '';
}
?>
<style>
    h2{
        background-color: gray;
        padding: 5px !important;
        height: 30px !important;
    }
</style>
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
                    <input type="text" id="api_key" name="whatsapp_settings[api_key]" value="<?php echo whatsapp_get_value('api_key'); ?>" required>
                </td>
            </tr>
            <tr>
                <th><label for="api_url"><?php _e('API URL', 'whatsapp-plugin'); ?></label></th>
                <td>
                    <input type="text" id="api_url" name="whatsapp_settings[api_url]" value="<?php echo whatsapp_get_value('api_url'); ?>" required>
                </td>
            </tr>
        </table>

        <!-- Template Configuration Section -->
        <div id="template-config-section">
            <h3 class="title"><?php _e('Template Configuration', 'whatsapp-plugin'); ?></h3>
            <table class="form-table">
                <tr>
                    <th><label for="channel_id"><?php _e('Channel ID', 'whatsapp-plugin'); ?></label></th>
                    <td>
                        <input type="text" id="channel_id" name="whatsapp_settings[channel_id]" value="<?php echo whatsapp_get_value('channel_id'); ?>" required>
                    </td>
                </tr>
                <tr>
                    <th><label for="selected_category"><?php _e('Category', 'whatsapp-plugin'); ?></label></th>
                    <td>
                        <select id="selected_category" name="whatsapp_settings[selected_category]" required>
                            <option value=""><?php _e('Select Category', 'whatsapp-plugin'); ?></option>
                            <option value="MARKETING" <?php echo whatsapp_get_value('selected_category') === 'MARKETING' ? 'selected' : ''; ?>><?php _e('MARKETING', 'whatsapp-plugin'); ?></option>
                            <option value="UTILITY" <?php echo whatsapp_get_value('selected_category') === 'UTILITY' ? 'selected' : ''; ?>><?php _e('UTILITY', 'whatsapp-plugin'); ?></option>
                            <option value="AUTHENTICATION" <?php echo whatsapp_get_value('selected_category') === 'AUTHENTICATION' ? 'selected' : ''; ?>><?php _e('AUTHENTICATION', 'whatsapp-plugin'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="template_name"><?php _e('Template Name', 'whatsapp-plugin'); ?></label></th>
                    <td>
                        <input type="text" id="template_name" name="whatsapp_settings[template_name]" value="<?php echo whatsapp_get_value('template_name'); ?>" required>
                    </td>
                </tr>
                <tr>
                    <th><label for="message"><?php _e('Message', 'whatsapp-plugin'); ?></label></th>
                    <td>
                        <textarea id="message" name="whatsapp_settings[message]" rows="5" cols="50" required><?php echo whatsapp_get_value('message'); ?></textarea>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Save Button -->
        <p class="submit">
            <input type="submit" class="button-primary" value="<?php _e('Save and Send Data', 'whatsapp-plugin'); ?>">
        </p>
    </form>
</div>

<script>
jQuery(document).ready(function ($) {
    const templateConfigSection = $('#template-config-section');
    const apiKeyField = $('#api_key');
    const apiUrlField = $('#api_url');

    function toggleTemplateConfig() {
        if (apiKeyField.val().trim() && apiUrlField.val().trim()) {
            templateConfigSection.removeClass('hidden');
        } else {
            templateConfigSection.addClass('hidden');
        }
    }

    toggleTemplateConfig();

    apiKeyField.on('input', toggleTemplateConfig);
    apiUrlField.on('input', toggleTemplateConfig);
});
</script>
