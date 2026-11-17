<?php
/**
 * Streamers Admin Panel - AutoDJ View & Management (Bottom)
 *
 * Integriertes Tab-Management & Übersicht mit music_bottom.php im Playlist-Tab.
 * Modernisiert für PHP 8.x, MeekroDB & Bootstrap 5 (Hell-/Dunkelmodus)
 */

declare(strict_types=1);

if (stripos($_SERVER['PHP_SELF'], 'content.php') === false) {
    die("You can't access this file directly...");
}

$loginun = $_SESSION['username'] ?? '';
$actionVal = $_GET['action'] ?? '';
$userLevel = $_SESSION['user_level'] ?? '';
$hostAdd = htmlspecialchars($setting['host_add'] ?? 'localhost', ENT_QUOTES, 'UTF-8');
$manageTab = $_GET['tab'] ?? 'bearbeiten'; 
$serverId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$saveSuccess = isset($_GET['saved']) && $_GET['saved'] === '1';

// Port ermitteln
$portBase = 0;
if ($serverId > 0) {
    try {
        $serverPortRow = DB::queryFirstField("SELECT portbase FROM servers WHERE id=%i", $serverId);
        if ($serverPortRow) {
            $portBase = (int)$serverPortRow;
        }
    } catch (Exception $e) {}
}

// ---------------------------------------------------------
// SPEICHERN-LOGIK FÜR DEN BEARBEITEN-TAB (POST-Request abfangen)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit']) && $actionVal === 'manage' && $manageTab === 'bearbeiten' && $serverId > 0) {
    $updateData = [
        'public' => $_POST['publ'] ?? '0',
        'streamtitle' => $_POST['titl'] ?? '',
        'streamurl' => $_POST['surl'] ?? '',
        'genre' => $_POST['genr'] ?? '',
        'shuffle' => $_POST['shuf'] ?? '1',
        'displaymetadatapattern_1' => $_POST['displaymetadatapattern_1'] ?? '',
        'uvoxradiometadata_1' => $_POST['uvoxradiometadata_1'] ?? '0',
        'uvoxnewmetadata_1' => $_POST['uvoxnewmetadata_1'] ?? '0',
        'samplerate_1' => $_POST['samp_1'] ?? 44100,
        'samplerate_2' => $_POST['samp_2'] ?? 44100,
        'channels_1' => $_POST['chan'] ?? '2',
        'xfade' => $_POST['xfade'] ?? '2',
        'xfadethreshol' => $_POST['xfadethreshol'] ?? '20'
    ];

    try {
        DB::update('servers', $updateData, 'id=%i AND owner=%s', $serverId, $loginun);
        header("Location: content.php?include=autodj&id=" . $serverId . "&action=manage&tab=bearbeiten&saved=1");
        exit();
    } catch (Exception $e) {
        // Fehler abfangen
    }
}
?>

<!-- Header / Titel -->
<div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom" style="border-color: var(--border-color) !important;">
    <h2 class="h4 mb-0 fw-bold" style="color: var(--text-primary);">
        <i class="fa-solid fa-radio text-primary me-2"></i>
        <?php 
        if ($actionVal === 'manage') {
            echo 'AutoDJ Server #' . $serverId . ' (Port: ' . $portBase . ') verwalten';
        } else {
            echo htmlspecialchars($messages["277"] ?? 'AutoDJ &Uuml;bersicht', ENT_QUOTES, 'UTF-8');
        }
        ?>
    </h2>
    <?php if ($actionVal === 'manage'): ?>
        <a class="btn btn-sm btn-outline-secondary" href="content.php?include=autodj" style="border-color: var(--border-color); color: var(--text-primary);">
            <i class="fa-solid fa-arrow-left me-1"></i> Zur&uuml;ck zur &Uuml;bersicht
        </a>
    <?php endif; ?>
</div>

<!-- Erfolgsmeldung -->
<?php if ($saveSuccess): ?>
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center mb-4 shadow-sm" role="alert">
        <i class="fa-solid fa-circle-check fs-5 me-2"></i>
        <div><strong>Erfolgreich!</strong> Die AutoDJ-Einstellungen wurden erfolgreich aktualisiert.</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($actionVal !== 'manage'): ?>
    <!-- ========================================== -->
    <!-- 1. HAUPT-ÜBERSICHT (STARTSEITE) -->
    <!-- ========================================== -->
    <div class="row g-4">
        <!-- Linke Spalte: Server-Liste -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 mb-4" style="background-color: var(--bg-card); color: var(--text-primary); border-color: var(--border-color) !important;">
                <div class="card-body p-4">
                    <h5 class="card-title fw-bold small text-uppercase mb-3" style="color: var(--text-muted);">
                        <i class="fa-solid fa-list me-1 text-warning"></i> <?php echo htmlspecialchars($messages["278"] ?? 'Manage AutoDJ', ENT_QUOTES, 'UTF-8'); ?>
                    </h5>

                    <?php
                    if (empty($select)) {
                        echo '<div class="text-center py-4" style="color: var(--text-muted);">' . htmlspecialchars($messages["284"] ?? 'No servers available.', ENT_QUOTES, 'UTF-8') . '</div>';
                    } else {
                        echo '<div class="d-flex flex-column gap-3">';
                        foreach ($select as $data) {
                            if (isset($data['autopid']) && (string)$data['autopid'] !== "9999999") {
                                $pBase = (int)$data['portbase'];
                                $sId = (int)$data['id'];
                                
                                $entries_per_page = 100;
                                $offset = (isset($_GET['filecount']) && is_numeric($_GET['filecount'])) ? (int)$_GET['filecount'] : 1;
                                $listing_start = ($offset === 1) ? 0 : ($offset * $entries_per_page - $entries_per_page + 3);
                                $listing_end = $offset * $entries_per_page + 2;
                                
                                $playlistDir = "./temp/" . $pBase . "/playlist/";
                                @mkdir($playlistDir, 0777, true);
                                $dirlisting = @scandir($playlistDir) ?: [];
                                $isDisabledSelect = (count($dirlisting) <= 2) ? ' disabled' : '';
                                ?>
                                <div class="card border p-3 shadow-sm rounded-3" style="background-color: var(--bg-card); border-color: var(--border-color) !important;">
                                    <form action="content.php?include=autodj&id=<?php echo $sId; ?>&action=restart" method="post" name="sc_transform<?php echo $pBase; ?>">
                                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-2 pb-2 border-bottom" style="border-color: var(--border-color) !important;">
                                            <div class="d-flex align-items-center gap-3">
                                                <span class="fw-bold fs-6" style="color: var(--text-primary);">AutoDJ #<?php echo $sId; ?></span>
                                                <a href="http://<?php echo $hostAdd . ':' . $pBase; ?>/" target="_blank" class="small text-decoration-none fw-bold text-primary">
                                                    <i class="fa-solid fa-up-right-from-square me-1"></i>Port: <?php echo $pBase; ?>
                                                </a>
                                                <span class="small d-none d-md-inline" style="color: var(--text-muted);">|</span>
                                                <span class="small" style="color: var(--text-muted);">Host: <strong style="color: var(--text-primary);"><?php echo $hostAdd; ?></strong></span>
                                            </div>
                                        </div>

                                        <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3 my-2">
                                            <div class="w-100" style="max-width: 260px;">
                                                <label class="form-label small mb-1" style="color: var(--text-muted);">Playlist ausw&auml;hlen:</label>
                                                <select name="pllist" class="form-select form-select-sm" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);"<?php echo $isDisabledSelect; ?>>
                                                    <?php
                                                    for ($i = $listing_start; $i <= $listing_end; $i++) {
                                                        if (isset($dirlisting[$i]) && $dirlisting[$i] !== "." && $dirlisting[$i] !== ".." && $dirlisting[$i] !== "") {
                                                            $safePlName = htmlspecialchars($dirlisting[$i], ENT_QUOTES, 'UTF-8');
                                                            echo '<option value="' . $safePlName . '">' . $safePlName . '</option>';
                                                        }
                                                    }
                                                    if (count($dirlisting) <= 2) {
                                                        echo '<option value="">Keine Playlisten verf&uuml;gbar!</option>';
                                                    }
                                                    ?>
                                                </select>
                                            </div>

                                            <div class="d-flex flex-wrap gap-2 justify-content-end align-items-center">
                                                <a class="btn btn-sm btn-outline-warning px-3 py-1" href="javascript:document.sc_transform<?php echo $pBase; ?>.submit()" title="AutoDJ neustarten" onclick="return confirm('Wollen Sie den AutoDJ <?php echo $pBase; ?> wirklich neustarten?')">
                                                    <i class="fa-solid fa-rotate me-1"></i> Starten/Neustarten
                                                </a>
                                                <a class="btn btn-sm btn-outline-danger px-3 py-1" href="content.php?include=autodj&id=<?php echo $sId; ?>&action=stop" title="AutoDJ stoppen" onclick="return confirm('Wollen Sie den AutoDJ <?php echo $pBase; ?> wirklich stoppen?')">
                                                    <i class="fa-solid fa-stop me-1"></i> Stoppen
                                                </a>

                                                <?php if ($userLevel !== 'dj'): ?>
                                                    <a class="btn btn-sm btn-primary px-3 py-1" href="content.php?include=autodj&id=<?php echo $sId; ?>&action=manage&tab=bearbeiten" title="Server verwalten">
                                                        <i class="fa-solid fa-gear me-1"></i> Verwalten
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                                <?php
                            }
                        }
                        echo '</div>';
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- Rechte Spalte: Info-Fenster -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100" style="background-color: var(--bg-card); border: 1px solid var(--border-color) !important;">
                <div class="card-body p-4">
                    <h5 class="card-title fw-bold mb-3 text-primary">
                        <i class="fa-solid fa-circle-info me-1"></i> Server Auswahl
                    </h5>
                    <p class="card-text mb-0" style="color: var(--text-muted);">Bitte w&auml;hlen Sie von den folgenden Servern, um diesen zu editieren, starten, oder stoppen zu k&ouml;nnen.</p>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- ========================================== -->
    <!-- 2. MANAGEMENT-SEITE MIT TABS (Links) & INFO-FENSTER (Rechts) -->
    <!-- ========================================== -->
    <div class="row g-4">
        <!-- Linke Spalte: Tabs & Inhalte -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 mb-4" style="background-color: var(--bg-card); color: var(--text-primary); border-color: var(--border-color) !important;">
                <div class="card-body p-4">
                    
                    <ul class="nav nav-tabs mb-4 custom-autodj-tabs" id="autoDJManageTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <a class="nav-link <?php echo ($manageTab === 'playlist') ? 'active' : ''; ?> fw-bold" href="content.php?include=autodj&id=<?php echo $serverId; ?>&action=manage&tab=playlist">
                                <i class="fa-solid fa-music me-1"></i> Playlist & Musik
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link <?php echo ($manageTab === 'djs') ? 'active' : ''; ?> fw-bold" href="content.php?include=autodj&id=<?php echo $serverId; ?>&action=manage&tab=djs">
                                <i class="fa-solid fa-user-shield me-1"></i> Moderatoren
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link <?php echo ($manageTab === 'bearbeiten') ? 'active' : ''; ?> fw-bold" href="content.php?include=autodj&id=<?php echo $serverId; ?>&action=manage&tab=bearbeiten">
                                <i class="fa-solid fa-gear me-1"></i> Bearbeiten
                            </a>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <?php
                        // ------------------------------------------
                        // TAB 1: PLAYLIST / MUSIK (music_bottom.php)
                        // ------------------------------------------
                        if ($manageTab === 'playlist') {
                            $_GET['portbase'] = (string)$portBase;
                            $_GET['id'] = (string)$serverId;
                            if (file_exists('./pages/music_bottom.php')) {
                                include('./pages/music_bottom.php');
                            } else {
                                echo '<div class="alert alert-danger">music_bottom.php nicht gefunden!</div>';
                            }
                        } 
                        // ------------------------------------------
                        // TAB 2: MODERATOREN
                        // ------------------------------------------
                        elseif ($manageTab === 'djs') {
                            $_GET['view'] = $serverId;
                            $_GET['id'] = $serverId;
                            $_GET['action'] = 'edit';
                            if (file_exists('./pages/dj_bottom.php')) {
                                include('./pages/dj_bottom.php');
                            } else {
                                echo '<div class="alert alert-danger">dj_bottom.php nicht gefunden!</div>';
                            }
                        } 
                        // ------------------------------------------
                        // TAB 3: BEARBEITEN
                        // ------------------------------------------
                        else {
                            $editRow = DB::queryFirstRow("SELECT * FROM servers WHERE id=%i", $serverId);
                            if ($editRow) {
                                $formedit_port = $editRow["portbase"] ?? 0;
                                $formedit_pass = $editRow["password"] ?? '';
                                $formedit_bitr_1 = $editRow["bitrate_1"] ?? '';
                                $formedit_bitr_2 = $editRow["bitrate_2"] ?? '';
                                $formedit_titl = $editRow["streamtitle"] ?? '';
                                $formedit_surl = $editRow["streamurl"] ?? '';
                                $formedit_genr = $editRow["genre"] ?? '';
                                $formedit_shuf = $editRow["shuffle"] ?? '1';
                                $formedit_samp_1 = $editRow["samplerate_1"] ?? 44100;
                                $formedit_samp_2 = $editRow["samplerate_2"] ?? 44100;
                                $formedit_publ = $editRow["public"] ?? '0';
                                $formedit_chan = $editRow["channels_1"] ?? '2';
                                $formedit_displaymetadatapattern_1 = $editRow["displaymetadatapattern_1"] ?? '';
                                $formedit_uvoxradiometadata_1 = $editRow["uvoxradiometadata_1"] ?? '0';
                                $formedit_uvoxnewmetadata_1 = $editRow["uvoxnewmetadata_1"] ?? '0';
                                $formedit_xfade = $editRow["xfade"] ?? '2';
                                $formedit_xfadethreshol = $editRow["xfadethreshol"] ?? '20';
                            }
                            ?>
                            <h5 class="card-title fw-bold mb-3 small text-uppercase" style="color: var(--text-muted);">
                                <i class="fa-solid fa-sliders me-1 text-warning"></i> AutoDJ Einstellungen bearbeiten
                            </h5>

                            <form method="post" action="content.php?include=autodj&id=<?php echo $serverId; ?>&action=manage&tab=bearbeiten">
                                <fieldset class="border-0 p-0 m-0">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold" style="color: var(--text-primary);">Host</label>
                                            <input class="form-control" type="text" value="<?php echo $hostAdd; ?>" disabled style="background-color: var(--bg-surface); color: var(--text-primary); border-color: var(--border-color);" />
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold" style="color: var(--text-primary);">Port</label>
                                            <input class="form-control" type="text" value="<?php echo $formedit_port ?? 0; ?>" disabled style="background-color: var(--bg-surface); color: var(--text-primary); border-color: var(--border-color);" />
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold" style="color: var(--text-primary);">Password</label>
                                            <input class="form-control" type="text" value="<?php echo htmlspecialchars((string)($formedit_pass ?? ''), ENT_QUOTES, 'UTF-8'); ?>" disabled style="background-color: var(--bg-surface); color: var(--text-primary); border-color: var(--border-color);" />
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold" style="color: var(--text-primary);">Bitrate (Sid1)</label>
                                            <input class="form-control" type="text" value="<?php echo htmlspecialchars((string)($formedit_bitr_1 ?? ''), ENT_QUOTES, 'UTF-8'); ?>" disabled style="background-color: var(--bg-surface); color: var(--text-primary); border-color: var(--border-color);" />
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold" style="color: var(--text-primary);">Bitrate (Sid2)</label>
                                            <input class="form-control" type="text" value="<?php echo htmlspecialchars((string)($formedit_bitr_2 ?? ''), ENT_QUOTES, 'UTF-8'); ?>" disabled style="background-color: var(--bg-surface); color: var(--text-primary); border-color: var(--border-color);" />
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold" style="color: var(--text-primary);">Public</label>
                                            <select class="form-select" name="publ" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);">
                                                <option value="0"<?php if (($formedit_publ ?? '') === '0') echo " selected"; ?>>No</option>
                                                <option value="1"<?php if (($formedit_publ ?? '') === '1') echo " selected"; ?>>Yes</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold" style="color: var(--text-primary);">Radio Slogan</label>
                                            <input class="form-control" name="titl" type="text" value="<?php echo htmlspecialchars((string)($formedit_titl ?? ''), ENT_QUOTES, 'UTF-8'); ?>" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);"/>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold" style="color: var(--text-primary);">Stream URL</label>
                                            <input class="form-control" name="surl" type="text" value="<?php echo htmlspecialchars((string)($formedit_surl ?? ''), ENT_QUOTES, 'UTF-8'); ?>" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);"/>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold" style="color: var(--text-primary);">Genre</label>
                                            <select class="form-select" name="genr" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);">
                                                <?php
                                                $genres = ['Alternative', 'Fox', 'Schlager', 'Jazz', 'Dance', 'Disco', 'House', 'RnB', 'Rab', 'PoP', 'Dance PoP', 'World PoP', 'Rock', 'Techno', 'Querbeet', 'World', 'The 70s', 'The 80s', 'The 90s', 'Mixed', 'Mixe'];
                                                foreach ($genres as $genre) {
                                                    $selected = (($formedit_genr ?? '') === $genre) ? ' selected' : '';
                                                    echo '<option value="' . $genre . '"' . $selected . '>' . $genre . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold" style="color: var(--text-primary);">Shuffle</label>
                                            <select class="form-select" name="shuf" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);">
                                                <option value="1"<?php if (($formedit_shuf ?? '') === '1') echo " selected"; ?>>On</option>
                                                <option value="0"<?php if (($formedit_shuf ?? '') === '0') echo " selected"; ?>>Off</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold" style="color: var(--text-primary);">Nextsonganzeige</label>
                                            <select class="form-select" name="displaymetadatapattern_1" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);">
                                                <option value="The Best Radio"<?php if (($formedit_displaymetadatapattern_1 ?? '') === 'The Best Radio') echo " selected"; ?>>Aus (The Best Radio)</option>
                                                <option value="%R"<?php if (($formedit_displaymetadatapattern_1 ?? '') === '%R') echo " selected"; ?>>Interpret</option>
                                                <option value="%N"<?php if (($formedit_displaymetadatapattern_1 ?? '') === '%N') echo " selected"; ?>>Titel</option>
                                                <option value="%R [-] %N"<?php if (($formedit_displaymetadatapattern_1 ?? '') === '%R [-] %N') echo " selected"; ?>>Interpret - Titel</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold" style="color: var(--text-primary);">Titelanzeige (ohne Nextsong)</label>
                                            <select class="form-select" name="uvoxradiometadata_1" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);">
                                                <option value="0"<?php if (($formedit_uvoxradiometadata_1 ?? '') === '0') echo " selected"; ?>>Aus</option>
                                                <option value="1"<?php if (($formedit_uvoxradiometadata_1 ?? '') === '1') echo " selected"; ?>>An</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold" style="color: var(--text-primary);">Titelanzeige (mit Nextsong)</label>
                                            <select class="form-select" name="uvoxnewmetadata_1" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);">
                                                <option value="0"<?php if (($formedit_uvoxnewmetadata_1 ?? '') === '0') echo " selected"; ?>>Aus</option>
                                                <option value="1"<?php if (($formedit_uvoxnewmetadata_1 ?? '') === '1') echo " selected"; ?>>An</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold" style="color: var(--text-primary);">Bandbreite Sid1</label>
                                            <select class="form-select" name="samp_1" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);">
                                                <?php
                                                $samplerates = [48000, 44100, 32000, 24000, 22025, 16000, 12000, 11025, 8000];
                                                foreach ($samplerates as $rate) {
                                                    $selected = ((int)($formedit_samp_1 ?? 0) === $rate) ? ' selected' : '';
                                                    echo '<option value="' . $rate . '"' . $selected . '>' . $rate . ' Hz</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold" style="color: var(--text-primary);">Bandbreite Sid2</label>
                                            <select class="form-select" name="samp_2" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);">
                                                <?php
                                                foreach ($samplerates as $rate) {
                                                    $selected = ((int)($formedit_samp_2 ?? 0) === $rate) ? ' selected' : '';
                                                    echo '<option value="' . $rate . '"' . $selected . '>' . $rate . ' Hz</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold" style="color: var(--text-primary);">Channels</label>
                                            <select class="form-select" name="chan" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);">
                                                <option value="1"<?php if (($formedit_chan ?? '') === '1') echo " selected"; ?>>Mono</option>
                                                <option value="2"<?php if (($formedit_chan ?? '') === '2') echo " selected"; ?>>Stereo</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold" style="color: var(--text-primary);">Crossfade</label>
                                            <select class="form-select" name="xfade" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);">
                                                <option value="2"<?php if (($formedit_xfade ?? '') === '2') echo " selected"; ?>>kein xFade</option>
                                                <option value="4"<?php if (($formedit_xfade ?? '') === '4') echo " selected"; ?>>2 Sekunden</option>
                                                <option value="6"<?php if (($formedit_xfade ?? '') === '6') echo " selected"; ?>>4 Sekunden</option>
                                                <option value="8"<?php if (($formedit_xfade ?? '') === '8') echo " selected"; ?>>6 Sekunden</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold" style="color: var(--text-primary);">Threshold</label>
                                            <select class="form-select" name="xfadethreshol" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);">
                                                <option value="20"<?php if (($formedit_xfadethreshol ?? '') === '20') echo " selected"; ?>>min. 20 Sekunden</option>
                                                <option value="40"<?php if (($formedit_xfadethreshol ?? '') === '40') echo " selected"; ?>>min. 40 Sekunden</option>
                                                <option value="60"<?php if (($formedit_xfadethreshol ?? '') === '60') echo " selected"; ?>>min. 60 Sekunden</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="mt-4 d-flex justify-content-between">
                                        <button class="btn btn-primary px-4" type="submit" name="submit">
                                            <i class="fa-solid fa-floppy-disk me-1"></i> Speichern
                                        </button>
                                    </div>
                                </fieldset>
                            </form>
                            <?php
                        }
                        ?>
                    </div>

                </div>
            </div>
        </div>

        <!-- Rechte Spalte: Info-Fenster -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100" style="background-color: var(--bg-card); border: 1px solid var(--border-color) !important;">
                <div class="card-body p-4">
                    <h5 class="card-title fw-bold mb-3 text-primary">
                        <i class="fa-solid fa-circle-info me-1"></i> Server Verwaltung
                    </h5>
                    <p class="card-text mb-0" style="color: var(--text-muted);">Hier k&ouml;nnen Sie die Einstellungen verwalten, Playlisten zuweisen oder Moderatoren f&uuml;r diesen Server einrichten.</p>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<style>
    .custom-autodj-tabs .nav-link { color: var(--text-primary) !important; border-color: transparent !important; }
    .custom-autodj-tabs .nav-link.active { background-color: var(--bg-card) !important; border-color: var(--border-color) var(--border-color) var(--bg-card) !important; color: var(--bs-primary) !important; }
</style>