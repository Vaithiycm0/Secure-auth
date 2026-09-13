<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';

function response(bool $success, string $message, int $statusCode = 200): never
{
    http_response_code($statusCode);

    echo json_encode(
        [
            'success' => $success,
            'message' => $message
        ],
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    response(false, 'Invalid request method.', 405);
}

$username = trim((string) ($_POST['username'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

$name = trim((string) ($_POST['full_name'] ?? ''));
$age = filter_var($_POST['age'] ?? null, FILTER_VALIDATE_INT);
$bio = trim((string) ($_POST['bio'] ?? ''));
$interestsInput = trim((string) ($_POST['interests'] ?? ''));

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

if (
    $username === '' ||
    !preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)
) {
    response(false, 'Username must contain 3-50 letters, numbers, or underscores.', 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
    response(false, 'Please enter a valid email address.', 422);
}

if (strlen($password) < 8 || strlen($password) > 128) {
    response(false, 'Password must contain 8-128 characters.', 422);
}

if ($name === '' || strlen($name) > 100) {
    response(false, 'Please enter a valid name.', 422);
}

if ($age === false || $age < 13 || $age > 120) {
    response(false, 'Age must be between 13 and 120.', 422);
}

if ($bio === '' || strlen($bio) > 500) {
    response(false, 'Bio must contain 1-500 characters.', 422);
}

if ($interestsInput === '' || strlen($interestsInput) > 300) {
    response(false, 'Please enter at least one interest.', 422);
}

/*
|--------------------------------------------------------------------------
| Convert interests into an array
|--------------------------------------------------------------------------
*/

$interests = array_values(
    array_filter(
        array_map(
            static fn(string $interest): string => trim($interest),
            explode(',', $interestsInput)
        ),
        static fn(string $interest): bool => $interest !== ''
    )
);

if (count($interests) === 0) {
    response(false, 'Please enter at least one interest.', 422);
}

/*
|--------------------------------------------------------------------------
| Check existing username/email
|--------------------------------------------------------------------------
*/

try {
    $checkStmt = $pdo->prepare(
        'SELECT id
         FROM users
         WHERE username = :username OR email = :email
         LIMIT 1'
    );

    $checkStmt->execute([
        ':username' => $username,
        ':email' => strtolower($email)
    ]);

    if ($checkStmt->fetch()) {
        response(false, 'Username or email is already registered.', 409);
    }

    /*
    |--------------------------------------------------------------------------
    | Hash password
    |--------------------------------------------------------------------------
    */

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    if ($passwordHash === false) {
        response(false, 'Unable to secure the password.', 500);
    }

    /*
    |--------------------------------------------------------------------------
    | Insert account into MySQL
    |--------------------------------------------------------------------------
    */

    $insertStmt = $pdo->prepare(
        'INSERT INTO users
            (username, email, password_hash)
         VALUES
            (:username, :email, :password_hash)'
    );

    $insertStmt->execute([
        ':username' => $username,
        ':email' => strtolower($email),
        ':password_hash' => $passwordHash
    ]);

    $userId = (int) $pdo->lastInsertId();

    /*
    |--------------------------------------------------------------------------
    | Insert profile into MongoDB
    |--------------------------------------------------------------------------
    */

    $profileCollection = $mongoDb->selectCollection('profiles');

    try {

        $profileCollection->insertOne([
            'user_id' => $userId,
            'full_name' => $name,,
            'age' => $age,
            'bio' => $bio,
            'interests' => $interests,
            'created_at' => new MongoDB\BSON\UTCDateTime(),
            'updated_at' => new MongoDB\BSON\UTCDateTime()
        ]);

    } catch (Throwable $mongoException) {

        /*
        |--------------------------------------------------------------------------
        | Compensation:
        | Remove the MySQL account if MongoDB profile creation fails.
        |--------------------------------------------------------------------------
        */

        $deleteStmt = $pdo->prepare(
            'DELETE FROM users WHERE id = :id'
        );

        $deleteStmt->execute([
            ':id' => $userId
        ]);

        throw $mongoException;
    }

    response(
        true,
        'Account created successfully. Redirecting to login...',
        201
    );

} catch (PDOException $e) {

    error_log('Registration MySQL error: ' . $e->getMessage());

    response(
        false,
        'Registration could not be completed.',
        500
    );

} catch (Throwable $e) {

    error_log('Registration error: ' . $e->getMessage());

    response(
        false,
        'Registration could not be completed.',
        500
    );
}