<?php
/**
 * Streamers Admin Panel - Main Dashboard View (Bottom)
 * 
 * Inklusive Live Streamstatus (auf den aktuellen Benutzer beschränkt),
 * TeamSpeak-Steuerung (besitzerbasiert gefiltert), Coveranzeige & Server-Steuerung.
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

$userLevel = $_SESSION['user_level'] ?? '';
$loginun = $_SESSION['username'] ?? '';
$osSetting = $setting['os'] ?? '';
$hostAdd = htmlspecialchars($setting['host_add'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost', ENT_QUOTES, 'UTF-8');

$msg = static function(string $key, string $default = '') use ($messages): string {
    return $messages[$key] ?? $default;
};

// Helper-Funktion: Titel, Hörer & Max-Hörer direkt vom Shoutcast / Icecast Server auslesen
function getLiveStreamInfo(string $host, int $port): array {
    $defaultInfo = ['title' => 'Stream Offline / Keine Daten', 'listeners' => 0, 'max' => 100];

    // Versuche 1: Shoutcast v1 Stats (/7.html)
    $fp = @fsockopen($host, $port, $errno, $errstr, 1.0);
    if ($fp) {
        fputs($fp, "GET /7.html HTTP/1.0\r\nUser-Agent: Mozilla/5.0\r\n\r\n");
        $page = '';
        while (!feof($fp)) {
            $page .= fgets($fp, 1000);
        }
        fclose($fp);

        $pageParts = explode("\r\n\r\n", $page, 2);
        $body = $pageParts[1] ?? $page;

        if (!empty($body) && strpos($body, ',') !== false) {
            $data = explode(',', strip_tags($body));
            if (isset($data[6])) {
                $song = trim($data[6]);
                return [
                    'title' => !empty($song) ? $song : 'Kein Titel angegeben',
                    'listeners' => (int)($data[0] ?? 0),
                    'max' => (int)($data[3] ?? 100)
                ];
            }
        }
    }

    // Versuche 2: Shoutcast v2 / Icecast JSON Status (/stats?json=1 oder /status-json.xsl)
    $jsonUrl = "http://{$host}:{$port}/stats?json=1";
    $ctx = stream_context_create(['http' => ['timeout' => 1.0]]);
    $jsonContent = @file_get_contents($jsonUrl, false, $ctx);
    if ($jsonContent !== false) {
        $jsonData = json_decode($jsonContent, true);
        if (isset($jsonData['songtitle']) || isset($jsonData['title'])) {
            $song = $jsonData['songtitle'] ?? $jsonData['title'] ?? 'Kein Titel angegeben';
            return [
                'title' => !empty($song) ? $song : 'Kein Titel angegeben',
                'listeners' => (int)($jsonData['currentlisteners'] ?? 0),
                'max' => (int)($jsonData['maxlisteners'] ?? 100)
            ];
        }
    }

    return $defaultInfo;
}

// Hinweis / Notiz löschen, falls Action gesetzt ist
$actionParam = $_GET['action'] ?? '';
$delMessId = isset($_GET['delmessid']) ? (int)$_GET['delmessid'] : 0;
if ($actionParam === 'remove' && $delMessId > 0 && $userLevel === 'Super Administrator') {
    try {
        DB::delete('notices', 'id=%i', $delMessId);
    } catch (Exception $e) {}
}

// --- STATISTIKEN SICHER ABRUFEN ---

$configuredServerName = $setting['title'] ?? 'Shoutcast Admin';
try {
    $settingsRow = DB::queryFirstRow("SELECT title FROM settings WHERE id = '0'");
    if ($settingsRow && !empty($settingsRow['title'])) {
        $configuredServerName = $settingsRow['title'];
    }
} catch (Exception $e) {}

$totalStreams = 0;
try {
    if ($userLevel === 'Super Administrator') {
        $totalStreams = (int)DB::queryFirstField("SELECT COUNT(*) FROM servers");
    } else {
        $totalStreams = (int)DB::queryFirstField("SELECT COUNT(*) FROM servers WHERE owner = %s", $loginun);
    }
} catch (Exception $e) {
    $totalStreams = 0;
}

$totalNotices = 0;
try {
    if ($userLevel === 'Super Administrator') {
        $totalNotices = (int)DB::queryFirstField("SELECT COUNT(*) FROM notices");
    } else {
        $totalNotices = (int)DB::queryFirstField("SELECT COUNT(*) FROM notices WHERE username = %s", $loginun);
    }
} catch (Exception $e) {
    $totalNotices = 0;
}
?>

<!-- Dashboard Header -->
<div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom" style="border-color: var(--border-color) !important;">
    <h2 class="h4 mb-0 fw-bold" style="color: var(--text-primary);">
        <i class="fa-solid fa-gauge-high text-primary me-2"></i><?php echo htmlspecialchars($msg("358", "Dashboard"), ENT_QUOTES, 'UTF-8'); ?>
    </h2>
    <button class="btn btn-outline-primary btn-sm rounded-pill px-3" onclick="location.reload();">
        <i class="fa-solid fa-rotate-right me-1"></i> Aktualisieren
    </button>
</div>

<!-- 3 Statistik-Karten im modernen Stil -->
<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="card stat-card bg-gradient-purple text-white border-0 shadow-sm p-3 h-100">
            <div class="card-body p-0 d-flex justify-content-between align-items-center position-relative">
                <div>
                    <h3 class="fw-bold mb-1 text-truncate" style="max-width: 220px;" title="<?php echo htmlspecialchars((string)$configuredServerName, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars((string)$configuredServerName, ENT_QUOTES, 'UTF-8'); ?>
                    </h3>
                    <p class="mb-0 text-white-50 small text-uppercase fw-semibold">Servername</p>
                </div>
                <div class="stat-icon">
                    <i class="fa-solid fa-server"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-md-4">
        <div class="card stat-card bg-gradient-blue text-white border-0 shadow-sm p-3 h-100">
            <div class="card-body p-0 d-flex justify-content-between align-items-center position-relative">
                <div>
                    <h3 class="fw-bold mb-1"><?php echo (int)$totalStreams; ?></h3>
                    <p class="mb-0 text-white-50 small text-uppercase fw-semibold">Angelegte Streams</p>
                </div>
                <div class="stat-icon">
                    <i class="fa-solid fa-radio"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-md-4">
        <div class="card stat-card bg-gradient-red text-white border-0 shadow-sm p-3 h-100">
            <div class="card-body p-0 d-flex justify-content-between align-items-center position-relative">
                <div>
                    <h3 class="fw-bold mb-1"><?php echo (int)$totalNotices; ?></h3>
                    <p class="mb-0 text-white-50 small text-uppercase fw-semibold">Nachrichten</p>
                </div>
                <div class="stat-icon">
                    <i class="fa-solid fa-envelope"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Access / Shortcuts -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-4">
                <h5 class="card-title fw-bold mb-3 small text-uppercase" style="color: var(--text-muted);">
                    <i class="fa-solid fa-bolt me-1 text-warning"></i> Schnellzugriff
                </h5>
                <div class="row g-3">
                    <?php if ($userLevel === 'dj'): ?>
                        <div class="col-6 col-md-3 d-flex">
                            <a href="content.php?include=autodj" class="card text-decoration-none w-100 shadow-sm text-center p-3 modern-tile tile-primary">
                                <div class="card-body p-0 d-flex flex-column align-items-center justify-content-center">
                                    <div class="icon-shape text-primary mb-2">
                                        <i class="fa-solid fa-radio fa-2x"></i>
                                    </div>
                                    <span class="fw-bold small" style="color: var(--text-primary);">AutoDJ An/Aus</span>
                                </div>
                            </a>
                        </div>
                    <?php else: ?>
                        <?php if ($osSetting === "linux"): ?>
                            <div class="col-6 col-md-3 d-flex">
                                <a href="content.php?include=autodj" class="card text-decoration-none w-100 shadow-sm text-center p-3 modern-tile tile-primary">
                                    <div class="card-body p-0 d-flex flex-column align-items-center justify-content-center">
                                        <div class="icon-shape text-primary mb-2">
                                            <i class="fa-solid fa-radio fa-2x"></i>
                                        </div>
                                        <span class="fw-bold small" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("359", "AutoDJ"), ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <div class="col-6 col-md-3 d-flex">
                            <a href="content.php?include=account" class="card text-decoration-none w-100 shadow-sm text-center p-3 modern-tile tile-success">
                                <div class="card-body p-0 d-flex flex-column align-items-center justify-content-center">
                                    <div class="icon-shape text-success mb-2">
                                        <i class="fa-solid fa-user-gear fa-2x"></i>
                                    </div>
                                    <span class="fw-bold small" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("360", "Account"), ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            </a>
                        </div>
                        
                        <div class="col-6 col-md-3 d-flex">
                            <a href="content.php?include=server" class="card text-decoration-none w-100 shadow-sm text-center p-3 modern-tile tile-warning">
                                <div class="card-body p-0 d-flex flex-column align-items-center justify-content-center">
                                    <div class="icon-shape text-warning mb-2">
                                        <i class="fa-solid fa-server fa-2x"></i>
                                    </div>
                                    <span class="fw-bold small" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("362", "Server"), ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            </a>
                        </div>

                        <?php if ($userLevel === "Super Administrator"): ?>
                            <div class="col-6 col-md-3 d-flex">
                                <a href="content.php?include=admts" class="card text-decoration-none w-100 shadow-sm text-center p-3 modern-tile tile-primary">
                                    <div class="card-body p-0 d-flex flex-column align-items-center justify-content-center">
                                        <div class="icon-shape text-primary mb-2">
                                            <i class="fa-solid fa-headset fa-2x"></i>
                                        </div>
                                        <span class="fw-bold small" style="color: var(--text-primary);">TeamSpeak</span>
                                    </div>
                                </a>
                            </div>
                            <div class="col-6 col-md-3 d-flex">
                                <a href="content.php?include=admserver" class="card text-decoration-none w-100 shadow-sm text-center p-3 modern-tile tile-danger">
                                    <div class="card-body p-0 d-flex flex-column align-items-center justify-content-center">
                                        <div class="icon-shape text-danger mb-2">
                                            <i class="fa-solid fa-sliders fa-2x"></i>
                                        </div>
                                        <span class="fw-bold small" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("364", "Einstellungen"), ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                </a>
                            </div>
                            <div class="col-6 col-md-3 d-flex">
                                <a href="content.php?include=admradio" class="card text-decoration-none w-100 shadow-sm text-center p-3 modern-tile tile-purple">
                                    <div class="card-body p-0 d-flex flex-column align-items-center justify-content-center">
                                        <div class="icon-shape text-purple mb-2">
                                            <i class="fa-solid fa-wave-square fa-2x"></i>
                                        </div>
                                        <span class="fw-bold small" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("365", "Radio"), ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                </a>
                            </div>
                            <div class="col-6 col-md-3 d-flex">
                                <a href="content.php?include=admuser" class="card text-decoration-none w-100 shadow-sm text-center p-3 modern-tile tile-dark">
                                    <div class="card-body p-0 d-flex flex-column align-items-center justify-content-center">
                                        <div class="icon-shape text-secondary mb-2">
                                            <i class="fa-solid fa-users fa-2x"></i>
                                        </div>
                                        <span class="fw-bold small" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("366", "Benutzer"), ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- LIVE STREAMSTATUS & COVERANZEIGE (NUR EIGENER STREAM) -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body p-3 p-md-4">
                <h5 class="card-title fw-bold mb-3 small text-uppercase" style="color: var(--text-muted);">
                    <i class="fa-solid fa-tower-broadcast me-1 text-primary"></i> Live Stream Status &amp; Steuerung
                </h5>

                <?php
                try {
                    // Zeige im Dashboard immer nur den Stream des aktuellen Benutzers (unabhängig von der Rolle)
                    $streamsQuery = DB::query("SELECT * FROM servers WHERE owner = %s ORDER BY portbase ASC", $loginun);

                    if (empty($streamsQuery)) {
                        echo '<div class="alert border text-center py-4" style="background-color: var(--bg-surface); border-color: var(--border-color) !important; color: #ffffff !important;">Kein eigener Stream f&uuml;r deinen Account zugewiesen.</div>';
                    } else {
                        echo '<div class="d-flex flex-column gap-3">';
                        foreach ($streamsQuery as $stream) {
                            $sId = (int)$stream['id'];
                            $sPort = (int)$stream['portbase'];
                            $sOwner = htmlspecialchars((string)($stream['owner'] ?? 'Unbekannt'), ENT_QUOTES, 'UTF-8');
                            $sName = htmlspecialchars((string)($stream['servername'] ?? $stream['title'] ?? ('Stream #' . $sPort)), ENT_QUOTES, 'UTF-8');
                            
                            $pidVal = trim((string)($stream['pid'] ?? ''));
                            $isRunning = ($pidVal !== '');
                            
                            $autoPidVal = trim((string)($stream['autopid'] ?? ''));
                            $isAutoDjRunning = ($autoPidVal !== '');
                            $streamType = strtoupper((string)($stream['isserver'] ?? 'SHOUTCAST'));

                            // Live-Daten (Titel & Hörer) direkt vom Shoutcast/Icecast Server via Port auslesen
$listeners = 0;
// Primär den echten Datenbank-Wert 'maxuser' (z.B. 77) nutzen statt 'maxlisteners'
$maxListeners = (int)($stream['maxuser'] ?? 100);
$currentSong = 'Server Offline';

if ($isRunning) {
    $liveInfo = getLiveStreamInfo('127.0.0.1', $sPort);
    $currentSong = $liveInfo['title'];
    $listeners = $liveInfo['listeners'];
    // Den Live-Wert nur als Fallback nutzen, falls in der DB kein maxuser hinterlegt ist
    if ($maxListeners <= 0 && $liveInfo['max'] > 0) {
        $maxListeners = $liveInfo['max'];
    }
}
                            $safeSongTitle = htmlspecialchars($currentSong, ENT_QUOTES, 'UTF-8');
                            $webUrl = "http://" . $hostAdd . ":" . $sPort . "/";
                            ?>
                            <!-- Stream Item in Voller Breite (100%) -->
                            <div class="card w-100 shadow-sm border rounded-3 p-3 stream-item-card">
                                <div class="row g-3 align-items-center">
                                    
                                    <!-- Spalte 1: Cover oder FontAwesome Antennen-Icon Fallback -->
                                    <div class="col-auto">
                                        <div class="position-relative">
                                            <div id="icon-fallback-<?php echo $sPort; ?>" class="rounded shadow-sm bg-primary text-white d-flex align-items-center justify-content-center stream-fallback-icon" style="width: 75px; height: 75px;">
                                                <i class="fa-solid fa-tower-broadcast fa-2x"></i>
                                            </div>
                                            <img id="cover-<?php echo $sPort; ?>" src="" alt="Cover" class="rounded shadow-sm bg-secondary stream-cover-img d-none" style="width: 75px; height: 75px; object-fit: cover;">
                                            
                                            <span class="position-absolute bottom-0 start-0 bg-dark text-white px-1 rounded-end" style="font-size: 0.60rem; z-index: 2;">
                                                <?php echo $streamType; ?>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Spalte 2: Server Details & Aktueller Titel -->
                                    <div class="col-12 col-md-4 flex-grow-1">
                                        <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                            <h6 class="fw-bold mb-0" style="color: var(--text-primary);" title="<?php echo $sName; ?>">
                                                <i class="fa-solid fa-radio text-primary me-1"></i> <?php echo $sName; ?>
                                            </h6>
                                            <span class="small" style="color: var(--text-muted);">| Besitzer: <strong style="color: var(--text-primary);"><?php echo $sOwner; ?></strong></span>
                                        </div>
                                        
                                        <!-- Aktueller Titel & Status Badges -->
                                        <div class="mb-1">
                                            <span class="text-uppercase fw-bold d-inline-block me-1" style="font-size: 0.65rem; letter-spacing: 0.05em; color: var(--text-muted);">Titel:</span>
                                            <span id="song-title-<?php echo $sPort; ?>" class="fw-semibold small" style="color: var(--text-primary);" data-song="<?php echo $safeSongTitle; ?>" title="<?php echo $safeSongTitle; ?>">
                                                <?php echo $safeSongTitle; ?>
                                            </span>
                                        </div>

                                        <div class="progress" style="height: 3px; max-width: 100%; background-color: var(--border-color);">
                                            <div class="progress-bar bg-primary <?php echo $isRunning ? 'progress-bar-striped progress-bar-animated' : ''; ?>" role="progressbar" style="width: 100%"></div>
                                        </div>
                                    </div>

                                    <!-- Spalte 3: Hörer & Port Status -->
                                    <div class="col-6 col-md-2 text-md-center">
                                        <div class="p-2 border rounded-2 stat-box">
                                            <span class="d-block" style="font-size: 0.70rem; color: var(--text-muted);"><i class="fa-solid fa-headphones me-1 text-primary"></i> H&ouml;rer</span>
                                            <strong class="fs-6" style="color: var(--text-primary);"><?php echo $listeners; ?> <span class="fw-normal fs-6" style="color: var(--text-muted);">/ <?php echo $maxListeners; ?></span></strong>
                                        </div>
                                    </div>

                                    <div class="col-6 col-md-2 text-md-center">
                                        <div class="p-2 border rounded-2 stat-box">
                                            <span class="d-block" style="font-size: 0.70rem; color: var(--text-muted);"><i class="fa-solid fa-network-wired me-1 text-info"></i> Port</span>
                                            <a href="<?php echo $webUrl; ?>" target="_blank" class="fw-bold text-primary fs-6 text-decoration-none">
                                                <?php echo $sPort; ?> <i class="fa-solid fa-up-right-from-square" style="font-size: 0.65rem;"></i>
                                            </a>
                                        </div>
                                    </div>

                                    <!-- Spalte 4: Online Badge & Steuerung Buttons -->
                                    <div class="col-12 col-md-3 d-flex flex-column align-items-md-end justify-content-center gap-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if ($isRunning): ?>
                                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 rounded-pill">
                                                    <span class="spinner-grow spinner-grow-sm text-success me-1" role="status" style="width: 6px; height: 6px;"></span> Live Online
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1 rounded-pill">
                                                    <i class="fa-solid fa-circle-xmark me-1"></i> Offline
                                                </span>
                                            <?php endif; ?>

                                            <?php if ($osSetting === "linux"): ?>
                                                <?php if ($isAutoDjRunning): ?>
                                                    <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-2 py-1 rounded-pill">
                                                        AutoDJ An
                                                    </span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>

                                        <div class="d-flex flex-wrap gap-1 w-100 justify-content-md-end">
                                            <?php if ($isRunning): ?>
                                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="openPlayerModal(<?php echo $sPort; ?>)">
                                                    <i class="fa-solid fa-headphones me-1"></i> Anh&ouml;ren
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($osSetting === "linux"): ?>
                                                <?php if ($isAutoDjRunning): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" title="AutoDJ Stoppen" onclick="controlAutoDJ(<?php echo $sId; ?>, 'stop')">
                                                        <i class="fa-solid fa-podcast me-1"></i> AutoDJ Off
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-outline-success" title="AutoDJ Starten" onclick="controlAutoDJ(<?php echo $sId; ?>, 'start')">
                                                        <i class="fa-solid fa-podcast me-1"></i> AutoDJ On
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>

                                            <?php if (!$isRunning): ?>
                                                <button type="button" class="btn btn-sm btn-danger fw-bold" onclick="controlServer(<?php echo $sId; ?>, 'start', <?php echo $sPort; ?>)">
                                                    <i class="fa-solid fa-power-off me-1"></i> Start
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger fw-bold" onclick="controlServer(<?php echo $sId; ?>, 'stop', <?php echo $sPort; ?>)">
                                                    <i class="fa-solid fa-stop me-1"></i> Stopp
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                </div>
                            </div>
                            <?php
                        }
                        echo '</div>';
                    }
                } catch (Exception $e) {
                    echo '<div class="text-center text-danger py-3">Fehler beim Laden der Streams.</div>';
                }
                ?>
            </div>
        </div>
    </div>
</div>

<!-- TEAMSPEAK VERWALTUNG & STEUERUNG (BESITZERBASIERT GEFILTERT) -->
<?php
try {
    if ($userLevel === 'Super Administrator') {
        $tsServersList = DB::query("SELECT * FROM ts_servers ORDER BY id ASC");
    } else {
        $tsServersList = DB::query("SELECT * FROM ts_servers WHERE owner = %s ORDER BY id ASC", $loginun);
    }
} catch (Exception $e) {
    $tsServersList = [];
}

// Zeige den Block nur an, wenn der Admin ihn sieht ODER wenn der normale Benutzer mindestens einen TS-Server zugewiesen hat
if ($userLevel === 'Super Administrator' || !empty($tsServersList)):
?>
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body p-3 p-md-4">
                <h5 class="card-title fw-bold mb-3 small text-uppercase" style="color: var(--text-muted);">
                    <i class="fa-solid fa-headset me-1 text-primary"></i> TeamSpeak 3 Server Steuerung
                </h5>

                <?php
                if (empty($tsServersList)) {
                    echo '<div class="alert border text-center py-4 text-muted" style="background-color: var(--bg-surface); border-color: var(--border-color) !important;">Keine TeamSpeak Server in der Datenbank gefunden.</div>';
                } else {
                    echo '<div class="d-flex flex-column gap-3">';
                    foreach ($tsServersList as $ts) {
                        $tsId = (int)$ts['id'];
                        $tsPort = (int)$ts['port'];
                        $tsOwner = htmlspecialchars((string)($ts['owner'] ?? 'Admin'), ENT_QUOTES, 'UTF-8');
                        $isOnline = ((int)($ts['status'] ?? 0) === 1);
                        $tsVersion = htmlspecialchars(strtoupper($ts['version'] ?? 'TS3'), ENT_QUOTES, 'UTF-8');
                        ?>
                        <div class="card w-100 shadow-sm border rounded-3 p-3 stream-item-card">
                            <div class="row g-3 align-items-center">
                                <div class="col-auto">
                                    <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-3 text-center" style="width: 75px; height: 75px; display: flex; align-items: center; justify-content: center;">
                                        <i class="fa-solid fa-headset fa-2x"></i>
                                    </div>
                                </div>

                                <div class="col-12 col-md-5 flex-grow-1">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <h6 class="fw-bold mb-0" style="color: var(--text-primary);">TeamSpeak Server #<?php echo $tsId; ?></h6>
                                        <span class="badge bg-primary px-2 py-1" style="font-size: 0.65rem;"><?php echo $tsVersion; ?></span>
                                    </div>
                                    <p class="small mb-0" style="color: var(--text-muted);">
                                        Port: <strong style="color: var(--text-primary);"><?php echo $tsPort; ?></strong> | Besitzer: <strong style="color: var(--text-primary);"><?php echo $tsOwner; ?></strong>
                                    </p>
                                </div>

                                <div class="col-12 col-md-5 d-flex flex-column align-items-md-end justify-content-center gap-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if ($isOnline): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 rounded-pill">
                                                <span class="spinner-grow spinner-grow-sm text-success me-1" role="status" style="width: 6px; height: 6px;"></span> Online
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1 rounded-pill">
                                                <i class="fa-solid fa-circle-xmark me-1"></i> Offline
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="d-flex flex-wrap gap-1 w-100 justify-content-md-end">
                                        <a href="content.php?include=admts&view=<?php echo $tsId; ?>&action=manage" class="btn btn-sm btn-dark fw-bold">
                                            <i class="fa-solid fa-sliders me-1"></i> Verwalten
                                        </a>

                                        <?php if (!$isOnline): ?>
                                            <button type="button" class="btn btn-sm btn-success fw-bold" onclick="controlTeamSpeak(<?php echo $tsId; ?>, 'start', <?php echo $tsPort; ?>)">
                                                <i class="fa-solid fa-power-off me-1"></i> Start
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-outline-warning fw-bold" onclick="controlTeamSpeak(<?php echo $tsId; ?>, 'restart', <?php echo $tsPort; ?>)">
                                                <i class="fa-solid fa-rotate me-1"></i> Neustart
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger fw-bold" onclick="controlTeamSpeak(<?php echo $tsId; ?>, 'stop', <?php echo $tsPort; ?>)">
                                                <i class="fa-solid fa-stop me-1"></i> Stopp
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                    echo '</div>';
                }
                ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Bootstrap Modal für den integrierten Player -->
<div class="modal fade" id="streamPlayerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="background-color: var(--bg-card); color: var(--text-primary);">
            <div class="modal-header border-bottom" style="border-color: var(--border-color) !important;">
                <h5 class="modal-title fw-bold">
                    <i class="fa-solid fa-headphones text-primary me-2"></i>Live Player (Port: <span id="modalPortDisplay"></span>)
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="stopPlayer()"></button>
            </div>
            <div class="modal-body text-center p-4">
                <audio id="dashboardAudioPlayer" controls class="w-100 mb-3">
                    <source id="audioSourceSrc" src="" type="audio/mpeg">
                    Dein Browser unterstützt das Audio-Element nicht.
                </audio>
                <div class="text-success small fw-semibold">
                    <i class="fa-solid fa-circle-check me-1"></i> Live-Stream wiedergabebereit
                </div>
            </div>
        </div>
    </div>
</div>

<!-- System Notices Section (Admin Only) -->
<?php if ($userLevel === "Super Administrator"): ?>
    <div class="card shadow-sm border-0 mt-4">
        <div class="card-body p-4">
            <h5 class="card-title fw-bold mb-3 small text-uppercase" style="color: var(--text-muted);">
                <i class="fa-solid fa-bell me-1 text-warning"></i> <?php echo htmlspecialchars($msg("370", "System Notices"), ENT_QUOTES, 'UTF-8'); ?>
            </h5>
            
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead style="background-color: var(--bg-surface); color: var(--text-primary);">
                        <tr>
                            <th class="py-3" style="background-color: transparent; color: var(--text-primary);">Benutzer</th>
                            <th class="py-3" style="background-color: transparent; color: var(--text-primary);">Grund / Nachricht</th>
                            <th class="py-3 text-end" style="background-color: transparent; color: var(--text-primary);">Aktion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            $noticesq = DB::query("SELECT * FROM notices");
                        } catch (Exception $e) {
                            $noticesq = [];
                        }

                        if (empty($noticesq)) {
                            echo '<tr>
                                    <td colspan="3" class="text-center py-4 text-muted">' . htmlspecialchars($msg("371", "No notices found."), ENT_QUOTES, 'UTF-8') . '</td>
                                  </tr>';
                        } else {
                            foreach ($noticesq as $data) {
                                $noticeId = (int)$data['id'];
                                $noticeUser = htmlspecialchars((string)$data['username'], ENT_QUOTES, 'UTF-8');
                                $noticeReason = htmlspecialchars((string)$data['reason'], ENT_QUOTES, 'UTF-8');
                                ?>
                                <tr>
                                    <td style="color: var(--text-primary);"><strong><?php echo $noticeUser; ?></strong></td>
                                    <td style="color: var(--text-primary);"><?php echo $noticeReason; ?></td>
                                    <td class="text-end text-nowrap">
                                        <a class="btn btn-sm btn-outline-danger me-1" href="content.php?include=main&action=remove&delmessid=<?php echo $noticeId; ?>">
                                            <i class="fa-solid fa-trash-can me-1"></i> <?php echo htmlspecialchars($msg("372", "Delete"), ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                        <a href="messages.php?id=<?php echo $noticeId; ?>" class="btn btn-sm btn-outline-primary nyroModal selector">
                                            <i class="fa-solid fa-eye me-1"></i> <?php echo htmlspecialchars($msg("373", "View"), ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- JavaScript für iTunes Cover-Suche und Icon/Cover Umschaltung -->
<script>
let activeModal = null;

document.addEventListener("DOMContentLoaded", function() {
    // Automatisches Abrufen von Album-Covern über die iTunes Search API
    document.querySelectorAll('[id^="song-title-"]').forEach(element => {
        let songText = element.getAttribute('data-song');
        let portId = element.id.replace('song-title-', '');
        let coverImg = document.getElementById('cover-' + portId);
        let fallbackIcon = document.getElementById('icon-fallback-' + portId);

        if (songText && songText !== 'Stream Offline / Keine Daten' && songText !== 'Server Offline' && songText.trim() !== '') {
            let cleanQuery = songText.replace(/\[.*?\]|\(.*?\)/g, '').trim();
            
            fetch('https://itunes.apple.com/search?term=' + encodeURIComponent(cleanQuery) + '&media=music&limit=1')
                .then(response => response.json())
                .then(data => {
                    if (data.results && data.results.length > 0) {
                        let artworkUrl = data.results[0].artworkUrl100;
                        if (artworkUrl) {
                            coverImg.src = artworkUrl;
                            // Zeige das echte Bild und verstecke das Icon
                            coverImg.classList.remove('d-none');
                            if (fallbackIcon) fallbackIcon.classList.add('d-none');
                        }
                    }
                })
                .catch(err => {
                    console.log('Cover Fetch Error für Port ' + portId, err);
                });
        }
    });
});

function controlServer(serverId, action, port) {
    let confirmMsg = action === 'start' 
        ? 'Wollen Sie den Server ' + port + ' wirklich starten?' 
        : 'Wollen Sie den Server ' + port + ' wirklich stoppen?';

    if (!confirm(confirmMsg)) return;

    fetch('content.php?include=server&view=' + serverId + '&action=' + action, { method: 'GET' })
        .then(() => setTimeout(() => window.location.href = 'content.php?include=main', 500));
}

function controlAutoDJ(serverId, action) {
    let actionText = action === 'start' ? 'starten' : 'stoppen';
    if (!confirm('Wollen Sie den AutoDJ für Server ID ' + serverId + ' wirklich ' + actionText + '?')) return;

    fetch('content.php?include=autodj&id=' + serverId + '&action=' + action, { method: 'GET' })
        .then(() => setTimeout(() => window.location.href = 'content.php?include=main', 500));
}

// TeamSpeak Server Steuerungs-Funktion direkt aus dem Dashboard
function controlTeamSpeak(serverId, action, port) {
    let texts = { 'start': 'starten', 'stop': 'stoppen', 'restart': 'neustarten' };
    if (!confirm('Wollen Sie den TeamSpeak Server auf Port ' + port + ' wirklich ' + texts[action] + '?')) return;

    fetch('content.php?include=admts&view=' + serverId + '&action=' + action, { method: 'GET' })
        .then(() => {
            setTimeout(() => {
                window.location.href = 'content.php?include=main';
            }, 500);
        })
        .catch(() => alert('Fehler bei der TeamSpeak-Steuerung.'));
}

function openPlayerModal(port) {
    document.getElementById('modalPortDisplay').innerText = port;
    let audioEle = document.getElementById('dashboardAudioPlayer');
    let sourceEle = document.getElementById('audioSourceSrc');

    sourceEle.src = 'stream_proxy.php?port=' + port + '&t=' + Date.now();
    audioEle.load();
    audioEle.play().catch(error => {
        console.log("Play-Fehler:", error);
    });

    if (!activeModal) {
        activeModal = new bootstrap.Modal(document.getElementById('streamPlayerModal'));
    }
    activeModal.show();
}

function stopPlayer() {
    let audioEle = document.getElementById('dashboardAudioPlayer');
    audioEle.pause();
    audioEle.currentTime = 0;
}
</script>

<style>
    .stat-card {
        border-radius: 0.75rem;
        transition: transform 0.2s ease-in-out;
    }
    .stat-card:hover {
        transform: translateY(-3px);
    }
    .stat-icon {
        font-size: 2.8rem;
        opacity: 0.25;
        position: absolute;
        right: 0;
        bottom: -5px;
        line-height: 1;
    }
    
    .bg-gradient-purple { background: linear-gradient(135deg, #6f42c1 0%, #563d7c 100%); }
    .bg-gradient-blue   { background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%); }
    .bg-gradient-red    { background: linear-gradient(135deg, #dc3545 0%, #b02a37 100%); }

    /* Dynamische Kacheln für hellen & dunklen Modus */
    .modern-tile {
        background-color: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 0.75rem;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
        border-left: 4px solid transparent !important;
        min-height: 115px;
    }
    
    .modern-tile:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15) !important;
    }

    .tile-primary:hover { border-left-color: #2563eb !important; background: linear-gradient(to right, rgba(37, 99, 235, 0.08), transparent); }
    .tile-success:hover { border-left-color: #16a34a !important; background: linear-gradient(to right, rgba(22, 163, 74, 0.08), transparent); }
    .tile-info:hover    { border-left-color: #0284c7 !important; background: linear-gradient(to right, rgba(2, 132, 199, 0.08), transparent); }
    .tile-warning:hover { border-left-color: #d97706 !important; background: linear-gradient(to right, rgba(217, 119, 6, 0.08), transparent); }
    .tile-danger:hover  { border-left-color: #dc2626 !important; background: linear-gradient(to right, rgba(220, 38, 38, 0.08), transparent); }
    .tile-purple:hover  { border-left-color: #7c3aed !important; background: linear-gradient(to right, rgba(124, 58, 237, 0.08), transparent); }
    .tile-dark:hover    { border-left-color: #475569 !important; background: linear-gradient(to right, rgba(71, 85, 105, 0.08), transparent); }

    .icon-shape {
        width: 50px;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background-color: var(--bg-surface);
        transition: all 0.2s ease;
    }

    .modern-tile:hover .icon-shape {
        transform: scale(1.1);
    }
    
    .text-purple {
        color: #7c3aed !important;
    }

    /* Stream- und TS-Karten im Theme-Stil */
    .stream-item-card {
        background-color: var(--bg-card) !important;
        border-color: var(--border-color) !important;
    }

    .stat-box {
        background-color: var(--bg-surface) !important;
        border-color: var(--border-color) !important;
    }

    /* Tabellen im Dark/Light Mode anpassen */
    .table {
        color: var(--text-primary) !important;
        border-color: var(--border-color) !important;
    }

    .table > :not(caption) > * > * {
        background-color: transparent !important;
        color: var(--text-primary) !important;
        border-bottom-color: var(--border-color) !important;
    }
</style>