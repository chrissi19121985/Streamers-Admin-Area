<?php
/**
 * PHP Real Ajax Uploader - Modernisiert für PHP 8.x
 * Ursprünglich von Alban Xhaferllari
 * @see original[cite: 1]
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

// Parameter sicher abrufen mit dem Null-Coalescing-Operator
$uploadPath   = $_REQUEST['ax-file-path'] ?? '';
$fileName     = $_REQUEST['ax-file-name'] ?? '';
$currByte     = isset($_REQUEST['ax-start-byte']) ? (int)$_REQUEST['ax-start-byte'] : 0;
$maxFileSize  = $_REQUEST['ax-maxFileSize'] ?? '';
$html5fsize   = isset($_REQUEST['ax-fileSize']) ? (int)$_REQUEST['ax-fileSize'] : 0;
$isLast       = $_REQUEST['isLast'] ?? 'false';

// Thumbnail-Optionen
$thumbHeight  = isset($_REQUEST['ax-thumbHeight']) ? (int)$_REQUEST['ax-thumbHeight'] : 0;
$thumbWidth   = isset($_REQUEST['ax-thumbWidth']) ? (int)$_REQUEST['ax-thumbWidth'] : 0;
$thumbPostfix = $_REQUEST['ax-thumbPostfix'] ?? '';
$thumbPath    = $_REQUEST['ax-thumbPath'] ?? '';
$thumbFormat  = $_REQUEST['ax-thumbFormat'] ?? 'jpg';

$allowExt     = empty($_REQUEST['ax-allow-ext']) ? [] : explode('|', $_REQUEST['ax-allow-ext']);

// Pfade normalisieren und Verzeichnisse erstellen
if (!empty($uploadPath)) {
    $uploadPath = rtrim($uploadPath, '/\\') . DIRECTORY_SEPARATOR;
    if (!file_exists($uploadPath)) {
        mkdir($uploadPath, 0777, true);
    }
}

if (!empty($thumbPath)) {
    $thumbPath = rtrim($thumbPath, '/\\') . DIRECTORY_SEPARATOR;
    if (!file_exists($thumbPath)) {
        mkdir($thumbPath, 0777, true);
    }
}

/**
 * Erstellt ein Thumbnail über die GD Library.
 */
function createThumbGD(string $filepath, string $thumbPath, string $postfix, int $maxwidth, int $maxheight, string $format = 'jpg', int $quality = 75) 
{
    if ($maxwidth <= 0 && $maxheight <= 0) {
        return 'No valid width and height given';
    }

    $gd_formats = ['jpg', 'jpeg', 'png', 'gif'];
    $fileInfo = pathinfo($filepath);
    $fileExt = strtolower($fileInfo['extension'] ?? '');

    if (empty($format)) {
        $format = $fileExt;
    }

    if (!in_array($fileExt, $gd_formats, true)) {
        return false;
    }

    $thumbName = ($fileInfo['filename'] ?? 'thumb') . $postfix . '.' . $format;

    if (empty($thumbPath)) {
        $thumbPath = $fileInfo['dirname'] . DIRECTORY_SEPARATOR;
    } else {
        $thumbPath = rtrim($thumbPath, '/\\') . DIRECTORY_SEPARATOR;
    }

    $imageSize = @getimagesize($filepath);
    if ($imageSize === false) {
        return false;
    }

    list($width_orig, $height_orig) = $imageSize;
    if ($width_orig > 0 && $height_orig > 0) {
        $ratioX = $maxwidth > 0 ? $maxwidth / $width_orig : 1;
        $ratioY = $maxheight > 0 ? $maxheight / $height_orig : 1;
        $ratio = min($ratioX, $ratioY);
        if ($ratio == 0) {
            $ratio = max($ratioX, $ratioY);
        }
        $newW = (int)round($width_orig * $ratio);
        $newH = (int)round($height_orig * $ratio);

        $thumb = imagecreatetruecolor($newW, $newH);
        $fileContent = file_get_contents($filepath);
        if ($fileContent === false) {
            if ($thumb) imagedestroy($thumb);
            return false;
        }
        
        $image = imagecreatefromstring($fileContent);
        if (!$image || !$thumb) {
            if ($image) imagedestroy($image);
            if ($thumb) imagedestroy($thumb);
            return false;
        }

        // Transparenz für PNG/GIF bewahren
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);

        imagecopyresampled($thumb, $image, 0, 0, 0, 0, $newW, $newH, $width_orig, $height_orig);

        switch (strtolower($format)) {
            case 'png':
                imagepng($thumb, $thumbPath . $thumbName, 9);
                break;
            case 'gif':
                imagegif($thumb, $thumbPath . $thumbName);
                break;
            default:
                imagejpeg($thumb, $thumbPath . $thumbName, $quality);
                break;
        }
        imagedestroy($image);
        imagedestroy($thumb);
        return true;
    }
    return false;
}

/**
 * Erstellt ein Thumbnail über ImageMagick (Fehler im Original behoben).
 */
function createThumbIM(string $filepath, string $thumbPath, string $postfix, int $maxwidth, int $maxheight, string $format) 
{
    if (!class_exists('Imagick')) {
        return false;
    }

    $fileInfo = pathinfo($filepath);
    $thumbName = ($fileInfo['filename'] ?? 'thumb') . $postfix . '.' . $format;

    if (empty($thumbPath)) {
        $thumbPath = $fileInfo['dirname'] . DIRECTORY_SEPARATOR;
    } else {
        $thumbPath = rtrim($thumbPath, '/\\') . DIRECTORY_SEPARATOR;
    }

    try {
        $image = new Imagick($filepath);
        $image->thumbnailImage($maxwidth, $maxheight);
        $image->writeImage($thumbPath . $thumbName); // Korrigiert von $images->writeImages
        $image->clear();
        $image->destroy();
        return true;
    } catch (\Exception $e) {
        return false;
    }
}

/**
 * Überprüft und bereinigt den Dateinamen.
 */
function checkFilename(string $fileName, int $size, string $uploadPath, string $maxFileSize, array $allowExt) 
{
    $maxSize = 4 * 1024 * 1024; // Default 4 MB
    if (!empty($maxFileSize)) {
        $maxsize_regex = preg_match('/^(?P<size>[\d]+)(?P<rang>[a-z]{0,1})$/i', $maxFileSize, $match);
        if ($maxsize_regex && isset($match['size']) && is_numeric($match['size'])) {
            $val = (int)$match['size'];
            switch (strtoupper($match['rang'] ?? '')) {
                case 'K': $maxSize = $val * 1024; break;
                case 'M': $maxSize = $val * 1024 * 1024; break;
                case 'G': $maxSize = $val * 1024 * 1024 * 1024; break;
                case 'T': $maxSize = $val * 1024 * 1024 * 1024 * 1024; break;
                default:  $maxSize = $val;
            }
        }
    }

    if ($size > $maxSize) {
        echo json_encode(['name' => $fileName, 'size' => $size, 'status' => 'error', 'info' => 'File size not allowed.']);
        return false;
    }

    $windowsReserved = [
        'CON', 'PRN', 'AUX', 'NUL', 'COM1', 'COM2', 'COM3', 'COM4', 
        'COM5', 'COM6', 'COM7', 'COM8', 'COM9', 'LPT1', 'LPT2', 'LPT3', 
        'LPT4', 'LPT5', 'LPT6', 'LPT7', 'LPT8', 'LPT9'
    ];
    $badWinChars = array_merge(array_map('chr', range(0, 31)), ['<', '>', ':', '"', '/', '\\', '|', '?', '*']);

    $fileName = str_replace($badWinChars, '', $fileName);
    $fileInfo = pathinfo($fileName);
    $fileExt = strtolower($fileInfo['extension'] ?? '');
    $fileBase = $fileInfo['filename'] ?? 'file';

    if (in_array(strtoupper($fileName), $windowsReserved, true)) {
        echo json_encode(['name' => $fileName, 'size' => 0, 'status' => 'error', 'info' => 'File name not allowed. Windows reserved.']);
        return false;
    }

    if (!empty($allowExt) && !in_array($fileExt, $allowExt, true)) {
        echo json_encode(['name' => $fileName, 'size' => 0, 'status' => 'error', 'info' => "Extension [$fileExt] not allowed."]);
        return false;
    }

    $fullPath = $uploadPath . $fileName;
    $c = 0;
    while (file_exists($fullPath)) {
        $c++;
        $fileName = $fileBase . "($c)." . $fileExt;
        $fullPath = $uploadPath . $fileName;
    }
    return $fullPath;
}

// Hauptlogik: Normaler Upload oder Chunked Upload
if (isset($_FILES['ax-files'])) {
    foreach ($_FILES['ax-files']['error'] as $key => $error) {
        if ($error === UPLOAD_ERR_OK) {
            $tmpName  = $_FILES['ax-files']['tmp_name'][$key] ?? '';
            $origName = $_FILES['ax-files']['name'][$key] ?? 'unknown';
            $fileSize = $_FILES['ax-files']['size'][$key] ?? 0;

            $newName = !empty($fileName) ? $fileName : $origName;
            $fullPath = checkFilename($newName, (int)$fileSize, $uploadPath, $maxFileSize, $allowExt);

            if ($fullPath && move_uploaded_file($tmpName, $fullPath)) {
                if (!empty($thumbWidth) || !empty($thumbHeight)) {
                    createThumbGD($fullPath, $thumbPath, $thumbPostfix, $thumbWidth, $thumbHeight, $thumbFormat);
                }
                echo json_encode([
                    'name'   => basename($fullPath), 
                    'size'   => filesize($fullPath), 
                    'status' => 'uploaded', 
                    'info'   => 'File uploaded'
                ]);
            }
        } else {
            $origName = $_FILES['ax-files']['name'][$key] ?? 'unknown';
            $fileSize = $_FILES['ax-files']['size'][$key] ?? 0;
            echo json_encode([
                'name'   => basename($origName), 
                'size'   => (int)$fileSize, 
                'status' => 'error', 
                'info'   => $error
            ]);
        }
    }
} elseif (!empty($fileName)) {
    // Chunked Upload via php://input
    $fullPath = ($currByte !== 0) ? $uploadPath . $fileName : checkFilename($fileName, $html5fsize, $uploadPath, $maxFileSize, $allowExt);

    if ($fullPath) {
        $flag = ($currByte === 0) ? 0 : FILE_APPEND;
        $receivedBytes = file_get_contents('php://input');

        if ($receivedBytes !== false) {
            $attempts = 0;
            while (@file_put_contents($fullPath, $receivedBytes, $flag) === false && $attempts < 10) {
                $attempts++;
                usleep(50);
            }

            if ($isLast === 'true' && (!empty($thumbWidth) || !empty($thumbHeight))) {
                createThumbGD($fullPath, $thumbPath, $thumbPostfix, $thumbWidth, $thumbHeight, $thumbFormat);
            }

            echo json_encode([
                'name'   => basename($fullPath), 
                'size'   => $currByte, 
                'status' => 'uploaded', 
                'info'   => 'File/chunk uploaded'
            ]);
        } else {
            echo json_encode([
                'name'   => basename($fullPath), 
                'size'   => $currByte, 
                'status' => 'error', 
                'info'   => 'Failed to read input stream'
            ]);
        }
    }
}