<?php
/**
 * Streamers Admin Panel - Admin Server Settings Logic (Top)
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

$userLevel = $_SESSION['user_level'] ?? '';
$action = $_GET['action'] ?? '';

if ($userLevel === "Super Administrator") {
    if ($action === "savesettings") {
        $hostAdd = $_POST['host_add'] ?? '';
        $dirToCpanel = $_POST['dir_to_cpanel'] ?? '';
        $title = $_POST['title'] ?? '';
        $slogan = $_POST['slogan'] ?? '';
        $phpMp3 = $_POST['php_mp3'] ?? '';
        $phpExe = $_POST['php_exe'] ?? '';
        $displayLimit = (int)($_POST['display_limit'] ?? 10);
        $updateCheck = $_POST['update_check'] ?? '';
        $sshUser = base64_encode($_POST['ssh_user'] ?? '');
        $language = $_POST['language'] ?? '';
        $sshPass = $_POST['ssh_pass'] ?? '';
        $sshPort = (int)($_POST['ssh_port'] ?? 22);
        $loginCaptcha = $_POST['login_captcha'] ?? '';
        $serverNews = $_POST['servernews'] ?? '';

        try {
            if ($sshPass !== '***') {
                $encodedPass = base64_encode($sshPass);
                DB::update('settings', [
                    'host_add' => $hostAdd,
                    'dir_to_cpanel' => $dirToCpanel,
                    'title' => $title,
                    'slogan' => $slogan,
                    'php_mp3' => $phpMp3,
                    'php_exe' => $phpExe,
                    'display_limit' => $displayLimit,
                    'update_check' => $updateCheck,
                    'ssh_user' => $sshUser,
                    'language' => $language,
                    'ssh_pass' => $encodedPass,
                    'ssh_port' => $sshPort,
                    'login_captcha' => $loginCaptcha,
                    'server_news' => $serverNews
                ], "id='0'");
            } else {
                DB::update('settings', [
                    'host_add' => $hostAdd,
                    'dir_to_cpanel' => $dirToCpanel,
                    'title' => $title,
                    'slogan' => $slogan,
                    'php_mp3' => $phpMp3,
                    'php_exe' => $phpExe,
                    'display_limit' => $displayLimit,
                    'update_check' => $updateCheck,
                    'ssh_user' => $sshUser,
                    'language' => $language,
                    'ssh_port' => $sshPort,
                    'login_captcha' => $loginCaptcha,
                    'server_news' => $serverNews
                ], "id='0'");
            }

            $correc[] = "<h2>" . ($messages["226"] ?? 'Settings saved successfully') . "</h2>";
        } catch (Exception $e) {
            $errors[] = "<h2>" . ($messages["227"] ?? 'Could not save settings') . "</h2>";
        }
    }
}