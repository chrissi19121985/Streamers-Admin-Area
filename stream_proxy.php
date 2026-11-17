<?php
/**
 * Mini Stream Proxy für HTTPS-Browserkompatibilität
 */
$port = isset($_GET['port']) ? (int)$_GET['port'] : 0;
if ($port <= 1024 || $port > 65535) {
    exit('Invalid port');
}

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
// Hostname ohne Port extrahieren falls nötig
if (strpos($host, ':') !== false) {
    $host = explode(':', $host)[0];
}

$streamUrl = "http://{$host}:{$port}/stream";

// Header für Audio-Streaming setzen
header('Content-Type: audio/mpeg');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-Accel-Buffering: no'); // Deaktiviert Nginx-Buffering falls vorhanden

$fp = fopen($streamUrl, 'rb');
if ($fp) {
    while (!feof($fp)) {
        echo fread($fp, 8192);
        flush();
        if (connection_status() !== CONNECTION_NORMAL) {
            break;
        }
    }
    fclose($fp);
}