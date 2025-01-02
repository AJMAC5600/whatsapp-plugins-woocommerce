jQuery(document).ready(function ($) {
  // Send OTP on registration
  $("#send_otp").on("click", function () {
    var phoneNumber = $("#reg_phone_number").val();
    var nonce = ajax_object.nonce; // Registration nonce

    // Check if phone number is entered
    if (!phoneNumber) {
      alert("Please enter your phone number.");
      return;
    }

    // Send the OTP via AJAX
    $.post(ajax_object.ajax_url, {
      action: "send_otp",
      security: nonce,
      phone_number: phoneNumber,
    })
      .done(function (response) {
        if (response.success) {
          alert("OTP sent successfully!");
        } else {
          alert("Failed to send OTP.");
        }
      })
      .fail(function () {
        alert("AJAX request failed. Please try again.");
      });
  });

  // Send OTP on login page
  $("#send_login_otp").on("click", function (e) {
    e.preventDefault();

    var username = $("#username").val(); // Adjust to the actual field ID (e.g., #email or #username)
    var loginNonce = ajax_object.login_nonce; // Use the login-specific nonce

    // Check if username/email is entered
    if (!username) {
      alert("Please enter your username or email.");
      return;
    }

    // Send the login OTP via AJAX
    $.ajax({
      url: ajax_object.ajax_url,
      method: "POST",
      data: {
        action: "send_login_otp",
        username: username, // Use the field name that matches your PHP handler
        security: loginNonce, // Use the login nonce here
      },
      success: function (response) {
        if (response.success) {
          alert("OTP sent to your registered phone number.");
        } else {
          alert(response.data.message || "Failed to send OTP. Try again.");
        }
      },
      error: function (xhr, status, error) {
        console.log(xhr.responseText);
        alert("Error: " + error + ". Please try again.");
      },
    });
  });
});
