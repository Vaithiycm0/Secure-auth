$(document).ready(function () {

    /*
    |--------------------------------------------------------------------------
    | Load Profile
    |--------------------------------------------------------------------------
    */

    function loadProfile() {

        $.ajax({
            url: 'php/profile.php',
            type: 'GET',
            dataType: 'json',

            success: function (response) {

                if (response.success) {

                    const account = response.data.account;
                    const profile = response.data.profile;

                    /*
                    |------------------------------------------------------------------
                    | Account Information
                    |------------------------------------------------------------------
                    */

                    $('#username').text(account.username);
                    $('#email').text(account.email);
                    $('#createdAt').text(account.created_at);


                    /*
                    |------------------------------------------------------------------
                    | Profile Information
                    |------------------------------------------------------------------
                    */

                    $('#welcomeUsername').text(account.username);

                    $('#fullName').text(
                        profile.full_name || 'Not provided'
                    );

                    $('#age').text(
                        profile.age !== null && profile.age !== undefined
                            ? profile.age
                            : 'Not provided'
                    );

                    $('#bio').text(
                        profile.bio || 'No bio provided'
                    );


                    /*
                    |------------------------------------------------------------------
                    | Interests
                    |------------------------------------------------------------------
                    */

                    const interests = profile.interests || [];

                    $('#interests').empty();

                    if (interests.length > 0) {

                        interests.forEach(function (interest) {

                            const badge = $('<span>')
                                .addClass('badge text-bg-primary me-2 mb-2')
                                .text(interest);

                            $('#interests').append(badge);
                        });

                    } else {

                        $('#interests').append(
                            $('<span>')
                                .addClass('text-muted')
                                .text('No interests provided')
                        );
                    }


                    /*
                    |------------------------------------------------------------------
                    | Show Profile
                    |------------------------------------------------------------------
                    */

                    $('#loadingMessage').addClass('d-none');
                    $('#errorMessage').addClass('d-none');
                    $('#profileContent').removeClass('d-none');

                } else {

                    handleProfileError(
                        response.message || 'Unable to load profile.'
                    );
                }
            },

            error: function (xhr) {

                let message = 'Unable to load your profile.';

                /*
                |------------------------------------------------------------------
                | Session Expired / Unauthorized
                |------------------------------------------------------------------
                */

                if (xhr.status === 401) {

                    message = 'Your session has expired. Redirecting to login...';

                    $('#loadingMessage').addClass('d-none');
                    $('#errorMessage')
                        .removeClass('d-none')
                        .text(message);

                    setTimeout(function () {
                        window.location.href = 'login.html';
                    }, 1500);

                    return;
                }

                /*
                |------------------------------------------------------------------
                | Other Server Errors
                |------------------------------------------------------------------
                */

                if (
                    xhr.responseJSON &&
                    xhr.responseJSON.message
                ) {
                    message = xhr.responseJSON.message;
                }

                handleProfileError(message);
            }
        });
    }


    /*
    |--------------------------------------------------------------------------
    | Handle Profile Error
    |--------------------------------------------------------------------------
    */

    function handleProfileError(message) {

        $('#loadingMessage').addClass('d-none');

        $('#profileContent').addClass('d-none');

        $('#errorMessage')
            .removeClass('d-none')
            .text(message);
    }


    /*
    |--------------------------------------------------------------------------
    | Logout
    |--------------------------------------------------------------------------
    */

    $('#logoutButton').on('click', function () {

        const button = $(this);

        button.prop('disabled', true);
        button.text('Logging out...');

        $.ajax({
            url: 'php/profile.php',
            type: 'POST',
            data: {
                action: 'logout'
            },
            dataType: 'json',

            success: function (response) {

                if (response.success) {

                    window.location.href = 'login.html';

                } else {

                    button.prop('disabled', false);
                    button.text('Logout');

                    $('#errorMessage')
                        .removeClass('d-none')
                        .text(
                            response.message || 'Logout failed.'
                        );
                }
            },

            error: function () {

                button.prop('disabled', false);
                button.text('Logout');

                $('#errorMessage')
                    .removeClass('d-none')
                    .text(
                        'Logout could not be completed.'
                    );
            }
        });
    });


    /*
    |--------------------------------------------------------------------------
    | Start Profile Loading
    |--------------------------------------------------------------------------
    */

    loadProfile();

});