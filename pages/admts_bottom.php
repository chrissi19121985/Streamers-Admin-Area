<?php
/**
 * Streamers Admin Panel - TeamSpeak Server Management (Bottom)
 *
 * Vollständig angepasst an den hellen und dunklen Modus sowie besitzerbasierte Benutzerrechte.
 * Modernisiert für PHP 8.x, MeekroDB & Bootstrap 5 (inkl. Backups, Snapshots & Upload)
 */

declare(strict_types=1);

if (stripos($_SERVER['PHP_SELF'], 'content.php') === false) {
    die("You can't access this file directly...");
}

$userLevel = $_SESSION['user_level'] ?? '';
$loginun = $_SESSION['username'] ?? '';

// Sicherheitsprüfung für den Verwaltungs-Modus:
if ($actionVal === 'manage' && $viewId > 0 && $userLevel !== 'Super Administrator') {
    $checkOwner = DB::queryFirstField("SELECT COUNT(*) FROM ts_servers WHERE id=%i AND owner=%s", $viewId, $loginun);
    if ((int)$checkOwner === 0) {
        echo '<div class="alert alert-danger m-4">Zugriff verweigert! Dieser TeamSpeak Server ist dir nicht zugeordnet.</div>';
        return;
    }
}

// Wenn iFrame-Embed-Ansicht aufgerufen wird
if (isset($_GET['action']) && $_GET['action'] === 'embed_view') {
    $embedServerId = isset($_GET['view']) ? (int)$_GET['view'] : 0;
    $embedOnline = false;
    $embedChannels = [];
    $embedClients = [];
    
    if ($userLevel !== 'Super Administrator') {
        $embedCheck = DB::queryFirstField("SELECT COUNT(*) FROM ts_servers WHERE id=%i AND owner=%s", $embedServerId, $loginun);
        if ((int)$embedCheck === 0) {
            die("Zugriff verweigert.");
        }
    }

    $embedServer = DB::queryFirstRow("SELECT * FROM ts_servers WHERE id=%i", $embedServerId);
    if ($embedServer) {
        $embedOnline = ((int)$embedServer['status'] === 1);
    }
    
    if ($embedOnline && !empty($embedServer['query_password'])) {
        $tSock = @fsockopen('127.0.0.1', (int)$embedServer['query_port'], $errno, $errstr, 1);
        if ($tSock) {
            @stream_set_timeout($tSock, 1);
            @fgets($tSock, 1024); @fgets($tSock, 1024);
            fputs($tSock, "login serveradmin {$embedServer['query_password']}\n"); @fgets($tSock, 1024);
            fputs($tSock, "use port={$embedServer['port']}\n"); @fgets($tSock, 1024);
            
            fputs($tSock, "channellist -flags\n");
            $cr = ''; while ($l = @fgets($tSock, 1024)) { $cr .= $l; if (strpos($l, 'error id=') !== false) break; }
            foreach (explode('|', trim(preg_replace('/error id=[0-9]+.*$/', '', $cr))) as $item) {
                if (empty($item)) continue; $p = [];
                foreach (explode(' ', trim($item)) as $pair) { $kv = explode('=', $pair, 2); if (count($kv) === 2) $p[$kv[0]] = rawurldecode($kv[1]); }
                if (isset($p['cid'], $p['channel_name'])) $embedChannels[] = $p;
            }
            
            fputs($tSock, "clientlist\n");
            $clr = ''; while ($l = @fgets($tSock, 1024)) { $clr .= $l; if (strpos($l, 'error id=') !== false) break; }
            foreach (explode('|', trim(preg_replace('/error id=[0-9]+.*$/', '', $clr))) as $item) {
                if (empty($item)) continue; $p = [];
                foreach (explode(' ', trim($item)) as $pair) { $kv = explode('=', $pair, 2); if (count($kv) === 2) $p[$kv[0]] = rawurldecode($kv[1]); }
                if (isset($p['client_nickname']) && (int)($p['client_type'] ?? 0) !== 1) $embedClients[] = $p;
            }
            fputs($tSock, "quit\n"); @fclose($tSock);
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        <style>
            body { background-color: #131314; color: #f8fafc; font-family: system-ui, sans-serif; padding: 12px; }
            .ts-channel { padding: 3px 0; font-weight: 600; font-size: 13px; }
            .ts-client { padding: 2px 0 2px 20px; color: #cbd5e1; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom border-secondary">
            <span class="fw-bold small text-primary"><i class="fa-solid fa-headset me-1"></i> TS3 Live Viewer</span>
            <span class="badge bg-<?php echo $embedOnline ? 'success' : 'danger'; ?>" style="font-size: 10px;"><?php echo $embedOnline ? count($embedClients) . ' User' : 'Offline'; ?></span>
        </div>
        <?php if (!$embedOnline): ?>
            <div class="text-muted small text-center py-4">Server ist zur Zeit offline.</div>
        <?php else: ?>
            <div style="max-height: 350px; overflow-y: auto;">
                <?php foreach ($embedChannels as $chan): 
                    $cId = $chan['cid'];
                    $cName = htmlspecialchars(ts3Unescape($chan['channel_name']), ENT_QUOTES, 'UTF-8');
                    $cClients = array_filter($embedClients, function($cli) use ($cId) {
                        return isset($cli['cid']) && (int)$cli['cid'] === (int)$cId;
                    });
                ?>
                    <div class="ts-channel"><i class="fa-solid fa-folder-open text-warning me-1"></i> <?php echo $cName; ?></div>
                    <?php foreach ($cClients as $cli): ?>
                        <div class="ts-client"><i class="fa-solid fa-user text-secondary me-1"></i> <?php echo htmlspecialchars(ts3Unescape($cli['client_nickname']), ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </body>
    </html>
    <?php
    exit;
}

// Hilfsfunktion zum Dekodieren von TS3 Query Strings (\s -> Leerzeichen, etc.)
if (!function_exists('ts3Unescape')) {
    function ts3Unescape(string $str): string {
        $search  = ['\\s', '\\p', '\\/', '\\\\'];
        $replace = [' ',   '|',   '/',   '\\'];
        return str_replace($search, $replace, $str);
    }
}
?>

<!-- Header / Titel -->
<div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom" style="border-color: var(--border-color) !important;">
    <h2 class="h4 mb-0 fw-bold" style="color: var(--text-primary);">
        <i class="fa-solid fa-headset text-primary me-2"></i> TeamSpeak Server Management
    </h2>
    <?php if ($actionVal === 'manage' && $viewId > 0): ?>
        <a class="btn btn-sm btn-outline-secondary" href="content.php?include=admts" style="border-color: var(--border-color); color: var(--text-primary);">
            <i class="fa-solid fa-arrow-left me-1"></i> Zur&uuml;ck zur &Uuml;bersicht
        </a>
    <?php endif; ?>
</div>

<?php if (!empty($noticeMsg)): ?>
    <div class="alert alert-<?php echo $noticeType; ?> alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-circle-info me-1"></i> <?php echo htmlspecialchars($noticeMsg, ENT_QUOTES, 'UTF-8'); ?>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- TOKEN AUSGABE BOX -->
<?php if (!empty($tokenResult)): ?>
    <div class="alert alert-success border-2 shadow-sm mb-4">
        <h5 class="fw-bold text-success mb-2">
            <i class="fa-solid fa-key me-1"></i> Admin-Berechtigungsschl&uuml;ssel (Token)
        </h5>
        <div class="input-group">
            <input type="text" class="form-control font-monospace fw-bold" id="tsTokenInput" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo htmlspecialchars($tokenResult, ENT_QUOTES, 'UTF-8'); ?>" readonly />
            <button class="btn btn-outline-success" type="button" onclick="navigator.clipboard.writeText(document.getElementById('tsTokenInput').value); alert('Kopiert!');">
                <i class="fa-solid fa-copy me-1"></i> Kopieren
            </button>
        </div>
    </div>
<?php endif; ?>

<!-- DETAIL / MANAGEMENT DASHBOARD -->
<?php if ($actionVal === 'manage' && $viewId > 0): 
    $activeServer = DB::queryFirstRow("SELECT * FROM ts_servers WHERE id=%i", $viewId);
    if (!$activeServer) {
        echo '<div class="alert alert-danger">Server nicht gefunden!</div>';
    } else {
        $srvPort = (int)$activeServer['port'];
        $queryPort = (int)$activeServer['query_port'];
        $storedQueryPass = $activeServer['query_password'] ?? '';
        $srvDir = getcwd() . "/ts3_servers/" . $srvPort . "/";
        $iniFile = $srvDir . "ts3server.ini";
        
        $liveServerInfo = [];
        if ((int)$activeServer['status'] === 1 && !empty($storedQueryPass)) {
            $tmpQ = new TS3QueryHelper('127.0.0.1', $queryPort);
            if (empty($tmpQ->error) && $tmpQ->login('serveradmin', $storedQueryPass) && $tmpQ->selectServerByPort($srvPort)) {
                $liveServerInfo = $tmpQ->getServerInfo();
            }
        }

        $iniRaw = file_exists($iniFile) ? file_get_contents($iniFile) : '';
        $getSettingVal = function($key, $default = '') use ($iniRaw, $liveServerInfo) {
            $val = '';
            if (preg_match('/^' . preg_quote($key, '/') . '\s*=\s*(.*)$/m', $iniRaw, $m)) {
                $val = trim($m[1]);
            } elseif (isset($liveServerInfo[$key]) && is_scalar($liveServerInfo[$key])) {
                $val = (string)$liveServerInfo[$key];
            } else {
                $val = (string)$default;
            }

            $valStr = is_scalar($val) ? (string)$val : (string)$default;
            while (stripos($valStr, $key . '=') === 0) {
                $valStr = trim(substr($valStr, strlen($key) + 1));
            }
            if ($valStr === $key) {
                $valStr = (string)$default;
            }

            return $valStr;
        };

        $cfgName = ts3Unescape($getSettingVal('virtualserver_name', 'TeamSpeak ]I[ Server'));
        $cfgPass = $getSettingVal('virtualserver_password', '');
        $cfgMaxClients = (int)$getSettingVal('virtualserver_maxclients', 32);
        $cfgReserved = (int)$getSettingVal('virtualserver_reserved_slots', 0);
        $cfgWelcome = ts3Unescape($getSettingVal('virtualserver_welcomemessage', 'Welcome to TeamSpeak'));
        $cfgHostMsg = ts3Unescape($getSettingVal('virtualserver_hostmessage', ''));
        $cfgHostMsgMode = (int)$getSettingVal('virtualserver_hostmessage_mode', 0);
        $cfgBannerGfx = $getSettingVal('virtualserver_hostbanner_gfx_url', '');
        $cfgBannerUrl = $getSettingVal('virtualserver_hostbanner_url', '');
        $cfgBannerInterval = (int)$getSettingVal('virtualserver_hostbanner_gfx_interval', 0);
        $cfgBtnTooltip = ts3Unescape($getSettingVal('virtualserver_hostbutton_tooltip', ''));
        $cfgBtnUrl = $getSettingVal('virtualserver_hostbutton_url', '');
        $cfgBtnIcon = $getSettingVal('virtualserver_hostbutton_gfx_url', '');

        $logDir = $srvDir . "logs/";
        $logFiles = is_dir($logDir) ? glob($logDir . "*.log") : [];
        rsort($logFiles);
        $latestLogContent = (!empty($logFiles)) ? @file_get_contents($logFiles[0]) : "Keine Logs vorhanden.";
?>
    <div class="row g-4">
        <!-- Status & Schnellstart Kopfzeile -->
        <div class="col-12">
            <div class="card shadow-sm border-0" style="background-color: var(--bg-card); color: var(--text-primary); border-color: var(--border-color) !important;">
                <div class="card-body p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <div>
                        <h4 class="fw-bold mb-1" style="color: var(--text-primary);">
                            <i class="fa-solid fa-server text-primary me-2"></i> Instanz #<?php echo $activeServer['id']; ?> 
                            <span class="badge bg-primary fs-6 ms-2">Port: <?php echo $srvPort; ?></span>
                        </h4>
                        <span class="small" style="color: var(--text-muted);">Besitzer: <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($activeServer['owner'], ENT_QUOTES, 'UTF-8'); ?></strong> | Query-Port: <?php echo $queryPort; ?></span>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-success btn-sm px-3" href="content.php?include=admts&view=<?php echo $viewId; ?>&action=start">
                            <i class="fa-solid fa-play me-1"></i> Start
                        </a>
                        <a class="btn btn-warning btn-sm px-3" href="content.php?include=admts&view=<?php echo $viewId; ?>&action=restart">
                            <i class="fa-solid fa-rotate me-1"></i> Neustart
                        </a>
                        <a class="btn btn-danger btn-sm px-3" href="content.php?include=admts&view=<?php echo $viewId; ?>&action=stop">
                            <i class="fa-solid fa-stop me-1"></i> Stop
                        </a>
                        <a class="btn btn-outline-success btn-sm px-3" href="content.php?include=admts&view=<?php echo $viewId; ?>&action=token">
                            <i class="fa-solid fa-key me-1"></i> Token
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Funktions-Tabs -->
        <div class="col-12">
            <div class="card shadow-sm border-0" style="background-color: var(--bg-card); color: var(--text-primary); border-color: var(--border-color) !important;">
                <div class="card-body p-4">
                    <ul class="nav nav-tabs mb-4 custom-ts-tabs" id="tsAdminTabs" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link <?php echo ($activeTab === 'clients') ? 'active' : ''; ?> fw-bold" id="clients-tab" data-bs-toggle="tab" data-bs-target="#clients-pane" type="button" role="tab">
                                <i class="fa-solid fa-users me-1"></i> Benutzer, Kicks &amp; Bans
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link <?php echo ($activeTab === 'channels') ? 'active' : ''; ?> fw-bold" id="channels-tab" data-bs-toggle="tab" data-bs-target="#channels-pane" type="button" role="tab">
                                <i class="fa-solid fa-comments me-1"></i> R&auml;ume / Kan&auml;le
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link <?php echo ($activeTab === 'backups') ? 'active' : ''; ?> fw-bold" id="backups-tab" data-bs-toggle="tab" data-bs-target="#backups-pane" type="button" role="tab">
                                <i class="fa-solid fa-clock-rotate-left me-1"></i> Backups &amp; Snapshots
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link <?php echo ($activeTab === 'settings') ? 'active' : ''; ?> fw-bold" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings-pane" type="button" role="tab">
                                <i class="fa-solid fa-sliders me-1"></i> Server-Einstellungen
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link <?php echo ($activeTab === 'logs') ? 'active' : ''; ?> fw-bold" id="logs-tab" data-bs-toggle="tab" data-bs-target="#logs-pane" type="button" role="tab">
                                <i class="fa-solid fa-file-lines me-1"></i> Live-Logs
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link <?php echo ($activeTab === 'viewer') ? 'active' : ''; ?> fw-bold" id="viewer-tab" data-bs-toggle="tab" data-bs-target="#viewer-pane" type="button" role="tab">
                                <i class="fa-solid fa-code me-1"></i> Viewer &amp; Einbetten
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="tsAdminTabsContent">
                        
                        <!-- TAB 1: BENUTZER, KICKS & BANS -->
                        <div class="tab-pane fade <?php echo ($activeTab === 'clients') ? 'show active' : ''; ?>" id="clients-pane" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold mb-0" style="color: var(--text-primary);"><i class="fa-solid fa-user-gear me-1 text-primary"></i> Live verbundene Clients</h6>
                                <a href="content.php?include=admts&view=<?php echo $viewId; ?>&action=manage&tab=clients" class="btn btn-outline-secondary btn-sm" style="border-color: var(--border-color); color: var(--text-primary);">
                                    <i class="fa-solid fa-rotate-right me-1"></i> Aktualisieren
                                </a>
                            </div>

                            <?php
                            $connectedClients = [];
                            $connectedChannels = [];
                            $serverGroupsList = [];
                            $channelGroupsList = [];
                            $queryStatusInfo = '';

                            if ((int)$activeServer['status'] === 1) {
                                if (!empty($storedQueryPass)) {
                                    $qHelper = new TS3QueryHelper('127.0.0.1', $queryPort);
                                    if (empty($qHelper->error)) {
                                        if ($qHelper->login('serveradmin', $storedQueryPass)) {
                                            if ($qHelper->selectServerByPort($srvPort)) {
                                                $connectedClients = $qHelper->getClients();
                                                $connectedChannels = $qHelper->getChannels();
                                                $serverGroupsList = $qHelper->getServerGroups();
                                                $channelGroupsList = $qHelper->getChannelGroups();
                                            } else {
                                                $queryStatusInfo = $qHelper->error;
                                            }
                                        } else {
                                            $queryStatusInfo = $qHelper->error;
                                        }
                                    } else {
                                        $queryStatusInfo = $qHelper->error;
                                    }
                                } else {
                                    $queryStatusInfo = "Kein Query-Passwort hinterlegt.";
                                }
                            } else {
                                $queryStatusInfo = "Der TeamSpeak Server ist aktuell offline.";
                            }

                            if (!empty($queryStatusInfo)) {
                                echo '<div class="alert alert-danger py-3 text-center mb-0"><strong>Status:</strong> ' . htmlspecialchars($queryStatusInfo, ENT_QUOTES, 'UTF-8') . '</div>';
                            } elseif (empty($connectedClients)) {
                                echo '<div class="text-center py-4" style="color: var(--text-muted);">Keine Benutzer online.</div>';
                            } else {
                                echo '<div class="d-flex flex-column gap-2">';
                                foreach ($connectedClients as $cli) {
                                    $cliId = htmlspecialchars($cli['clid'] ?? '', ENT_QUOTES, 'UTF-8');
                                    $cliDbId = htmlspecialchars($cli['client_database_id'] ?? '', ENT_QUOTES, 'UTF-8');
                                    $cliName = htmlspecialchars(ts3Unescape($cli['client_nickname'] ?? ''), ENT_QUOTES, 'UTF-8');
                                    $cliUid = htmlspecialchars($cli['client_unique_identifier'] ?? '', ENT_QUOTES, 'UTF-8');
                                    $cliIp = htmlspecialchars($cli['connection_client_ip'] ?? 'Nicht verf&uuml;gbar', ENT_QUOTES, 'UTF-8');
                                    $cliVersion = htmlspecialchars(ts3Unescape($cli['client_version'] ?? 'Unbekannt'), ENT_QUOTES, 'UTF-8');
                                    $cliPlatform = htmlspecialchars(ts3Unescape($cli['client_platform'] ?? 'Desktop'), ENT_QUOTES, 'UTF-8');
                                    $cliCid = htmlspecialchars($cli['cid'] ?? '0', ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <div class="card border p-3 shadow-sm rounded-3" style="background-color: var(--bg-card); border-color: var(--border-color) !important;">
                                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="fa-solid fa-user text-secondary fs-5 me-1"></i>
                                                <div>
                                                    <div class="fw-bold" style="color: var(--text-primary);"><?php echo $cliName; ?></div>
                                                    <span class="badge bg-secondary opacity-75" style="font-size: 10px;"><?php echo $cliPlatform; ?></span>
                                                </div>
                                            </div>

                                            <div class="d-flex flex-wrap gap-1 align-items-center justify-content-end">
                                                <button class="btn btn-sm btn-outline-info px-2" data-bs-toggle="modal" data-bs-target="#clientInfoModal" onclick="showClientInfo('<?php echo addslashes($cliName); ?>', '<?php echo $cliUid; ?>', '<?php echo $cliDbId; ?>', '<?php echo $cliIp; ?>', '<?php echo addslashes($cliVersion); ?>', '<?php echo $cliPlatform; ?>')" title="Verbindungsinformationen">
                                                    <i class="fa-solid fa-circle-info"></i>
                                                </button>

                                                <button class="btn btn-sm btn-outline-secondary px-2" data-bs-toggle="modal" data-bs-target="#moveClientModal" onclick="prepareMoveClient('<?php echo $cliId; ?>', '<?php echo addslashes($cliName); ?>')" title="In anderen Raum verschieben" style="border-color: var(--border-color); color: var(--text-primary);">
                                                    <i class="fa-solid fa-right-to-bracket"></i>
                                                </button>

                                                <button class="btn btn-sm btn-outline-warning px-2" data-bs-toggle="modal" data-bs-target="#serverGroupModal" onclick="prepareServerGroup('<?php echo $cliDbId; ?>', '<?php echo addslashes($cliName); ?>')" title="Servergruppen zuweisen">
                                                    <i class="fa-solid fa-layer-group"></i>
                                                </button>

                                                <button class="btn btn-sm btn-outline-primary px-2" data-bs-toggle="modal" data-bs-target="#channelGroupModal" onclick="prepareChannelGroup('<?php echo $cliDbId; ?>', '<?php echo $cliCid; ?>', '<?php echo addslashes($cliName); ?>')" title="Channelgruppen zuweisen">
                                                    <i class="fa-solid fa-user-shield"></i>
                                                </button>

                                                <button class="btn btn-sm btn-outline-dark px-2" data-bs-toggle="modal" data-bs-target="#banClientModal" onclick="prepareBanClient('<?php echo $cliId; ?>', '<?php echo addslashes($cliName); ?>')" title="Benutzer bannen" style="border-color: var(--border-color); color: var(--text-primary);">
                                                    <i class="fa-solid fa-ban"></i>
                                                </button>

                                                <a href="content.php?include=admts&view=<?php echo $viewId; ?>&action=kick&clid=<?php echo $cliId; ?>&tab=clients" class="btn btn-sm btn-outline-danger px-2" title="User kicken" onclick="return confirm('Benutzer <?php echo $cliName; ?> wirklich kicken?');">
                                                    <i class="fa-solid fa-user-slash"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <?php
                                }
                                echo '</div>';
                            }
                            ?>
                        </div>

                        <!-- TAB 2: RÄUME / KANÄLE -->
                        <div class="tab-pane fade <?php echo ($activeTab === 'channels') ? 'show active' : ''; ?>" id="channels-pane" role="tabpanel">
                            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-3">
                                <h6 class="fw-bold mb-0" style="color: var(--text-primary);"><i class="fa-solid fa-sitemap me-1 text-primary"></i> Server Kan&auml;le / R&auml;ume</h6>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-success btn-sm flex-fill flex-sm-grow-0" data-bs-toggle="modal" data-bs-target="#channelModal" onclick="prepareChannelModal('create')">
                                        <i class="fa-solid fa-plus me-1"></i> Neuer Raum
                                    </button>
                                    <a href="content.php?include=admts&view=<?php echo $viewId; ?>&action=manage&tab=channels" class="btn btn-outline-secondary btn-sm flex-fill flex-sm-grow-0" style="border-color: var(--border-color); color: var(--text-primary);">
                                        <i class="fa-solid fa-rotate-right me-1"></i> Aktualisieren
                                    </a>
                                </div>
                            </div>

                            <?php
                            if (!empty($queryStatusInfo)) {
                                echo '<div class="alert alert-danger py-3 text-center mb-0"><strong>Status:</strong> ' . htmlspecialchars($queryStatusInfo, ENT_QUOTES, 'UTF-8') . '</div>';
                            } elseif (empty($connectedChannels)) {
                                echo '<div class="text-center py-4" style="color: var(--text-muted);">Keine Kan&auml;le gefunden oder Server offline.</div>';
                            } else {
                                echo '<div class="d-flex flex-column gap-2">';
                                foreach ($connectedChannels as $chan) {
                                    $chanId = htmlspecialchars($chan['cid'] ?? '', ENT_QUOTES, 'UTF-8');
                                    $cleanName = ts3Unescape($chan['channel_name'] ?? 'Unbekannt');
                                    $cleanTopic = ts3Unescape($chan['channel_topic'] ?? '');
                                    
                                    $chanNameEsc = htmlspecialchars($cleanName, ENT_QUOTES, 'UTF-8');
                                    $chanTopicEsc = htmlspecialchars($cleanTopic, ENT_QUOTES, 'UTF-8');
                                    
                                    $isDefault = (isset($chan['channel_flag_default']) && (int)$chan['channel_flag_default'] === 1) ? 1 : 0;
                                    $isPermanent = (isset($chan['channel_flag_permanent']) && (int)$chan['channel_flag_permanent'] === 1);
                                    $isSemi = (isset($chan['channel_flag_semi_permanent']) && (int)$chan['channel_flag_semi_permanent'] === 1);
                                    $cTypeVal = $isPermanent ? 2 : ($isSemi ? 1 : 0);
                                    ?>
                                    <div class="card border p-3 shadow-sm rounded-3" style="background-color: var(--bg-card); border-color: var(--border-color) !important;">
                                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                                            
                                            <div class="d-flex align-items-start gap-2">
                                                <i class="fa-solid fa-comments text-primary mt-1 fs-5"></i>
                                                <div class="w-100">
                                                    <div class="fw-bold fs-6 lh-sm mb-1" style="color: var(--text-primary);"><?php echo $chanNameEsc; ?></div>
                                                    
                                                    <div class="d-flex align-items-center gap-1 flex-wrap mb-1">
                                                        <?php if ($isDefault): ?>
                                                            <span class="badge bg-info text-dark">Standard</span>
                                                        <?php endif; ?>

                                                        <?php if ($isPermanent): ?>
                                                            <span class="badge bg-success">Permanent</span>
                                                        <?php elseif ($isSemi): ?>
                                                            <span class="badge bg-warning text-dark">Semi-Permanent</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Tempor&auml;r</span>
                                                        <?php endif; ?>
                                                    </div>

                                                    <?php if (!empty($cleanTopic)): ?>
                                                        <div class="small" style="color: var(--text-muted);">
                                                            <i class="fa-solid fa-circle-info me-1 opacity-50"></i><?php echo $chanTopicEsc; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <div class="d-flex flex-wrap gap-1 align-items-center justify-content-end ms-auto mt-2 mt-md-0">
                                                <button class="btn btn-sm btn-outline-primary px-2" type="button" data-bs-toggle="modal" data-bs-target="#channelModal" onclick="prepareChannelModal('edit', {
                                                    cid: '<?php echo $chanId; ?>',
                                                    name: '<?php echo addslashes($cleanName); ?>',
                                                    topic: '<?php echo addslashes($cleanTopic); ?>',
                                                    type: '<?php echo $cTypeVal; ?>',
                                                    isdefault: '<?php echo $isDefault; ?>'
                                                })" title="Bearbeiten">
                                                    <i class="fa-solid fa-pen"></i>
                                                </button>

                                                <a href="content.php?include=admts&view=<?php echo $viewId; ?>&action=delete_channel&cid=<?php echo $chanId; ?>&tab=channels" class="btn btn-sm btn-outline-danger px-2" title="L&ouml;schen" onclick="return confirm('Raum wirklich l&ouml;schen?');">
                                                    <i class="fa-solid fa-trash"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <?php
                                }
                                echo '</div>';
                            }
                            ?>
                        </div>

                        <!-- TAB 3: BACKUPS & SNAPSHOTS -->
                        <div class="tab-pane fade <?php echo ($activeTab === 'backups') ? 'show active' : ''; ?>" id="backups-pane" role="tabpanel">
                            <!-- Upload-Formular für externe Backups (Methode 2) -->
                            <div class="card border p-3 mb-3 shadow-sm rounded-3" style="background-color: var(--bg-card); border-color: var(--border-color) !important;">
                                <form method="post" action="content.php?include=admts&view=<?php echo $viewId; ?>&action=upload_backup" enctype="multipart/form-data" class="row g-2 align-items-center">
                                    <div class="col-md-8">
                                        <label class="form-label small fw-bold mb-1" style="color: var(--text-primary);">Bestehendes Backup (.snapshot) hochladen:</label>
                                        <input type="file" class="form-control form-control-sm" name="snapshot_file" accept=".snapshot" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" required />
                                    </div>
                                    <div class="col-md-4 text-md-end mt-4">
                                        <button type="submit" class="btn btn-outline-success btn-sm w-100">
                                            <i class="fa-solid fa-upload me-1"></i> Hochladen &amp; Einspielen
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-3">
                                <div>
                                    <h6 class="fw-bold mb-0" style="color: var(--text-primary);"><i class="fa-solid fa-clock-rotate-left me-1 text-primary"></i> Server-Snapshots &amp; Backups</h6>
                                    <p class="small mb-0" style="color: var(--text-muted);">Sichere und wiederherstelle Kan&auml;le, Servergruppen, Channelgruppen und s&auml;mtliche Rechte.</p>
                                </div>
                                <a href="content.php?include=admts&view=<?php echo $viewId; ?>&action=create_backup&tab=backups" class="btn btn-success btn-sm">
                                    <i class="fa-solid fa-camera me-1"></i> Neues Backup erstellen
                                </a>
                            </div>

                            <?php
                            $backupDir = $srvDir . "backups/";
                            $backupFiles = is_dir($backupDir) ? glob($backupDir . "*.snapshot") : [];
                            rsort($backupFiles);

                            if (empty($backupFiles)) {
                                echo '<div class="text-center py-5 border rounded-3" style="background-color: var(--bg-base); border-color: var(--border-color) !important; color: var(--text-muted);">Noch keine Backups vorhanden. Erstelle jetzt deinen ersten Snapshot oder lade ein bestehendes hoch!</div>';
                            } else {
                                echo '<div class="d-flex flex-column gap-2">';
                                foreach ($backupFiles as $bFile) {
                                    $bName = basename($bFile);
                                    $bSize = round(filesize($bFile) / 1024, 2);
                                    $bDate = date("d.m.Y H:i:s", filemtime($bFile));
                                    ?>
                                    <div class="card border p-3 shadow-sm rounded-3" style="background-color: var(--bg-card); border-color: var(--border-color) !important;">
                                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                                            <div class="d-flex align-items-center gap-3">
                                                <i class="fa-solid fa-file-shield text-success fs-4"></i>
                                                <div>
                                                    <div class="fw-bold font-monospace" style="color: var(--text-primary);"><?php echo htmlspecialchars($bName, ENT_QUOTES, 'UTF-8'); ?></div>
                                                    <span class="small" style="color: var(--text-muted);">Erstellt am: <?php echo $bDate; ?> | Gr&ouml;&szlig;e: <?php echo $bSize; ?> KB</span>
                                                </div>
                                            </div>
                                            <div class="d-flex flex-wrap gap-2 align-items-center justify-content-end">
                                                <a href="content.php?include=admts&view=<?php echo $viewId; ?>&action=download_backup&file=<?php echo urlencode($bName); ?>" class="btn btn-sm btn-outline-secondary px-2" title="Herunterladen" style="border-color: var(--border-color); color: var(--text-primary);">
                                                    <i class="fa-solid fa-download me-1"></i> Download
                                                </a>
                                                <button class="btn btn-sm btn-outline-warning px-2" data-bs-toggle="modal" data-bs-target="#restoreBackupModal" onclick="prepareRestoreBackup('<?php echo htmlspecialchars($bName, ENT_QUOTES, 'UTF-8'); ?>')" title="Wiederherstellen">
                                                    <i class="fa-solid fa-rotate-left me-1"></i> Einspielen
                                                </button>
                                                <a href="content.php?include=admts&view=<?php echo $viewId; ?>&action=delete_backup&file=<?php echo urlencode($bName); ?>&tab=backups" class="btn btn-sm btn-outline-danger px-2" title="L&ouml;schen" onclick="return confirm('Backup <?php echo htmlspecialchars($bName, ENT_QUOTES, 'UTF-8'); ?> wirklich l&ouml;schen?');">
                                                    <i class="fa-solid fa-trash"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <?php
                                }
                                echo '</div>';
                            }
                            ?>
                        </div>

                        <!-- TAB 4: SERVER EINSTELLUNGEN -->
                        <div class="tab-pane fade <?php echo ($activeTab === 'settings') ? 'show active' : ''; ?>" id="settings-pane" role="tabpanel">
                            <form method="post" action="content.php?include=admts&view=<?php echo $viewId; ?>&action=save_config">
                                <h6 class="fw-bold mb-3" style="color: var(--text-primary);"><i class="fa-solid fa-sliders me-1 text-primary"></i> Virtuellen Server konfigurieren</h6>
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold" style="color: var(--text-primary);">Server Name:</label>
                                        <input type="text" class="form-control form-control-sm" name="virtualserver_name" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo htmlspecialchars($cfgName, ENT_QUOTES, 'UTF-8'); ?>" />
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold" style="color: var(--text-primary);">Passwort:</label>
                                        <input type="password" class="form-control form-control-sm" name="virtualserver_password" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo htmlspecialchars($cfgPass, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Leer f&uuml;r &ouml;ffentlich" />
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold" style="color: var(--text-primary);">Maximale Clients:</label>
                                        <input type="number" class="form-control form-control-sm" name="virtualserver_maxclients" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo (int)$cfgMaxClients; ?>" min="1" />
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold" style="color: var(--text-primary);">Reservierte Slots:</label>
                                        <input type="number" class="form-control form-control-sm" name="virtualserver_reserved_slots" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo (int)$cfgReserved; ?>" min="0" />
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-bold" style="color: var(--text-primary);">Willkommensnachricht:</label>
                                        <textarea class="form-control form-control-sm" name="virtualserver_welcomemessage" rows="2" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);"><?php echo htmlspecialchars($cfgWelcome, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                    </div>

                                    <div class="col-12"><hr class="my-2" style="border-color: var(--border-color);"><h6 class="fw-bold small" style="color: var(--text-muted);">Hostnachricht</h6></div>
                                    <div class="col-md-8">
                                        <label class="form-label small fw-bold" style="color: var(--text-primary);">Nachricht:</label>
                                        <input type="text" class="form-control form-control-sm" name="virtualserver_hostmessage" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo htmlspecialchars($cfgHostMsg, ENT_QUOTES, 'UTF-8'); ?>" />
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold" style="color: var(--text-primary);">Modus:</label>
                                        <select class="form-select form-select-sm" name="virtualserver_hostmessage_mode" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);">
                                            <option value="0" <?php echo ($cfgHostMsgMode == 0) ? 'selected' : ''; ?>>Keine Nachricht (NONE)</option>
                                            <option value="1" <?php echo ($cfgHostMsgMode == 1) ? 'selected' : ''; ?>>Log-Nachricht</option>
                                            <option value="2" <?php echo ($cfgHostMsgMode == 2) ? 'selected' : ''; ?>>Modal Popup</option>
                                            <option value="3" <?php echo ($cfgHostMsgMode == 3) ? 'selected' : ''; ?>>Chat-Nachricht</option>
                                        </select>
                                    </div>

                                    <div class="col-12"><hr class="my-2" style="border-color: var(--border-color);"><h6 class="fw-bold small" style="color: var(--text-muted);">Hostbanner</h6></div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold" style="color: var(--text-primary);">Banner Gfx URL:</label>
                                        <input type="text" class="form-control form-control-sm" name="virtualserver_hostbanner_gfx_url" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo htmlspecialchars($cfgBannerGfx, ENT_QUOTES, 'UTF-8'); ?>" />
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold" style="color: var(--text-primary);">URL:</label>
                                        <input type="text" class="form-control form-control-sm" name="virtualserver_hostbanner_url" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo htmlspecialchars($cfgBannerUrl, ENT_QUOTES, 'UTF-8'); ?>" />
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small fw-bold" style="color: var(--text-primary);">Gfx Interval:</label>
                                        <input type="number" class="form-control form-control-sm" name="virtualserver_hostbanner_gfx_interval" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo (int)$cfgBannerInterval; ?>" />
                                    </div>

                                    <div class="col-12"><hr class="my-2" style="border-color: var(--border-color);"><h6 class="fw-bold small" style="color: var(--text-muted);">Hostbutton</h6></div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold" style="color: var(--text-primary);">Tooltip:</label>
                                        <input type="text" class="form-control form-control-sm" name="virtualserver_hostbutton_tooltip" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo htmlspecialchars($cfgBtnTooltip, ENT_QUOTES, 'UTF-8'); ?>" />
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold" style="color: var(--text-primary);">URL:</label>
                                        <input type="text" class="form-control form-control-sm" name="virtualserver_hostbutton_url" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo htmlspecialchars($cfgBtnUrl, ENT_QUOTES, 'UTF-8'); ?>" />
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold" style="color: var(--text-primary);">Icon URL:</label>
                                        <input type="text" class="form-control form-control-sm" name="virtualserver_hostbutton_gfx_url" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo htmlspecialchars($cfgBtnIcon, ENT_QUOTES, 'UTF-8'); ?>" />
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <button type="submit" class="btn btn-primary btn-sm px-4">
                                        <i class="fa-solid fa-floppy-disk me-1"></i> Einstellungen speichern
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- TAB 5: LIVE LOGS -->
                        <div class="tab-pane fade <?php echo ($activeTab === 'logs') ? 'show active' : ''; ?>" id="logs-pane" role="tabpanel">
                            <div class="mb-3">
                                <textarea class="form-control font-monospace text-light small" rows="12" readonly style="font-size: 11px; background-color: var(--bg-base); border-color: var(--border-color); color: var(--text-primary) !important;"><?php echo htmlspecialchars($latestLogContent, ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </div>
                        </div>

                        <!-- TAB 6: VIEWER & EINBETTEN -->
                        <div class="tab-pane fade <?php echo ($activeTab === 'viewer') ? 'show active' : ''; ?>" id="viewer-pane" role="tabpanel">
                            <h6 class="fw-bold mb-3" style="color: var(--text-primary);"><i class="fa-solid fa-code me-1 text-primary"></i> TeamSpeak Live Viewer &amp; Embed Code</h6>
                            <p class="small" style="color: var(--text-muted);">
                                Hier siehst du die Live-Ansicht des TeamSpeak-Servers direkt im Panel. &Uuml;ber den folgenden iFrame-Code kannst du den Viewer zudem auf deiner eigenen Homepage einbinden.
                            </p>

                            <!-- Live Vorschau Box -->
                            <div class="card p-3 mb-4" style="background-color: var(--bg-base); border-color: var(--border-color);">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="fw-bold small" style="color: var(--text-primary);"><i class="fa-solid fa-headset text-primary me-1"></i> Live Tree Vorschau</div>
                                    <a href="content.php?include=admts&view=<?php echo $viewId; ?>&action=manage&tab=viewer" class="btn btn-outline-secondary btn-sm" style="border-color: var(--border-color); color: var(--text-primary);">
                                        <i class="fa-solid fa-rotate-right me-1"></i> Aktualisieren
                                    </a>
                                </div>

                                <?php if (!empty($viewerError)): ?>
                                    <div class="text-danger small text-center py-3"><?php echo htmlspecialchars($viewerError, ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php else: ?>
                                    <div class="ts-tree px-2" style="max-height: 250px; overflow-y: auto;">
                                        <div class="fw-bold mb-2 text-primary small">
                                            <i class="fa-solid fa-server me-1"></i> Server Port: <?php echo $srvPort; ?> (<?php echo count($viewerClients); ?> User online)
                                        </div>
                                        <?php foreach ($viewerChannels as $chan): 
                                            $cId = $chan['cid'];
                                            $cName = htmlspecialchars(ts3Unescape($chan['channel_name']), ENT_QUOTES, 'UTF-8');
                                            $chanClients = array_filter($viewerClients, function($cli) use ($cId) {
                                                return isset($cli['cid']) && (int)$cli['cid'] === (int)$cId;
                                            });
                                        ?>
                                            <div class="py-1 fw-semibold small" style="color: var(--text-primary);">
                                                <i class="fa-solid fa-folder-open text-warning me-1"></i> <?php echo $cName; ?>
                                            </div>
                                            <?php foreach ($chanClients as $cli): 
                                                $cliName = htmlspecialchars(ts3Unescape($cli['client_nickname']), ENT_QUOTES, 'UTF-8');
                                            ?>
                                                <div class="py-1 small ps-3" style="color: var(--text-muted);">
                                                    <i class="fa-solid fa-user me-1 text-secondary" style="font-size: 0.75rem;"></i> <?php echo $cliName; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Code zum Kopieren -->
                            <div class="mb-3">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);">Direkter iFrame-Code f&uuml;r externe Webseiten:</label>
                                <?php 
                                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                                    $domainName = $_SERVER['HTTP_HOST'];
                                    $scriptPath = dirname($_SERVER['REQUEST_URI']);
                                    $embedUrl = $protocol . $domainName . rtrim($scriptPath, '/') . '/content.php?include=admts&view=' . $viewId . '&action=embed_view';
                                    $iframeCode = '<iframe src="' . $embedUrl . '" width="100%" height="400" style="border:none; background:#131314; border-radius:8px;" title="TeamSpeak Live Viewer"></iframe>';
                                ?>
                                <div class="input-group">
                                    <input type="text" class="form-control font-monospace small" id="embedCodeInput" value='<?php echo htmlspecialchars($iframeCode, ENT_QUOTES, 'UTF-8'); ?>' readonly style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" />
                                    <button class="btn btn-outline-primary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('embedCodeInput').value); alert('Code kopiert!');">
                                        <i class="fa-solid fa-copy me-1"></i> Kopieren
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL 1: CLIENT INFO -->
    <div class="modal fade" id="clientInfoModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow" style="background-color: var(--bg-card); color: var(--text-primary); border-color: var(--border-color);">
                <div class="modal-header border-bottom" style="background-color: var(--bg-surface); border-color: var(--border-color) !important;">
                    <h5 class="modal-title fw-bold" style="color: var(--text-primary);"><i class="fa-solid fa-circle-info text-info me-1"></i> Client Info: <span id="infoClientName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 small">
                    <ul class="list-group list-group-flush rounded" style="background-color: var(--bg-base);">
                        <li class="list-group-item d-flex justify-content-between" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);"><span>IP-Adresse:</span> <strong id="infoIp"></strong></li>
                        <li class="list-group-item d-flex justify-content-between" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);"><span>Plattform:</span> <strong id="infoPlatform"></strong></li>
                        <li class="list-group-item d-flex justify-content-between" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);"><span>Client Version:</span> <strong id="infoVersion"></strong></li>
                        <li class="list-group-item d-flex justify-content-between" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);"><span>Database ID:</span> <strong id="infoDbId"></strong></li>
                        <li class="list-group-item" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);"><span>Unique ID (UID):</span><br><code id="infoUid" class="text-break" style="color: var(--text-primary);"></code></li>
                    </ul>
                </div>
                <div class="modal-footer border-top" style="background-color: var(--bg-surface); border-color: var(--border-color) !important;">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Schlie&szlig;en</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL 2: MOVE CLIENT -->
    <div class="modal fade" id="moveClientModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow" style="background-color: var(--bg-card); color: var(--text-primary); border-color: var(--border-color);">
                <form method="post" action="content.php?include=admts&view=<?php echo $viewId; ?>&action=move_client">
                    <input type="hidden" name="clid" id="moveClid" />
                    <div class="modal-header border-bottom" style="background-color: var(--bg-surface); border-color: var(--border-color) !important;">
                        <h5 class="modal-title fw-bold" style="color: var(--text-primary);"><i class="fa-solid fa-right-to-bracket text-secondary me-1"></i> Benutzer verschieben: <span id="moveClientName"></span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <label class="form-label small fw-bold" style="color: var(--text-primary);">Ziel-Kanal w&auml;hlen:</label>
                        <select class="form-select form-select-sm" name="cid" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" required>
                            <?php 
                            if (!empty($connectedChannels)) {
                                foreach ($connectedChannels as $optChan) {
                                    $cOptName = htmlspecialchars(ts3Unescape($optChan['channel_name'] ?? ''));
                                    echo '<option value="' . $optChan['cid'] . '">' . $cOptName . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="modal-footer border-top" style="background-color: var(--bg-surface); border-color: var(--border-color) !important;">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal" style="border-color: var(--border-color); color: var(--text-primary);">Abbrechen</button>
                        <button type="submit" class="btn btn-secondary btn-sm px-4">Verschieben</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL 3: SERVER GRUPPEN -->
    <div class="modal fade" id="serverGroupModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow" style="background-color: var(--bg-card); color: var(--text-primary); border-color: var(--border-color);">
                <form method="post" action="content.php?include=admts&view=<?php echo $viewId; ?>&action=set_servergroup">
                    <input type="hidden" name="cldbid" id="sgCldbid" />
                    <div class="modal-header border-bottom" style="background-color: var(--bg-surface); border-color: var(--border-color) !important;">
                        <h5 class="modal-title fw-bold" style="color: var(--text-primary);"><i class="fa-solid fa-layer-group text-warning me-1"></i> Servergruppe f&uuml;r <span id="sgClientName"></span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <label class="form-label small fw-bold" style="color: var(--text-primary);">Servergruppe w&auml;hlen:</label>
                        <select class="form-select form-select-sm mb-3" name="sgid" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" required>
                            <?php 
                            if (!empty($serverGroupsList)) {
                                foreach ($serverGroupsList as $sg) {
                                    $sgName = htmlspecialchars(ts3Unescape($sg['name'] ?? ''));
                                    echo '<option value="' . $sg['sgid'] . '">' . $sgName . '</option>';
                                }
                            }
                            ?>
                        </select>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="clear_old_groups" id="clearOldGroups" value="1" checked />
                            <label class="form-check-label small fw-semibold text-danger" for="clearOldGroups">
                                Vorherige Gruppen vorher entfernen (Erlaubt Downgrade von ServerAdmin zu Gast/Normal)
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer border-top" style="background-color: var(--bg-surface); border-color: var(--border-color) !important;">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal" style="border-color: var(--border-color); color: var(--text-primary);">Abbrechen</button>
                        <button type="submit" class="btn btn-warning btn-sm px-4 text-dark fw-bold">Gruppe zuweisen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL 4: CHANNEL GRUPPEN -->
    <div class="modal fade" id="channelGroupModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow" style="background-color: var(--bg-card); color: var(--text-primary); border-color: var(--border-color);">
                <form method="post" action="content.php?include=admts&view=<?php echo $viewId; ?>&action=set_channelgroup">
                    <input type="hidden" name="cldbid" id="cgCldbid" />
                    <input type="hidden" name="cid" id="cgCid" />
                    <div class="modal-header border-bottom" style="background-color: var(--bg-surface); border-color: var(--border-color) !important;">
                        <h5 class="modal-title fw-bold" style="color: var(--text-primary);"><i class="fa-solid fa-user-shield text-primary me-1"></i> Channelgruppe f&uuml;r <span id="cgClientName"></span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <label class="form-label small fw-bold" style="color: var(--text-primary);">Channelgruppe w&auml;hlen:</label>
                        <select class="form-select form-select-sm" name="cgid" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" required>
                            <?php 
                            if (!empty($channelGroupsList)) {
                                foreach ($channelGroupsList as $cg) {
                                    $cgName = htmlspecialchars(ts3Unescape($cg['name'] ?? ''));
                                    echo '<option value="' . $cg['cgid'] . '">' . $cgName . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="modal-footer border-top" style="background-color: var(--bg-surface); border-color: var(--border-color) !important;">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal" style="border-color: var(--border-color); color: var(--text-primary);">Abbrechen</button>
                        <button type="submit" class="btn btn-primary btn-sm px-4">Channelgruppe zuweisen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL 5: BENUTZER BANNEN -->
    <div class="modal fade" id="banClientModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow" style="background-color: var(--bg-card); color: var(--text-primary); border-color: var(--border-color);">
                <form method="post" action="content.php?include=admts&view=<?php echo $viewId; ?>&action=ban_client">
                    <input type="hidden" name="clid" id="banClid" />
                    <div class="modal-header border-bottom" style="background-color: var(--bg-surface); border-color: var(--border-color) !important;">
                        <h5 class="modal-title fw-bold" style="color: var(--text-primary);"><i class="fa-solid fa-ban text-danger me-1"></i> Benutzer bannen: <span id="banClientName"></span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label small fw-bold" style="color: var(--text-primary);">Grund:</label>
                            <input type="text" class="form-control form-control-sm" name="ban_reason" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="AdminBan" required />
                        </div>
                        <div class="mb-0">
                            <label class="form-label small fw-bold" style="color: var(--text-primary);">Dauer (in Sekunden, 0 = Permanent):</label>
                            <input type="number" class="form-control form-control-sm" name="ban_time" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="0" min="0" required />
                        </div>
                    </div>
                    <div class="modal-footer border-top" style="background-color: var(--bg-surface); border-color: var(--border-color) !important;">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal" style="border-color: var(--border-color); color: var(--text-primary);">Abbrechen</button>
                        <button type="submit" class="btn btn-danger btn-sm px-4">Benutzer bannen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL 6: RESTORE BACKUP -->
    <div class="modal fade" id="restoreBackupModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow" style="background-color: var(--bg-card); color: var(--text-primary); border-color: var(--border-color);">
                <form method="post" action="content.php?include=admts&view=<?php echo $viewId; ?>&action=restore_backup">
                    <input type="hidden" name="backup_file" id="restoreBackupFile" />
                    <div class="modal-header border-bottom" style="background-color: var(--bg-surface); border-color: var(--border-color) !important;">
                        <h5 class="modal-title fw-bold text-warning" style="color: var(--text-primary);"><i class="fa-solid fa-triangle-exclamation me-1 text-warning"></i> Backup wiederherstellen</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4 small">
                        <div class="alert alert-warning mb-3">
                            <strong>Achtung!</strong> Das Einspielen eines Snapshots &uuml;berschreibt die aktuellen Kan&auml;le, Servergruppen, Channelgruppen und Rechte dieses virtuellen Servers vollst&auml;ndig!
                        </div>
                        <p class="mb-0">Ausgew&auml;hltes Backup: <strong id="restoreBackupName" class="font-monospace text-primary"></strong></p>
                    </div>
                    <div class="modal-footer border-top" style="background-color: var(--bg-surface); border-color: var(--border-color) !important;">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal" style="border-color: var(--border-color); color: var(--text-primary);">Abbrechen</button>
                        <button type="submit" class="btn btn-warning btn-sm px-4 text-dark fw-bold">Jetzt wiederherstellen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- CHANNEL DIALOG -->
    <div class="modal fade" id="channelModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow" style="background-color: var(--bg-card); color: var(--text-primary); border-color: var(--border-color);">
                <form id="channelForm" method="post" action="content.php?include=admts&view=<?php echo $viewId; ?>&action=create_channel">
                    <input type="hidden" name="cid" id="modalCid" />
                    <div class="modal-header border-bottom" style="background-color: var(--bg-surface); border-color: var(--border-color) !important;">
                        <h5 class="modal-title fw-bold" id="modalTitle" style="color: var(--text-primary);"><i class="fa-solid fa-plus text-success me-1"></i> Channel erstellen</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);">Name:</label>
                                <input type="text" class="form-control form-control-sm" name="channel_name" id="modalName" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" required />
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);">Thema:</label>
                                <input type="text" class="form-control form-control-sm" name="channel_topic" id="modalTopic" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" />
                            </div>
                            <div class="col-12">
                                <div class="border p-3 rounded" style="background-color: var(--bg-base); border-color: var(--border-color) !important;">
                                    <label class="form-label small fw-bold mb-2" style="color: var(--text-primary);">Channel Typ</label>
                                    <div class="form-check mb-2"><input class="form-check-input" type="radio" name="channel_type" id="typeTemp" value="0" checked /><label class="form-check-label small" for="typeTemp" style="color: var(--text-primary);">Tempor&auml;r</label></div>
                                    <div class="form-check mb-2"><input class="form-check-input" type="radio" name="channel_type" id="typeSemi" value="1" /><label class="form-check-label small" for="typeSemi" style="color: var(--text-primary);">Semi-Permanent</label></div>
                                    <div class="form-check mb-3"><input class="form-check-input" type="radio" name="channel_type" id="typePerm" value="2" /><label class="form-check-label small" for="typePerm" style="color: var(--text-primary);">Permanent</label></div>
                                    <hr class="my-2" style="border-color: var(--border-color);">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input" type="checkbox" name="channel_flag_default" id="modalDefault" value="1" />
                                        <label class="form-check-label small fw-bold text-primary" for="modalDefault">Standard Channel</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer border-top" style="background-color: var(--bg-surface); border-color: var(--border-color) !important;">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal" style="border-color: var(--border-color); color: var(--text-primary);">Abbrechen</button>
                        <button type="submit" class="btn btn-success btn-sm px-4" id="modalSubmitBtn">Raum erstellen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    function showClientInfo(name, uid, dbid, ip, version, platform) {
        document.getElementById('infoClientName').innerText = name;
        document.getElementById('infoUid').innerText = uid;
        document.getElementById('infoDbId').innerText = dbid;
        document.getElementById('infoIp').innerText = ip;
        document.getElementById('infoVersion').innerText = version;
        document.getElementById('infoPlatform').innerText = platform;
    }

    function prepareMoveClient(clid, name) {
        document.getElementById('moveClid').value = clid;
        document.getElementById('moveClientName').innerText = name;
    }

    function prepareServerGroup(cldbid, name) {
        document.getElementById('sgCldbid').value = cldbid;
        document.getElementById('sgClientName').innerText = name;
    }

    function prepareChannelGroup(cldbid, cid, name) {
        document.getElementById('cgCldbid').value = cldbid;
        document.getElementById('cgCid').value = cid;
        document.getElementById('cgClientName').innerText = name;
    }

    function prepareBanClient(clid, name) {
        document.getElementById('banClid').value = clid;
        document.getElementById('banClientName').innerText = name;
    }

    function prepareRestoreBackup(filename) {
        document.getElementById('restoreBackupFile').value = filename;
        document.getElementById('restoreBackupName').innerText = filename;
    }

    function prepareChannelModal(mode, data = {}) {
        var form = document.getElementById('channelForm');
        var title = document.getElementById('modalTitle');
        var submitBtn = document.getElementById('modalSubmitBtn');

        if (mode === 'edit') {
            form.action = "content.php?include=admts&view=<?php echo $viewId; ?>&action=edit_channel";
            title.innerHTML = '<i class="fa-solid fa-pen text-primary me-1"></i> Raum bearbeiten';
            submitBtn.innerText = "&Auml;nderungen speichern";
            submitBtn.className = "btn btn-primary btn-sm px-4";

            document.getElementById('modalCid').value = data.cid || '';
            document.getElementById('modalName').value = data.name || '';
            document.getElementById('modalTopic').value = data.topic || '';
            document.getElementById('modalDefault').checked = (data.isdefault == '1');

            if (data.type == '2') document.getElementById('typePerm').checked = true;
            else if (data.type == '1') document.getElementById('typeSemi').checked = true;
            else document.getElementById('typeTemp').checked = true;
        } else {
            form.action = "content.php?include=admts&view=<?php echo $viewId; ?>&action=create_channel";
            title.innerHTML = '<i class="fa-solid fa-plus text-success me-1"></i> Channel erstellen';
            submitBtn.innerText = "Raum erstellen";
            submitBtn.className = "btn btn-success btn-sm px-4";

            form.reset();
            document.getElementById('modalCid').value = '';
        }
    }
    </script>
<?php 
    } 
endif; 
?>

<!-- HAUPT-ÜBERSICHT -->
<?php if ($actionVal !== 'manage'): ?>
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 mb-4" style="background-color: var(--bg-card); color: var(--text-primary); border-color: var(--border-color) !important;">
            <div class="card-body p-4">
                
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title fw-bold small text-uppercase mb-0" style="color: var(--text-muted);">
                        <i class="fa-solid fa-list me-1 text-warning"></i> &Uuml;bersicht &amp; Instanzen
                    </h5>
                    <?php if ($userLevel === 'Super Administrator'): ?>
                        <a class="btn btn-sm btn-success" href="content.php?include=admts&action=new">
                            <i class="fa-solid fa-plus me-1"></i> Neuer TeamSpeak Server
                        </a>
                    <?php endif; ?>
                </div>

                <?php
                $tsServers = []; 
                try {
                    if ($userLevel === 'Super Administrator') {
                        $tsServers = DB::query("SELECT * FROM ts_servers ORDER BY id ASC");
                    } else {
                        $tsServers = DB::query("SELECT * FROM ts_servers WHERE owner = %s ORDER BY id ASC", $loginun);
                    }
                } catch (Exception $e) {
                    $tsServers = [];
                }

                if (empty($tsServers)) {
                    echo '<div class="text-center py-4" style="color: var(--text-muted);">Keine TeamSpeak Server gefunden.</div>';
                } else {
                    echo '<div class="d-flex flex-column gap-3">';
                    foreach ($tsServers as $ts) {
                        $tsId = (int)$ts['id'];
                        $tsPort = (int)$ts['port'];
                        $tsVersion = htmlspecialchars(strtoupper($ts['version'] ?? 'TS3'), ENT_QUOTES, 'UTF-8');
                        $tsOwner = htmlspecialchars($ts['owner'] ?? 'Admin', ENT_QUOTES, 'UTF-8');
                        $isOnline = ((int)($ts['status'] ?? 0) === 1);
                        ?>
                        <div class="card border p-3 shadow-sm rounded-3" style="background-color: var(--bg-card); border-color: var(--border-color) !important;">
                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-2 pb-2 border-bottom" style="border-color: var(--border-color) !important;">
                                <div class="d-flex align-items-center gap-3">
                                    <span class="fw-bold fs-6" style="color: var(--text-primary);">TS-Server #<?php echo $tsId; ?></span>
                                    <span class="badge bg-primary px-2 py-1"><?php echo $tsVersion; ?></span>
                                    <a href="ts3server://<?php echo $hostAdd; ?>?port=<?php echo $tsPort; ?>" class="small text-decoration-none fw-bold text-primary">
                                        <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>Port: <?php echo $tsPort; ?>
                                    </a>
                                    <span class="small d-none d-md-inline" style="color: var(--text-muted);">|</span>
                                    <span class="small" style="color: var(--text-muted);">Besitzer: <strong style="color: var(--text-primary);"><?php echo $tsOwner; ?></strong></span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <?php if ($isOnline): ?>
                                        <span class="badge bg-success px-2 py-1"><i class="fa-solid fa-circle fa-2xs me-1"></i> Online</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger px-2 py-1"><i class="fa-solid fa-circle fa-2xs me-1"></i> Offline</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 my-2">
                                <div class="small" style="color: var(--text-muted);">
                                    Status: <span class="fw-semibold" style="color: var(--text-primary);">Bereit zur Steuerung</span>
                                </div>

                                <div class="d-flex flex-wrap gap-2 justify-content-end align-items-center">
                                    <a class="btn btn-sm btn-dark px-3" href="content.php?include=admts&view=<?php echo $tsId; ?>&action=manage" title="Server verwalten" style="background-color: #212529; border-color: #373b3e; color: #fff;">
                                        <i class="fa-solid fa-sliders me-1"></i> Verwalten
                                    </a>

                                    <?php if (!$isOnline): ?>
                                        <a class="btn btn-sm btn-outline-success px-3" href="content.php?include=admts&view=<?php echo $tsId; ?>&action=start" title="Server starten">
                                            <i class="fa-solid fa-play me-1"></i> Start
                                        </a>
                                    <?php else: ?>
                                        <a class="btn btn-sm btn-outline-danger px-3" href="content.php?include=admts&view=<?php echo $tsId; ?>&action=stop" title="Server stoppen">
                                            <i class="fa-solid fa-stop me-1"></i> Stop
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($userLevel === 'Super Administrator'): ?>
                                        <a class="btn btn-sm btn-outline-danger px-3" href="content.php?include=admts&view=<?php echo $tsId; ?>&action=delete" onclick="return confirm('TeamSpeak Server und alle Dateien wirklich l&ouml;schen?')" title="L&ouml;schen">
                                            <i class="fa-solid fa-trash me-1"></i> L&ouml;schen
                                        </a>
                                    <?php endif; ?>
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

        <!-- NEUER SERVER FORMULAR (Nur für Admins) -->
        <?php if ($actionVal === 'new' && $userLevel === 'Super Administrator'): ?>
        <div class="card shadow-sm border-0" style="background-color: var(--bg-card); color: var(--text-primary); border-color: var(--border-color) !important;">
            <div class="card-body p-4">
                <h5 class="card-title fw-bold mb-3 small text-uppercase" style="color: var(--text-muted);">
                    <i class="fa-solid fa-sliders me-1 text-warning"></i> TeamSpeak Installieren
                </h5>

                <form method="post" action="content.php?include=admts&action=save">
                    <fieldset class="border-0 p-0 m-0">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);">TeamSpeak Version</label>
                                <select class="form-select" name="ts_version" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);">
                                    <option value="ts3">TeamSpeak 3 Server (Offizieller Download)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);">Besitzer</label>
                                <select class="form-select" name="owner" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);">
                                    <?php
                                    try {
                                        $usersList = DB::query("SELECT username FROM users ORDER BY username ASC");
                                        foreach ($usersList as $uRow) {
                                            $uName = htmlspecialchars($uRow['username'], ENT_QUOTES, 'UTF-8');
                                            $sel = ($uName === $loginun) ? ' selected' : '';
                                            echo '<option value="' . $uName . '"' . $sel . '>' . $uName . '</option>';
                                        }
                                    } catch (Exception $e) {}
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);">Voice-Port (Standard: 9987)</label>
                                <input class="form-control" name="port" type="text" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="9987"/>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);">FileTransfer-Port (Standard: 30033)</label>
                                <input class="form-control" name="file_port" type="text" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="30033"/>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);">Query-Port (Standard: 10011)</label>
                                <input class="form-control" name="query_port" type="text" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="10011"/>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);">Maximale Slots</label>
                                <input class="form-control" name="max_slots" type="text" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="32"/>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button class="btn btn-primary px-4" type="submit">
                                <i class="fa-solid fa-download me-1"></i> Server herunterladen &amp; installieren
                            </button>
                        </div>
                    </fieldset>
                </form>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- Info Sidebar -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 h-100" style="background-color: var(--bg-card); border: 1px solid var(--border-color) !important;">
            <div class="card-body p-4">
                <h5 class="card-title fw-bold mb-3 text-primary">
                    <i class="fa-solid fa-headset me-1"></i> TeamSpeak Management
                </h5>
                <p class="card-text mb-0" style="color: var(--text-muted);">
                    Verwalte deine TeamSpeak-Instanzen zentral und &uuml;bersichtlich. Lege neue Server an, steuere den Live-Status per Schnellzugriff (Starten/Stoppen) und &uuml;berwache Ports sowie zugewiesene Besitzer in Echtzeit.
                </p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
    /* Passt die Admin-Tabs an das Theme an */
    .custom-ts-tabs .nav-link {
        color: var(--text-primary) !important;
        border-color: transparent !important;
    }
    .custom-ts-tabs .nav-link:hover {
        border-color: var(--border-color) var(--border-color) var(--border-color) !important;
    }
    .custom-ts-tabs .nav-link.active {
        background-color: var(--bg-card) !important;
        color: var(--text-primary) !important;
        border-color: var(--border-color) var(--border-color) var(--bg-card) !important;
    }
</style>