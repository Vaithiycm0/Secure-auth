$(document).ready(function () {

    $("#registerForm").on("submit", function (event) {

        event.preventDefault();

        const form = this;

        if (!form.checkValidity()) {
            event.stopPropagation();
            $(form).addClass("was-validated");
            return;
        }

        const $button = $("#registerButton");

        $button.prop("disabled", true);
        $button.text("Creating account...");

        $("#message").html("");

        $.ajax({
            url: "php/register.php",
            method: "POST",
            dataType: "json",
            data: {
                username: $("#username").val().trim(),
                email: $("#email").val().trim(),
                password: $("#password").val(),
                name: $("#name").val().trim(),
                age: $("#age").val(),
                bio: $("#bio").val().trim(),
                interests: $("#interests").val().trim()
            },

            success: function (response) {

                if (response.success) {

                    $("#message").html(`
                        <div class="alert alert-success" role="alert">
                            ${response.message}
                        </div>
                    `);

                    form.reset();
                    $(form).removeClass("was-validated");

                    setTimeout(function () {
                        window.location.href = "login.html";
                    }, 1500);

                } else {

                    $("#message").html(`
                        <div class="alert alert-danger" role="alert">
                            ${response.message}
                        </div>
                    `);
                }
            },

            error: function (xhr) {

                let message = "Something went wrong. Please try again.";

                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }

                $("#message").html(`
                    <div class="alert alert-danger" role="alert">
                        ${message}
                    </div>
                `);
            },

            complete: function () {
                $button.prop("disabled", false);
                $button.text("Create Account");
            }
        });
    });

});