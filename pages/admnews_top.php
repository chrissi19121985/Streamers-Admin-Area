<?php
/**
 * Streamers Admin Panel - Admin News Management Logic (Top)
 *
 * Modernisiert für PHP 8.x und MeekroDB
 * Originally written by Sebastian Graebner <djcrackhome>
 * Fixed and edited by David Schomburg <dave>
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

// --- NEWS LÖSCHEN (PER GET-PARAMETER MIT SICHEREM REDIRECT) ---
if (isset($_GET['action']) && $_GET['action'] === 'delnews' && isset($_GET['id'])) {
    $delId = (int)$_GET['id'];
    try {
        DB::delete('news', 'id = %i', $delId);
    } catch (Exception $e) {
        // Fehler stumm abfangen
    }
    header('Location: content.php?include=admnews');
    exit;
}

$submitPost = $_POST['submit'] ?? null;

// --- NEWS HINZUFÜGEN ---
if ($submitPost !== null) {
    $titel = trim((string)($_POST['titel'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));

    if ($titel === '') {
        $formerror = "reason";
    } elseif ($message === '') {
        $formerror = "message";
    } else {
        $messageSql = nl2br($message);
        $reasonSql = html_entity_decode($titel, ENT_QUOTES, 'UTF-8');

        try {
            DB::insert('news', [
                'titel' => $reasonSql,
                'text' => $messageSql
            ]);
            header('Location: content.php?include=admnews');
            exit;
        } catch (Exception $e) {
            $errors[] = "<h2>Could not add news</h2>";
        }
    }
}