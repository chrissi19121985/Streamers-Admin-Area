<?php
/**
 * Streamers Admin Panel - Admin News Management View & Form (Bottom)
 *
 * Vollständig angepasst an den hellen und dunklen Modus (Tabellen-Hintergrund gefixt).
 *
 * Modernisiert für PHP 8.x, MeekroDB & Bootstrap 5
 * Originally written by Sebastian Graebner <djcrackhome>
 * Fixed and edited by David Schomburg <dave> & Shippo21
 */

declare(strict_types=1);

if (stripos($_SERVER['PHP_SELF'], 'content.php') === false) {
    die("You can't access this file directly...");
}

// Hilfsfunktion für Nachrichten
$msg = static function(string $key, string $default = '') use ($messages): string {
    return $messages[$key] ?? $default;
};

// --- LÖSCH-LOGIK FÜR NEWS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delmes'])) {
    if (!empty($_POST['chboxid']) && is_array($_POST['chboxid'])) {
        try {
            foreach ($_POST['chboxid'] as $idToDelete) {
                DB::delete('news', 'id = %i', (int)$idToDelete);
            }
            $correc[] = "Ausgewählte News wurden erfolgreich gelöscht.";
        } catch (Exception $e) {
            $errors[] = "Fehler beim Löschen der News.";
        }
    }
}

$formErrorVal = $formerror ?? '';
?>

<!-- Header / Titel -->
<div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom" style="border-color: var(--border-color) !important;">
    <h2 class="h4 mb-0 fw-bold" style="color: var(--text-primary);">
        <i class="fa-solid fa-bullhorn text-primary me-2"></i>
        <?php echo htmlspecialchars($msg("adm1", "News Management"), ENT_QUOTES, 'UTF-8'); ?>
    </h2>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        
        <!-- NEUE ANKÜNDIGUNG ERSTELLEN -->
        <div class="card shadow-sm border-0 mb-4" style="background-color: var(--bg-card); color: var(--text-primary); border-color: var(--border-color) !important;">
            <div class="card-body p-4">
                <h5 class="card-title fw-bold mb-3 small text-uppercase" style="color: var(--text-muted);">
                    <i class="fa-solid fa-pen-to-square me-1 text-warning"></i> <?php echo htmlspecialchars($msg("adm5", "New Announcement"), ENT_QUOTES, 'UTF-8'); ?>
                </h5>

                <form method="post" action="content.php?include=admnews" id="contactform">
                    <fieldset class="border-0 p-0 m-0">
                        <div class="mb-3">
                            <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("adm6", "Title"), ENT_QUOTES, 'UTF-8'); ?></label>
                            <input type="text" name="titel" class="form-control" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value=""/>
                            <?php
                            if ($formErrorVal === "reason") {
                                echo '<div class="text-danger small mt-1">' . htmlspecialchars($msg("adm7", "Please enter a title"), ENT_QUOTES, 'UTF-8') . '</div>';
                            }
                            ?>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold" style="color: var(--text-primary);">Nachrichtentext</label>
                            <textarea name="message" rows="5" class="form-control" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);"></textarea>
                            <?php
                            if ($formErrorVal === "message") {
                                echo '<div class="text-danger small mt-1">' . htmlspecialchars($msg("adm7", "Please enter a message"), ENT_QUOTES, 'UTF-8') . '</div>';
                            }
                            ?>
                        </div>

                        <div class="d-flex gap-2">
                            <button class="btn btn-primary px-4" type="submit" name="submit">
                                <i class="fa-solid fa-floppy-disk me-1"></i> <?php echo htmlspecialchars($msg("353", "Save"), ENT_QUOTES, 'UTF-8'); ?>
                            </button>
                            <button class="btn btn-outline-secondary px-4" type="reset" style="border-color: var(--border-color); color: var(--text-primary);">
                                <i class="fa-solid fa-rotate-left me-1"></i> <?php echo htmlspecialchars($msg("354", "Reset"), ENT_QUOTES, 'UTF-8'); ?>
                            </button>
                        </div>
                    </fieldset>
                </form>
            </div>
        </div>

        <!-- VORHANDENE NEWS / LÖSCHEN -->
        <div class="card shadow-sm border-0" style="background-color: var(--bg-card); color: var(--text-primary); border-color: var(--border-color) !important;">
            <div class="card-body p-4">
                <h5 class="card-title fw-bold mb-3 small text-uppercase" style="color: var(--text-muted);">
                    <i class="fa-solid fa-list me-1 text-warning"></i> Vorhandene News
                </h5>

                <form method="post" action="content.php?include=admnews">
                    <div class="table-responsive">
                        <table class="table align-middle mb-3 custom-theme-table">
                            <thead>
                                <tr>
                                    <th style="width: 40px;" class="py-3">#</th>
                                    <th class="py-3">Titel</th>
                                    <th class="py-3">Inhalt</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                try {
                                    $editNews = DB::query("SELECT id, titel, text FROM news ORDER BY id DESC");
                                    if (empty($editNews)) {
                                        echo '<tr><td colspan="3" class="text-center py-4 text-muted">No news available.</td></tr>';
                                    } else {
                                        foreach ($editNews as $row) {
                                            $rowId = (int)$row['id'];
                                            $rowTitle = htmlspecialchars($row['titel'], ENT_QUOTES, 'UTF-8');
                                            $rowText = $row['text'] ?? '';

                                            echo '<tr>';
                                            echo '<td><input class="form-check-input" type="checkbox" name="chboxid[]" value="' . $rowId . '"></td>';
                                            echo '<td class="fw-bold">' . $rowTitle . '</td>';
                                            echo '<td class="small text-muted">' . $rowText . '</td>';
                                            echo '</tr>';
                                        }
                                    }
                                } catch (Exception $e) {
                                    echo '<tr><td colspan="3" class="text-center text-danger py-3">Could not load news.</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>

                    <button class="btn btn-danger btn-sm px-3" type="submit" name="delmes" onclick="return confirm('Ausgew&auml;hlte News wirklich l&ouml;schen?')">
                        <i class="fa-solid fa-trash me-1"></i> <?php echo htmlspecialchars($msg("adm8", "Delete Selected"), ENT_QUOTES, 'UTF-8'); ?>
                    </button>
                </form>
            </div>
        </div>

    </div>

    <!-- Info Sidebar Widget -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 h-100" style="background-color: var(--bg-card); border: 1px solid var(--border-color) !important;">
            <div class="card-body p-4">
                <h5 class="card-title fw-bold mb-3 text-primary">
                    <i class="fa-solid fa-circle-info me-1"></i> <?php echo htmlspecialchars($msg("adm3", "Information"), ENT_QUOTES, 'UTF-8'); ?>
                </h5>
                <p class="card-text mb-0" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("adm4", "Create or delete announcements here."), ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </div>
    </div>
</div>

<style>
    /* Erzwingt saubere Hintergründe und Textfarben in der Tabelle je nach Theme */
    .custom-theme-table {
        color: var(--text-primary) !important;
        border-color: var(--border-color) !important;
    }
    .custom-theme-table thead {
        background-color: var(--bg-surface) !important;
        color: var(--text-primary) !important;
    }
    .custom-theme-table thead th {
        background-color: transparent !important;
        color: var(--text-primary) !important;
        border-color: var(--border-color) !important;
    }
    .custom-theme-table tbody td {
        background-color: transparent !important;
        color: var(--text-primary) !important;
        border-color: var(--border-color) !important;
    }
</style>