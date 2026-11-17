<?php
/**
 * Streamers Admin Panel - Music Management Top / Error Handling
 *
 * Modernisiert für PHP 8.x und MeekroDB
 * Originally written by Sebastian Graebner <djcrackhome>[cite: 16]
 * Fixed and edited by David Schomburg <dave>[cite: 16]
 */

declare(strict_types=1);

if (stripos($_SERVER['PHP_SELF'], 'content.php') === false) {
    die("You can't access this file directly...");
}

// Einstellungen über MeekroDB laden
$setting = [];
try {
    $settingsq = DB::query("SELECT * FROM settings WHERE id='0'");
    foreach ($settingsq as $row) {
        foreach ($row as $key => $pref) {
            if (!is_numeric((string)$key)) {
                $setting[$key] = stripslashes((string)$pref);
            }
        }
    }
} catch (Exception $e) {
    $errors[] = "<h2>" . ($messages["g5"] ?? 'Database error') . "</h2>";
}

$errorParam = $_GET['error'] ?? '';

if ($errorParam === "port") {
    $errors[] = "<h2>" . ($messages["385"] ?? 'Invalid port') . "</h2>";
}
if ($errorParam === "access") {
    $errors[] = "<h2>" . ($messages["386"] ?? 'Access denied') . "</h2>";
}
if ($errorParam === "dir") {
    $errors[] = "<h2>" . ($messages["387"] ?? 'Directory error') . "</h2>";
}
if ($errorParam === "space") {
    $errors[] = "<h2>" . ($messages["388"] ?? 'Space error') . "</h2>";
}
if ($errorParam === "dirlisting") {
    $errors[] = "<h2>" . ($messages["389"] ?? 'Directory listing error') . "</h2>";
}
if ($errorParam === "sc_trans_access") {
    $correc[] = "<h2>" . ($messages["390"] ?? 'SC Trans access success') . "</h2>";
}