<?php
/**
 * Streamers Admin Panel - Admin Server Settings View & Form (Bottom)
 *
 * Vollständig angepasst an den hellen und dunklen Modus.
 *
 * Modernisiert für PHP 8.x & Bootstrap 5
 * Originally written by Sebastian Graebner <djcrackhome>
 * Fixed and edited by David Schomburg <dave> & Shippo21
 */

declare(strict_types=1);

if (stripos($_SERVER['PHP_SELF'], 'content.php') === false) {
    die("You can't access this file directly...");
}

// Hilfsfunktion für sichere Nachrichten-Ausgabe
$msg = static function(string $key, string $default = '') use ($messages): string {
    return $messages[$key] ?? $default;
};

// Settings-Werte absichern
$hostAdd = htmlspecialchars($setting['host_add'] ?? '', ENT_QUOTES, 'UTF-8');
$osVal = htmlspecialchars($setting['os'] ?? '', ENT_QUOTES, 'UTF-8');
$sshUserDecoded = htmlspecialchars(base64_decode($setting['ssh_user'] ?? ''), ENT_QUOTES, 'UTF-8');
$sshPort = htmlspecialchars((string)($setting['ssh_port'] ?? ''), ENT_QUOTES, 'UTF-8');
$dirToCpanel = htmlspecialchars($setting['dir_to_cpanel'] ?? '', ENT_QUOTES, 'UTF-8');
$title = htmlspecialchars($setting['title'] ?? '', ENT_QUOTES, 'UTF-8');
$slogan = htmlspecialchars($setting['slogan'] ?? '', ENT_QUOTES, 'UTF-8');
$phpExe = htmlspecialchars((string)($setting['php_exe'] ?? ''), ENT_QUOTES, 'UTF-8');
$displayLimit = htmlspecialchars((string)($setting['display_limit'] ?? 10), ENT_QUOTES, 'UTF-8');
$currentLang = $setting['language'] ?? 'german';
$loginCaptcha = $setting['login_captcha'] ?? '0';
$serverNews = $setting['server_news'] ?? '1';
?>

<!-- Header / Titel -->
<div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom" style="border-color: var(--border-color) !important;">
    <h2 class="h4 mb-0 fw-bold" style="color: var(--text-primary);">
        <i class="fa-solid fa-gears text-primary me-2"></i>
        <?php echo htmlspecialchars($msg("188", "Server Settings"), ENT_QUOTES, 'UTF-8'); ?> 
    </h2>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0" style="background-color: var(--bg-card); color: var(--text-primary); border-color: var(--border-color) !important;">
            <div class="card-body p-4">
                <h5 class="card-title fw-bold mb-3 small text-uppercase" style="color: var(--text-muted);">
                    <i class="fa-solid fa-sliders me-1 text-warning"></i> <?php echo htmlspecialchars($msg("189", "Configure global server options"), ENT_QUOTES, 'UTF-8'); ?> 
                </h5>

                <form method="post" action="content.php?include=admserver&action=savesettings">    
                    <fieldset class="border-0 p-0 m-0">
                        <legend class="visually-hidden"><?php echo htmlspecialchars($msg("192", "Settings"), ENT_QUOTES, 'UTF-8'); ?> </legend>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("193", "Host Address"), ENT_QUOTES, 'UTF-8'); ?> </label>
                                <input class="form-control" name="host_add" type="text" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo $hostAdd; ?>" /> 
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("194", "Main server IP or hostname"), ENT_QUOTES, 'UTF-8'); ?> </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("195", "Operating System"), ENT_QUOTES, 'UTF-8'); ?> </label>
                                <input class="form-control" name="os" type="text" style="background-color: var(--bg-surface); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo $osVal; ?>" readonly /> 
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("196", "Detected OS"), ENT_QUOTES, 'UTF-8'); ?> </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("197", "SSH Username"), ENT_QUOTES, 'UTF-8'); ?> </label>
                                <input class="form-control" name="ssh_user" type="text" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo $sshUserDecoded; ?>" /> 
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("198", "SSH login user"), ENT_QUOTES, 'UTF-8'); ?> </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("199", "SSH Password"), ENT_QUOTES, 'UTF-8'); ?> </label>
                                <input class="form-control" name="ssh_pass" type="password" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="***" /> 
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("200", "Leave *** to keep unchanged"), ENT_QUOTES, 'UTF-8'); ?> </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("201", "SSH Port"), ENT_QUOTES, 'UTF-8'); ?> </label>
                                <input class="form-control" name="ssh_port" type="text" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo $sshPort; ?>" /> 
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("202", "SSH connection port"), ENT_QUOTES, 'UTF-8'); ?> </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("203", "CPanel Directory"), ENT_QUOTES, 'UTF-8'); ?> </label>
                                <input class="form-control" name="dir_to_cpanel" type="text" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo $dirToCpanel; ?>" /> 
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("204", "Absolute path to panel"), ENT_QUOTES, 'UTF-8'); ?> </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("205", "Panel Title"), ENT_QUOTES, 'UTF-8'); ?> </label>
                                <input class="form-control" name="title" type="text" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo $title; ?>" /> 
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("206", "Main title"), ENT_QUOTES, 'UTF-8'); ?> </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("207", "Slogan"), ENT_QUOTES, 'UTF-8'); ?> </label>
                                <input class="form-control" name="slogan" type="text" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo $slogan; ?>" /> 
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("208", "Panel slogan"), ENT_QUOTES, 'UTF-8'); ?> </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("211", "Language"), ENT_QUOTES, 'UTF-8'); ?> </label>
                                <select class="form-select" name="language" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);">
                                    <?php
                                    define('entries_per_page', 100);
                                    $offset = (isset($_GET['filecount']) && is_numeric($_GET['filecount'])) ? (int)$_GET['filecount'] : 1;
                                    if ($offset === 1) {
                                        $listing_start = $offset * entries_per_page - entries_per_page;
                                    } else {
                                        $listing_start = $offset * entries_per_page - entries_per_page + 3;
                                    }
                                    $listing_end = $offset * entries_per_page + 2;
                                    
                                    $langDir = "./pages/messages/";
                                    $dirlisting = @scandir($langDir);
                                    if ($dirlisting === false) {
                                        $dirlisting = [];
                                    }
                                    
                                    for ($i = $listing_start; $i <= $listing_end; $i++) {
                                        if (isset($dirlisting[$i]) && $dirlisting[$i] !== "." && $dirlisting[$i] !== ".." && $dirlisting[$i] !== "") {
                                            $langName = substr($dirlisting[$i], 0, -4);
                                            $selectedAttr = ($langName === $currentLang) ? " selected" : "";
                                            echo "<option" . $selectedAttr . " value=\"" . htmlspecialchars($langName, ENT_QUOTES, 'UTF-8') . "\">" . htmlspecialchars(ucfirst($langName), ENT_QUOTES, 'UTF-8') . "</option>";
                                        }
                                    }
                                    ?>
                                </select>
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("212", "Select language"), ENT_QUOTES, 'UTF-8'); ?> </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("213", "PHP Execution Time"), ENT_QUOTES, 'UTF-8'); ?> </label>
                                <input class="form-control" name="php_exe" type="text" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo $phpExe; ?>" /> 
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("214", "Max execution time"), ENT_QUOTES, 'UTF-8'); ?> </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("223", "Display Limit"), ENT_QUOTES, 'UTF-8'); ?> </label>
                                <input class="form-control" name="display_limit" type="text" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo $displayLimit; ?>" /> 
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("224", "Pagination limit"), ENT_QUOTES, 'UTF-8'); ?> </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("221", "Login Captcha"), ENT_QUOTES, 'UTF-8'); ?> </label>
                                <select class="form-select" name="login_captcha" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);">
                                    <option value="0"<?php if ($loginCaptcha === '0') echo " selected"; ?>><?php echo htmlspecialchars($msg("dd16", "Off"), ENT_QUOTES, 'UTF-8'); ?> </option> 
                                    <option value="1"<?php if ($loginCaptcha === '1') echo " selected"; ?>><?php echo htmlspecialchars($msg("dd15", "On"), ENT_QUOTES, 'UTF-8'); ?> </option> 
                                </select>
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("220", "Enable captcha on login"), ENT_QUOTES, 'UTF-8'); ?> </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("admsrv_9", "Server News"), ENT_QUOTES, 'UTF-8'); ?> </label>
                                <select class="form-select" name="servernews" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);">
                                    <option value="1"<?php if ($serverNews === '1') echo " selected"; ?>><?php echo htmlspecialchars($msg("admsrv_10", "Show"), ENT_QUOTES, 'UTF-8'); ?> </option> 
                                    <option value="0"<?php if ($serverNews === '0') echo " selected"; ?>><?php echo htmlspecialchars($msg("admsrv_11", "Hide"), ENT_QUOTES, 'UTF-8'); ?> </option> 
                                </select>
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("admsrv_12", "Display news widget"), ENT_QUOTES, 'UTF-8'); ?> </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button class="btn btn-primary px-4" type="submit">
                                <i class="fa-solid fa-floppy-disk me-1"></i> <?php echo htmlspecialchars($msg("225", "Save Settings"), ENT_QUOTES, 'UTF-8'); ?> 
                            </button>
                        </div>
                    </fieldset>
                </form>
            </div>
        </div>
    </div>

    <!-- Info Sidebar Widget -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 h-100" style="background-color: var(--bg-card); border: 1px solid var(--border-color) !important;">
            <div class="card-body p-4">
                <h5 class="card-title fw-bold mb-3 text-primary">
                    <i class="fa-solid fa-circle-info me-1"></i> <?php echo htmlspecialchars($msg("190", "Information"), ENT_QUOTES, 'UTF-8'); ?> 
                </h5>
                <p class="card-text mb-0" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("191", "Manage SSH, paths and core configurations here."), ENT_QUOTES, 'UTF-8'); ?> </p>
            </div>
        </div>
    </div>
</div>