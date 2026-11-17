<?php
/**
 * Streamers Admin Panel - AutoDJ View & Configuration
 *
 * Kompakte Akkordeon-Ansicht für die Moderatoren (1 bis 30) mit weißen Beschriftungen.
 * Modernisiert für PHP 8.x, MeekroDB & Bootstrap 5
 */

declare(strict_types=1);

if (stripos($_SERVER['PHP_SELF'], 'content.php') === false) {
    die("You can't access this file directly...");
}

$loginun = $_SESSION['username'] ?? '';
$action = $_GET['action'] ?? '';
$hostAdd = htmlspecialchars($setting['host_add'] ?? 'localhost', ENT_QUOTES, 'UTF-8');
$viewId = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_GET['view']) ? (int)$_GET['view'] : 0);
$saveSuccess = isset($_GET['saved']) && $_GET['saved'] === '1';

// 1. DIREKTES SPEICHERN BEI POST-REQUEST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit']) && $viewId > 0) {
    $updateData = [
        'calendarrewrite' => $_POST['calendarrewrite'] ?? '0',
        'djcapture' => $_POST['djcapture'] ?? '0'
    ];

    for ($k = 1; $k <= 30; $k++) {
        $updateData['djlogin_' . $k] = $_POST['djlogin_' . $k] ?? '';
        $updateData['djpassword_' . $k] = $_POST['djpassword_' . $k] ?? '';
        $updateData['djpriority_' . $k] = $_POST['djpriority_' . $k] ?? '1';
        
        if ($k === 1) {
            $updateData['djport_1'] = $_POST['djport_1'] ?? '';
        }
    }

    try {
        DB::update('servers', $updateData, 'id=%i AND owner=%s', $viewId, $loginun);
        header("Location: content.php?include=autodj&id=" . $viewId . "&action=manage&tab=djs&saved=1");
        exit();
    } catch (Exception $e) {
        // Fehler abfangen
    }
}

// 2. Server- und Moderator-Daten laden
$formedit_port = 0;
$formedit_calendarrewrite = '0';
$formedit_djcapture = '0';

for ($k = 1; $k <= 30; $k++) {
    ${'formedit_djlogin_' . $k} = '';
    ${'formedit_djpassword_' . $k} = '';
    ${'formedit_djport_' . $k} = '';
    ${'formedit_djpriority_' . $k} = '1';
}

if ($viewId > 0) {
    try {
        $serverRow = DB::queryFirstRow("SELECT * FROM servers WHERE id=%i AND owner=%s", $viewId, $loginun);
        if ($serverRow) {
            $formedit_port = $serverRow["portbase"] ?? 0;
            $formedit_calendarrewrite = $serverRow["calendarrewrite"] ?? '0';
            $formedit_djcapture = $serverRow["djcapture"] ?? '0';

            for ($k = 1; $k <= 30; $k++) {
                ${'formedit_djlogin_' . $k} = $serverRow["djlogin_" . $k] ?? '';
                ${'formedit_djpassword_' . $k} = $serverRow["djpassword_" . $k] ?? '';
                ${'formedit_djport_' . $k} = $serverRow["djport_" . $k] ?? '';
                ${'formedit_djpriority_' . $k} = $serverRow["djpriority_" . $k] ?? '1';
            }
        }
    } catch (Exception $e) {}
}
?>

<!-- Erfolgsmeldung -->
<?php if ($saveSuccess): ?>
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center mb-4 shadow-sm" role="alert">
        <i class="fa-solid fa-circle-check fs-5 me-2"></i>
        <div><strong>Erfolgreich!</strong> Die Moderatoren-Einstellungen wurden erfolgreich aktualisiert.</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div>
    <?php
    $osSetting = $setting['os'] ?? '';

    if ($osSetting === 'linux' && $formedit_port > 0) {
        $fileban = getcwd() . "/temp/" . $formedit_port . "/logs/banfile.ban";
        $dirBan = dirname($fileban);
        if (!file_exists($dirBan)) { @mkdir($dirBan, 0777, true); }
        @chmod($fileban, 0777);
        $gzp = @fopen($fileban, "a");
        if ($gzp) {
            @fwrite($gzp, $ban ?? '');
            @fclose($gzp);
        }

        $filerip = getcwd() . "/temp/" . $formedit_port . "/logs/ripfile.rip";
        $dirRip = dirname($filerip);
        if (!file_exists($dirRip)) { @mkdir($dirRip, 0777, true); }
        @chmod($filerip, 0777);
        $gzp2 = @fopen($filerip, "a");
        if ($gzp2) {
            @fwrite($gzp2, $rip ?? '');
            @fclose($gzp2);
        }

        $filexml = getcwd() . "/temp/" . $formedit_port . "/calendar/calendar.xml";
        $dirXml = dirname($filexml);
        if (!file_exists($dirXml)) { @mkdir($dirXml, 0777, true); }
        if (file_exists($filexml)) {
            @unlink($filexml);
        }

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\r\n";
        $xml .= "<eventlist>\r\n";
        for ($j = 1; $j <= 30; $j++) {
            $prioVar = ${'formedit_djpriority_' . $j} ?? '0';
            $loginVar = ${'formedit_djlogin_' . $j} ?? '';
            $xml .= "<event type=\"dj\">\r\n";
            $xml .= "<dj archive=\"" . htmlspecialchars((string)$prioVar, ENT_QUOTES, 'UTF-8') . "\">" . htmlspecialchars((string)$loginVar, ENT_QUOTES, 'UTF-8') . "</dj>\r\n";
            $xml .= "<calendar />\r\n";
            $xml .= "</event>\r\n";
        }
        $xml .= "</eventlist>";

        $gz = @fopen($filexml, "a");
        if ($gz) {
            @fwrite($gz, $xml);
            @fclose($gz);
            @chmod($filexml, 0777);
        }
    }
    ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="fw-bold small text-uppercase" style="color: var(--text-muted);">
            <i class="fa-solid fa-user-shield me-1 text-warning"></i> Moderatoren-Verwaltung (Slots 1-30)
        </div>
    </div>

    <form method="post" action="content.php?include=autodj&id=<?php echo $viewId; ?>&action=manage&tab=djs">
        <fieldset class="border-0 p-0 m-0">
            <legend class="visually-hidden">Moderatoren Einstellungen</legend>

            <!-- Allgemeine Optionen -->
            <div class="card p-3 mb-3 border" style="background-color: var(--bg-surface); border-color: var(--border-color) !important;">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold" style="color: var(--text-primary);">Calendar Rewrite</label>
                        <select class="form-select form-select-sm" name="calendarrewrite" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="0"<?php if ($formedit_calendarrewrite === '0') echo " selected"; ?>>Off</option>
                            <option value="1"<?php if ($formedit_calendarrewrite === '1') echo " selected"; ?>>On</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold" style="color: var(--text-primary);">Stream Recording</label>
                        <select class="form-select form-select-sm" name="djcapture" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="0"<?php if ($formedit_djcapture === '0') echo " selected"; ?>>Off</option>
                            <option value="1"<?php if ($formedit_djcapture === '1') echo " selected"; ?>>On</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-danger">Moderator IP / URL</label>
                        <input class="form-control form-control-sm" type="text" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo $hostAdd; ?>" readonly />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold" style="color: var(--text-primary);">Moderator-Port</label>
                        <input class="form-control form-control-sm" name="djport_1" type="text" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo htmlspecialchars((string)$formedit_djport_1, ENT_QUOTES, 'UTF-8'); ?>" readonly />
                    </div>
                </div>
            </div>

            <!-- AKKORDEON FÜR DIE MODERATOREN -->
            <div class="accordion" id="djAccordion">
                <?php
                for ($block = 0; $block < 6; $block++) {
                    $startSlot = ($block * 5) + 1;
                    $endSlot = $startSlot + 4;
                    $collapseId = "djBlock" . $block;
                    $headingId = "heading" . $block;
                ?>
                <div class="accordion-item mb-2 border rounded-3 overflow-hidden" style="background-color: var(--bg-card); border-color: var(--border-color) !important;">
                    <h2 class="accordion-header" id="<?php echo $headingId; ?>">
                        <button class="accordion-button collapsed py-2 px-3 fw-bold small" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>" aria-expanded="false" aria-controls="<?php echo $collapseId; ?>" style="background-color: var(--bg-surface); color: var(--text-primary);">
                            <i class="fa-solid fa-users me-2 text-primary"></i> Moderatoren <?php echo $startSlot; ?> bis <?php echo $endSlot; ?>
                        </button>
                    </h2>
                    <div id="<?php echo $collapseId; ?>" class="accordion-collapse collapse" aria-labelledby="<?php echo $headingId; ?>" data-bs-parent="#djAccordion">
                        <div class="accordion-body p-3">
                            <div class="row g-3">
                                <?php
                                for ($k = $startSlot; $k <= $endSlot; $k++) {
                                    $loginVal = htmlspecialchars((string)(${'formedit_djlogin_' . $k}), ENT_QUOTES, 'UTF-8');
                                    $passVal = htmlspecialchars((string)(${'formedit_djpassword_' . $k}), ENT_QUOTES, 'UTF-8');
                                    $prioVal = ${'formedit_djpriority_' . $k};
                                    
                                    $labelLogin = ($k === 1) ? 'Moderator Login' : "Moderator Login " . $k;
                                    $labelPass = ($k === 1) ? 'Moderator Passwort' : "Moderator Passwort " . $k;
                                ?>
                                <div class="col-12 <?php echo ($k > $startSlot) ? 'border-top pt-2 mt-1' : ''; ?>" style="border-color: var(--border-color) !important;">
                                    <span class="fw-bold small text-white">Moderator <?php echo $k; ?></span>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label x-small text-white mb-1"><?php echo $labelLogin; ?></label>
                                    <input class="form-control form-control-sm" name="djlogin_<?php echo $k; ?>" type="text" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo $loginVal; ?>" />
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label x-small text-white mb-1"><?php echo $labelPass; ?></label>
                                    <input class="form-control form-control-sm" name="djpassword_<?php echo $k; ?>" type="text" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo $passVal; ?>" />
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label x-small text-white mb-1">Rolle / Rechte</label>
                                    <select class="form-select form-select-sm" name="djpriority_<?php echo $k; ?>" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);">
                                        <?php if ($k === 1) { ?>
                                        <option value="0"<?php if ($prioVal === '0') echo " selected"; ?>>Default</option>
                                        <?php } ?>
                                        <option value="1"<?php if ($prioVal === '1') echo " selected"; ?>>Gast Moderator</option>
                                        <option value="3"<?php if ($prioVal === '3') echo " selected"; ?>>Moderator</option>
                                        <option value="5"<?php if ($prioVal === '5') echo " selected"; ?>>Administrator</option>
                                        <option value="9"<?php if ($prioVal === '9') echo " selected"; ?>>Super Administrator</option>
                                    </select>
                                </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php } ?>
            </div>

            <!-- Speichern-Button -->
            <div class="mt-4 pt-3 border-top d-flex justify-content-between align-items-center" style="border-color: var(--border-color) !important;">
                <button class="btn btn-primary px-4" type="submit" name="submit">
                    <i class="fa-solid fa-floppy-disk me-1"></i> Moderatoren-Daten aktualisieren
                </button>
            </div>
        </fieldset>
    </form>
</div>

<style>
    .accordion-button:not(.collapsed) {
        background-color: var(--bg-surface) !important;
        color: var(--bs-primary) !important;
        box-shadow: none !important;
    }
    .accordion-button:focus {
        box-shadow: none !important;
        border-color: var(--border-color) !important;
    }
    .x-small {
        font-size: 0.75rem;
    }
</style>