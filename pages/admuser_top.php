<?php
/**
 * Streamers Admin Panel - Admin User Management Logic (Top)
 *
 * Modernisiert für PHP 8.x und MeekroDB
 * Originally written by Sebastian Graebner <djcrackhome>[cite: 19]
 * Fixed and edited by David Schomburg <dave>[cite: 19]
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

$limit = isset($setting['display_limit']) ? (int)$setting['display_limit'] : 10;
$p = 0;
if (isset($_POST['p'])) {
    $p = max(0, (int)$_POST['p'] * $limit);
}
$l = $p + $limit;

$action = $_GET['action'] ?? '';
$function = $_GET['function'] ?? '';
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$loginun = $_SESSION['username'] ?? '';
$userLevelSession = $_SESSION['user_level'] ?? '';
$userTbIdSession = $_SESSION['user_tb_id'] ?? 0;

// --- NEUEN BENZER ERSTELLEN ---
if ($action === "newuser" && $function === "update") {
    $eusername = trim((string)($_POST['eusername'] ?? ''));
    try {
        $existingUserCount = DB::queryFirstField("SELECT COUNT(*) FROM users WHERE username=%s", $eusername);
        if ($existingUserCount > 0) {
            $errors[] = "<h2>" . ($messages["266"] ?? 'Username already exists') . "</h2>";
        } else {
            $pass1 = $_POST['euser_password'] ?? '';
            $pass2 = $_POST['ceuser_password'] ?? '';

            if ($pass1 !== $pass2) {
                $notifi[] = "<h2>" . ($messages["267"] ?? 'Passwords do not match') . "</h2>";
            } else {
                $md5Hash = md5(strtolower($eusername . $pass1));
                $insertData = [
                    'username' => $eusername,
                    'user_password' => $pass1,
                    'md5_hash' => $md5Hash,
                    'user_level' => $_POST['euser_level'] ?? 'User',
                    'user_email' => $_POST['euser_email'] ?? '',
                    'contact_number' => $_POST['econtact_number'] ?? '',
                    'mobile_number' => $_POST['emobile_number'] ?? '',
                    'account_notes' => $_POST['eaccount_notes'] ?? '',
                    'name' => $_POST['ename'] ?? '',
                    'surname' => $_POST['esurname'] ?? '',
                    'age' => $_POST['eage'] ?? ''
                ];

                if ($userLevelSession === 'User') {
                    $insertData['dj_of_user'] = $userTbIdSession;
                }

                try {
                    DB::insert('users', $insertData);
                    $correc[] = "<h2>" . ($messages["268"] ?? 'User created successfully') . "</h2>";
                } catch (Exception $e) {
                    $errors[] = "<h2>" . ($messages["269"] ?? 'Could not create user') . "</h2>";
                }
            }
        }
    } catch (Exception $e) {
        $errors[] = "<h2>Database error during user check</h2>";
    }
}

// --- BENUTZER BEARBEITEN / LÖSCHEN ---
if ($action === "edit" && $userId > 0) {
    try {
        $selUserPost = $_POST['seluser'] ?? null;
        if ($selUserPost !== null && $selUserPost !== '') {
            DB::update('users', ['dj_of_user' => $selUserPost], 'id=%i', $userId);
        }

        $targetUser = DB::queryFirstRow("SELECT username, user_level FROM users WHERE id=%i", $userId);
        if (!$targetUser) {
            $errors[] = "<h2>" . ($messages["270"] ?? 'User not found') . "</h2>";
            $user_check = "1";
        } else {
            $user_check = "0";
            $targetUsername = $targetUser['username'];
            $targetUserLevel = $targetUser['user_level'];

            if ($function === "update" && $user_check !== "1") {
                $passUpdate = $_POST['euser_password'] ?? '';
                $newMd5 = md5($targetUsername . $passUpdate);

                try {
                    DB::update('users', [
                        'md5_hash' => $newMd5,
                        'user_level' => $_POST['euser_level'] ?? 'User',
                        'contact_number' => $_POST['econtact_number'] ?? '',
                        'mobile_number' => $_POST['emobile_number'] ?? '',
                        'user_email' => $_POST['euser_email'] ?? '',
                        'name' => $_POST['ename'] ?? '',
                        'surname' => $_POST['esurname'] ?? '',
                        'age' => $_POST['eage'] ?? '',
                        'account_notes' => $_POST['eaccount_notes'] ?? ''
                    ], 'id=%i', $userId);

                    $correc[] = "<h2>" . ($messages["271"] ?? 'User updated successfully') . "</h2>";
                } catch (Exception $e) {
                    $errors[] = "<h2>" . ($messages["272"] ?? 'Could not update user') . "</h2>";
                }
            }

            if ($function === "delete" && $user_check !== "1") {
                if ($targetUsername === $loginun) {
                    $errors[] = "<h2>" . ($messages["273"] ?? 'You cannot delete yourself') . "</h2>";
                } elseif ($targetUserLevel === "Super Administrator") {
                    $errors[] = "<h2>" . ($messages["274"] ?? 'Cannot delete Super Administrator') . "</h2>";
                } else {
                    try {
                        DB::delete('users', 'id=%i', $userId);
                        $correc[] = "<h2>" . ($messages["275"] ?? 'User deleted successfully') . "</h2>";
                        $user_check = "1";
                    } catch (Exception $e) {
                        $errors[] = "<h2>" . ($messages["276"] ?? 'Could not delete user') . "</h2>";
                    }
                }
            }
        }
    } catch (Exception $e) {
        $errors[] = "<h2>Database error during user management</h2>";
    }
}