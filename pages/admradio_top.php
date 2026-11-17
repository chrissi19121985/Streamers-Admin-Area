<?php
/**
 * Streamers Admin Panel - Admin Server Management Logic (Top)
 *
 * Modernisiert für PHP 8.x und MeekroDB
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

$action = $_GET['action'] ?? '';
$newParam = $_GET['new'] ?? '';
$viewId = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$manageId = isset($_GET['manage']) ? (int)$_GET['manage'] : 0;
$loginun = $_SESSION['username'] ?? '';

// Basisverzeichnis des Panels ermitteln
$baseDir = !empty($setting['dir_to_cpanel']) ? rtrim($setting['dir_to_cpanel'], '/') . '/' : getcwd() . '/';

// --- HILFSFUNKTION ZUM SCHREIBEN DER SC_SERV.CONF ---
$writeServerConfig = static function(array $serverData) use ($baseDir): string {
    $radioport = (int)($serverData['portbase'] ?? 0);
    if ($radioport <= 0) return '';

    $maxuserVal = (int)($serverData['maxuser'] ?? 32);

    // Kernparameter ganz an den Anfang schreiben (Pflicht für SHOUTcast v2)
    $ini_content  = "portbase=" . $radioport . "\n";
    $ini_content .= "maxuser=" . $maxuserVal . "\n";
    $ini_content .= "streammaxuser_1=" . $maxuserVal . "\n";
    $ini_content .= "streammaxuser_2=" . $maxuserVal . "\n";
    $ini_content .= "adminpassword=" . ($serverData['adminpassword'] ?? '') . "\n";
    $ini_content .= "password=" . ($serverData['password'] ?? '') . "\n";

    // Unlock-Key für SHOUTcast DNAS v2 mit übergeben (verhindert den 100-User-Fallback)
    if (!empty($serverData['unlockkeyname']) && !empty($serverData['unlockkeycode'])) {
        $ini_content .= "unlockkeyname=" . $serverData['unlockkeyname'] . "\n";
        $ini_content .= "unlockkeycode=" . $serverData['unlockkeycode'] . "\n";
    }

    $ini_content .= "streamid_1=1\n";
    $ini_content .= "streampath_1=" . ($serverData['streampath_1'] ?? '') . "\n";
    $ini_content .= "uvoxauth_1=" . ($serverData['uvoxauth_1'] ?? ($serverData['password'] ?? '')) . "\n";
    $ini_content .= "streamid_2=2\n";
    $ini_content .= "streampath_2=" . ($serverData['streampath_2'] ?? '') . "\n";
    $ini_content .= "uvoxauth_2=" . ($serverData['uvoxauth_2'] ?? ($serverData['password'] ?? '')) . "\n";

    // Restliche Einstellungen anhängen (Duplikate & interne Felder ausschließen)
    $excluded = [
        'id', 'owner', 'portbase', 'maxuser', 'adminpassword', 'password', 'streamid_1', 'streamid_2', 
        'streampath_1', 'streampath_2', 'uvoxauth_1', 'uvoxauth_2', 'bitrate_1', 'bitrate_2', 'tchlog', 'yp2', 
        'djcapture', 'sitepublic', 'suspended', 'abuse', 'pid', 'autopid', 'webspace', 'serverip_1', 
        'serverport_1', 'serverip_2', 'serverport_2', 'streamtitle', 'streamurl', 'shuffle', 
        'samplerate_1', 'channels_1', 'samplerate_2', 'channels_2', 'genre', 'public', 'aim', 
        'icq', 'irc', 'encoder_1', 'encoder_2', 'mp3quality', 'mp3mode', 'calendarrewrite', 
        'calendarfile', 'outprotocol_1', 'outprotocol_2', 'screenlog', 'xfade', 'xfadethreshol', 
        'uvoxstreamid_1', 'uvoxstreamid_2', 'log', 'displaymetadatapattern_1', 'displaymetadatapattern_2', 
        'useMetadata_1', 'useMetadata_2', 'uvoxradiometadata_1', 'uvoxradiometadata_2', 'uvoxnewmetadata_1', 
        'uvoxnewmetadata_2', 'unlockkeyname', 'unlockkeycode', 'djport_1', 'djlogin_1', 'djpassword_1'
    ];

    foreach ($serverData as $field => $value) {
        if (!is_numeric((string)$field) && $value !== "" && !in_array($field, $excluded, true)) {
            $ini_content .= $field . "=" . $value . "\n";
        }
    }

    $filename = $baseDir . "temp/" . $radioport . "/conf/sc_serv.conf";
    @mkdir(dirname($filename), 0777, true);
    $handle = @fopen($filename, 'w');
    if ($handle) {
        @fwrite($handle, $ini_content);
        @fclose($handle);
        @chmod($filename, 0777);
    }

    return $filename;
};

// --- NEUEN SERVER ANLEGEN ---
if ($newParam === "server" && !empty($_POST)) {
    $postPortBase = isset($_POST['portbase']) ? (int)$_POST['portbase'] : 0;
    
    try {
        $portExistCount = DB::queryFirstField("SELECT COUNT(*) FROM servers WHERE portbase=%i", $postPortBase);
        if ($portExistCount > 0) {
            $nextPort = (int)DB::queryFirstField("SELECT portbase FROM servers ORDER BY portbase DESC LIMIT 1") + 2;
            $notifi[] = "<h2>Port " . $postPortBase . " " . ($messages["169"] ?? 'already exists, using new port') . ": " . $nextPort . "</h2>";
            $postPortBase = $nextPort;
            $_POST['portbase'] = $nextPort;
        }

        if ($postPortBase > 0) {
            $webspaceVal = isset($_POST['webspace']) ? ((int)$_POST['webspace'] * 1024) : 20480;
            
            $owner = $_POST['owner'] ?? $loginun;
            $maxuser = (int)($_POST['maxuser'] ?? 32);
            $bitrate_1 = (int)($_POST['bitrate_1'] ?? 320000);
            $bitrate_2 = (int)($_POST['bitrate_2'] ?? 64000);
            $adminpassword = $_POST['adminpassword'] ?? '';
            $password = $_POST['password'] ?? '';
            $uvoxauth_1 = $_POST['uvoxauth_1'] ?? '';
            $uvoxauth_2 = $_POST['uvoxauth_2'] ?? '';
            $streamauthhash_1 = $_POST['streamauthhash_1'] ?? '';
            $streamauthhash_2 = $_POST['streamauthhash_2'] ?? '';
            $djport_1 = (int)($_POST['djport_1'] ?? 6000);
            $sitepublic = (int)($_POST['sitepublic'] ?? 1);
            $screenlog = (int)($_POST['screenlog'] ?? 0);
            $songhistory = (int)($_POST['songhistory'] ?? 10);
            $tchlog = (int)($_POST['tchlog'] ?? 0);
            $w3cenable = (int)($_POST['w3cenable'] ?? 0);
            $outprotocol_1 = (int)($_POST['outprotocol_1'] ?? 3);
            $outprotocol_2 = (int)($_POST['outprotocol_2'] ?? 3);
            $srcip = $_POST['srcip'] ?? 'ANY';
            $destip = $_POST['destip'] ?? 'ANY';
            $yport = (int)($_POST['yport'] ?? 80);
            $namelookups = (int)($_POST['namelookups'] ?? 0);
            $relayport = (int)($_POST['relayport'] ?? 0);
            $relayserver = $_POST['relayserver'] ?? '';
            $autodumpusers = (int)($_POST['autodumpusers'] ?? 0);
            $autodumptime = (int)($_POST['autodumptime'] ?? 30);
            $contentdir = $_POST['contentdir'] ?? '';
            $streamintrofile = $_POST['streamintrofile'] ?? '';
            $titleformat = $_POST['titleformat'] ?? 'Radio Station Name - %s';
            $urlformat = $_POST['urlformat'] ?? '';
            $publicserver = $_POST['publicserver'] ?? 'default';
            $allowrelay = (int)($_POST['allowrelay'] ?? 1);
            $allowpublicrelay = (int)($_POST['allowpublicrelay'] ?? 0);
            $metainterval = (int)($_POST['metainterval'] ?? 8192);
            $autopid = (int)($_POST['autopid'] ?? 0);

            $streampath_1 = $baseDir . "temp/" . $postPortBase . "/stream/1";
            $streampath_2 = $baseDir . "temp/" . $postPortBase . "/stream/2";
            $logfile = $baseDir . "temp/" . $postPortBase . "/logs/sc_" . $postPortBase . ".log";
            $w3clog = $baseDir . "temp/" . $postPortBase . "/logs/w3c/sc_" . $postPortBase . ".log";
            $banfile = $baseDir . "temp/" . $postPortBase . "/logs/banfile.ban";
            $ripfile = $baseDir . "temp/" . $postPortBase . "/logs/ripfile.rip";
            $djbroadcasts = $baseDir . "temp/" . $postPortBase . "/recorded";
            $calendarfile = $baseDir . "temp/" . $postPortBase . "/calendar/calendar.xml";

            DB::query(
                "INSERT INTO servers (
                    owner, maxuser, portbase, bitrate_1, bitrate_2, adminpassword, password, 
                    uvoxauth_1, uvoxauth_2, streamauthhash_1, streampath_1, streamauthhash_2, 
                    streampath_2, djport_1, sitepublic, logfile, screenlog, songhistory, 
                    tchlog, w3cenable, w3clog, banfile, ripfile, outprotocol_1, outprotocol_2, 
                    srcip, destip, yport, namelookups, relayport, relayserver, autodumpusers, 
                    autodumptime, contentdir, streamintrofile, titleformat, urlformat, 
                    publicserver, allowrelay, allowpublicrelay, metainterval, webspace, 
                    serverip_1, serverip_2, serverport_1, serverport_2, streamtitle, 
                    streamurl, shuffle, samplerate_1, channels_1, genre, public, autopid, 
                    djbroadcasts, calendarfile
                ) VALUES (
                    %s, %i, %i, %i, %i, %s, %s, %s, %s, %s, %s, %s, %s, %i, %i, %s, %i, %i, 
                    %i, %i, %s, %s, %s, %i, %i, %s, %s, %i, %i, %i, %s, %i, %i, %s, %s, %s, 
                    %s, %s, %i, %i, %i, %i, %s, %s, %i, %i, %s, %s, %s, %s, %s, %s, %s, %i, %s, %s
                )",
                $owner, $maxuser, $postPortBase, $bitrate_1, $bitrate_2, $adminpassword, $password,
                $uvoxauth_1, $uvoxauth_2, $streamauthhash_1, $streampath_1, $streamauthhash_2,
                $streampath_2, $djport_1, $sitepublic, $logfile, $screenlog, $songhistory,
                $tchlog, $w3cenable, $w3clog, $banfile, $ripfile, $outprotocol_1, $outprotocol_2,
                $srcip, $destip, $yport, $namelookups, $relayport, $relayserver, $autodumpusers,
                $autodumptime, $contentdir, $streamintrofile, $titleformat, $urlformat,
                $publicserver, $allowrelay, $allowpublicrelay, $metainterval, $webspaceVal,
                '127.0.0.1', '127.0.0.1', $postPortBase, $postPortBase, 'The Best Radio',
                'http://' . ($setting['host_add'] ?? 'localhost'), '1', '44100', '2', 'PoP', 'default',
                $autopid, $djbroadcasts, $calendarfile
            );

            $old = umask(0);
            @mkdir($baseDir . "uploads/" . $postPortBase, 0777, true);
            @mkdir($baseDir . "temp/" . $postPortBase . "/conf", 0777, true);
            @mkdir($baseDir . "temp/" . $postPortBase . "/recorded", 0777, true);
            @mkdir($baseDir . "temp/" . $postPortBase . "/logs/w3c", 0777, true);
            @mkdir($baseDir . "temp/" . $postPortBase . "/calendar", 0777, true);
            @mkdir($baseDir . "temp/" . $postPortBase . "/stream/1", 0777, true);
            @mkdir($baseDir . "temp/" . $postPortBase . "/stream/2", 0777, true);
            sleep(1);
            @mkdir($baseDir . "temp/" . $postPortBase . "/playlist", 0777, true);
            umask($old);

            $newServerData = DB::queryFirstRow("SELECT * FROM servers WHERE portbase=%i", $postPortBase);
            if ($newServerData) {
                $writeServerConfig($newServerData);
            }

            $correc[] = "" . ($messages["170"] ?? 'Server created successfully') . "";
        }
    } catch (Exception $e) {
        $errors[] = "" . ($messages["171"] ?? 'Could not create server') . ": " . $e->getMessage() . "";
    }
}

// --- DATEIEN FÜR LOGS UND PLAYLISTS SCHREIBEN ---
$portBaseForFiles = isset($_POST['portbase']) ? (int)$_POST['portbase'] : 0;
if ($portBaseForFiles > 0) {
    if (($setting['os'] ?? '') === 'linux') {
        $fileban = $baseDir . "temp/" . $portBaseForFiles . "/logs/banfile.ban";
        @mkdir(dirname($fileban), 0777, true);
        @chmod($fileban, 0777);
        $gzp = @fopen($fileban, "a");
        if ($gzp) {
            @fwrite($gzp, $ban ?? '');
            @fclose($gzp);
        }

        $filerip = $baseDir . "temp/" . $portBaseForFiles . "/logs/ripfile.rip";
        @mkdir(dirname($filerip), 0777, true);
        @chmod($filerip, 0777);
        $gzp2 = @fopen($filerip, "a");
        if ($gzp2) {
            @fwrite($gzp2, $rip ?? '');
            @fclose($gzp2);
        }

        $filepls1 = $baseDir . "temp/" . $portBaseForFiles . "/playlist/demoplaylist.lst";
        @mkdir(dirname($filepls1), 0777, true);
        $demoContent = $baseDir . "demo/Kalimba.mp3\r\n" . $baseDir . "demo/Sleep_Away.mp3\r\n";
        @chmod($filepls1, 0777);
        $gzd = @fopen($filepls1, "a");
        if ($gzd) {
            @fwrite($gzd, $demoContent);
            @fclose($gzd);
        }

        $filepls = $baseDir . "temp/" . $portBaseForFiles . "/playlist/record.lst";
        if (file_exists($filepls)) {
            @unlink($filepls);
        }
        $plsContent = $baseDir . "temp/" . $portBaseForFiles . "/recorded/*.mp3\r\n";
        @chmod($filepls, 0777);
        $gzp3 = @fopen($filepls, "a");
        if ($gzp3) {
            @fwrite($gzp3, $plsContent);
            @fclose($gzp3);
        }
    }
}

// --- UPDATE ÜBER "new=update" ODER "manage" ---
if (($newParam === "update" || $manageId > 0) && !empty($_POST)) {
    $targetId = ($viewId > 0) ? $viewId : $manageId;
    
    if ($targetId > 0) {
        try {
            $owner = $_POST['owner'] ?? $loginun;
            $adminpassword = $_POST['adminpassword'] ?? '';
            $password = $_POST['password'] ?? '';
            $uvoxauth_1 = $_POST['uvoxauth_1'] ?? '';
            $uvoxauth_2 = $_POST['uvoxauth_2'] ?? '';
            $streamauthhash_1 = $_POST['streamauthhash_1'] ?? '';
            $streamauthhash_2 = $_POST['streamauthhash_2'] ?? '';
            $djport_1 = (int)($_POST['djport_1'] ?? 6000);
            $portbase = (int)($_POST['portbase'] ?? 9000);
            $maxuser = (int)($_POST['maxuser'] ?? 32);
            $bitrate_1 = (int)($_POST['bitrate_1'] ?? 320000);
            $bitrate_2 = (int)($_POST['bitrate_2'] ?? 64000);
            $sitepublic = (int)($_POST['sitepublic'] ?? 1);
            $autopid = (int)($_POST['autopid'] ?? 0);
            $webspace = isset($_POST['webspace']) ? ((int)$_POST['webspace'] * 1024) : 20480;
            $screenlog = (int)($_POST['screenlog'] ?? 0);
            $songhistory = (int)($_POST['songhistory'] ?? 10);
            $tchlog = (int)($_POST['tchlog'] ?? 0);
            $w3cenable = (int)($_POST['w3cenable'] ?? 0);
            $srcip = $_POST['srcip'] ?? 'ANY';
            $destip = $_POST['destip'] ?? 'ANY';
            $outprotocol_1 = (int)($_POST['outprotocol_1'] ?? 3);
            $outprotocol_2 = (int)($_POST['outprotocol_2'] ?? 3);
            $yport = (int)($_POST['yport'] ?? 80);
            $namelookups = (int)($_POST['namelookups'] ?? 0);
            $relayport = (int)($_POST['relayport'] ?? 0);
            $relayserver = $_POST['relayserver'] ?? '';
            $autodumpusers = (int)($_POST['autodumpusers'] ?? 0);
            $autodumptime = (int)($_POST['autodumptime'] ?? 30);
            $contentdir = $_POST['contentdir'] ?? '';
            $streamintrofile = $_POST['streamintrofile'] ?? '';
            $titleformat = $_POST['titleformat'] ?? 'Radio Station Name - %s';
            $urlformat = $_POST['urlformat'] ?? '';
            $publicserver = $_POST['publicserver'] ?? 'default';
            $allowrelay = (int)($_POST['allowrelay'] ?? 1);
            $allowpublicrelay = (int)($_POST['allowpublicrelay'] ?? 0);
            $metainterval = (int)($_POST['metainterval'] ?? 8192);

            DB::query(
                "UPDATE servers SET 
                    owner=%s, adminpassword=%s, password=%s, uvoxauth_1=%s, uvoxauth_2=%s, 
                    streamauthhash_1=%s, streamauthhash_2=%s, djport_1=%i, portbase=%i, 
                    maxuser=%i, bitrate_1=%i, bitrate_2=%i, sitepublic=%i, autopid=%i, 
                    webspace=%i, screenlog=%i, songhistory=%i, tchlog=%i, w3cenable=%i, 
                    srcip=%s, destip=%s, outprotocol_1=%i, outprotocol_2=%i, yport=%i, 
                    namelookups=%i, relayport=%i, relayserver=%s, autodumpusers=%i, 
                    autodumptime=%i, contentdir=%s, streamintrofile=%s, titleformat=%s, 
                    urlformat=%s, publicserver=%s, allowrelay=%i, allowpublicrelay=%i, 
                    metainterval=%i 
                WHERE id=%i",
                $owner, $adminpassword, $password, $uvoxauth_1, $uvoxauth_2,
                $streamauthhash_1, $streamauthhash_2, $djport_1, $portbase,
                $maxuser, $bitrate_1, $bitrate_2, $sitepublic, $autopid,
                $webspace, $screenlog, $songhistory, $tchlog, $w3cenable,
                $srcip, $destip, $outprotocol_1, $outprotocol_2, $yport,
                $namelookups, $relayport, $relayserver, $autodumpusers,
                $autodumptime, $contentdir, $streamintrofile, $titleformat,
                $urlformat, $publicserver, $allowrelay, $allowpublicrelay,
                $metainterval, $targetId
            );

            $updatedServerData = DB::queryFirstRow("SELECT * FROM servers WHERE id=%i", $targetId);
            if ($updatedServerData) {
                $writeServerConfig($updatedServerData);
            }

            $correc[] = "<h2>" . ($messages["173"] ?? ($messages["522"] ?? 'Server updated')) . "</h2>";
        } catch (Exception $e) {
            $errors[] = "<h2>" . ($messages["174"] ?? ($messages["523"] ?? 'Could not update server')) . ": " . $e->getMessage() . "</h2>";
        }
    }
}

// --- SERVER START / STOP / DELETE AKTIONEN ---
if ($viewId > 0 && !empty($action) && $action !== 'update') {
    try {
        $serverData = DB::queryFirstRow("SELECT * FROM servers WHERE id=%i", $viewId);
        if ($serverData) {
            $radioport = (int)($serverData['portbase'] ?? 0);
            $pidVal = $serverData['pid'] ?? '';

            if ($action === "start") {
                if (!$radioport) {
                    $errors[] = "<h2>" . ($messages["175"] ?? 'Port not found') . "</h2>";
                } else {
                    if (($setting["os"] ?? '') === "linux") {
                        if (!empty($pidVal)) {
                            @shell_exec("kill -9 " . (int)$pidVal);
                        }
                        @shell_exec("pkill -9 -f " . escapeshellarg("sc_serv.*" . $radioport));
                        sleep(1);
                    }

                    // Konfigurationsdatei vor dem Start frisch in das korrekte Panel-Verzeichnis schreiben
                    $filename = $writeServerConfig($serverData);
                    $newPid = '';

                    if (($setting['os'] ?? '') === 'linux') {
                        $cmd = "cd " . escapeshellarg($baseDir . "files/linux/") . " && ./sc_serv.bin " . escapeshellarg($filename) . " > /dev/null 2>&1 &";
                        shell_exec($cmd);
                        sleep(1);
                        $newPid = trim((string)shell_exec("pgrep -f " . escapeshellarg("sc_serv.*" . $radioport)));

                        if ($newPid === '') {
                            DB::insert('notices', [
                                'username' => $loginun,
                                'reason' => 'Server failure',
                                'message' => 'The server with id ' . $viewId . ' cannot start on port ' . $serverData['portbase'],
                                'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
                            ]);
                            $errors[] = "<h2>" . ($messages["517"] ?? 'Server start failed') . "</h2>";
                        }
                    }

                    DB::update('servers', ['pid' => $newPid], 'id=%i', $viewId);
                    $correc[] = "<h2>" . ($messages["518"] ?? 'Server started') . "</h2>";
                    
                    if (($setting["scs_config"] ?? '') === "0" && $filename !== '') {
                        @unlink($filename);
                    }
                }
            } elseif ($action === "stop") {
                if (($setting["os"] ?? '') === "linux") {
                    if (!empty($pidVal)) {
                        @shell_exec("kill -9 " . (int)$pidVal);
                    }
                    if ($radioport > 0) {
                        @shell_exec("pkill -9 -f " . escapeshellarg("sc_serv.*" . $radioport));
                    }
                    sleep(1);
                }
                DB::update('servers', ['pid' => ''], 'id=%i', $viewId);
                $notifi[] = "<h2>" . ($messages["521"] ?? 'Server stopped') . "</h2>";
            } elseif ($action === "delete") {
                if (($setting["os"] ?? '') === "linux") {
                    if (!empty($pidVal)) {
                        @shell_exec("kill -9 " . (int)$pidVal);
                    }
                    if ($radioport > 0) {
                        @shell_exec("pkill -9 -f " . escapeshellarg("sc_serv.*" . $radioport));
                    }
                    sleep(1);
                }

                $unlinkPort = $radioport;
                if ($unlinkPort > 0) {
                    $helperDeleteDir = static function($dirname) use (&$helperDeleteDir) {
                        if (is_dir($dirname)) {
                            $dirHandle = @opendir($dirname);
                            if ($dirHandle) {
                                while (($file = readdir($dirHandle)) !== false) {
                                    if ($file !== "." && $file !== "..") {
                                        $targetPath = $dirname . "/" . $file;
                                        if (!is_dir($targetPath)) {
                                            @unlink($targetPath);
                                        } else {
                                            $helperDeleteDir($targetPath);
                                        }
                                    }
                                }
                                @closedir($dirHandle);
                                @rmdir($dirname);
                            }
                        }
                    };

                    $helperDeleteDir($baseDir . "uploads/" . $unlinkPort . "/");
                    $helperDeleteDir($baseDir . "temp/" . $unlinkPort . "/playlist/");
                    $helperDeleteDir($baseDir . "temp/" . $unlinkPort . "/");

                    try {
                        DB::delete('servers', 'id=%i', $viewId);
                        $correc[] = "" . ($messages["184"] ?? 'Server deleted') . "";
                    } catch (Exception $e) {
                        $errors[] = "" . ($messages["185"] ?? 'Could not delete server') . "";
                    }
                }
            }
        }
    } catch (Exception $e) {
        $errors[] = "<h2>Database error during server action</h2>";
    }
}

// Pagination & Listenabfrage
$limit = isset($setting['display_limit']) ? (int)$setting['display_limit'] : 10;
$p = 0;
if (isset($_GET['p']) && is_numeric($_GET['p'])) {
    $p = max(0, (int)$_GET['p'] * $limit);
}
$l = $p + $limit;

try {
    $listq = DB::query("SELECT * FROM servers ORDER BY id ASC LIMIT %i, %i", $p, $limit);
} catch (Exception $e) {
    $listq = [];
}

if ($action === "update" && $viewId > 0) {
    try {
        $updateget_data = DB::queryFirstRow("SELECT * FROM servers WHERE id=%i", $viewId);
        if (!$updateget_data) {
            $errors[] = "<h2>" . ($messages["186"] ?? 'Server not found') . "</h2>";
            header('Location: edit_serv.php');
            exit();
        }
    } catch (Exception $e) {
        // Fehler abfangen
    }
}