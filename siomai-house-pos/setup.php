<?php
/*
 * Creates (or resets) an Admin account with a correctly hashed password.
 *
 * Open in your browser:
 *   http://localhost/siomai-house-pos/setup.php?email=paranoia@gmail.com&password=123456&name=Moncee
 *
 * Optional: &role=admin | owner | staff | cashier | client   (default: admin)
 * This is how Admin / Owner / Staff accounts are created. Public registration only makes Clients.
 *
 * Works from localhost only. DELETE THIS FILE when you are done.
 */
header("Content-Type: text/plain; charset=UTF-8");

$ip = $_SERVER["REMOTE_ADDR"] ?? "";
if (!in_array($ip, ["127.0.0.1", "::1"], true)) {
    http_response_code(403);
    exit("setup.php only works from localhost.");
}

require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/config/helpers.php";

$email    = trim($_GET["email"] ?? "");
$password = $_GET["password"] ?? "";
$name     = trim($_GET["name"] ?? "Admin");
$roleKey  = strtolower(trim($_GET["role"] ?? "admin"));

if ($email === "" || strlen($password) < 6) {
    exit("Usage: setup.php?email=you@example.com&password=atleast6chars&name=YourName");
}


if ($roleKey === "client") {

    ensure_clients_table($pdo);

    $existing = $pdo->prepare("SELECT id FROM clients WHERE email = ? LIMIT 1");
    $existing->execute([$email]);
    $clientId = $existing->fetchColumn();
    $hash = password_hash($password, PASSWORD_DEFAULT);

    if ($clientId) {
        $pdo->prepare("UPDATE clients SET name = ?, password = ?, status = 'Active' WHERE id = ?")
            ->execute([$name, $hash, $clientId]);
        exit("Updated existing client account: $email\n");
    }

    if (email_in_users($pdo, $email)) {
        exit("That email already belongs to a staff account.");
    }

    $pdo->prepare("INSERT INTO clients (name, email, password, status) VALUES (?, ?, ?, 'Active')")
        ->execute([$name, $email, $hash]);

    exit("Created client account: $email\n");
}

$roleNames = [
    "admin"   => "Admin",
    "owner"   => "Owner",
    "cashier" => "Cashier",
    "staff"   => "Staff",
    "client"  => "Client",
];

if (!isset($roleNames[$roleKey])) {
    exit("Unknown role. Use admin, owner, staff, cashier or client.");
}

$r = $pdo->prepare("SELECT id FROM roles WHERE role_name = ? LIMIT 1");
$r->execute([$roleNames[$roleKey]]);
$roleId = $r->fetchColumn();

if (!$roleId) {
    $pdo->prepare("INSERT INTO roles (role_name) VALUES (?)")->execute([$roleNames[$roleKey]]);
    $roleId = $pdo->lastInsertId();
}

$roleId = (int)$roleId;

$hash = password_hash($password, PASSWORD_DEFAULT);

if (email_in_clients($pdo, $email)) {
    exit("That email already belongs to a client account.");
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$existing = $stmt->fetch();

if ($existing) {
    $pdo->prepare("UPDATE users SET password = ?, status = 'Active', role_id = ?, name = ? WHERE id = ?")
        ->execute([$hash, $roleId, $name, $existing["id"]]);
    echo "Updated existing account: $email (role: $roleKey, password reset, Active).\n";
} else {
    $pdo->prepare("INSERT INTO users (role_id, name, email, password, status) VALUES (?, ?, ?, ?, 'Active')")
        ->execute([$roleId, $name, $email, $hash]);
    echo "Created $roleKey account: $email\n";
}

echo "You can now log in. Delete setup.php afterwards.\n";
