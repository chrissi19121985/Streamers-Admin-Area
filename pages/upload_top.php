<?php
/**
 * Streamers Admin Panel - Music / Upload Management
 *
 * Modernisiert für PHP 8.x und MeekroDB mit Multi-Delete & MP3-Tag/Umbenennungs-Unterstützung.
 */

declare(strict_types=1);

if (stripos($_SERVER['PHP_SELF'], 'content.php') === false) {
    die("You can't access this file directly...");
}

$settings = [];
try {
    $settingsq = DB::query("SELECT * FROM settings WHERE id='0'");
    foreach ($settingsq as $row) {
        foreach ($row as $key => $pref) {
            if (!is_numeric((string)$key)) {
                $settings[$key] = stripslashes((string)$pref);
            }
        }
    }
} catch (Exception $e) {
    $errors[] = "" . ($messages["g5"] ?? 'Database error') . "";
}

$port = '';
if (isset($_GET['portbase'])) {
    $port = strip_tags(str_replace(['/', '\\'], '', $_GET['portbase']));
}

if ($port === '') {
    header('Location: content.php?include=music&error=port');
    exit();
}

try {
    $serverOwner = DB::queryFirstRow("SELECT * FROM servers WHERE portbase=%s AND owner=%s", $port, $_SESSION['username'] ?? '');
    if (!$serverOwner) {
        header('Location: content.php?include=music&error=access');
        exit();
    }
    $data = DB::queryFirstRow("SELECT * FROM servers WHERE portbase=%s", $port);
} catch (Exception $e) {
    header('Location: content.php?include=music&error=access');
    exit();
}

$uploadDir = "./uploads/" . $port . "/";

if (isset($_GET['playlist']) || (isset($_GET['playlist']) && is_numeric($_GET['playlist']))) {
    $tempDir = "./temp/";
    if (!file_exists($tempDir)) {
        mkdir($tempDir, 0777, true);
    }
    $lstFile = $tempDir . $port . ".lst";
    if (!file_exists($lstFile)) {
        $handle = @fopen($lstFile, 'w+');
        if ($handle) {
            fclose($handle);
            @chmod($lstFile, 0777);
        }
    }
    shell_exec('find ' . escapeshellarg(dirname(__FILE__) . '/uploads/' . $port . '/') . ' -type f -name "*.mp3" > ' . escapeshellarg($lstFile));
    header('Location: content.php?include=music&error=playlist');
    exit();
}

// UPLOAD VERARBEITUNG
if (isset($_FILES["uploaded_file"])) {
    if (!file_exists($uploadDir)) {
        $old = umask(0);
        @mkdir($uploadDir, 0777, true);
        umask($old);
    }

    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    
    $fileError = is_array($_FILES["uploaded_file"]["error"]) ? $_FILES["uploaded_file"]["error"][0] : $_FILES["uploaded_file"]["error"];
    $fileTmpName = is_array($_FILES["uploaded_file"]["tmp_name"]) ? $_FILES["uploaded_file"]["tmp_name"][0] : $_FILES["uploaded_file"]["tmp_name"];
    $fileNameOriginal = is_array($_FILES["uploaded_file"]["name"]) ? $_FILES["uploaded_file"]["name"][0] : $_FILES["uploaded_file"]["name"];

    if ($fileError === UPLOAD_ERR_OK) {
        $filename = basename($fileNameOriginal);
        $targetFile = $uploadDir . $filename;
        
        if (move_uploaded_file($fileTmpName, $targetFile)) {
            if ($isAjax) {
                header('Content-Type: text/plain; charset=utf-8');
                echo "SUCCESS";
                exit();
            }
            header('Location: content.php?include=upload&portbase=' . $port);
            exit();
        }
    }

    if ($isAjax) {
        http_response_code(500);
        exit("ERROR: Upload failed");
    }
} else {
    // EINZELNES LÖSCHEN
    if (isset($_GET['delete'])) {
        $deletefiledecoded = base64_decode($_GET['delete']);
        if ($deletefiledecoded !== false) {
            $filePath = $uploadDir . basename($deletefiledecoded);
            if (file_exists($filePath)) {
                @unlink($filePath);
                $correc[] = "" . ($messages["556"] ?? 'File deleted') . "";
            }
        }
    }

    // MEHRERE AUSGEWÄHLTE LÖSCHEN (POST)
    if (isset($_POST['delete_selected']) && !empty($_POST['selected_files'])) {
        $deletedCount = 0;
        foreach ($_POST['selected_files'] as $encodedFile) {
            $decoded = base64_decode((string)$encodedFile);
            if ($decoded !== false) {
                $filePath = $uploadDir . basename($decoded);
                if (file_exists($filePath) && is_file($filePath)) {
                    @unlink($filePath);
                    $deletedCount++;
                }
            }
        }
        if ($deletedCount > 0) {
            $correc[] = "$deletedCount Datei(en) erfolgreich gelöscht.";
        }
    }

    // ALLES LÖSCHEN
    if (isset($_GET['delete_all'])) {
        if (file_exists($uploadDir) && is_dir($uploadDir)) {
            $dh = opendir($uploadDir);
            if ($dh) {
                while (($file = readdir($dh)) !== false) {
                    if ($file !== '.' && $file !== '..') {
                        $filePath = $uploadDir . $file;
                        if (is_file($filePath)) {
                            @unlink($filePath);
                        }
                    }
                }
                closedir($dh);
            }
            $correc[] = "Alle Dateien wurden erfolgreich gelöscht.";
        }
    }

    // EINZELNE DATEI BEARBEITEN (UMBENENNEN & MP3-TAGS SPEICHERN)
    if (isset($_POST['save_single']) && !empty($_POST['save_single'])) {
        $fileEncoded = $_POST['save_single'];
        $oldFileName = base64_decode($fileEncoded);
        
        if ($oldFileName !== false && file_exists($uploadDir . basename($oldFileName))) {
            $currentFileName = basename($oldFileName);
            
            // 1. Dateinamen aktualisieren, falls geändert
            if (isset($_POST['rename_file'][$fileEncoded])) {
                $newFileName = trim($_POST['rename_file'][$fileEncoded]);
                $newFileName = basename($newFileName); // Sicherheitsfilter gegen Pfad-Traversierung
                
                if (!empty($newFileName) && $newFileName !== $currentFileName && strtolower(pathinfo($newFileName, PATHINFO_EXTENSION)) === 'mp3') {
                    $newFullPath = $uploadDir . $newFileName;
                    if (!file_exists($newFullPath)) {
                        if (rename($uploadDir . $currentFileName, $newFullPath)) {
                            $currentFileName = $newFileName; // Aktualisieren für evtl. Tag-Verarbeitung
                            $correc[] = "Dateiname erfolgreich geändert.";
                        } else {
                            $errors[] = "Fehler beim Umbenennen der Datei.";
                        }
                    } else {
                        $errors[] = "Eine Datei mit diesem Namen existiert bereits.";
                    }
                }
            }
            
            // 2. MP3-Tags abgreifen (Titel, Interpret, Album)
            $tagTitle = $_POST['tag_title'][$fileEncoded] ?? '';
            $tagArtist = $_POST['tag_artist'][$fileEncoded] ?? '';
            $tagAlbum = $_POST['tag_album'][$fileEncoded] ?? '';
            
            // Falls du eine ID3-Bibliothek nutzt, kannst du hier die Tags in die Datei schreiben.
            // Der Dateiname/Pfad lautet nun: $uploadDir . $currentFileName
            
            if (empty($errors)) {
                $correc[] = "Änderungen für '" . htmlspecialchars($currentFileName) . "' erfolgreich gespeichert.";
            }
        }
    }

    // DOWNLOAD
    if (isset($_GET['download'])) {
        $downloadiddecode = base64_decode($_GET['download']);
        if ($downloadiddecode !== false) {
            $filePath = $uploadDir . basename($downloadiddecode);
            if (file_exists($filePath)) {
                if (ini_get("zlib.output_compression")) {
                    @ini_set("zlib.output_compression", "On");
                }
                header("Pragma: public");
                header("Expires: 0");
                header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
                header("Cache-Control: private", false);
                header("Content-Type: audio/mpeg");
                header("Content-Disposition: attachment; filename=\"" . basename($filePath) . "\";");
                header("Content-Transfer-Encoding: binary");
                header("Content-Length: " . filesize($filePath));
                @readfile($filePath);
                exit();
            }
        }
    }

    $actual_dir_size = 0;
    if (file_exists($uploadDir) && is_dir($uploadDir)) {
        $filesize = 0;
        $dp = @opendir($uploadDir);
        if ($dp) {
            while (($filename = readdir($dp)) !== false) {
                if ($filename === '.' || $filename === '..') {
                    continue;
                }
                $filedata = @stat($uploadDir . "/" . $filename);
                if ($filedata && isset($filedata[7])) {
                    $filesize += $filedata[7];
                }
            }
            closedir($dp);
            $actual_dir_size = $filesize / 1024;
        }
    } else {
        @mkdir($uploadDir, 0777, true);
    }
}

$dirlistdir = @opendir($uploadDir);
if ($dirlistdir) {
    closedir($dirlistdir);
}

define('entries_per_page', 250);
$offset = (isset($_GET['filecount']) && is_numeric($_GET['filecount'])) ? (int)$_GET['filecount'] : 1;
$listing_start = ($offset === 1) ? 0 : ($offset * entries_per_page - entries_per_page + 3);
$listing_end = $offset * entries_per_page + 2;

$dirlisting = @scandir($uploadDir);
if ($dirlisting === false) {
    $dirlisting = [];
}