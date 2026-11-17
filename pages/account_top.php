<?php
/**
 * Streamers Admin Panel - User Profile Logic (Top)
 *
 * Modernisiert für PHP 8.x und MeekroDB
 * Originally written by Sebastian Graebner <djcrackhome>[cite: 16]
 * Fixed and edited by David Schomburg <dave>[cite: 16]
 */

declare(strict_types=1);

if (stripos($_SERVER['PHP_SELF'], 'content.php') === false) {
    die("You can't access this file directly...");
}

// Einstellungen über MeekroDB laden[cite: 16]
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

$submitPost = $_POST['submit'] ?? null;
$loginun = $_SESSION['username'] ?? '';
$userDataId = isset($userdata['id']) ? (int)$userdata['id'] : 0;

if ($submitPost !== null && $userDataId > 0) {
    $uUserPassword = $_POST['u_user_password'] ?? '';
    $uCuserPassword = $_POST['u_cuser_password'] ?? '';
    $sessionUserPassword = $_SESSION['user_password'] ?? '';

    if ($uCuserPassword !== "") {
        if ($uUserPassword === $uCuserPassword) {
            $uMd5Hash = md5($loginun . $uUserPassword);
            try {
                DB::update('users', [
                    'user_password' => $uUserPassword,
                    'md5_hash' => $uMd5Hash
                ], 'id=%i', $userDataId);

                $_SESSION['user_password'] = $uUserPassword;
            } catch (Exception $e) {
                // Fehler beim Passwort-Update abfangen
            }
        } else {
            $notifi[] = "<h2>" . ($messages["82"] ?? 'Passwords do not match') . "</h2>";
        }
    }

    if ($sessionUserPassword !== $uUserPassword) {
        $notifi[] = "<h2>" . ($messages["83"] ?? 'Password mismatch with session') . "</h2>";
    }

    $uUserEmail = trim((string)($_POST["u_user_email"] ?? ''));
    $uContactNumber = trim((string)($_POST["u_contact_number"] ?? ''));
    $uMobileNumber = trim((string)($_POST["u_mobile_number"] ?? ''));
    $uName = trim((string)($_POST["u_name"] ?? ''));
    $uSurname = trim((string)($_POST["u_surname"] ?? ''));
    $uAge = trim((string)($_POST["u_age"] ?? ''));

    try {
        DB::update('users', [
            'user_email' => $uUserEmail,
            'contact_number' => $uContactNumber,
            'mobile_number' => $uMobileNumber,
            'name' => $uName,
            'surname' => $uSurname,
            'age' => $uAge
        ], 'id=%i', $userDataId);

        $correc[] = "<h2>" . ($messages["84"] ?? 'Profile updated successfully') . "</h2>";
    } catch (Exception $e) {
        $errors[] = "<h2>" . ($messages["85"] ?? 'Could not update profile') . "</h2>";
    }
}