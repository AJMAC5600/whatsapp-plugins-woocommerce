jQuery(document).ready(function ($) {
    if (typeof custom_ajax_object === "undefined") {
        console.error("custom_ajax_object is not defined. Check your wp_localize_script implementation.");
        return;
    }

    const apiKeyField = jQuery("#api_key");
    const apiUrlField = jQuery("#api_url");
    const configurableSections = jQuery(".form-table").not(":has(#api_key, #api_url)");

    function toggleSections() {
        const apiKey = apiKeyField.val().trim();
        const apiUrl = apiUrlField.val().trim();

        if (apiKey && apiUrl) {
            configurableSections.show();
        } else {
            configurableSections.hide();
        }
    }

    toggleSections();
    apiKeyField.on("input", toggleSections);
    apiUrlField.on("input", toggleSections);

    const templates = custom_ajax_object.templates || [];
    const sections = ["order_book", "order_cancellation", "order_status_change", "order_success"];
    persistTemplateSelection();
    sections.forEach((section) => {
        const $templateDropdown = jQuery(`#${section}_template`);
        const $categoryDropdown = jQuery(`#${section}_category`);
        const $messageTextarea = jQuery(`#${section}_message`);

        // Add storage key for template data
        const TEMPLATE_DATA_KEY = `${section}_template_data`;

        $categoryDropdown.on("change", function () {
            const selectedCategory = jQuery(this).val();
            $templateDropdown.empty().append('<option value="">Select Template</option>');

            templates.forEach((template) => {
                if (template.category === selectedCategory) {
                    $templateDropdown.append(
                        `<option value="${template.name}">${template.name}</option>`
                    );
                }
            });

            // Restore selected template after category change
            const savedTemplate = localStorage.getItem(`${section}_template`);
            if (savedTemplate) {
                $templateDropdown.val(savedTemplate);
            }
        });

        $templateDropdown.on("change", function () {
            const selectedTemplate = jQuery(this).val();
            const channelNumber = jQuery(`#channel_id`).val();
            console.log(`Selected template: ${selectedTemplate}, Channel: ${channelNumber}`);

            if (!selectedTemplate || !channelNumber) {
                $messageTextarea.val("Please select a valid template and channel.");
                return;
            }

            // Save selected template to local storage
            localStorage.setItem(`${section}_template`, selectedTemplate);

            $templateDropdown.prop("disabled", true); // Disable during request

            jQuery.ajax({
                url: custom_ajax_object.ajaxurl, // Use the localized AJAX URL
                method: "POST",
                data: {
                    action: "fetch_template_payload",
                    template_name: selectedTemplate,
                    channel_number: channelNumber,
                    nonce: custom_ajax_object.nonce, // Pass the localized nonce
                },
                success: function (response) {
                    $templateDropdown.prop("disabled", false);
                    if (response.success) {
                        console.log("Template payload fetched successfully.");
                        const newData = JSON.stringify(response.data, null, 4);
                        $messageTextarea.val(newData); // Remove existing data and add new JSON
                        // Store template data in localStorage
                        localStorage.setItem(TEMPLATE_DATA_KEY, newData);
                        generateTemplateInputs(response.data, section);
                        loadInputValues(section);
                    } else {
                        handleTemplateError(response, section);
                    }
                },
                error: function (xhr, status, error) {
                    handleAjaxError(section, status, error);
                },
            });
        });

        // Load saved template data on page load
        const savedTemplateData = localStorage.getItem(TEMPLATE_DATA_KEY);
        if (savedTemplateData) {
            try {
                const parsedData = JSON.parse(savedTemplateData);
                $messageTextarea.val(savedTemplateData);
                generateTemplateInputs(parsedData, section);
                loadInputValues(section);
            } catch (e) {
                console.error(`Error loading saved template data for ${section}:`, e);
            }
        }
    });

    function generateTemplateInputs(templateData, section) {
        console.log('Generating Template Inputs:', templateData, section); // Debugging line

        // Clear previous inputs
        jQuery(`#header-inputs-${section}`).empty();
        jQuery(`#body-inputs-${section}`).empty();
        jQuery(`#button-content-${section}`).empty();

        // Variables for dropdown
        const variables = {
            '%billing_first_name%': 'Customer\'s first name',
            '%billing_last_name%': 'Customer\'s last name',
            '%order_id%': 'Order ID',
            '%order_total%': 'Order total amount',
            '%billing_first_name% %billing_last_name%': 'Customer\'s full name',
            // Add more variables as needed
        };

        // Generate header inputs with value persistence
        const headerComponent = templateData.template.components.find(c => c.type === "header");
        if (headerComponent) {
            headerComponent.parameters.forEach((param, index) => {
                const savedValue = localStorage.getItem(`${section}_header_${index}`) || param.text || '';
                const $inputField = jQuery('<input>', {
                    type: 'text',
                    name: `header_${index}`,
                    value: savedValue,
                    placeholder: `Enter text for header (e.g., ${param.text})`
                });

                $inputField.on('input', function() {
                    localStorage.setItem(`${section}_header_${index}`, jQuery(this).val());
                });

                jQuery(`#header-inputs-${section}`)
                    .append(jQuery('<label>').text(`Header Text ${index + 1}:`))
                    .append($inputField);
            });
        }

        // Generate body inputs with value persistence
        const bodyComponent = templateData.template.components.find(c => c.type === "body");
        if (bodyComponent) {
            bodyComponent.parameters.forEach((param, index) => {
                const savedValue = localStorage.getItem(`${section}_body_${index}`) || param.text || '';
                const $selectField = jQuery('<select>', {
                    name: `body_${index}`
                }).append(jQuery('<option>').val('').text('Select Variable'));

                jQuery.each(variables, function(key, description) {
                    const $option = jQuery('<option>').val(key).text(description);
                    if (key === savedValue) {
                        $option.prop('selected', true);
                    }
                    $selectField.append($option);
                });

                $selectField.on('change', function() {
                    localStorage.setItem(`${section}_body_${index}`, jQuery(this).val());
                });

                jQuery(`#body-inputs-${section}`)
                    .append(jQuery('<label>').text(`Body Text ${index + 1}:`))
                    .append($selectField);
            });
        }

        // Generate button content with value persistence
        const buttonComponent = templateData.template.components.find(c => c.type === "button");
        if (buttonComponent) {
            buttonComponent.parameters.forEach((param, index) => {
                const savedValue = localStorage.getItem(`${section}_button_${index}`) || param.text || '';
                const $inputField = jQuery('<input>', {
                    type: 'text',
                    name: `button_${index}`,
                    value: savedValue,
                    placeholder: `Enter text for button (e.g., ${param.text})`,
                    readonly: true // Make the button text readonly
                });

                jQuery(`#button-content-${section}`)
                    .append(jQuery('<label>').text(`Button Text ${index + 1}:`))
                    .append($inputField);
            });
        }
    }

    function saveInputValues(section) {
        const inputs = jQuery(`#header-inputs-${section} input, #body-inputs-${section} select, #button-content-${section} input`);
        inputs.each(function() {
            const name = jQuery(this).attr('name');
            const value = jQuery(this).val();
            localStorage.setItem(`${section}_${name}`, value); // Save to local storage
        });
    }

    function loadInputValues(section) {
        const inputs = jQuery(`#header-inputs-${section} input, #body-inputs-${section} select, #button-content-${section} input`);
        inputs.each(function() {
            const name = jQuery(this).attr('name');
            const savedValue = localStorage.getItem(`${section}_${name}`);
            if (savedValue) {
                jQuery(this).val(savedValue); // Load from local storage
            }
        });
    }

    // Add event listener for Save Changes button
    jQuery('#save_changes').click(function(event) {
        event.preventDefault(); // Prevent default form submission

        // Assuming you want to update for all sections
        sections.forEach(section => {
            const templateData = jQuery(`#${section}_message`).val(); // Fetch the template data from the textarea
            if (templateData) {
                const updatedData = recreateJsonWithUpdatedValues(JSON.parse(templateData), section);
                const newData = JSON.stringify(updatedData, null, 4);
                jQuery(`#${section}_message`).val(newData); // Save updated data to textarea
                localStorage.setItem(`${section}_template_data`, newData);
            }
        });

        // Optionally, you can submit the form or perform other actions here
        jQuery(this).closest('form').submit(); // Submit the form
    });

    // Reload input values and update JSON after form submission or page refresh
    jQuery(window).on('load', function() {
        sections.forEach(section => {
            loadInputValues(section);
            const templateData = jQuery(`#${section}_message`).val(); // Fetch the template data from the textarea
            console.log(`Loaded template data for section ${section}:`, templateData); // Log the loaded template data
            if (templateData) {
                try {
                    const parsedData = JSON.parse(templateData);
                    generateTemplateInputs(parsedData, section); // Regenerate inputs
                } catch (e) {
                    console.error(`Error parsing JSON data for section ${section}:`, e);
                }
            }
        });
    });

    // Ensure updated data is saved to textarea before submitting the form
    jQuery('form').on('submit', function() {
        console.log('Form is about to be submitted. Save changes first!');
        sections.forEach(section => {
            const templateData = jQuery(`#${section}_message`).val(); // Fetch the template data from the textarea
            if (templateData) {
                const updatedData = recreateJsonWithUpdatedValues(JSON.parse(templateData), section);
                const newData = JSON.stringify(updatedData, null, 4);
                
                jQuery(`#${section}_message`).val(newData); // Remove existing data and add updated data to textarea
                console.log(jQuery(`#${section}_message`).val()); // Log the updated data
            }
            const template = $(`#${section}_template`).val();
            if (template) {
                localStorage.setItem(`${section}_selected_template`, template);
            }
        });
    });
    function persistTemplateSelection() {
        sections.forEach(section => {
            const $categoryDropdown = $(`#${section}_category`);
            const $templateDropdown = $(`#${section}_template`);
            
            // Save selection when changed
            $templateDropdown.on('change', function() {
                const selectedTemplate = $(this).val();
                localStorage.setItem(`${section}_selected_template`, selectedTemplate);
            });

            // Restore selection on page load
            const savedTemplate = localStorage.getItem(`${section}_selected_template`);
            if (savedTemplate) {
                // First ensure category is selected
                const templateOption = $templateDropdown.find(`option[value="${savedTemplate}"]`);
                const category = templateOption.data('category');
                if (category) {
                    $categoryDropdown.val(category).trigger('change');
                    
                    // Wait for templates to load then set selected template
                    setTimeout(() => {
                        $templateDropdown.val(savedTemplate).trigger('change');
                    }, 100);
                }
            }
        });

        // Handle OTP template persistence
        const $authTemplate = $('#auth_template');
        $authTemplate.on('change', function() {
            localStorage.setItem('auth_selected_template', $(this).val());
        });

        const savedAuthTemplate = localStorage.getItem('auth_selected_template');
        if (savedAuthTemplate) {
            $authTemplate.val(savedAuthTemplate).trigger('change');
        }
    }
    // Function to filter templates
    // Replace the existing filterTemplatesByCategory function with:
    function filterTemplatesByCategory(categoryDropdown, templateDropdown) {
        const selectedCategory = categoryDropdown.val();
        const section = categoryDropdown.attr('id').replace('_category', '');
        
        console.log('Filtering templates for:', {
            section: section,
            category: selectedCategory,
            availableTemplates: templates
        });
    
        // Clear and reset dropdown
        templateDropdown.empty()
            .append('<option value="">Select Template</option>');
        
        // Add templates for selected category
        if (selectedCategory && templates.length > 0) {
            templates.forEach(template => {
                if (template.category === selectedCategory) {
                    templateDropdown.append(
                        `<option value="${template.name}" data-category="${template.category}">
                            ${template.name}
                        </option>`
                    );
                }
            });
        }
    
        // Initialize textarea if empty
        const textarea = jQuery(`#${section}_message`);
        if (!textarea.val().trim()) {
            textarea.val(JSON.stringify({
                template: {
                    components: []
                }
            }, null, 2));
        }
    
        console.log('Templates filtered:', {
            selectedCategory: selectedCategory,
            templateOptions: templateDropdown.find('option').length - 1
        });
    }
    
    // Replace the category change handler with:
    sections.forEach(section => {
        const categoryDropdown = jQuery(`#${section}_category`);
        const templateDropdown = jQuery(`#${section}_template`);
        
        categoryDropdown.on('change', function() {
            console.log('Category changed:', jQuery(this).val());
            filterTemplatesByCategory(jQuery(this), templateDropdown);
        });
    
        // Initial load if category is selected
        if (categoryDropdown.val()) {
            categoryDropdown.trigger('change');
        }
    });

    // Event dropdown change event
    jQuery('#event').change(function() {
        var selectedEvent = jQuery(this).val();
        jQuery('.dynamic-section').hide();
        if (selectedEvent) {
            jQuery('#section-' + selectedEvent).show();
        }
    });

    // Category dropdown change event for each section
    sections.forEach(function(section) {
        jQuery('#' + section + '_category').change(function() {
            var categoryDropdown = jQuery(this);
            var templateDropdown = jQuery('#' + section + '_template');
            
            // Filter templates based on selected category
            filterTemplatesByCategory(categoryDropdown, templateDropdown);

            // Fetch template payload when a template is selected
            templateDropdown.off('change').on('change', function() {
                var templateName = jQuery(this).val();
                var channelNumber = jQuery('#channel_id').val();

                if (templateName && channelNumber) {
                    jQuery.ajax({
                        url: custom_ajax_object.ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'fetch_template_payload',
                            template_name: templateName,
                            channel_number: channelNumber,
                            nonce: custom_ajax_object.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                // Display dynamic variables
                                var section = categoryDropdown.attr('id').replace('_category', '');
                                generateTemplateInputs(response.data, section);
                            } else {
                                console.error(response.data.message);
                            }
                        },
                        error: function() {
                            console.error('Error fetching template payload.');
                        }
                    });
                }
            });
        });

        // Optional: Trigger initial filtering on page load
        (function() {
            var categoryDropdown = jQuery('#' + section + '_category');
            var templateDropdown = jQuery('#' + section + '_template');
            
            // If a category is already selected, filter templates
            if (categoryDropdown.val()) {
                filterTemplatesByCategory(categoryDropdown, templateDropdown);
            }
        })();
    });

    // Fetch template payload for OTP template selection
    jQuery('#auth_template').on('change', function() {
        var templateName = jQuery(this).val();
        var channelNumber = jQuery('#channel_id').val();

        if (templateName && channelNumber) {
            jQuery.ajax({
                url: custom_ajax_object.ajaxurl,
                type: 'POST',
                data: {
                    action: 'fetch_template_payload',
                    template_name: templateName,
                    channel_number: channelNumber,
                    nonce: custom_ajax_object.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Set the JSON data to the textarea
                        jQuery('#auth_message').val(JSON.stringify(response.data, null, 4));
                    } else {
                        console.error(response.data.message);
                    }
                },
                error: function() {
                    console.error('Error fetching template payload.');
                }
            });
        }
    });

    // Optional: Trigger initial filtering for OTP template on page load
    (function() {
        var templateDropdown = jQuery('#auth_template');
        var templateName = templateDropdown.val();
        var channelNumber = jQuery('#channel_id').val();

        if (templateName && channelNumber) {
            jQuery.ajax({
                url: custom_ajax_object.ajaxurl,
                type: 'POST',
                data: {
                    action: 'fetch_template_payload',
                    template_name: templateName,
                    channel_number: channelNumber,
                    nonce: custom_ajax_object.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Set the JSON data to the textarea
                        jQuery('#auth_message').val(JSON.stringify(response.data, null, 4));
                    } else {
                        console.error(response.data.message);
                    }
                },
                error: function() {
                    console.error('Error fetching template payload.');
                }
            });
        }
    })();
});

function fetchTemplatePayload(templateName, channelNumber, section) {
    console.log('Fetching Template Payload:', {
        templateName: templateName,
        channelNumber: channelNumber,
        section: section,
        nonce: custom_ajax_object.nonce
    });

    jQuery.ajax({
        url: custom_ajax_object.ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'fetch_template_payload',
            template_name: templateName,
            channel_number: channelNumber,
            nonce: custom_ajax_object.nonce
        },
        beforeSend: function(xhr) {
            console.log('AJAX Request Sending...');
            jQuery(`#template-inputs-${section}`).html('<p>Loading template details...</p>');
        },
        success: function(response) {
            console.log('AJAX Success Response:', response);
            
            if (response.success) {
                generateTemplateInputs(response.data, section);
                loadInputValues(section); // Load saved input values
            } else {
                console.error('Server returned error:', response);
                handleTemplateError(response, section);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error Details:', {
                status: status,
                error: error,
                responseText: xhr.responseText,
                readyState: xhr.readyState
            });

            handleAjaxError(section, status, error);
        }
    });
}

function handleAjaxError(section, status, error) {
    const $container = jQuery(`#template-inputs-${section}`);
    $container.html(`
        <div class="error-message">
            <p>Network Error: ${error}</p>
            <p>Status: ${status}</p>
            <p>Please check your network connection and API configuration.</p>
        </div>
    `);
}

function handleTemplateError(response, section) {
    const $container = jQuery(`#template-inputs-${section}`);
    const $messageTextarea = jQuery(`#${section}_message`);
    $container.html(`
        <div class="error-message">
            <p>Error: ${response.data.message || 'Unable to fetch template data.'}</p>
            <p>Debug Info: ${JSON.stringify(response.data.debug_info || {})}</p>
        </div>
    `);
    $messageTextarea.val(
        "Error: " +
        (response.data.message || "Unable to fetch template data.") +
        "\nDebug Info: " +
        JSON.stringify(response.data.debug_info || {})
    );
}

function recreateJsonWithUpdatedValues(templateData, section) {
    // Clone the original JSON to avoid mutating it directly
    const updatedTemplateData = JSON.parse(JSON.stringify(templateData));

    // Helper function to update parameters in a component
    function updateComponentParams(componentType) {
        const component = updatedTemplateData.template.components.find(
            (c) => c.type === componentType
        );
        if (component) {
            component.parameters.forEach((param, index) => {
                let inputField;
                if (componentType === 'body') {
                    inputField = document.querySelector(
                        `#body-inputs-${section} select[name="body_${index}"]`
                    );
                } else {
                    inputField = document.querySelector(
                        `#${componentType}-inputs-${section} input[name="${componentType}_${index}"]`
                    );
                }
                if (inputField) {
                    param.text = inputField.value; // Update with the new value from the input field
                }
            });
        }
    }

    // Update header components
    updateComponentParams("header");

    // Update body components
    updateComponentParams("body");

    // Update button components
    updateComponentParams("button");

    // Return the updated JSON
    return updatedTemplateData;
}
