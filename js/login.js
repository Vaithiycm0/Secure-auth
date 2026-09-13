$(document).ready(function () {

    $("#loginForm").on("submit", function (event) {

        event.preventDefault();

        const form = this;

        if (!form.checkValidity()) {
            event.stopPropagation();
            $(form).addClass("was-validated");
            return;
        }

        const $button = $("#loginButton");

        $button.prop("disabled", true);
        $button.text("Logging in...");

        $("#message").html("");

        $.ajax({
            url: "php/login.php",
            method: "POST",
            dataType: "json",

            data: {
                username: $("#username").val().trim(),
                password: $("#password").val()
            },

            success: function (response) {

                if (response.success) {

                    $("#message").html(`
                        <div class="alert alert-success">
                            ${response.message}
                        </div>
                    `);

                    setTimeout(function () {
                        window.location.href = "profile.html";
                    }, 800);

                } else {

                    $("#message").html(`
                        <div class="alert alert-danger">
                            ${response.message}
                        </div>
                    `);
                }
            },

            error: function (xhr) {

                let message = "Login failed. Please try again.";

                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }

                $("#message").html(`
                    <div class="alert alert-danger">
                        ${message}
                    </div>
                `);
            },

            complete: function () {

                $button.prop("disabled", false);
                $button.text("Login");
            }
        });

    });

});