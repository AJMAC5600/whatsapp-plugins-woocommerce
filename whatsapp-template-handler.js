(function($) {
    // Document ready function
    $(document).ready(function() {
        // Template Selector Change Event
        $('.template-selector').on('change', function() {
            const $selector = $(this);
            const templateName = $selector.val();
            const section = $selector.data('section');
            const channelNumber = $selector.closest('table').find('.channel-dropdown').val();

            // Reset input containers
            resetTemplateInputContainers(section);

            // Validate template selection
            if (!templateName) {
                console.log('No template selected');
                return;
            }

            // Fetch template payload via AJAX
            fetchTemplatePayload(templateName, channelNumber, section);
        });

        // Fetch Template Payload Function
        function fetchTemplatePayload(templateName, channelNumber, section) {
            $.ajax({
                url: custom_ajax_object.ajaxurl,
                type: 'POST',
                data: {
                    action: 'fetch_template_payload',
                    template_name: templateName,
                    channel_number: channelNumber,
                    security: custom_ajax_object.nonce
                },
                beforeSend: function() {
                    // Optional: Add loading indicator
                    $(`#template-inputs-${section}`).html('<p>Loading template details...</p>');
                },
                success: function(response) {
                    if (response.success) {
                        generateTemplateInputs(response.data, section);
                    } else {
                        handleTemplateError(response, section);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    handleAjaxError(section);
                }
            });
        }

        // Reset Template Input Containers
        function resetTemplateInputContainers(section) {
            $(`#header-inputs-${section}`).empty();
            $(`#body-inputs-${section}`).empty();
            $(`#button-inputs-${section}`).empty();
        }

        // Generate Template Inputs
        function generateTemplateInputs(templateData, section) {
            // Validate template data
            if (!templateData || !templateData.template || !templateData.template.components) {
                console.error('Invalid template data');
                return;
            }

            // Components to process
            const componentTypes = ['header', 'body', 'button'];
            const variables = {
                '%billing_first_name%': 'Customer\'s first name',
                '%billing_last_name%': 'Customer\'s last name',
                '%order_id%': 'Order ID',
                '%order_total%': 'Order total amount',
                // Add more variables as needed
            };

            componentTypes.forEach(componentType => {
                const component = templateData.template.components.find(
                    c => c.type.toLowerCase() === componentType
                );

                if (component && component.parameters) {
                    const $container = $(`#${componentType}-inputs-${section}`);
                    
                    component.parameters.forEach((param, index) => {
                        const inputName = `whatsapp_settings[${section}_${componentType}_${index}]`;
                        const inputId = `${section}_${componentType}_${index}`;
                        
                        const $inputGroup = $('<div>').addClass('input-group');
                        const $label = $('<label>')
                            .attr('for', inputId)
                            .text(`${capitalizeFirstLetter(componentType)} Text ${index + 1}:`);
                        
                        if (componentType === 'body') {
                            const $select = $('<select>')
                                .attr({
                                    id: inputId,
                                    name: inputName
                                })
                                .append($('<option>').val('').text('Select Variable'));

                            $.each(variables, function(key, description) {
                                $select.append($('<option>').val(key).text(description));
                            });

                            $inputGroup
                                .append($label)
                                .append($select);
                        } else {
                            const $input = $('<input>')
                                .attr({
                                    type: 'text',
                                    id: inputId,
                                    name: inputName,
                                    placeholder: param.text || `Enter ${componentType} text`,
                                    value: param.text || ''
                                });

                            $inputGroup
                                .append($label)
                                .append($input);
                        }

                        $container.append($inputGroup);
                    });
                }
            });
        }

        // Error Handling Functions
        function handleTemplateError(response, section) {
            const $container = $(`#template-inputs-${section}`);
            $container.html(`
                <div class="error-message">
                    <p>Failed to load template: ${response.data.message || 'Unknown error'}</p>
                </div>
            `);
        }

        function handleAjaxError(section) {
            const $container = $(`#template-inputs-${section}`);
            $container.html(`
                <div class="error-message">
                    <p>Network error. Please try again later.</p>
                </div>
            `);
        }

        // Utility Functions
        function capitalizeFirstLetter(string) {
            return string.charAt(0).toUpperCase() + string.slice(1);
        }

        // Optional: Form Submission Handling
        $('#whatsapp-config-form').on('submit', function(e) {
            // Validate inputs if needed
            const $requiredInputs = $(this).find('input[required]');
            let isValid = true;

            $requiredInputs.each(function() {
                if (!$(this).val().trim()) {
                    isValid = false;
                    $(this).addClass('error');
                } else {
                    $(this).removeClass('error');
                }
            });

            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });

        // Dynamic Validation
        $(document).on('input', '.template-inputs-container input', function() {
            const $input = $(this);
            const maxLength = $input.data('max-length') || 50; // Default max length

            if ($input.val().length > maxLength) {
                $input.val($input.val().substring(0, maxLength));
                alert(`Maximum length is ${maxLength} characters`);
            }
        });
    });
})(jQuery);