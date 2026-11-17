<?php
/**
 * Streamers Admin Panel - Update Management (Bottom)
 *
 * Vollst‰ndig angepasst an den hellen und dunklen Modus.
 */

declare(strict_types=1);

if (stripos($_SERVER['PHP_SELF'], 'content.php') === false) {
    die("You can't access this file directly...");
}

// Aktuelle Panel-Version definieren (kannst du bei Updates anpassen)
define('PANEL_VERSION', '1.0');

// URL zu deiner Update-JSON auf deinem Hosting-Server (Beispiel)
$updateCheckUrl = 'https://streamersadminarea.de/download/version.json';

$remoteData = null;
$updateAvailable = false;
$updateError = '';
$updateSuccess = '';

// Remote-Versionsinfo abfragen
$context = stream_context_create(['http' => ['timeout' => 3]]);
$jsonContent = @file_get_contents($updateCheckUrl, false, $context);

if ($jsonContent !== false) {
    $remoteData = json_decode($jsonContent, true);
    if ($remoteData && isset($remoteData['version'])) {
        if (version_compare(PANEL_VERSION, $remoteData['version'], '<')) {
            $updateAvailable = true;
        }
    } else {
        $updateError = 'Die Versionsdatei konnte nicht verarbeitet werden.';
    }
} else {
    $updateError = 'Verbindung zum Update-Server fehlgeschlagen.';
}

// Action: Update ausf¸hren
$action = $_GET['action'] ?? '';
if ($action === 'execute_update' && $updateAvailable && !empty($remoteData['download_url'])) {
    $zipUrl = $remoteData['download_url'];
    $rootPath = dirname(__DIR__) . '/'; // Wurzelverzeichnis des Panels
    $tempZip = $rootPath . 'temp/update_package.zip';

    // Sicherstellen, dass der temp-Ordner existiert
    if (!is_dir($rootPath . 'temp')) {
        @mkdir($rootPath . 'temp', 0777, true);
    }

    // ZIP herunterladen
    $zipData = @file_get_contents($zipUrl);
    if ($zipData !== false && @file_put_contents($tempZip, $zipData) !== false) {
        $zip = new ZipArchive();
        if ($zip->open($tempZip) === TRUE) {
            
            // Dateien entpacken, sensible Dateien aber ausschlieﬂen
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                
                // Ausschlussliste (diese Dateien/Ordner d¸rfen niemals ¸berschrieben werden!)
                if (
                    stripos($filename, 'database.php') !== false ||
                    stripos($filename, 'uploads/') === 0 ||
                    stripos($filename, 'temp/') === 0 ||
                    stripos($filename, 'ts3_servers/') === 0 ||
                    stripos($filename, '.git') !== false
                ) {
                    continue;
                }

                $zip->extractTo($rootPath, [$filename]);
            }
            $zip->close();
            @unlink($tempZip); // ZIP lˆschen

            $updateSuccess = 'Das Update wurde erfolgreich eingespielt!';
            $updateAvailable = false;
        } else {
            $updateError = 'Das Update-Archiv konnte nicht ge&ouml;ffnet werden.';
        }
    } else {
        $updateError = 'Fehler beim Herunterladen des Update-Pakets.';
    }
}
?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom" style="border-color: var(--border-color) !important;">
    <h2 class="h4 mb-0 fw-bold" style="color: var(--text-primary);">
        <i class="fa-solid fa-rotate text-primary me-2"></i> Panel Update-Manager
    </h2>
</div>

<?php if (!empty($updateError)): ?>
    <div class="alert alert-danger shadow-sm">
        <i class="fa-solid fa-triangle-exclamation me-2"></i> <?php echo htmlspecialchars($updateError, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<?php if (!empty($updateSuccess)): ?>
    <div class="alert alert-success shadow-sm">
        <i class="fa-solid fa-circle-check me-2"></i> <?php echo htmlspecialchars($updateSuccess, ENT_QUOTES, 'UTF-8'); ?>
        <div class="mt-2">
            <a href="content.php?include=update" class="btn btn-sm btn-success">Neu laden</a>
        </div>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card shadow-sm border-0" style="background-color: var(--bg-card); color: var(--text-primary); border-color: var(--border-color) !important;">
            <div class="card-body p-4">
                <h5 class="card-title fw-bold mb-3 small text-uppercase" style="color: var(--text-muted);">
                    <i class="fa-solid fa-circle-info me-1 text-primary"></i> Versionsstatus
                </h5>
                
                <ul class="list-group list-group-flush mb-4" style="background-color: transparent;">
                    <li class="list-group-item d-flex justify-content-between py-3" style="background-color: var(--bg-card); color: var(--text-primary); border-color: var(--border-color) !important;">
                        <span>Installierte Version:</span>
                        <strong style="color: var(--text-primary);">v<?php echo PANEL_VERSION; ?></strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between py-3" style="background-color: var(--bg-card); color: var(--text-primary); border-color: var(--border-color) !important;">
                        <span>Verf&uuml;gbare Version:</span>
                        <strong class="text-primary">
                            <?php echo ($remoteData && isset($remoteData['version'])) ? 'v' . htmlspecialchars($remoteData['version'], ENT_QUOTES, 'UTF-8') : 'Unbekannt'; ?>
                        </strong>
                    </li>
                </ul>

                <?php if ($updateAvailable): ?>
                    <div class="alert alert-warning mb-3">
                        <i class="fa-solid fa-bell me-1"></i> Es ist eine neue Version verf&uuml;gbar! Deine Konfiguration und Uploads werden dabei automatisch gesch&uuml;tzt.
                    </div>
                    <a href="content.php?include=update&action=execute_update" class="btn btn-success px-4 fw-bold" onclick="return confirm('M&ouml;chtest du das Update jetzt wirklich einspielen?');">
                        <i class="fa-solid fa-download me-1"></i> Jetzt automatisch aktualisieren
                    </a>
                <?php else: ?>
                    <div class="text-success fw-semibold">
                        <i class="fa-solid fa-circle-check me-1"></i> Dein Panel ist auf dem neuesten Stand.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100" style="background-color: var(--bg-card); border: 1px solid var(--border-color) !important;">
            <div class="card-body p-4">
                <h5 class="card-title fw-bold mb-3 small text-uppercase" style="color: var(--text-muted);">
                    <i class="fa-solid fa-shield-halved me-1 text-success"></i> Sicherheits-Hinweis
                </h5>
                <p class="small mb-0" style="color: var(--text-muted);">
                    Der Updater l&auml;dt das offizielle Update-Paket direkt von deinem Server herunter. Folgende sensible Bereiche werden beim Update strikt ausgeschlossen und bleiben unangetastet:
                </p>
                <ul class="small mt-2 mb-0" style="color: var(--text-muted);">
                    <li>Deine Datenbank-Konfiguration (`database.php`)</li>
                    <li>Hochgeladene Streams &amp; Ordner (`uploads/`)</li>
                    <li>Tempor&auml;re Daten &amp; Logs (`temp/`)</li>
                    <li>Installierte TeamSpeak-Instanzen (`ts3_servers/`)</li>
                </ul>
            </div>
        </div>
    </div>
</div>