jQuery(document).ready(function ($) {
    if (typeof custom_ajax_object === "undefined") {
        console.error("custom_ajax_object is not defined. Check your wp_localize_script implementation.");
        return;
    }

    const apiKeyField = $("#api_key");
    const apiUrlField = $("#api_url");
    const configurableSections = $(".form-table").not(":has(#api_key, #api_url)");

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

    sections.forEach((section) => {
        const $templateDropdown = $(`#${section}_template`);
        const $categoryDropdown = $(`#${section}_category`);
        const $messageTextarea = $(`#${section}_message`);

        $categoryDropdown.on("change", function () {
            const selectedCategory = $(this).val();
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
            const selectedTemplate = $(this).val();
            const channelNumber = $(`#channel_id`).val();
            console.log(`Selected template: ${selectedTemplate}, Channel: ${channelNumber}`);

            if (!selectedTemplate || !channelNumber) {
                $messageTextarea.val("Please select a valid template and channel.");
                return;
            }

            // Save selected template to local storage
            localStorage.setItem(`${section}_template`, selectedTemplate);

            $templateDropdown.prop("disabled", true); // Disable during request

            $.ajax({
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
                        generateTemplateInputs(response.data, section);
                    } else {
                        console.error("Error fetching template payload:", response);
                        $messageTextarea.val(
                            "Error: " +
                            (response.data.message || "Unable to fetch template data.") +
                            "\nDebug Info: " +
                            JSON.stringify(response.data.debug_info || {})
                        );
                    }
                },
                error: function (xhr, status, error) {
                    $templateDropdown.prop("disabled", false);
                    console.error("AJAX Error:", {
                        status: status,
                        error: error,
                        responseText: xhr.responseText,
                    });
                    $messageTextarea.val(
                        "Error fetching template payload. Please try again."
                    );
                },
            });
        });

        // Restore selected template on page load
        const savedTemplate = localStorage.getItem(`${section}_template`);
        if (savedTemplate) {
            $templateDropdown.val(savedTemplate);
        }
    });

    sections.forEach((section) => {
        // Load saved input values on page load
        loadInputValues(section);

        // Event listener for template selection
        $(`#${section}_template`).on("change", function () {
            const selectedTemplate = $(this).val();
            const channelNumber = $(`#channel_id`).val();

            if (selectedTemplate && channelNumber) {
                fetchTemplatePayload(selectedTemplate, channelNumber, section);
            }
        });

        // Event listener for input changes to save values
        $(`#header-inputs-${section}, #body-inputs-${section}, #button-content-${section}`).on('input', 'input', function() {
            saveInputValues(section);
        });
    });

    // Add event listener for Save Changes button
    $('#save_changes').click(function(event) {
        event.preventDefault(); // Prevent default form submission

        // Assuming you want to update for all sections
        sections.forEach(section => {
            const templateData = $(`#${section}_message`).val(); // Fetch the template data from the textarea
            if (templateData) {
                const updatedData = recreateJsonWithUpdatedValues(JSON.parse(templateData), section);
                $(`#${section}_message`).val(JSON.stringify(updatedData)); // Save updated data to textarea
            }
        });

        // Optionally, you can submit the form or perform other actions here
        $(this).closest('form').submit(); // Submit the form
    });

    // Reload input values and update JSON after form submission or page refresh
    $(window).on('load', function() {
        sections.forEach(section => {
            loadInputValues(section);
            const templateData = $(`#${section}_message`).val(); // Fetch the template data from the textarea
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
    $('form').on('submit', function() {
        console.log('Form is about to be submitted. Save changes first!');
        sections.forEach(section => {
            const templateData = $(`#${section}_message`).val(); // Fetch the template data from the textarea
            if (templateData) {
                const updatedData = recreateJsonWithUpdatedValues(JSON.parse(templateData), section);
                const newData = JSON.stringify(updatedData, null, 4);
                $(`#${section}_message`).val(newData); // Remove existing data and add updated data to textarea
            }
        });
    });
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

function generateTemplateInputs(templateData, section) {
    console.log('Generating Template Inputs:', templateData, section); // Debugging line

    // Clear previous inputs
    jQuery(`#header-inputs-${section}`).empty();
    jQuery(`#body-inputs-${section}`).empty();
    jQuery(`#button-content-${section}`).empty();

    // Generate header inputs
    const headerComponent = templateData.template.components.find(c => c.type === "header");
    if (headerComponent) {
        headerComponent.parameters.forEach((param, index) => {
            const $inputLabel = jQuery('<label>').text(`Header Text ${index + 1}:`);
            const $inputField = jQuery('<input>', {
                type: 'text',
                name: `header_${index}`,
                value: param.text || '',
                placeholder: `Enter text for header (e.g., ${param.text})`
            });

            jQuery(`#header-inputs-${section}`).append($inputLabel).append($inputField);
        });
    }

    // Generate body inputs
    const bodyComponent = templateData.template.components.find(c => c.type === "body");
    if (bodyComponent) {
        bodyComponent.parameters.forEach((param, index) => {
            const $inputLabel = jQuery('<label>').text(`Body Text ${index + 1}:`);
            const $inputField = jQuery('<input>', {
                type: 'text',
                name: `body_${index}`,
                value: param.text || '',
                placeholder: `Enter text for body (e.g., ${param.text})`
            });

            jQuery(`#body-inputs-${section}`).append($inputLabel).append($inputField);
        });
    }

    // Generate button content
    const buttonComponent = templateData.template.components.find(c => c.type === "button");
    if (buttonComponent) {
        buttonComponent.parameters.forEach((param, index) => {
            const $inputLabel = jQuery('<label>').text(`Button Text ${index + 1}:`);
            const $inputField = jQuery('<input>', {
                type: 'text',
                name: `button_${index}`,
                value: param.text || '',
                placeholder: `Enter text for button (e.g., ${param.text})`
            });

            jQuery(`#button-content-${section}`).append($inputLabel).append($inputField);
        });
    }
}

function saveInputValues(section) {
    const inputs = jQuery(`#header-inputs-${section} input, #body-inputs-${section} input, #button-content-${section} input`);
    inputs.each(function() {
        const name = jQuery(this).attr('name');
        const value = jQuery(this).val();
        localStorage.setItem(`${section}_${name}`, value); // Save to local storage
    });
}

function loadInputValues(section) {
    const inputs = jQuery(`#header-inputs-${section} input, #body-inputs-${section} input, #button-content-${section} input`);
    inputs.each(function() {
        const name = jQuery(this).attr('name');
        const savedValue = localStorage.getItem(`${section}_${name}`);
        if (savedValue) {
            jQuery(this).val(savedValue); // Load from local storage
        }
    });
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
                const inputField = document.querySelector(
                    `#${componentType}-inputs-${section} input[name="${componentType}_${index}"]`
                );
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
