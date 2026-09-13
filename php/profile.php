<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';


/**
 * Send JSON response and stop execution.
 */
function response(
    bool $success,
    string $message,
    mixed $data = null,
    int $statusCode = 200
): never {

    http_response_code($statusCode);

    $response = [
        'success' => $success,
        'message' => $message
    ];

    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode(
        $response,
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


/**
 * Get the current session from Redis.
 */
function getCurrentSession(): array
{
    global $redis;

    /*
    |--------------------------------------------------------------------------
    | Check Session Cookie
    |--------------------------------------------------------------------------
    */

    $sessionId = $_COOKIE['session_id'] ?? '';

    if (!is_string($sessionId) || $sessionId === '') {
        response(
            false,
            'Authentication required.',
            null,
            401
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Session ID Format
    |--------------------------------------------------------------------------
    |
    | Login creates a 64-character hexadecimal session ID.
    |--------------------------------------------------------------------------
    */

    if (!preg_match('/^[a-f0-9]{64}$/', $sessionId)) {

        response(
            false,
            'Invalid session.',
            null,
            401
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Get Session From Redis
    |--------------------------------------------------------------------------
    */

    $sessionJson = $redis->get(
        'session:' . $sessionId
    );

    if ($sessionJson === null || $sessionJson === false) {

        response(
            false,
            'Session expired. Please login again.',
            null,
            401
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Decode Session Data
    |--------------------------------------------------------------------------
    */

    try {

        $sessionData = json_decode(
            (string) $sessionJson,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

    } catch (JsonException $e) {

        response(
            false,
            'Invalid session.',
            null,
            401
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Session Data
    |--------------------------------------------------------------------------
    */

    if (
        !isset($sessionData['user_id']) ||
        !is_numeric($sessionData['user_id'])
    ) {

        response(
            false,
            'Invalid session.',
            null,
            401
        );
    }


    return $sessionData;
}


/*
|--------------------------------------------------------------------------
| Request Method
|--------------------------------------------------------------------------
*/

$requestMethod = $_SERVER['REQUEST_METHOD'];


/*
|--------------------------------------------------------------------------
| Logout
|--------------------------------------------------------------------------
|
| Logout is handled using POST /php/profile.php
| with action=logout.
|--------------------------------------------------------------------------
*/

if (
    $requestMethod === 'POST' &&
    ($_POST['action'] ?? '') === 'logout'
) {

    try {

        $sessionId = $_COOKIE['session_id'] ?? '';

        /*
        |----------------------------------------------------------------------
        | Delete Redis Session
        |----------------------------------------------------------------------
        */

        if (
            is_string($sessionId) &&
            preg_match('/^[a-f0-9]{64}$/', $sessionId)
        ) {

            $redis->del(
                'session:' . $sessionId
            );
        }


        /*
        |----------------------------------------------------------------------
        | Delete Session Cookie
        |----------------------------------------------------------------------
        */

        setcookie(
            'session_id',
            '',
            [
                'expires' => time() - 3600,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => (
                    isset($_SERVER['HTTPS']) &&
                    $_SERVER['HTTPS'] !== 'off'
                )
            ]
        );


        response(
            true,
            'Logout successful.'
        );

    } catch (Throwable $e) {

        error_log(
            'Logout error: ' . $e->getMessage()
        );

        response(
            false,
            'Logout could not be completed.',
            null,
            500
        );
    }
}


/*
|--------------------------------------------------------------------------
| Profile Requests Must Use GET
|--------------------------------------------------------------------------
*/

if ($requestMethod !== 'GET') {

    response(
        false,
        'Invalid request method.',
        null,
        405
    );
}


try {

    /*
    |--------------------------------------------------------------------------
    | Authenticate Using Redis
    |--------------------------------------------------------------------------
    */

    $session = getCurrentSession();

    $userId = (int) $session['user_id'];


    /*
    |--------------------------------------------------------------------------
    | Get Account Information From MySQL
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare(
        'SELECT
            id,
            username,
            email,
            created_at
         FROM users
         WHERE id = :user_id
         LIMIT 1'
    );

    $stmt->execute([
        ':user_id' => $userId
    ]);

    $user = $stmt->fetch();


    /*
    |--------------------------------------------------------------------------
    | Check User
    |--------------------------------------------------------------------------
    */

    if (!$user) {

        /*
        |----------------------------------------------------------------------
        | User no longer exists.
        | Remove Redis session.
        |---------------------------------------------------------------------- 
        */

        $sessionId = $_COOKIE['session_id'] ?? '';

        if (
            is_string($sessionId) &&
            preg_match('/^[a-f0-9]{64}$/', $sessionId)
        ) {

            $redis->del(
                'session:' . $sessionId
            );
        }

        response(
            false,
            'User account not found.',
            null,
            404
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Get Additional Profile Information From MongoDB
    |--------------------------------------------------------------------------
    */

    $profileDocument = $mongoDb
        ->profiles
        ->findOne([
            'user_id' => $userId
        ]);


    /*
    |--------------------------------------------------------------------------
    | Default Profile Values
    |--------------------------------------------------------------------------
    */

    $profile = [
        'full_name' => '',
        'age' => null,
        'bio' => '',
        'interests' => []
    ];


    /*
    |--------------------------------------------------------------------------
    | Convert MongoDB Document
    |--------------------------------------------------------------------------
    */

    if ($profileDocument !== null) {

        $profile['full_name'] =
            isset($profileDocument['full_name'])
                ? (string) $profileDocument['full_name']
                : '';

        $profile['age'] =
            isset($profileDocument['age'])
                ? (int) $profileDocument['age']
                : null;

        $profile['bio'] =
            isset($profileDocument['bio'])
                ? (string) $profileDocument['bio']
                : '';

        if (
            isset($profileDocument['interests']) &&
            is_iterable($profileDocument['interests'])
        ) {

            $profile['interests'] = [];

            foreach ($profileDocument['interests'] as $interest) {

                $profile['interests'][] =
                    (string) $interest;
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Format Account Created Date
    |--------------------------------------------------------------------------
    */

    $createdAt = $user['created_at'];

    if ($createdAt instanceof DateTimeInterface) {

        $createdAt = $createdAt->format(
            'd M Y, h:i A'
        );

    } else {

        $createdAt = (string) $createdAt;
    }


    /*
    |--------------------------------------------------------------------------
    | Prepare Response
    |--------------------------------------------------------------------------
    */

    $data = [

        'account' => [
            'id' => (int) $user['id'],
            'username' => (string) $user['username'],
            'email' => (string) $user['email'],
            'created_at' => $createdAt
        ],

        'profile' => $profile
    ];


    /*
    |--------------------------------------------------------------------------
    | Return Profile
    |--------------------------------------------------------------------------
    */

    response(
        true,
        'Profile loaded successfully.',
        $data
    );


} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | Log Technical Error
    |--------------------------------------------------------------------------
    |
    | Never expose database or Redis errors to the browser.
    |--------------------------------------------------------------------------
    */

    error_log(
        'Profile error: ' . $e->getMessage()
    );

    response(
        false,
        'Profile could not be loaded.',
        null,
        500
    );
}