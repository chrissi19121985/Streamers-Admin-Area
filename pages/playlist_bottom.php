<?php
/**
 * Streamers Admin Panel - Playlist View & Editor (Bottom)
 *
 * Mit Duplikatsprüfung beim Hinzufügen (verhindert doppelte Einträge).
 * Ohne Info-Sidebar im Vollbreiten-Layout.
 *
 * Modernisiert für PHP 8.x & Bootstrap 5
 * Originally written by Sebastian Graebner <djcrackhome>
 * Fixed and edited by David Schomburg <dave> & Shippo21
 */

declare(strict_types=1);

if (stripos($_SERVER['PHP_SELF'], 'content.php') === false) {
    die("You can't access this file directly...");
}

// Variablen und Port absolut sicher initialisieren
$portVal = $_GET['portbase'] ?? ($port ?? '');
$portSafe = htmlspecialchars((string)$portVal, ENT_QUOTES, 'UTF-8');
$playlistDir = "./temp/" . $portVal . "/playlist/";
$uploadDir = "./uploads/" . $portVal . "/";

// Verzeichnis für Playlists anlegen
if (!file_exists($playlistDir)) {
    @mkdir($playlistDir, 0777, true);
}

// Dateien aus dem Upload-Ordner einlesen
$availableFiles = [];
if (file_exists($uploadDir) && is_dir($uploadDir)) {
    $di = @scandir($uploadDir);
    if ($di) {
        foreach ($di as $file) {
            if ($file !== "." && $file !== "..") {
                if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'mp3') {
                    $fileUtf8 = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $file);
                    if ($fileUtf8 === false) { $fileUtf8 = $file; }
                    $availableFiles[] = $fileUtf8;
                }
            }
        }
    }
}
sort($availableFiles);

// Pagination
defined('entries_per_page') || define('entries_per_page', 250);
$offset = (isset($_GET['filecount']) && is_numeric($_GET['filecount'])) ? (int)$_GET['filecount'] : 1;
$listing_start = ($offset === 1) ? 0 : ($offset * entries_per_page - entries_per_page + 3);
$listing_end = $offset * entries_per_page + 2;

$dirlisting = @scandir($playlistDir);
if ($dirlisting === false) {
    $dirlisting = [];
}
?>

<!-- Header / Titel -->
<div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom" style="border-color: var(--border-color) !important;">
    <h2 class="h4 mb-0 fw-bold" style="color: var(--text-primary);">
        <i class="fa-solid fa-list-music text-primary me-2"></i>
        <?php echo htmlspecialchars($messages["391"] ?? 'Playlist Management', ENT_QUOTES, 'UTF-8'); ?> (Port: <?php echo $portSafe; ?>)
    </h2>
</div>

<div class="row g-4">
    <!-- Hauptbereich über die volle Breite -->
    <div class="col-lg-12">
        <div class="card shadow-sm border-0 mb-4" style="background-color: var(--bg-card); color: var(--text-primary); border-color: var(--border-color) !important;">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title fw-bold small text-uppercase mb-0" style="color: var(--text-muted);">
                        <i class="fa-solid fa-list me-1 text-warning"></i> Playlists verwalten
                    </h5>
                    <a class="btn btn-sm btn-success" href="content.php?include=playlist&portbase=<?php echo $portSafe; ?>&indiv=1&listname=<?php echo base64_encode("new playlist.lst"); ?>">
                        <i class="fa-solid fa-plus me-1"></i> Playlist erstellen
                    </a>
                </div>

                <?php
                $hasFiles = false;
                $playlistCardsHtml = '';

                for ($i = $listing_start; $i <= $listing_end; $i++) {
                    if (!isset($dirlisting[$i])) {
                        continue;
                    }

                    $fileName = $dirlisting[$i];
                    if ($fileName !== "." && $fileName !== ".." && $fileName !== "") {
                        $hasFiles = true;
                        $filePath = $playlistDir . $fileName;
                        
                        // Dateigröße berechnen
                        $fileSizeKB = file_exists($filePath) ? (filesize($filePath) / 1024) : 0;
                        $fileSizeMB = $fileSizeKB / 1024;
                        
                        // Anzahl der Lieder in der Playlist-Datei zählen
                        $trackCount = 0;
                        if (file_exists($filePath)) {
                            $tracks = @file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                            if ($tracks !== false) {
                                $trackCount = count($tracks);
                            }
                        }

                        $encodedName = base64_encode($fileName);
                        $safeFileName = htmlspecialchars($fileName, ENT_QUOTES, 'UTF-8');

                        $playlistCardsHtml .= '
                        <div class="card border p-3 shadow-sm rounded-3 mb-3" style="background-color: var(--bg-card); border-color: var(--border-color) !important;">
                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-2 pb-2 border-bottom" style="border-color: var(--border-color) !important;">
                                <div class="d-flex align-items-center gap-3">
                                    <span class="fw-bold fs-6" style="color: var(--text-primary);"><i class="fa-solid fa-file-lines text-primary me-1"></i> ' . $safeFileName . '</span>
                                    <span class="badge bg-primary px-2 py-1"><i class="fa-solid fa-music me-1"></i> ' . $trackCount . ' Titel</span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-secondary px-2 py-1">' . round($fileSizeKB, 2) . ' KB (' . round($fileSizeMB, 2) . ' MB)</span>
                                </div>
                            </div>

                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 my-2">
                                <div class="small" style="color: var(--text-muted);">
                                    Status: <span class="fw-semibold" style="color: var(--text-primary);">Bereit zur Bearbeitung</span>
                                </div>

                                <div class="d-flex flex-wrap gap-2 justify-content-end align-items-center">
                                    <a class="btn btn-sm btn-outline-danger px-3" href="content.php?include=playlist&portbase=' . $portSafe . '&indiv=0&listname=' . $encodedName . '&delete=1" onclick="return confirm(\'Wirklich l&ouml;schen?\')" title="L&ouml;schen">
                                        <i class="fa-solid fa-trash me-1"></i> L&ouml;schen
                                    </a>
                                    <a class="btn btn-sm btn-outline-secondary px-3" href="content.php?include=playlist&portbase=' . $portSafe . '&indiv=0&delete=0&listname=' . $encodedName . '" title="Generieren" style="border-color: var(--border-color); color: var(--text-primary);">
                                        <i class="fa-solid fa-rotate me-1"></i> Generieren
                                    </a>
                                    <a class="btn btn-sm btn-outline-primary px-3" href="content.php?include=playlist&portbase=' . $portSafe . '&indiv=1&listname=' . $encodedName . '" title="Bearbeiten">
                                        <i class="fa-solid fa-pen-to-square me-1"></i> Bearbeiten
                                    </a>
                                </div>
                            </div>
                        </div>';
                    }
                }

                if (!$hasFiles) {
                    echo '<div class="text-center py-4" style="color: var(--text-muted);">Keine Playlisten vorhanden.</div>';
                } else {
                    echo '<div class="d-flex flex-column gap-2">' . $playlistCardsHtml . '</div>';
                }
                ?>

                <!-- Playlist Editor Bereich -->
                <?php 
                $indivVal = $_GET['indiv'] ?? '';
                $listnameVal = $_GET['listname'] ?? '';
                $isPlaylistSet = isset($_GET['playlist']);

                if (!$isPlaylistSet && $indivVal === "1") { 
                    $isNewList = ($listnameVal === "bmV3IHBsYXlsaXN0LmxzdA==");
                    $decodedListName = base64_decode($listnameVal);
                    $safeDecodedName = htmlspecialchars($decodedListName !== false ? $decodedListName : '', ENT_QUOTES, 'UTF-8');
                    $formInputValue = $isNewList ? "demoplaylistname" : $safeDecodedName;

                    $currentPlaylistTracks = [];
                    $targetListPath = $playlistDir . basename($decodedListName);
                    if (file_exists($targetListPath)) {
                        $lines = @file($targetListPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                        if ($lines) {
                            foreach ($lines as $line) {
                                $trackBase = basename($line);
                                $trackUtf8 = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $trackBase);
                                if ($trackUtf8 === false) { $trackUtf8 = $trackBase; }
                                $currentPlaylistTracks[] = $trackUtf8;
                            }
                        }
                    }
                ?>
                <div class="border-top mt-4 pt-4" style="border-color: var(--border-color) !important;">
                    <h5 class="fw-bold mb-3" style="color: var(--text-primary);">
                        <i class="fa-solid fa-pen-to-square text-primary me-1"></i> Playlist Editor: <span style="color: var(--text-muted);"><?php echo $safeDecodedName; ?></span>
                    </h5>

                    <form action="content.php?include=playlist&portbase=<?php echo $portSafe; ?>&indiv=1&listname=<?php echo htmlspecialchars($listnameVal, ENT_QUOTES, 'UTF-8'); ?><?php if ($isNewList) { echo '&new=1'; } ?>" method="post" name="treeform" onsubmit="return prepareSave();">
                        <input name="arv" id="arvInput" type="hidden" />
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold" style="color: var(--text-primary);">Playlist Name</label>
                            <input class="form-control" name="playlistformname" type="text" style="background-color: var(--bg-surface); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo htmlspecialchars($formInputValue, ENT_QUOTES, 'UTF-8'); ?>" <?php if (!$isNewList) { echo 'disabled'; } ?> />
                        </div>

                        <div class="row g-3 mb-3">
                            <!-- Linke Box: Verfügbare Dateien -->
                            <div class="col-md-6">
                                <div class="card border shadow-sm h-100" style="background-color: var(--bg-card); border-color: var(--border-color) !important;">
                                    <div class="card-body p-3 d-flex flex-column">
                                        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom" style="height: 42px; border-color: var(--border-color) !important;">
                                            <h6 class="fw-bold small mb-0 text-uppercase text-truncate" style="color: var(--text-muted);">
                                                <i class="fa-solid fa-folder-open me-1 text-warning"></i> Verf&uuml;gbare Dateien
                                            </h6>
                                            <button type="button" class="btn btn-sm btn-outline-primary py-1 px-2 flex-shrink-0" onclick="addAllTracks()" title="Alle Titel hinzuf&uuml;gen">
                                                <i class="fa-solid fa-angles-right me-1"></i> Alle hinzuf&uuml;gen
                                            </button>
                                        </div>
                                        <div id="sourceList" class="p-2 border rounded flex-grow-1" style="height: 350px; overflow-y: auto; background-color: var(--bg-base); border-color: var(--border-color) !important;">
                                            <?php if (empty($availableFiles)): ?>
                                                <div class="text-muted small text-center py-4">Keine MP3-Dateien gefunden.</div>
                                            <?php else: ?>
                                                <?php foreach ($availableFiles as $file): ?>
                                                    <div class="p-2 mb-2 rounded border track-item d-flex align-items-center" draggable="true" style="cursor: grab; background-color: var(--bg-card); color: var(--text-primary); border-color: var(--border-color) !important; transition: all 0.2s;">
                                                        <i class="fa-solid fa-music text-primary me-2"></i>
                                                        <span class="text-break small"><?php echo htmlspecialchars($file, ENT_QUOTES, 'UTF-8'); ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Rechte Box: Aktuelle Playlist -->
                            <div class="col-md-6">
                                <div class="card border shadow-sm h-100" style="background-color: var(--bg-card); border-color: var(--border-color) !important;">
                                    <div class="card-body p-3 d-flex flex-column">
                                        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom" style="height: 42px; border-color: var(--border-color) !important;">
                                            <h6 class="fw-bold small mb-0 text-uppercase text-truncate" style="color: var(--text-muted);">
                                                <i class="fa-solid fa-list-check me-1 text-warning"></i> Playlist Inhalt
                                            </h6>
                                            <button type="button" class="btn btn-sm btn-outline-danger py-1 px-2 flex-shrink-0" onclick="clearPlaylist()" title="Alle Titel l&ouml;schen">
                                                <i class="fa-solid fa-trash-can me-1"></i> Alle l&ouml;schen
                                            </button>
                                        </div>
                                        <div id="targetList" class="p-2 border rounded flex-grow-1" style="height: 350px; overflow-y: auto; background-color: var(--bg-base); border-color: var(--border-color) !important;">
                                            <?php foreach ($currentPlaylistTracks as $track): ?>
                                                <div class="p-2 mb-2 rounded border track-item d-flex justify-content-between align-items-center" draggable="true" style="cursor: grab; background-color: var(--bg-card); color: var(--text-primary); border-color: var(--border-color) !important;">
                                                    <div class="d-flex align-items-center overflow-hidden me-2">
                                                        <i class="fa-solid fa-music text-success me-2 flex-shrink-0"></i>
                                                        <span class="text-break small"><?php echo htmlspecialchars($track, ENT_QUOTES, 'UTF-8'); ?></span>
                                                    </div>
                                                    <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 flex-shrink-0" onclick="this.closest('.track-item').remove();" title="Entfernen">&times;</button>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <script>
                            const sourceList = document.getElementById('sourceList');
                            const targetList = document.getElementById('targetList');

                            document.querySelectorAll('.track-item').forEach(item => {
                                item.addEventListener('dragstart', e => {
                                    e.dataTransfer.setData('text/plain', item.querySelector('span').innerText);
                                });
                            });

                            targetList.addEventListener('dragover', e => {
                                e.preventDefault();
                            });

                            targetList.addEventListener('drop', e => {
                                e.preventDefault();
                                const trackName = e.dataTransfer.getData('text/plain');
                                if (trackName) {
                                    appendTrackToTarget(trackName);
                                }
                            });

                            // Hilfsfunktion: Prüft ob ein Titel bereits in der rechten Box (Playlist) existiert
                            function trackExistsInPlaylist(trackName) {
                                const existingSpans = targetList.querySelectorAll('.track-item span');
                                for (let span of existingSpans) {
                                    if (span.innerText.trim() === trackName.trim()) {
                                        return true;
                                    }
                                }
                                return false;
                            }

                            // Titel nur hinzufügen, wenn er nicht bereits in der Playlist ist
                            function appendTrackToTarget(trackName) {
                                if (trackExistsInPlaylist(trackName)) {
                                    return; // Verhindert Duplikate
                                }

                                const newItem = document.createElement('div');
                                newItem.className = 'p-2 mb-2 rounded border track-item d-flex justify-content-between align-items-center';
                                newItem.draggable = true;
                                newItem.style.cssText = 'cursor: grab; background-color: var(--bg-card); color: var(--text-primary); border-color: var(--border-color) !important;';
                                newItem.innerHTML = `
                                    <div class="d-flex align-items-center overflow-hidden me-2">
                                        <i class="fa-solid fa-music text-success me-2 flex-shrink-0"></i>
                                        <span class="text-break small">${trackName}</span>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 flex-shrink-0" onclick="this.closest('.track-item').remove();">&times;</button>
                                `;
                                
                                newItem.addEventListener('dragstart', ev => {
                                    ev.dataTransfer.setData('text/plain', trackName);
                                });
                                targetList.appendChild(newItem);
                            }

                            function addAllTracks() {
                                const spans = sourceList.querySelectorAll('.track-item span');
                                spans.forEach(span => {
                                    appendTrackToTarget(span.innerText);
                                });
                            }

                            function prepareSave() {
                                const tracks = [];
                                targetList.querySelectorAll('.track-item span').forEach(span => {
                                    tracks.push(span.innerText);
                                });
                                document.getElementById('arvInput').value = tracks.join(',');
                                return true;
                            }

                            function clearPlaylist() {
                                targetList.innerHTML = '';
                            }
                        </script>

                        <div class="d-flex justify-content-start mt-3">
                            <button class="btn btn-primary px-4" type="submit">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Speichern
                            </button>
                        </div>
                    </form>
                </div>
                <?php } ?>

            </div>
        </div>
    </div>
</div>