<?php
/**
 * Streamers Admin Panel - Public Servers List
 *
 * Vollständig angepasst an den hellen und dunklen Modus mit integriertem Web-Player.
 *
 * Modernisiert für PHP 8.x, MeekroDB & Bootstrap 5
 * Originally written by Sebastian Graebner <djcrackhome>
 * Fixed and edited by David Schomburg <dave> & Shippo21
 */

declare(strict_types=1);

if (stripos($_SERVER['PHP_SELF'], 'content.php') === false) {
    die("You can't access this file directly...");
}

/**
 * Überprüft, ob ein SHOUTcast-Server erreichbar ist.
 */
function shoutcastcheck(string $host, int $port2, int $wait_sec): bool {
    $fp = @fsockopen($host, $port2, $errno, $errstr, (float)$wait_sec);
    if ($fp) {
        @fputs($fp, "GET / HTTP/1.0\r\nUser-Agent: AmIoffOrOn\r\n\r\n");
        $ret = @fgets($fp, 255);
        @fclose($fp);
        return $ret !== false && strpos($ret, '200') !== false;
    }
    return false;
}

$limit = isset($setting['display_limit']) ? (int)$setting['display_limit'] : 10;
$p = 0;
if (isset($_GET['p'])) {
    $p = max(0, (int)$_GET['p'] * $limit);
}
$l = $p + $limit;

$hostAdd = htmlspecialchars($setting['host_add'] ?? 'localhost', ENT_QUOTES, 'UTF-8');

try {
    $select = DB::query("SELECT * FROM servers WHERE sitepublic='1' ORDER BY id ASC LIMIT %i, %i", $p, $limit);
} catch (Exception $e) {
    $select = [];
}
?>

<!-- Header / Titel -->
<div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom" style="border-color: var(--border-color) !important;">
    <h2 class="h4 mb-0 fw-bold" style="color: var(--text-primary);">
        <i class="fa-solid fa-globe text-primary me-2"></i><?php echo htmlspecialchars($messages["422"] ?? 'Public Servers', ENT_QUOTES, 'UTF-8'); ?>
    </h2>
</div>

<div class="row g-4">
    <!-- Liste der öffentlichen Server im modernen Karten-Design -->
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 mb-4" style="background-color: var(--bg-card); color: var(--text-primary); border-color: var(--border-color) !important;">
            <div class="card-body p-4">
                
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title fw-bold small text-uppercase mb-0" style="color: var(--text-muted);">
                        <i class="fa-solid fa-list me-1 text-warning"></i> <?php echo htmlspecialchars($messages["423"] ?? '&Uuml;bersicht &ouml;ffentlicher Server', ENT_QUOTES, 'UTF-8'); ?>
                    </h5>
                </div>

                <?php
                if (empty($select)) {
                    echo '<div class="text-center py-4" style="color: var(--text-muted);">' . htmlspecialchars($messages["430"] ?? 'Keine &ouml;ffentlichen Server verf&uuml;gbar.', ENT_QUOTES, 'UTF-8') . '</div>';
                } else {
                    echo '<div class="d-flex flex-column gap-3">';
                    foreach ($select as $data) {
                        $portBase = (int)$data['portbase'];
                        $ownerSafe = htmlspecialchars($data['owner'], ENT_QUOTES, 'UTF-8');
                        $isOnline = shoutcastcheck($setting['host_add'] ?? 'localhost', $portBase, 1);
                        ?>
                        <div class="card border p-3 shadow-sm rounded-3" style="background-color: var(--bg-card); border-color: var(--border-color) !important;">
                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-2 pb-2 border-bottom" style="border-color: var(--border-color) !important;">
                                <div class="d-flex align-items-center gap-3">
                                    <span class="fw-bold fs-6" style="color: var(--text-primary);"><i class="fa-solid fa-network-wired text-primary me-1"></i> Port: <?php echo $portBase; ?></span>
                                    <a href="http://<?php echo $hostAdd . ':' . $portBase; ?>/" target="_blank" class="small text-decoration-none fw-bold text-primary">
                                        <i class="fa-solid fa-up-right-from-square me-1"></i>Server &ouml;ffnen
                                    </a>
                                    <span class="small d-none d-md-inline" style="color: var(--text-muted);">|</span>
                                    <span class="small" style="color: var(--text-muted);">Besitzer: <strong style="color: var(--text-primary);"><?php echo $ownerSafe; ?></strong></span>
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
                                    Status: <span class="fw-semibold" style="color: var(--text-primary);"><?php echo $isOnline ? 'Sendung aktiv &amp; erreichbar' : 'Derzeit keine Sendung aktiv'; ?></span>
                                </div>

                                <div class="d-flex flex-wrap gap-2 justify-content-end align-items-center">
                                    <?php if ($isOnline): ?>
                                        <?php $listenText = htmlspecialchars($messages["431"] ?? 'H&ouml;ren', ENT_QUOTES, 'UTF-8'); ?>
                                        <!-- Player-Modal Button -->
                                        <button type="button" class="btn btn-sm btn-outline-success px-3" onclick="openPlayerModal(<?php echo $portBase; ?>)" title="Sendung anh&ouml;ren">
                                            <i class="fa-solid fa-headphones me-1"></i> <?php echo $listenText; ?>
                                        </button>
                                    <?php else: ?>
                                        <?php $offlineText = htmlspecialchars($messages["432"] ?? 'Offline', ENT_QUOTES, 'UTF-8'); ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary px-3" disabled title="Auf diesem Server l&auml;uft im Moment leider keine Sendung!" style="border-color: var(--border-color); color: var(--text-muted);">
                                            <i class="fa-solid fa-circle-xmark text-danger me-1"></i> <?php echo $offlineText; ?>
                                        </button>
                                    <?php endif; ?>

                                    <a class="btn btn-sm btn-outline-primary px-3" href="http://<?php echo $hostAdd . ':' . $portBase; ?>/" target="_blank">
                                        <i class="fa-solid fa-circle-info me-1"></i> Details
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                    echo '</div>';
                }
                ?>

                <!-- Pagination im Bootstrap-Stil -->
                <?php
                try {
                    if (!empty($select)) {
                        $totalServers = (int)DB::queryFirstField("SELECT COUNT(*) FROM servers WHERE sitepublic='1'");
                        $totalPages = ceil($totalServers / $limit);
                        
                        if ($totalPages > 1) {
                            echo '<nav aria-label="Server Pagination" class="mt-4">
                                    <ul class="pagination justify-content-center mb-0">';
                            
                            for ($i = 0; $i < $totalPages; $i++) {
                                $activeClass = (($p / $limit) === $i) ? ' active' : '';
                                echo '<li class="page-item' . $activeClass . '">
                                        <a class="page-link" href="content.php?include=public&p=' . $i . '">' . ($i + 1) . '</a>
                                      </li>';
                            }
                            echo '</ul></nav>';
                        }
                    }
                } catch (Exception $e) {
                    // Pagination fallback
                }
                ?>
            </div>
        </div>
    </div>

    <!-- Info Sidebar Widget -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 h-100" style="background-color: var(--bg-card); border: 1px solid var(--border-color) !important;">
            <div class="card-body p-4">
                <h5 class="card-title fw-bold mb-3 text-primary">
                    <i class="fa-solid fa-circle-info me-1"></i> <?php echo htmlspecialchars($messages["424"] ?? 'Information', ENT_QUOTES, 'UTF-8'); ?>
                </h5>
                <p class="card-text mb-0" style="color: var(--text-muted);"><?php echo htmlspecialchars($messages["425"] ?? '&Uuml;bersicht aller &ouml;ffentlichen Streaming-Server.', ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap Modal für den integrierten Web-Player -->
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

<!-- JavaScript für den Player -->
<script>
let activeModal = null;

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