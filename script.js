jQuery(document).ready(function ($) {
  if (typeof custom_ajax_object === "undefined") {
    console.error(
      "custom_ajax_object is not defined. Check your wp_localize_script implementation."
    );
    return;
  }
  const apiKeyField = $("#api_key");
  const apiUrlField = $("#api_url");
  const configurableSections = $(".form-table").not(":has(#api_key, #api_url)");
  const $selectedChannel = $("#selected_channel");

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
  const sections = [
    "order_book",
    "order_cancellation",
    "order_status_change",
    "order_success",
  ];

  sections.forEach((section) => {
    const $templateDropdown = $(`#${section}_template`);
    const $messageTextarea = $(`#${section}_message`);

    $templateDropdown.on("change", function () {
      const selectedTemplate = $(this).val();
      const channelNumber = "919833533311";
      console.log(
        `Selected template: ${selectedTemplate}, Channel: ${channelNumber}`
      );

      if (!selectedTemplate || !channelNumber) {
        $messageTextarea.val("Please select a valid template and channel.");
        return;
      }

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
            $messageTextarea.val(JSON.stringify(response.data, null, 4));
          } else {
            $messageTextarea.val(
              "Error: " +
                (response.data.error || "Unable to fetch template data.")
            );
          }
        },
        error: function (xhr, status, error) {
          $templateDropdown.prop("disabled", false);
          $messageTextarea.val(
            "Error fetching template payload. Please try again."
          );
          console.error("AJAX Error:", {
            status: status,
            error: error,
            responseText: xhr.responseText,
          });
        },
      });
    });
  });
});
