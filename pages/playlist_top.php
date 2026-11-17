<?php
/**
 * Streamers Admin Panel - Playlist Management (Top Logic)
 *
 * Modernisiert für PHP 8.x und MeekroDB mit direktem Zugriff auf das echte Upload-Verzeichnis.
 * Originally written by Sebastian Graebner <djcrackhome>
 * Fixed and edited by David Schomburg <dave>
 */

declare(strict_types=1);

if (stripos($_SERVER['PHP_SELF'], 'content.php') === false) {
    die("You can't access this file directly...");
}

// Einstellungen über MeekroDB laden
$setting = [];
try {
    $settingsq = DB::query("SELECT * FROM settings WHERE id='0'");
    foreach ($settingsq as $row) {
        foreach ($row as $key => $pref) {
            if (!is_numeric((string)$key)) {
                $setting[$key] = stripslashes((string)$pref);
            }
        }
    }
} catch (Exception $e) {
    $errors[] = "<h2>" . ($messages["g5"] ?? 'Database error') . "</h2>";
}

// Port-Validierung
$port = '';
if (isset($_GET['portbase'])) {
    $port = strip_tags(str_replace(['/', '\\'], '', $_GET['portbase']));
}

if ($port === '') {
    header('Location: content.php?include=music&error=port');
    exit();
}

$username = $_SESSION['username'] ?? '';

// Eigentümer-Prüfung über MeekroDB Prepared Statements
try {
    $selectowner = DB::queryFirstRow("SELECT * FROM servers WHERE portbase=%s AND owner=%s", $port, $username);
    if (!$selectowner) {
        header('Location: content.php?include=music&error=access');
        exit();
    }
} catch (Exception $e) {
    header('Location: content.php?include=music&error=access');
    exit();
}

$playlistDir = "./temp/" . $port . "/playlist/";
$indiv = $_GET['indiv'] ?? '';
$listnameParam = $_GET['listname'] ?? null;
$deleteParam = $_GET['delete'] ?? '';

// Playlist löschen
if ($indiv === "0" && $listnameParam !== null && $deleteParam === "1") {
    $deletefiledecoded = strip_tags(str_replace(['/', '\\'], '', base64_decode($listnameParam)));
    if ($deletefiledecoded !== false) {
        $filePath = $playlistDir . basename($deletefiledecoded);
        if (file_exists($filePath)) {
            @unlink($filePath);
            $correc[] = "<h2>" . ($messages["407"] ?? 'Playlist deleted') . "</h2>";
        } else {
            $errors[] = "<h2>" . ($messages["408"] ?? 'Playlist not found') . "</h2>";
        }
    }
}

// Playlist automatisch generieren / leeren
if ($indiv === "0" && $listnameParam !== null && $deleteParam === "0") {
    $decodedName = base64_decode($listnameParam);
    if ($decodedName !== false) {
        $safeFileName = basename($decodedName);
        if (!file_exists($playlistDir)) {
            @mkdir($playlistDir, 0777, true);
        }
        
        $filePath = $playlistDir . $safeFileName;
        if (!file_exists($filePath)) {
            $playlistfilecreate = @fopen($filePath, 'w');
            if ($playlistfilecreate) {
                @fclose($playlistfilecreate);
                @chmod($filePath, 0777);
            } else {
                $errors[] = "<h2>" . ($messages["409"] ?? 'Could not create playlist file') . "</h2>";
            }
        }
        
        $dirToCpanel = $setting['dir_to_cpanel'] ?? '';
        $findCmd = 'find ' . escapeshellarg($dirToCpanel . 'uploads/' . $port . '/') . ' -type f -name "*.mp3" > ' . escapeshellarg($dirToCpanel . 'temp/' . $port . '/playlist/' . $safeFileName);
        @shell_exec($findCmd);
        
        $correc[] = "" . ($messages["410"] ?? 'Playlist generated') . "";
    }
}

// Playlist speichern / aktualisieren via POST
if ($deleteParam !== "1") {
    if (isset($_POST['arv'], $_GET['listname'])) {
        $isNew = (isset($_GET['new']) && $_GET['new'] === "1");
        
        if ($isNew) {
            if ($_POST['playlistformname'] !== "bmV3IHBsYXlsaXN0LmxzdA==") {
                $playlistfilecreatename = strip_tags(str_replace(['/', '\\'], '', $_POST['playlistformname']));
                $targetListPath = $playlistDir . $playlistfilecreatename . ".lst";
                
                if (!file_exists($targetListPath)) {
                    if (!file_exists($playlistDir)) {
                        @mkdir($playlistDir, 0777, true);
                    }
                    
                    $playlistfilecreate = @fopen($targetListPath, 'w');
                    if ($playlistfilecreate) {
                        @fclose($playlistfilecreate);
                        
                        $postvar_form = (string)$_POST['arv'];
                        $filehandle = @fopen($targetListPath, "w+");
                        if ($filehandle) {
                            $tok = explode(',', $postvar_form);
                            $dirToCpanel = $setting['dir_to_cpanel'] ?? '';
                            foreach ($tok as $playlistentry) {
                                $safeEntry = trim(str_replace(['/', '\\'], '', $playlistentry));
                                if ($safeEntry !== '') {
                                    @fwrite($filehandle, $dirToCpanel . "uploads/" . $port . "/" . $safeEntry . "\n");
                                }
                            }
                            @fclose($filehandle);
                            @chmod($targetListPath, 0777);
                            $correc[] = "" . ($messages["412"] ?? 'Playlist saved') . "";
                        } else {
                            $errors[] = "<h2>" . ($messages["413"] ?? 'Could not write playlist') . "</h2>";
                        }
                    } else {
                        $errors[] = "<h2>" . ($messages["411"] ?? 'Could not create file') . "</h2>";
                    }
                } else {
                    $errors[] = "<h2>" . ($messages["415"] ?? 'Playlist already exists') . "</h2>";
                }
            } else {
                $errors[] = "<h2>" . ($messages["416"] ?? 'Invalid playlist name') . "</h2>";
            }
        } else {
            // Bestehende Playlist aktualisieren
            $decodedExisting = base64_decode($_GET['listname']);
            if ($decodedExisting !== false) {
                $targetListPath = $playlistDir . basename($decodedExisting);
                if (file_exists($playlistDir) && file_exists($targetListPath)) {
                    $postvar_form = (string)$_POST['arv'];
                    $filehandle = @fopen($targetListPath, "w+");
                    if ($filehandle) {
                        $tok = explode(',', $postvar_form);
                        $dirToCpanel = $setting['dir_to_cpanel'] ?? '';
                        foreach ($tok as $playlistentry) {
                            $safeEntry = trim(str_replace(['/', '\\'], '', $playlistentry));
                            if ($safeEntry !== '') {
                                @fwrite($filehandle, $dirToCpanel . "uploads/" . $port . "/" . $safeEntry . "\n");
                            }
                        }
                        @fclose($filehandle);
                        @chmod($targetListPath, 0777);
                        $correc[] = "" . ($messages["417"] ?? 'Playlist updated') . "";
                    } else {
                        $errors[] = "<h2>" . ($messages["418"] ?? 'Could not write playlist') . "</h2>";
                    }
                } else {
                    $errors[] = "<h2>" . ($messages["419"] ?? 'Playlist file missing') . "</h2>";
                }
            }
        }
    }
} else {
    define('entries_per_page', 250);
    $offset = (isset($_GET['filecount']) && is_numeric($_GET['filecount'])) ? (int)$_GET['filecount'] : 1;
    
    if ($offset === 1) {
        $listing_start = $offset * entries_per_page - entries_per_page;
    } else {
        $listing_start = $offset * entries_per_page - entries_per_page + 3;
    }
    
    $listing_end = $offset * entries_per_page + 2;
    
    if (!file_exists($playlistDir)) {
        @mkdir($playlistDir, 0777, true);
    }
    
    $dirlisting = @scandir($playlistDir);
    if ($dirlisting === false) {
        $errors[] = "<h2>" . ($messages["420"] ?? 'Could not read playlist directory') . "</h2>";
        $dirlisting = [];
    }
}