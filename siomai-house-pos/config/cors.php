<?php

/*
 * Shared CORS + JSON headers.
 * Allows the React dev server on localhost / 127.0.0.1 / LAN IPs (any port).
 */
$origin = $_SERVER["HTTP_ORIGIN"] ?? "";

$allowed = preg_match(
    '#^https?://(localhost|127\.0\.0\.1|192\.168\.\d{1,3}\.\d{1,3}|10\.\d{1,3}\.\d{1,3}\.\d{1,3}|172\.(1[6-9]|2[0-9]|3[0-1])\.\d{1,3}\.\d{1,3})(:\d+)?$#',
    $origin
);

if ($allowed) {
    header("Access-Control-Allow-Origin: " . $origin);
    header("Vary: Origin");
}

header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if (($_SERVER["REQUEST_METHOD"] ?? "") === "OPTIONS") {
    http_response_code(200);
    exit;
}

/* Never print PHP warnings into JSON responses (they break JSON.parse). */
ini_set("display_errors", "0");
error_reporting(E_ALL);
