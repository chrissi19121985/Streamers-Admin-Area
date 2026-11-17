<?php
/**
 * Streamers Admin Panel - Music Management Overview (Bottom)
 *
 * Vollständig angepasst an den hellen und dunklen Modus.
 * Ohne äußeren Karten-Rahmen im Vollbreiten-Layout.
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

$loginun = $_SESSION['username'] ?? '';
$hostAdd = htmlspecialchars($setting['host_add'] ?? 'localhost', ENT_QUOTES, 'UTF-8');

try {
    $select = DB::query("SELECT * FROM servers WHERE owner=%s ORDER BY id ASC LIMIT %i, %i", $loginun, $p, $limit);
} catch (Exception $e) {
    $select = [];
}
?>

<!-- Header / Titel -->
<div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom" style="border-color: var(--border-color) !important;">
    <h2 class="h4 mb-0 fw-bold" style="color: var(--text-primary);">
        <i class="fa-solid fa-music text-primary me-2"></i><?php echo htmlspecialchars($messages["374"] ?? 'Music Management', ENT_QUOTES, 'UTF-8'); ?>
    </h2>
</div>

<div class="row g-4">
    <!-- Karten-Bereich über die volle Breite ohne äußeren Rahmen -->
    <div class="col-lg-12">
        <div class="mb-4" style="color: var(--text-primary);">
            <div class="p-0">
                
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title fw-bold small text-uppercase mb-0" style="color: var(--text-muted);">
                        <i class="fa-solid fa-list me-1 text-warning"></i> <?php echo htmlspecialchars($messages["375"] ?? 'Server-Musik &amp; Playlisten verwalten', ENT_QUOTES, 'UTF-8'); ?>
                    </h5>
                </div>

                <?php
                if (empty($select)) {
                    echo '<div class="text-center py-4" style="color: var(--text-muted);">' . htmlspecialchars($messages["382"] ?? 'Keine Server verf&uuml;gbar.', ENT_QUOTES, 'UTF-8') . '</div>';
                } else {
                    echo '<div class="d-flex flex-column gap-3">';
                    foreach ($select as $data) {
                        if (isset($data['autopid']) && (string)$data['autopid'] !== "9999999") {
                            $portBase = (int)$data['portbase'];
                            $webspace = (float)($data['webspace'] > 0 ? $data['webspace'] : 1);
                            
                            // Verzeichnisgröße berechnen
                            $actual_dir_size = 0.0;
                            $dir = "./uploads/" . $portBase . "/";
                            if (file_exists($dir) && is_dir($dir)) {
                                $filesize = 0;
                                $dp = @opendir($dir);
                                if ($dp) {
                                    while (($filename = readdir($dp)) !== false) {
                                        if ($filename === '.' || $filename === '..') {
                                            continue;
                                        }
                                        $filedata = @stat($dir . "/" . $filename);
                                        if ($filedata && isset($filedata[7])) {
                                            $filesize += $filedata[7];
                                        }
                                    }
                                    closedir($dp);
                                    $actual_dir_size = $filesize / 1024;
                                }
                            }
                            $negative_background_pos = ($actual_dir_size / $webspace) * 120;
                            ?>
                            <div class="card border p-3 shadow-sm rounded-3" style="background-color: var(--bg-card); border-color: var(--border-color) !important;">
                                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-2 pb-2 border-bottom" style="border-color: var(--border-color) !important;">
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="fw-bold fs-6" style="color: var(--text-primary);"><i class="fa-solid fa-network-wired text-primary me-1"></i> Port: <?php echo $portBase; ?></span>
                                        <a href="http://<?php echo $hostAdd . ':' . $portBase; ?>/" target="_blank" class="small text-decoration-none fw-bold text-primary">
                                            <i class="fa-solid fa-up-right-from-square me-1"></i>Host: <?php echo $hostAdd; ?>
                                        </a>
                                    </div>
                                </div>

                                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 my-2">
                                    <div class="small" style="color: var(--text-muted);">
                                        Speicherauslastung:
                                        <div class="space_show mt-1" style="background-position:-<?php echo round($negative_background_pos, 2); ?>px 0px; width: 150px; height: 10px; background-color: var(--border-color); border-radius: 3px;" title="Speicher genutzt"></div>
                                    </div>

                                    <div class="d-flex flex-wrap gap-2 justify-content-end align-items-center">
                                        <a class="btn btn-sm btn-outline-secondary px-3" href="content.php?include=playlist&portbase=<?php echo $portBase; ?>" title="Playlist verwalten" style="border-color: var(--border-color); color: var(--text-primary);">
                                            <i class="fa-solid fa-list me-1"></i> <?php echo htmlspecialchars($messages["383"] ?? 'Playlist', ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                        <a class="btn btn-sm btn-outline-primary px-3" href="content.php?include=upload&portbase=<?php echo $portBase; ?>" title="Dateien hochladen">
                                            <i class="fa-solid fa-upload me-1"></i> <?php echo htmlspecialchars($messages["384"] ?? 'Upload', ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php
                        }
                    }
                    echo '</div>';
                }
                ?>

                <!-- Pagination -->
                <?php
                try {
                    if (!empty($select)) {
                        $totalCountRows = DB::queryFirstField("SELECT COUNT(*) FROM servers WHERE owner=%s", $loginun);
                        $totalPages = ceil((int)$totalCountRows / $limit);
                        
                        if ($totalPages > 1) {
                            echo '<nav aria-label="Music Pagination" class="mt-4">
                                    <ul class="pagination justify-content-center mb-0">';
                            
                            for ($i = 0; $i < $totalPages; $i++) {
                                $activeClass = (($p / $limit) === $i) ? ' active' : '';
                                echo '<li class="page-item' . $activeClass . '">
                                        <a class="page-link" href="content.php?include=music&p=' . $i . '">' . ($i + 1) . '</a>
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
</div>