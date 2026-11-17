<?php
/**
 * Streamers Admin Panel - Upload View Template
 *
 * Ansicht mit Multi-Upload, klar sichtbarer Speicherplatz-Anzeige und ID3-Tag-Bearbeitung.
 *
 * Modernisiert für PHP 8.x & Bootstrap 5
 */

declare(strict_types=1);

if (stripos($_SERVER['PHP_SELF'], 'content.php') === false) {
    die("You can't access this file directly...");
}

$portVal = $_GET['portbase'] ?? ($port ?? '');
$safePort = htmlspecialchars((string)$portVal, ENT_QUOTES, 'UTF-8');
$safePortGet = $safePort;

$webspace = isset($data['webspace']) ? (float)$data['webspace'] : 0.0;
$actualDirSize = isset($actual_dir_size) ? (float)$actual_dir_size : 0.0;

$msg = static function(string $key, string $default = '') use ($messages): string {
    return (isset($messages) && is_array($messages) && isset($messages[$key])) ? $messages[$key] : $default;
};

$freesize = $webspace - $actualDirSize;
$freesizeBytes = $freesize * 1024;

// Anzahl der hochgeladenen MP3-Titel ermitteln
$dirListingArr = isset($dirlisting) && is_array($dirlisting) ? $dirlisting : [];
$totalTracksCount = 0;
foreach ($dirListingArr as $fName) {
    if ($fName !== "." && $fName !== ".." && $fName !== "" && strtolower(pathinfo($fName, PATHINFO_EXTENSION)) === 'mp3') {
        $totalTracksCount++;
    }
}
?>

<!-- Header / Titel -->
<div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom" style="border-color: var(--border-color) !important;">
    <h2 class="h4 mb-0 fw-bold" style="color: var(--text-primary);">
        <i class="fa-solid fa-cloud-arrow-up text-primary me-2"></i>
        <?php echo $msg("525", "Upload Manager"); ?> (Port: <?php echo $safePort; ?>)
    </h2>
    <span class="badge bg-primary px-3 py-2 fs-6">
        <i class="fa-solid fa-music me-1"></i> <?php echo $totalTracksCount; ?> Titel hochgeladen
    </span>
</div>

<div class="row g-4">
    <!-- Hauptbereich -->
    <div class="col-lg-8">
        
        <!-- Info-Karten mit klar sichtbarem Gesamt- und Belegtspeicher -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100 p-3 rounded-3" style="background-color: var(--bg-card); border: 1px solid var(--border-color) !important;">
                    <div class="d-flex align-items-center gap-3">
                        <div class="d-flex align-items-center justify-content-center bg-primary bg-opacity-25 text-primary rounded-circle" style="width: 50px; height: 50px; min-width: 50px; max-width: 50px; font-size: 1.2rem;">
                            <i class="fa-solid fa-hard-drive"></i>
                        </div>
                        <div class="flex-grow-1">
                            <span class="d-block small text-uppercase fw-bold" style="color: var(--text-muted);">Speicherbelegung</span>
                            <div class="d-flex align-items-center justify-content-between mt-1">
                                <span class="fw-bold fs-6" style="color: var(--text-primary);">
                                    <?php echo round(($actualDirSize / 1024), 2); ?> MB 
                                    <span class="fw-semibold text-secondary ms-1">/ <?php echo round(($webspace / 1024), 2); ?> MB</span>
                                </span>
                            </div>
                            <div class="progress mt-2" style="height: 6px; background-color: var(--border-color);">
                                <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo ($webspace > 0) ? min(100, ($actualDirSize / $webspace) * 100) : 0; ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100 p-3 rounded-3" style="background-color: var(--bg-card); border: 1px solid var(--border-color) !important;">
                    <div class="d-flex align-items-center gap-3 h-100">
                        <div class="d-flex align-items-center justify-content-center bg-warning bg-opacity-25 text-warning rounded-circle" style="width: 50px; height: 50px; min-width: 50px; max-width: 50px; font-size: 1.2rem;">
                            <i class="fa-solid fa-circle-info"></i>
                        </div>
                        <div>
                            <span class="d-block small text-uppercase fw-bold" style="color: var(--text-muted);">Server-Details</span>
                            <div class="small mt-1" style="color: var(--text-primary);">
                                Format: <strong class="text-success">MP3</strong> &bull; Port: <strong class="text-info"><?php echo $safePort; ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4" style="background-color: var(--bg-card); color: var(--text-primary); border-color: var(--border-color) !important;">
            <div class="card-body p-4">
                <h5 class="card-title fw-bold mb-3 small text-uppercase" style="color: var(--text-muted);">
                    <i class="fa-solid fa-upload me-1 text-warning"></i> <?php echo $msg("526", "Dateien hochladen"); ?>
                </h5>

                <!-- Dropzone & Upload Feld -->
                <div class="mb-3">
                    <div id="dropZone" class="border border-2 border-dashed rounded p-5 text-center position-relative" style="min-height: 150px; background-color: var(--bg-base); border-color: var(--border-color) !important; cursor: pointer;">
                        <input type="file" id="fileInput" name="uploaded_file[]" multiple accept=".mp3,audio/mpeg" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer;" />
                        
                        <div id="dropZoneContent">
                            <i class="fa-solid fa-cloud-arrow-up fa-3x mb-3 text-primary"></i>
                            <h6 class="fw-bold mb-1" style="color: var(--text-primary);">MP3-Dateien hier per Drag &amp; Drop ablegen</h6>
                            <p class="mb-0 small" style="color: var(--text-muted);">Mehrere Dateien gleichzeitig mit Fortschrittsanzeige</p>
                        </div>

                        <!-- Container für dynamische Fortschrittsbalken pro Datei -->
                        <div id="fileProgressList" class="text-start mt-3 d-none"></div>
                    </div>
                </div>

                <script type="text/javascript">
                    const dropZone = document.getElementById('dropZone');
                    const fileInput = document.getElementById('fileInput');
                    const fileProgressList = document.getElementById('fileProgressList');
                    const dropZoneContent = document.getElementById('dropZoneContent');

                    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                        window.addEventListener(eventName, function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                        }, false);
                    });

                    ['dragenter', 'dragover'].forEach(eventName => {
                        dropZone.addEventListener(eventName, function() {
                            dropZone.style.borderColor = 'var(--bs-primary)';
                            dropZone.style.backgroundColor = 'var(--bg-surface)';
                        }, false);
                    });

                    ['dragleave', 'drop'].forEach(eventName => {
                        dropZone.addEventListener(eventName, function() {
                            dropZone.style.borderColor = 'var(--border-color)';
                            dropZone.style.backgroundColor = 'var(--bg-base)';
                        }, false);
                    });

                    dropZone.addEventListener('drop', function(e) {
                        let files = e.dataTransfer.files;
                        if (files.length > 0) {
                            handleFiles(files);
                        }
                    }, false);

                    fileInput.addEventListener('change', function() {
                        if (fileInput.files.length > 0) {
                            handleFiles(fileInput.files);
                        }
                    });

                    function handleFiles(files) {
                        let mp3Files = [];
                        for (let i = 0; i < files.length; i++) {
                            if (files[i].name.toLowerCase().endsWith('.mp3')) {
                                mp3Files.push(files[i]);
                            }
                        }

                        if (mp3Files.length === 0) {
                            alert('Bitte mindestens eine MP3-Datei ausw&auml;hlen.');
                            return;
                        }

                        dropZoneContent.classList.add('d-none');
                        fileProgressList.classList.remove('d-none');
                        fileProgressList.innerHTML = '';

                        mp3Files.forEach((file, index) => {
                            let fileRow = document.createElement('div');
                            fileRow.className = 'mb-3 pb-2 border-bottom';
                            fileRow.style.borderColor = 'var(--border-color)';
                            fileRow.innerHTML = `
                                <div class="d-flex justify-content-between mb-1 small">
                                    <span class="fw-bold text-truncate" style="color: var(--text-primary); max-width: 75%;" title="${file.name}">${file.name}</span>
                                    <span class="fw-bold" style="color: var(--text-primary);" id="status_${index}">0%</span>
                                </div>
                                <div class="progress" style="height: 8px; background-color: var(--border-color);">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" id="bar_${index}" role="progressbar" style="width: 0%;"></div>
                                </div>
                            `;
                            fileProgressList.appendChild(fileRow);

                            uploadSingleFile(file, index, mp3Files.length);
                        });
                    }

                    function uploadSingleFile(file, index, totalFiles) {
                        let formData = new FormData();
                        formData.append('uploaded_file[]', file);

                        let xhr = new XMLHttpRequest();
                        xhr.open('POST', 'content.php?include=upload&portbase=<?php echo $safePort; ?>', true);
                        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

                        let bar = document.getElementById(`bar_${index}`);
                        let statusText = document.getElementById(`status_${index}`);

                        xhr.upload.addEventListener('progress', function(e) {
                            if (e.lengthComputable) {
                                let percent = Math.round((e.loaded / e.total) * 100);
                                bar.style.width = percent + '%';
                                statusText.innerText = percent + '%';
                            }
                        }, false);

                        xhr.onload = function() {
                            if (xhr.status === 200 && xhr.responseText.trim() === 'SUCCESS') {
                                bar.style.width = '100%';
                                bar.classList.remove('progress-bar-animated', 'progress-bar-striped');
                                bar.classList.add('bg-success');
                                statusText.innerText = 'Fertig';
                                
                                checkIfAllDone(totalFiles);
                            } else {
                                statusText.innerText = 'Fehler';
                                statusText.style.color = '#ff6b6b';
                                bar.classList.add('bg-danger');
                            }
                        };

                        xhr.onerror = function() {
                            statusText.innerText = 'Netzwerkfehler';
                            statusText.style.color = '#ff6b6b';
                            bar.classList.add('bg-danger');
                        };

                        xhr.send(formData);
                    }

                    let completedCount = 0;
                    function checkIfAllDone(totalFiles) {
                        completedCount++;
                        if (completedCount === totalFiles) {
                            setTimeout(function() {
                                location.reload(true);
                            }, 600);
                        }
                    }
                </script>

            </div>
        </div>

        <!-- Bereits hochgeladene Dateien -->
        <div class="card shadow-sm border-0 mb-4" style="background-color: var(--bg-card); color: var(--text-primary); border-color: var(--border-color) !important;">
            <div class="card-body p-4">
                
                <form id="multiDeleteForm" method="post" action="content.php?include=upload&portbase=<?php echo $safePort; ?>">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3 gap-2">
                        <h5 class="card-title fw-bold small text-uppercase mb-0" style="color: var(--text-muted);">
                            <i class="fa-solid fa-list me-1 text-warning"></i> Hochgeladene Dateien &amp; Tags
                        </h5>
                        
                        <!-- Massen-Aktionsbuttons -->
                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" name="delete_selected" class="btn btn-sm btn-outline-danger" onclick="return confirm('Ausgew&auml;hlte Dateien wirklich l&ouml;schen?')">
                                <i class="fa-solid fa-trash-can me-1"></i> Ausgew&auml;hlte l&ouml;schen
                            </button>
                            <a href="content.php?include=upload&portbase=<?php echo $safePort; ?>&delete_all=1" class="btn btn-sm btn-danger" onclick="return confirm('ACHTUNG: WIRKLICH ALLE DATEIEN auf einmal l&ouml;schen?')">
                                <i class="fa-solid fa-triangle-exclamation me-1"></i> Alle l&ouml;schen
                            </a>
                        </div>
                    </div>

                    <?php
                    $startIdx = isset($listing_start) ? (int)$listing_start : 0;
                    $endIdx = isset($listing_end) ? (int)$listing_end : 0;
                    $dirListingArr = isset($dirlisting) && is_array($dirlisting) ? $dirlisting : [];
                    $hasFiles = false;
                    $uploadCardsHtml = '';
                    $modalsHtml = '';

                    for ($i = $startIdx; $i <= $endIdx; $i++) {
                        if (!isset($dirListingArr[$i])) {
                            continue;
                        }

                        $fileName = $dirListingArr[$i];
                        if ($fileName !== "." && $fileName !== ".." && $fileName !== "") {
                            $hasFiles = true;
                            $fnr = $i - 1;
                            $safeFileNameAttr = htmlspecialchars($fileName, ENT_QUOTES, 'UTF-8');
                            $thisfilename = htmlspecialchars(substr($fileName, 0, 45), ENT_QUOTES, 'UTF-8');
                            if (strlen($fileName) > 45) {
                                $thisfilename .= "...";
                            }
                            
                            $filePathCheck = "./uploads/" . $safePort . "/" . $fileName;
                            $fileSizeKB = file_exists($filePathCheck) ? (filesize($filePathCheck) / 1024) : 0;
                            $fileSizeMB = $fileSizeKB / 1024;
                            
                            $deleteEncoded = base64_encode($fileName);
                            $downloadEncoded = base64_encode($fileName);
                            $modalId = "editModal_" . $fnr;

                            // Tags-Fallback
                            $cleanName = pathinfo($fileName, PATHINFO_FILENAME);
                            $nameParts = explode('-', $cleanName, 2);
                            $tagArtist = count($nameParts) > 1 ? trim($nameParts[0]) : "Unbekannt";
                            $tagTitle = count($nameParts) > 1 ? trim($nameParts[1]) : trim($cleanName);
                            $tagAlbum = "Unbekannt";

                            // Hauptkarte
                            $uploadCardsHtml .= '
                            <div class="card border p-3 shadow-sm rounded-3 mb-3" style="background-color: var(--bg-card); border-color: var(--border-color) !important;">
                                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-2 pb-2 border-bottom" style="border-color: var(--border-color) !important;">
                                    <div class="d-flex align-items-center gap-3">
                                        <input class="form-check-input file-checkbox mt-0" type="checkbox" name="selected_files[]" value="' . $deleteEncoded . '" style="cursor: pointer; transform: scale(1.2);">
                                        <span class="badge bg-primary px-2 py-1">#' . $fnr . '</span>
                                        <div>
                                            <span class="fw-bold fs-6 text-break" style="color: var(--text-primary);"><i class="fa-solid fa-file-audio text-warning me-1"></i> ' . $thisfilename . '</span>
                                            <div class="small mt-1 d-flex align-items-center gap-2 flex-wrap">
                                                <span class="badge bg-info text-dark px-2 py-1" style="font-size: 0.7rem;"><i class="fa-solid fa-tags me-1"></i> ID3-Tag</span>
                                                <span style="color: var(--text-muted);">
                                                    <strong style="color: var(--text-primary);">' . htmlspecialchars($tagArtist, ENT_QUOTES, 'UTF-8') . '</strong> &ndash; ' . htmlspecialchars($tagTitle, ENT_QUOTES, 'UTF-8') . ' 
                                                    <span class="text-muted ms-1">[' . htmlspecialchars($tagAlbum, ENT_QUOTES, 'UTF-8') . ']</span>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-secondary px-2 py-1">' . round($fileSizeKB, 2) . ' KB</span>
                                    </div>
                                </div>

                                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 my-1">
                                    <div class="small" style="color: var(--text-muted);">
                                        Gr&ouml;&szlig;e: <span class="fw-semibold" style="color: var(--text-primary);">' . round($fileSizeMB, 2) . ' MB</span>
                                    </div>

                                    <div class="d-flex flex-wrap gap-2 justify-content-end align-items-center">
                                        <button type="button" class="btn btn-sm btn-outline-warning px-3" data-bs-toggle="modal" data-bs-target="#' . $modalId . '">
                                            <i class="fa-solid fa-pen-to-square me-1"></i> Bearbeiten
                                        </button>
                                        <a class="btn btn-sm btn-outline-danger px-3" href="content.php?include=upload&portbase=' . $safePort . '&delete=' . $deleteEncoded . '" onclick="return confirm(\'Datei wirklich l&ouml;schen?\')">
                                            <i class="fa-solid fa-trash me-1"></i> ' . $msg("543", "L&ouml;schen") . '
                                        </a>
                                        <a class="btn btn-sm btn-outline-primary px-3" href="content.php?include=upload&portbase=' . $safePort . '&download=' . $downloadEncoded . '">
                                            <i class="fa-solid fa-download me-1"></i> ' . $msg("544", "Download") . '
                                        </a>
                                    </div>
                                </div>
                            </div>';

                            // Modal für den Bearbeiten-Dialog
                            $modalsHtml .= '
                            <div class="modal fade" id="' . $modalId . '" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content border-0 shadow-lg" style="background-color: var(--bg-card); color: var(--text-primary);">
                                        <div class="modal-header border-bottom" style="border-color: var(--border-color) !important;">
                                            <h5 class="modal-title fw-bold">
                                                <i class="fa-solid fa-pen-to-square text-warning me-2"></i> Datei &amp; MP3-Tags bearbeiten
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body p-4">
                                            <div class="mb-3">
                                                <label class="form-label small fw-bold" style="color: var(--text-muted);">Dateiname</label>
                                                <input type="text" class="form-control font-monospace" name="rename_file[' . $deleteEncoded . ']" value="' . $safeFileNameAttr . '" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label small fw-bold" style="color: var(--text-muted);">Titel (ID3)</label>
                                                <input type="text" class="form-control" name="tag_title[' . $deleteEncoded . ']" value="' . htmlspecialchars($tagTitle, ENT_QUOTES, 'UTF-8') . '" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label small fw-bold" style="color: var(--text-muted);">Interpret (ID3)</label>
                                                <input type="text" class="form-control" name="tag_artist[' . $deleteEncoded . ']" value="' . htmlspecialchars($tagArtist, ENT_QUOTES, 'UTF-8') . '" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label small fw-bold" style="color: var(--text-muted);">Album (ID3)</label>
                                                <input type="text" class="form-control" name="tag_album[' . $deleteEncoded . ']" value="' . htmlspecialchars($tagAlbum, ENT_QUOTES, 'UTF-8') . '" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);">
                                            </div>
                                        </div>
                                        <div class="modal-footer border-top" style="border-color: var(--border-color) !important;">
                                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Abbrechen</button>
                                            <button type="submit" name="save_single" value="' . $deleteEncoded . '" class="btn btn-success btn-sm px-3">
                                                <i class="fa-solid fa-floppy-disk me-1"></i> Speichern
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>';
                        }
                    }

                    if (!$hasFiles) {
                        echo '<div class="text-center py-4" style="color: var(--text-muted);">Keine MP3-Dateien hochgeladen.</div>';
                    } else {
                        echo '
                        <div class="mb-3 d-flex align-items-center gap-2 px-1">
                            <input class="form-check-input mt-0" type="checkbox" id="selectAllCheckbox" style="cursor: pointer; transform: scale(1.2);">
                            <label class="form-check-label small fw-bold" for="selectAllCheckbox" style="color: var(--text-muted); cursor: pointer;">Alle auf dieser Seite ausw&auml;hlen</label>
                        </div>
                        <div class="d-flex flex-column gap-2">' . $uploadCardsHtml . '</div>';
                        
                        echo $modalsHtml;
                    }
                    ?>
                </form>

                <script type="text/javascript">
                    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
                    if (selectAllCheckbox) {
                        selectAllCheckbox.addEventListener('change', function() {
                            const checkboxes = document.querySelectorAll('.file-checkbox');
                            checkboxes.forEach(cb => {
                                cb.checked = selectAllCheckbox.checked;
                            });
                        });
                    }
                </script>

            </div>
        </div>
    </div>

    <!-- Info Sidebar Widget -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 h-100" style="background-color: var(--bg-card); border: 1px solid var(--border-color) !important;">
            <div class="card-body p-4">
                <h5 class="card-title fw-bold mb-3 text-primary">
                    <i class="fa-solid fa-circle-info me-1"></i> <?php echo $msg("527", "Information"); ?>
                </h5>
                <p class="card-text mb-0" style="color: var(--text-muted);"><?php echo $msg("528", "Hier sehen Sie die aktuellen MP3-Tags direkt in der Liste. &Uuml;ber 'Bearbeiten' können Sie diese anpassen."); ?></p>
            </div>
        </div>
    </div>
</div>