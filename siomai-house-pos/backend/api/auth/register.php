<?php

require_once "../../../config/cors.php";
require_once "../../../config/helpers.php";
require_once "../../../config/database.php";

session_start();

require_post();

$data = json_input();

$name = trim($data["name"] ?? "");
$email = trim($data["email"] ?? "");
$password = $data["password"] ?? "";
$confirmPassword = $data["confirmPassword"] ?? "";

if ($name === "" || $email === "" || $password === "" || $confirmPassword === "") {
    fail("All fields are required.");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fail("Please enter a valid email address.");
}

if (strlen($password) < 6) {
    fail("Password must contain at least 6 characters.");
}

if ($password !== $confirmPassword) {
    fail("Passwords do not match.");
}

try {

    /*
     * Public registration creates CLIENT accounts only.
     * They are stored in their own table (clients),
     * separate from staff / owner accounts (users).
     */
    ensure_clients_table($pdo);

    if (email_in_users($pdo, $email) || email_in_clients($pdo, $email)) {
        fail("This email is already registered.");
    }

    $pdo->prepare("
        INSERT INTO clients (name, email, password, status)
        VALUES (?, ?, ?, 'Active')
    ")->execute([
        $name,
        $email,
        password_hash($password, PASSWORD_DEFAULT)
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Account created successfully."
    ]);

} catch (PDOException $e) {

    fail("Registration failed because of a database error.", 500);
}
