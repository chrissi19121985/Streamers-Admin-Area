<?php
/**
 * Streamers Admin Panel - Admin Radio Server Management View (Bottom)
 *
 * Vollständig angepasst an den hellen und dunklen Modus.
 *
 * Modernisiert für PHP 8.x, MeekroDB & Bootstrap 5
 * Originally written by Sebastian Graebner <djcrackhome>
 * Fixed and edited by David Schomburg <dave> & Shippo21
 */

declare(strict_types=1);

if (stripos($_SERVER['PHP_SELF'], 'content.php') === false) {
    die("You can't access this file directly...");
}

$hostAdd = htmlspecialchars($setting['host_add'] ?? 'localhost', ENT_QUOTES, 'UTF-8');
$actionVal = $_GET['action'] ?? '';
$viewId = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$loginun = $_SESSION['username'] ?? '';

// Hilfsfunktion für Nachrichten
$msg = static function(string $key, string $default = '') use ($messages): string {
    return $messages[$key] ?? $default;
};
?>

<!-- Header / Titel -->
<div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom" style="border-color: var(--border-color) !important;">
    <h2 class="h4 mb-0 fw-bold" style="color: var(--text-primary);">
        <i class="fa-solid fa-server text-primary me-2"></i>
        <?php echo htmlspecialchars($msg("86", "Radio Server Management"), ENT_QUOTES, 'UTF-8'); ?>
    </h2>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 mb-4" style="background-color: var(--bg-card); color: var(--text-primary); border-color: var(--border-color) !important;">
            <div class="card-body p-4">
                
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title fw-bold small text-uppercase mb-0" style="color: var(--text-muted);">
                        <i class="fa-solid fa-list me-1 text-warning"></i> <?php echo htmlspecialchars($msg("87", "&Uuml;bersicht &amp; Konfiguration"), ENT_QUOTES, 'UTF-8'); ?>
                    </h5>
                    <a class="btn btn-sm btn-success" href="content.php?include=admradio&action=new">
                        <i class="fa-solid fa-plus me-1"></i> <?php echo htmlspecialchars($msg("93", "Neuer Server"), ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                </div>

                <!-- RADIO SERVER LIST (Modernes Karten-Design über volle Breite) -->
                <?php
                if (empty($listq)) {
                    echo '<div class="text-center py-4" style="color: var(--text-muted);">' . htmlspecialchars($msg("94", "Keine Server gefunden"), ENT_QUOTES, 'UTF-8') . '</div>';
                } else {
                    echo '<div class="d-flex flex-column gap-3">';
                    foreach ($listq as $data) {
                        $portBase = (int)$data['portbase'];
                        $dataId = (int)$data['id'];
                        $ownerSafe = htmlspecialchars($data['owner'], ENT_QUOTES, 'UTF-8');
                        
                        // Speicherplatz-Berechnung
                        $actual_dir_size = 0.0;
                        $uploadDir = "./uploads/" . $portBase . "/";
                        if (is_dir($uploadDir)) {
                            $filesize = 0;
                            if ($dp = @opendir($uploadDir)) {
                                while (($filename = readdir($dp)) !== false) {
                                    if ($filename === '.' || $filename === '..') {
                                        continue;
                                    }
                                    $filedata = @stat($uploadDir . $filename);
                                    if ($filedata) {
                                        $filesize += $filedata[7];
                                    }
                                }
                                @closedir($dp);
                                $actual_dir_size = $filesize / 1024;
                            }
                        }

                        $webspaceMax = max(1, (float)($data['webspace'] ?? 1));
                        $negative_background_pos = ($actual_dir_size / $webspaceMax) * 120;

                        $fp = @fsockopen($setting['host_add'] ?? 'localhost', $portBase, $errno, $errstr, 1);
                        $online = !$fp;
                        if ($fp) {
                            @fclose($fp);
                        }
                        ?>
                        <div class="card border p-3 shadow-sm rounded-3" style="background-color: var(--bg-card); border-color: var(--border-color) !important;">
                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-2 pb-2 border-bottom" style="border-color: var(--border-color) !important;">
                                <div class="d-flex align-items-center gap-3">
                                    <span class="fw-bold fs-6" style="color: var(--text-primary);">Stream #<?php echo $dataId; ?></span>
                                    <a href="http://<?php echo $hostAdd . ':' . $portBase; ?>/" target="_blank" class="small text-decoration-none fw-bold text-primary">
                                        <i class="fa-solid fa-up-right-from-square me-1"></i>Port: <?php echo $portBase; ?>
                                    </a>
                                    <span class="small d-none d-md-inline" style="color: var(--text-muted);">|</span>
                                    <span class="small" style="color: var(--text-muted);">Besitzer: <strong style="color: var(--text-primary);"><a href="http://<?php echo $hostAdd . ':' . $portBase; ?>/" target="_blank" class="text-decoration-none" style="color: var(--text-primary);"><?php echo $ownerSafe; ?></a></strong></span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <?php if ($online): ?>
                                        <span class="badge bg-danger px-2 py-1"><i class="fa-solid fa-circle fa-2xs me-1"></i> Gestoppt</span>
                                    <?php else: ?>
                                        <span class="badge bg-success px-2 py-1"><i class="fa-solid fa-circle fa-2xs me-1"></i> L&auml;uft</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 my-2">
                                <div class="small" style="color: var(--text-muted);">
                                    Speicherauslastung:
                                    <div class="space_show mt-1" style="background-position:-<?php echo round($negative_background_pos, 2); ?>px 0px; width: 150px; height: 10px; background-color: var(--border-color); border-radius: 3px;" title="Speicher genutzt"></div>
                                </div>

                                <div class="d-flex flex-wrap gap-2 justify-content-end align-items-center">
                                    <?php if ($online): ?>
                                        <a class="btn btn-sm btn-outline-danger px-3" href="content.php?include=admradio&view=<?php echo $dataId; ?>&action=start" onclick="return confirm('Wollen Sie den Server <?php echo $portBase; ?> wirklich starten?')" title="Server starten">
                                            <i class="fa-solid fa-play me-1"></i> Start
                                        </a>
                                    <?php else: ?>
                                        <a class="btn btn-sm btn-outline-success px-3" href="content.php?include=admradio&view=<?php echo $dataId; ?>&action=stop" onclick="return confirm('Wollen Sie den Server <?php echo $portBase; ?> wirklich stoppen?')" title="Server stoppen">
                                            <i class="fa-solid fa-stop me-1"></i> Stop
                                        </a>
                                    <?php endif; ?>

                                    <a class="btn btn-sm btn-outline-secondary px-3" href="content.php?include=admradio&action=update&view=<?php echo $dataId; ?>" title="Konfigurieren" style="border-color: var(--border-color); color: var(--text-primary);">
                                        <i class="fa-solid fa-gear me-1"></i> Bearbeiten
                                    </a>

                                    <a class="btn btn-sm btn-outline-danger px-3" href="content.php?include=admradio&view=<?php echo $dataId; ?>&action=delete" onclick="return confirm('Wollen Sie den Server <?php echo $portBase; ?> wirklich l&ouml;schen?')" title="Löschen">
                                        <i class="fa-solid fa-trash me-1"></i> L&ouml;schen
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                    echo '</div>';
                }
                ?>

                <!-- Pagination -->
                <?php
                try {
                    if (!empty($listq)) {
                        $totalServers = DB::queryFirstField("SELECT COUNT(*) FROM servers");
                        $totalPages = ceil((int)$totalServers / $limit);
                        
                        if ($totalPages > 1) {
                            echo '<nav aria-label="Server Pagination" class="mt-4"><ul class="pagination justify-content-center mb-0">';
                            for ($i = 0; $i < $totalPages; $i++) {
                                $activeClass = (($p / $limit) === $i) ? ' active' : '';
                                echo '<li class="page-item' . $activeClass . '"><a class="page-link" href="content.php?include=admradio&page=servers&p=' . $i . '">' . ($i + 1) . '</a></li>';
                            }
                            echo '</ul></nav>';
                        }
                    }
                } catch (Exception $e) {}
                ?>
            </div>
        </div>

        <!-- NEW / UPDATE SERVER FORM -->
        <?php if ($actionVal === 'new' || $actionVal === 'update'): ?>
        <?php
        $updateVar = [];
        if ($actionVal === "update" && $viewId > 0) {
            try {
                $updateVar = DB::queryFirstRow("SELECT * FROM servers WHERE id=%i", $viewId) ?? [];
            } catch (Exception $e) {
                $updateVar = [];
            }
        }

        try {
            $nextdjportVal = DB::queryFirstField("SELECT djport_1 FROM servers ORDER BY djport_1 DESC LIMIT 1");
            $nextdjport = $nextdjportVal ? ((int)$nextdjportVal + 4) : 6000;

            $nextportVal = DB::queryFirstField("SELECT portbase FROM servers ORDER BY portbase DESC LIMIT 1");
            $nextport = $nextportVal ? ((int)$nextportVal + 4) : 9000;
        } catch (Exception $e) {
            $nextdjport = 6000;
            $nextport = 9000;
        }

        $formAction = "content.php?include=admradio&";
        if ($actionVal === "new") {
            $formAction .= "new=server";
        } elseif ($actionVal === "update") {
            $formAction .= "new=update&action=update&view=" . $viewId;
        }
        ?>
        <div class="card shadow-sm border-0" style="background-color: var(--bg-card); color: var(--text-primary); border-color: var(--border-color) !important;">
            <div class="card-body p-4">
                <h5 class="card-title fw-bold mb-3 small text-uppercase" style="color: var(--text-muted);">
                    <i class="fa-solid fa-sliders me-1 text-warning"></i> <?php echo htmlspecialchars($msg("98", "Server-Einstellungen"), ENT_QUOTES, 'UTF-8'); ?>: <?php echo htmlspecialchars($msg("99", "Server-Konfiguration"), ENT_QUOTES, 'UTF-8'); ?>
                </h5>

                <form method="post" action="<?php echo $formAction; ?>">
                    <fieldset class="border-0 p-0 m-0">
                        <div class="row g-3">
                            
                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("100", "Besitzer"), ENT_QUOTES, 'UTF-8'); ?></label>
                                <select class="form-select" name="owner" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);">
                                <?php
                                try {
                                    $usersList = DB::query("SELECT username FROM users ORDER BY username ASC");
                                    foreach ($usersList as $uRow) {
                                        $uName = $uRow['username'];
                                        $selected = false;
                                        if ($actionVal === "update") {
                                            if ($uName === ($updateVar['owner'] ?? '')) $selected = true;
                                        } else {
                                            if ($uName === $loginun) $selected = true;
                                        }
                                        $selectAttr = $selected ? ' selected' : '';
                                        echo '<option' . $selectAttr . ' value="' . htmlspecialchars($uName, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($uName, ENT_QUOTES, 'UTF-8') . '</option>';
                                    }
                                } catch (Exception $e) {}
                                ?>
                                </select>
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("101", "Besitzer ausw&auml;hlen"), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("102", "Admin-Passwort"), ENT_QUOTES, 'UTF-8'); ?></label>
                                <input class="form-control" name="adminpassword" type="text" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo htmlspecialchars(($actionVal === "update") ? ($updateVar['adminpassword'] ?? '') : $msg("103", "adminpass"), ENT_QUOTES, 'UTF-8'); ?>"/>
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("104", "SHOUTcast Admin-Passwort"), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("105_pre", "Stream-Passwort"), ENT_QUOTES, 'UTF-8'); ?></label>
                                <input class="form-control" name="password" type="text" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo htmlspecialchars(($actionVal === "update") ? ($updateVar['password'] ?? '') : "testing", ENT_QUOTES, 'UTF-8'); ?>"/>
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("105", "Stream-Passwort"), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);">Passwort Sid1</label>
                                <input class="form-control" name="uvoxauth_1" type="text" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo htmlspecialchars(($actionVal === "update") ? ($updateVar['uvoxauth_1'] ?? '') : "testing", ENT_QUOTES, 'UTF-8'); ?>"/>
                                <div class="form-text" style="color: var(--text-muted);">Sid1 muss gleich sein mit Passwort</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);">Passwort Sid2</label>
                                <input class="form-control" name="uvoxauth_2" type="text" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo htmlspecialchars(($actionVal === "update") ? ($updateVar['uvoxauth_2'] ?? '') : "testing", ENT_QUOTES, 'UTF-8'); ?>"/>
                                <div class="form-text" style="color: var(--text-muted);">Sid2 muss gleich sein mit Passwort</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);">Stream-Auth-Hash 1</label>
                                <input class="form-control" name="streamauthhash_1" type="text" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo htmlspecialchars(($actionVal === "update") ? ($updateVar['streamauthhash_1'] ?? '') : "", ENT_QUOTES, 'UTF-8'); ?>"/>
                                <div class="form-text" style="color: var(--text-muted);">Hier Ihren Code eintragen</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);">Stream-Auth-Hash 2</label>
                                <input class="form-control" name="streamauthhash_2" type="text" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo htmlspecialchars(($actionVal === "update") ? ($updateVar['streamauthhash_2'] ?? '') : "", ENT_QUOTES, 'UTF-8'); ?>"/>
                                <div class="form-text" style="color: var(--text-muted);">Hier Ihren Code eintragen</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);">DJ-Port</label>
                                <input class="form-control" name="djport_1" type="text" style="background-color: var(--bg-surface); color: var(--text-primary); border-color: var(--border-color);" <?php echo ($actionVal === "update") ? 'readonly' : ''; ?> value="<?php echo htmlspecialchars(($actionVal === "update") ? (string)($updateVar['djport_1'] ?? '') : (string)$nextdjport, ENT_QUOTES, 'UTF-8'); ?>"/>
                                <div class="form-text" style="color: var(--text-muted);">DJ-Port</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("106", "Port-Basis"), ENT_QUOTES, 'UTF-8'); ?></label>
                                <input class="form-control" name="portbase" type="text" style="background-color: var(--bg-surface); color: var(--text-primary); border-color: var(--border-color);" <?php echo ($actionVal === "update") ? 'readonly' : ''; ?> value="<?php echo htmlspecialchars(($actionVal === "update") ? (string)($updateVar['portbase'] ?? '') : (string)$nextport, ENT_QUOTES, 'UTF-8'); ?>"/>
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("107", "Haupt-SHOUTcast-Port"), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("136", "W3C-Protokolldatei"), ENT_QUOTES, 'UTF-8'); ?></label>
                                <input class="form-control" name="w3clog" type="text" style="background-color: var(--bg-surface); color: var(--text-primary); border-color: var(--border-color);" readonly value="<?php echo htmlspecialchars(($actionVal === "update") ? basename((string)($updateVar['w3clog'] ?? '')) : "sc_$nextport.log", ENT_QUOTES, 'UTF-8'); ?>"/>
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("137", "Name der Protokolldatei"), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("122", "Protokolldatei"), ENT_QUOTES, 'UTF-8'); ?></label>
                                <input class="form-control" readonly name="logfile" type="text" style="background-color: var(--bg-surface); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo htmlspecialchars(($actionVal === "update") ? basename((string)($updateVar['logfile'] ?? '')) : "sc_$nextport.log", ENT_QUOTES, 'UTF-8'); ?>"/>
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("123", "Haupt-Protokolldatei"), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);">Sperrdatei (Banfile)</label>
                                <input class="form-control" name="banfile" type="text" style="background-color: var(--bg-surface); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo htmlspecialchars((string)($updateVar['banfile'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" disabled/>
                                <div class="form-text" style="color: var(--text-muted);">Banfile</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);">Ripfile</label>
                                <input class="form-control" name="ripfile" type="text" style="background-color: var(--bg-surface); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo htmlspecialchars((string)($updateVar['ripfile'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" disabled/>
                                <div class="form-text" style="color: var(--text-muted);">Ripfile</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("108", "Maximale Zuh&ouml;rer"), ENT_QUOTES, 'UTF-8'); ?></label>
                                <input class="form-control" name="maxuser" type="text" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo htmlspecialchars(($actionVal === "update") ? (string)($updateVar['maxuser'] ?? '32') : "32", ENT_QUOTES, 'UTF-8'); ?>"/>
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("109", "Maximale gleichzeitige Zuh&ouml;rer"), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("110", "Bitrate Sid1"), ENT_QUOTES, 'UTF-8'); ?></label>
                                <select class="form-select" name="bitrate_1" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);">
                                    <?php
                                    $bitrates = ['320000' => '320 kbps', '256000' => '256 kbps', '192000' => '192 kbps', '160000' => '160 kbps', '128000' => '128 kbps', '96000' => '96 kbps', '64000' => '64 kbps', '56000' => '56 kbps', '48000' => '48 kbps', '40000' => '40 kbps', '32000' => '32 kbps', '24000' => '24 kbps', '16000' => '16 kbps', '8000' => '8 kbps'];
                                    foreach ($bitrates as $bVal => $bLabel) {
                                        $sel = (($updateVar['bitrate_1'] ?? '') == $bVal) ? ' selected' : '';
                                        echo '<option value="' . $bVal . '"' . $sel . '>' . $bLabel . '</option>';
                                    }
                                    ?>
                                </select>
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("111", "Maximale Bitrate Sid1"), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("110", "Bitrate Sid2"), ENT_QUOTES, 'UTF-8'); ?></label>
                                <select class="form-select" name="bitrate_2" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);">
                                    <?php
                                    $bitrates2 = ['64000' => '64 kbps', '56000' => '56 kbps', '48000' => '48 kbps', '32000' => '32 kbps', '24000' => '24 kbps'];
                                    foreach ($bitrates2 as $bVal => $bLabel) {
                                        $sel = (($updateVar['bitrate_2'] ?? '') == $bVal) ? ' selected' : '';
                                        echo '<option value="' . $bVal . '"' . $sel . '>' . $bLabel . '</option>';
                                    }
                                    ?>
                                </select>
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("111", "Maximale Bitrate Sid2"), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("112", "&Ouml;ffentliche Auflistung"), ENT_QUOTES, 'UTF-8'); ?></label>
                                <select class="form-select" name="sitepublic" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);">
                                    <option value="1"<?php if (($updateVar['sitepublic'] ?? '') === '1') echo " selected"; ?>><?php echo htmlspecialchars($msg("add07", "Ja"), ENT_QUOTES, 'UTF-8'); ?></option>
                                    <option value="0"<?php if (($updateVar['sitepublic'] ?? '') === '0') echo " selected"; ?>><?php echo htmlspecialchars($msg("add08", "Nein"), ENT_QUOTES, 'UTF-8'); ?></option>
                                </select>
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("113", "&Ouml;ffentlich gelistet"), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("114", "Auto-Start PID"), ENT_QUOTES, 'UTF-8'); ?></label>
                                <select class="form-select" name="autopid" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);">
                                    <option value="0"<?php if (($updateVar['autopid'] ?? '') !== "9999999") echo ' selected'; ?>><?php echo htmlspecialchars($msg("115", "Aktiviert"), ENT_QUOTES, 'UTF-8'); ?></option>
                                    <option value="9999999"<?php if (($updateVar['autopid'] ?? '') === "9999999") echo ' selected'; ?>><?php echo htmlspecialchars($msg("116", "Deaktiviert"), ENT_QUOTES, 'UTF-8'); ?></option>
                                </select>
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("117", "AutoDJ-Status"), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("118", "Webspace (MB)"), ENT_QUOTES, 'UTF-8'); ?></label>
                                <input class="form-control" name="webspace" type="text" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo htmlspecialchars(($actionVal === "update") ? (string)(((int)($updateVar['webspace'] ?? 20480)) / 1024) : "20", ENT_QUOTES, 'UTF-8'); ?>"/>
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("119", "Quota in MB"), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("126", "Bildschirm-Log"), ENT_QUOTES, 'UTF-8'); ?></label>
                                <select class="form-select" name="screenlog" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);">
                                    <option value="0"<?php if (($updateVar['screenlog'] ?? '') === '0') echo " selected"; ?>>Stream-Infos aus</option>
                                    <option value="1"<?php if (($updateVar['screenlog'] ?? '') === '1') echo " selected"; ?>>Stream-Infos ausgeben</option>
                                </select>
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("127", "Konsolenausgabe"), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("128", "Song-Historie"), ENT_QUOTES, 'UTF-8'); ?></label>
                                <input class="form-control" name="songhistory" type="text" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo htmlspecialchars(($actionVal === "update") ? (string)($updateVar['songhistory'] ?? '10') : "10", ENT_QUOTES, 'UTF-8'); ?>"/>
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("129", "Limit f&uuml;r Historie"), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("130", "TCH-Log"), ENT_QUOTES, 'UTF-8'); ?></label>
                                <select class="form-select" name="tchlog" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);">
                                    <option value="0"<?php if (($updateVar['tchlog'] ?? '') === '0') echo " selected"; ?>>YP-Tracks ignorieren</option>
                                    <option value="1"<?php if (($updateVar['tchlog'] ?? '') === '1') echo " selected"; ?>>YP-Tracks im Log aufnehmen</option>
                                </select>
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("131", "YP-Log-Tracking"), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("134", "W3C-Protokollierung"), ENT_QUOTES, 'UTF-8'); ?></label>
                                <select class="form-select" name="w3cenable" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);">
                                    <option value="0"<?php if (($updateVar['w3cenable'] ?? '') === '0') echo " selected"; ?>>W3C-Log aus</option>
                                    <option value="1"<?php if (($updateVar['w3cenable'] ?? '') === '1') echo " selected"; ?>>W3C-Log ein</option>
                                </select>
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("135", "W3C-Log-Format aktivieren"), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("138", "Quell-IP"), ENT_QUOTES, 'UTF-8'); ?></label>
                                <input class="form-control" name="srcip" type="text" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo htmlspecialchars(($actionVal === "update") ? (string)($updateVar['srcip'] ?? 'ANY') : "ANY", ENT_QUOTES, 'UTF-8'); ?>"/>
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("139", "Quell-IP-Einschr&auml;nkung"), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("140", "Ziel-IP"), ENT_QUOTES, 'UTF-8'); ?></label>
                                <input class="form-control" name="destip" type="text" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo htmlspecialchars(($actionVal === "update") ? (string)($updateVar['destip'] ?? 'ANY') : "ANY", ENT_QUOTES, 'UTF-8'); ?>"/>
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("141", "Ziel-IP-Einschr&auml;nkung"), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("sct6", "Ausgangsprotokoll Sid1"), ENT_QUOTES, 'UTF-8'); ?></label>
                                <select class="form-select" name="outprotocol_1" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);">
                                    <option value="3"<?php if (($updateVar['outprotocol_1'] ?? '') === '3') echo " selected"; ?>>Shoutcast2</option>
                                    <option value="1"<?php if (($updateVar['outprotocol_1'] ?? '') === '1') echo " selected"; ?>>Shoutcast1</option>
                                </select>
                                <div class="form-text" style="color: var(--text-muted);">Ausgangsprotokoll Stream-ID 1</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("sct6", "Ausgangsprotokoll Sid2"), ENT_QUOTES, 'UTF-8'); ?></label>
                                <select class="form-select" name="outprotocol_2" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);">
                                    <option value="3"<?php if (($updateVar['outprotocol_2'] ?? '') === '3') echo " selected"; ?>>Shoutcast2</option>
                                    <option value="1"<?php if (($updateVar['outprotocol_2'] ?? '') === '1') echo " selected"; ?>>Shoutcast1</option>
                                </select>
                                <div class="form-text" style="color: var(--text-muted);">Ausgangsprotokoll Stream-ID 2</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("142", "YP-Port"), ENT_QUOTES, 'UTF-8'); ?></label>
                                <input class="form-control" name="yport" type="text" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo htmlspecialchars(($actionVal === "update") ? (string)($updateVar['yport'] ?? '80') : "80", ENT_QUOTES, 'UTF-8'); ?>"/>
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("143", "YP-Verzeichnisport"), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("144", "Namensaufl&ouml;sung"), ENT_QUOTES, 'UTF-8'); ?></label>
                                <select class="form-select" name="namelookups" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);">
                                    <option value="0"<?php if (($updateVar['namelookups'] ?? '') === '0') echo " selected"; ?>>DNS-Erkennung aus</option>
                                    <option value="1"<?php if (($updateVar['namelookups'] ?? '') === '1') echo " selected"; ?>>DNS-Erkennung ein</option>
                                </select>
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("145", "Reverse-DNS-Abfrage"), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("146", "Relay-Port"), ENT_QUOTES, 'UTF-8'); ?></label>
                                <input class="form-control" name="relayport" type="text" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo htmlspecialchars(($actionVal === "update") ? (string)($updateVar['relayport'] ?? '0') : "0", ENT_QUOTES, 'UTF-8'); ?>"/>
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("147", "Relay-Stream-Port"), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("148", "Relay-Server"), ENT_QUOTES, 'UTF-8'); ?></label>
                                <input class="form-control" name="relayserver" type="text" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo htmlspecialchars(($actionVal === "update") ? (string)($updateVar['relayserver'] ?? '') : "", ENT_QUOTES, 'UTF-8'); ?>"/>
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("149", "Relay-Server-Hostname"), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("150", "Auto-Dump-Benutzer"), ENT_QUOTES, 'UTF-8'); ?></label>
                                <input class="form-control" name="autodumpusers" type="text" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo htmlspecialchars(($actionVal === "update") ? (string)($updateVar['autodumpusers'] ?? '0') : "0", ENT_QUOTES, 'UTF-8'); ?>"/>
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("151", "Zuh&ouml;rer bei Quell-Trennung trennen"), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("152", "Auto-Dump-Zeit"), ENT_QUOTES, 'UTF-8'); ?></label>
                                <input class="form-control" name="autodumptime" type="text" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo htmlspecialchars(($actionVal === "update") ? (string)($updateVar['autodumptime'] ?? '30') : "30", ENT_QUOTES, 'UTF-8'); ?>"/>
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("153", "Dump-Timeout in Sekunden"), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("154", "Inhaltsverzeichnis"), ENT_QUOTES, 'UTF-8'); ?></label>
                                <input class="form-control" name="contentdir" type="text" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo htmlspecialchars(($actionVal === "update") ? (string)($updateVar['contentdir'] ?? '') : "", ENT_QUOTES, 'UTF-8'); ?>"/>
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("155", "Verzeichnis f&uuml;r On-Demand-Dateien"), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("156", "Intro-Datei"), ENT_QUOTES, 'UTF-8'); ?></label>
                                <input class="form-control" name="streamintrofile" type="text" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo htmlspecialchars(($actionVal === "update") ? (string)($updateVar['streamintrofile'] ?? '') : "", ENT_QUOTES, 'UTF-8'); ?>"/>
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("157", "Intro-Audiodatei"), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("158", "Titelformat"), ENT_QUOTES, 'UTF-8'); ?></label>
                                <input class="form-control" name="titleformat" type="text" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo htmlspecialchars(($actionVal === "update") ? (string)($updateVar['titleformat'] ?? 'Radio Station Name - %s') : "Radio Station Name - %s", ENT_QUOTES, 'UTF-8'); ?>"/>
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("159", "Stream-Titel-Muster"), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("urlformat", "URL-Format"), ENT_QUOTES, 'UTF-8'); ?></label>
                                <input class="form-control" name="urlformat" type="text" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo htmlspecialchars(($actionVal === "update") ? (string)($updateVar['urlformat'] ?? '') : "", ENT_QUOTES, 'UTF-8'); ?>" />
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("urlformat1", "Webseiten-URL-Muster"), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div> 

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("160", "&Ouml;ffentlicher Server"), ENT_QUOTES, 'UTF-8'); ?></label>
                                <select class="form-select" name="publicserver" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);">
                                    <option value="default"<?php if (($updateVar['publicserver'] ?? '') === 'default') echo " selected"; ?>>Standard</option>
                                    <option value="always"<?php if (($updateVar['publicserver'] ?? '') === 'always') echo " selected"; ?>>Immer</option>
                                    <option value="never"<?php if (($updateVar['publicserver'] ?? '') === 'never') echo " selected"; ?>>Niemals</option>
                                </select>
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("161", "YP-Verzeichnis-Auflistungsrichtlinie"), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("162", "Relay erlauben"), ENT_QUOTES, 'UTF-8'); ?></label>
                                <select class="form-select" name="allowrelay" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);">
                                    <option value="1"<?php if (($updateVar['allowrelay'] ?? '') === '1') echo " selected"; ?>><?php echo htmlspecialchars($msg("add01", "Ja"), ENT_QUOTES, 'UTF-8'); ?></option>
                                    <option value="0"<?php if (($updateVar['allowrelay'] ?? '') === '0') echo " selected"; ?>><?php echo htmlspecialchars($msg("add02", "Nein"), ENT_QUOTES, 'UTF-8'); ?></option>
                                </select>
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("163", "Relay-Verbindungen erlauben"), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("164", "&Ouml;ffentliches Relay erlauben"), ENT_QUOTES, 'UTF-8'); ?></label>
                                <select class="form-select" name="allowpublicrelay" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);">
                                    <option value="0"<?php if (($updateVar['allowpublicrelay'] ?? '') === '0') echo " selected"; ?>><?php echo htmlspecialchars($msg("add04", "Nein"), ENT_QUOTES, 'UTF-8'); ?></option>
                                    <option value="1"<?php if (($updateVar['allowpublicrelay'] ?? '') === '1') echo " selected"; ?>><?php echo htmlspecialchars($msg("add03", "Ja"), ENT_QUOTES, 'UTF-8'); ?></option>
                                </select>
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("165", "&Ouml;ffentliche Relays erlauben"), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("166", "Meta-Intervall"), ENT_QUOTES, 'UTF-8'); ?></label>
                                <input class="form-control" name="metainterval" type="text" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo htmlspecialchars(($actionVal === "update") ? (string)($updateVar['metainterval'] ?? '8192') : "8192", ENT_QUOTES, 'UTF-8'); ?>"/>
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("167", "Metadaten-Stream-Intervall"), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                        </div>

                        <div class="mt-4">
                            <button class="btn btn-primary px-4" type="submit">
                                <i class="fa-solid fa-floppy-disk me-1"></i> <?php echo htmlspecialchars($msg("168", "Server speichern"), ENT_QUOTES, 'UTF-8'); ?>
                            </button>
                        </div>
                    </fieldset>
                </form>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- Info Sidebar Widget -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 h-100" style="background-color: var(--bg-card); border: 1px solid var(--border-color) !important;">
            <div class="card-body p-4">
                <h5 class="card-title fw-bold mb-3 text-primary">
                    <i class="fa-solid fa-circle-info me-1"></i> <?php echo htmlspecialchars($msg("88", "Information"), ENT_QUOTES, 'UTF-8'); ?>
                </h5>
                <p class="card-text mb-0" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("89", "Verwalten Sie hier alle SHOUTcast-Server."), ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </div>
    </div>
</div>