<?php
/**
 * Streamers Admin Panel - AutoDJ Management Logic (Top)
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

$loginun = $_SESSION['username'] ?? '';
$limit = isset($setting['display_limit']) ? (int)$setting['display_limit'] : 10;
$p = 0;
if (isset($_GET['p'])) {
    $p = max(0, (int)$_GET['p'] * $limit);
}
$l = $p + $limit;

try {
    $select = DB::query("SELECT * FROM servers WHERE owner=%s ORDER BY id ASC LIMIT %i, %i", $loginun, $p, $limit);
} catch (Exception $e) {
    $select = [];
}

$action = $_GET['action'] ?? '';
$serverId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// --- SERVER-ID SPEZIFISCHE AKTIONEN ---
if ($serverId > 0) {
    try {
        $serverDataCheck = DB::queryFirstRow("SELECT portbase, autopid FROM servers WHERE id=%i AND owner=%s", $serverId, $loginun);
        $radioport = $serverDataCheck['portbase'] ?? 0;

        if (!$radioport) {
            header('Location: content.php?include=music&error=sc_trans_access');
            exit();
        }

        if ($action === "start" || $action === "restart") {
            $hostAdd = $setting['host_add'] ?? 'localhost';
            $portNum = (int)$radioport;
            
            $postPlList = $_POST['pllist'] ?? null;

            $serverdata = DB::queryFirstRow("SELECT * FROM servers WHERE id=%i AND portbase=%i", $serverId, $portNum);
            if ($serverdata) {
                $dirToCpanel = $setting['dir_to_cpanel'] ?? '';
                $safePlaylist = strip_tags(str_replace(['/', '\\'], '', (string)($postPlList ?? 'default.lst')));
                
                $logDir = rtrim($dirToCpanel, '/') . '/temp/' . $portNum . '/logs/';
                @mkdir($logDir, 0777, true);
                $logFileErr = $logDir . 'sc_trans_error.log';
                $scTransLog = $logDir . 'sc_trans.log';

                // --- 1. CALENDAR.XML GENERIEREN ---
                $calDir = rtrim($dirToCpanel, '/') . '/temp/' . $portNum . '/calendar/';
                @mkdir($calDir, 0777, true);
                $calFile = $calDir . 'calendar.xml';
                
                $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\r\n";
                $xml .= "<eventlist>\r\n";
                for ($j = 1; $j <= 30; $j++) {
                    $loginVar = $serverdata['djlogin_' . $j] ?? '';
                    $prioVar = $serverdata['djpriority_' . $j] ?? '1';
                    if (!empty($loginVar)) {
                        $xml .= "<event type=\"dj\">\r\n";
                        $xml .= "<dj archive=\"" . htmlspecialchars((string)$prioVar, ENT_QUOTES, 'UTF-8') . "\">" . htmlspecialchars((string)$loginVar, ENT_QUOTES, 'UTF-8') . "</dj>\r\n";
                        $xml .= "<calendar />\r\n";
                        $xml .= "</event>\r\n";
                    }
                }
                $xml .= "</eventlist>";
                @file_put_contents($calFile, $xml);
                @chmod($calFile, 0777);

                // --- WERTE AUS DER DATENBANK HOLEN ---
                $dbBitrate = $serverdata['bitrate_1'] ?? '320000';
                $dbSamplerate = $serverdata['samplerate_1'] ?? '44100';
                $dbChannels = $serverdata['channels_1'] ?? '2';
                $dbDjPort = !empty($serverdata['djport_1']) ? $serverdata['djport_1'] : '6000';
                $activeDjPassword = $serverdata['djpassword_1'] ?? ($serverdata['password'] ?? '');

                // --- SC_TRANS V2 KONFIGURATION (OHNE ENCODER) ---
                $ini_content  = "playlistfile=" . $dirToCpanel . "temp/" . $portNum . "/playlist/" . $safePlaylist . "\n";
                $ini_content .= "logfile=" . $scTransLog . "\n";
                $ini_content .= "calendarfile=" . $calFile . "\n";
                $ini_content .= "serverip=127.0.0.1\n";
                $ini_content .= "serverport=" . $portNum . "\n";
                $ini_content .= "djport=" . $dbDjPort . "\n";
                $ini_content .= "password=" . ($serverdata['password'] ?? '') . "\n";
                $ini_content .= "djpassword=" . $activeDjPassword . "\n";
                $ini_content .= "streamid=1\n";
                $ini_content .= "public=0\n";
                $ini_content .= "outprotocol=1\n";
                $ini_content .= "unlockkeyname=Shippo21\n";
                $ini_content .= "unlockkeycode=482QP-480TU-J4MFD-VF4YK\n";
                $ini_content .= "bitrate_1=" . $dbBitrate . "\n";
                $ini_content .= "samplerate_1=" . $dbSamplerate . "\n";
                $ini_content .= "channels_1=" . $dbChannels . "\n";
                $ini_content .= "shuffle=" . ($serverdata['shuffle'] ?? '1') . "\n";
                $ini_content .= "xfade=" . ($serverdata['xfade'] ?? '5') . "\n";
                $ini_content .= "xfadethreshold=" . ($serverdata['xfadethreshol'] ?? '5') . "\n";
                $ini_content .= "streamtitle=" . ($serverdata['streamtitle'] ?? 'The Best Radio') . "\n";
                $ini_content .= "streamurl=" . ($serverdata['streamurl'] ?? 'http://shoutcast.chaosify.online') . "\n";
                $ini_content .= "genre=" . ($serverdata['genre'] ?? 'PoP') . "\n";

                // --- DJ-LOGINS IN DIE CONFIG SCHREIBEN ---
                for ($j = 1; $j <= 30; $j++) {
                    $djUser = $serverdata['djlogin_' . $j] ?? '';
                    $djPass = $serverdata['djpassword_' . $j] ?? '';
                    $djPrio = $serverdata['djpriority_' . $j] ?? '1';

                    if (!empty($djUser) && !empty($djPass)) {
                        $ini_content .= "djlogin_" . $j . "=" . $djUser . "\n";
                        $ini_content .= "djpassword_" . $j . "=" . $djPass . "\n";
                        $ini_content .= "djpriority_" . $j . "=" . $djPrio . "\n";
                    }
                }

                $filename = "";
                if (($setting['os'] ?? '') === 'linux') {
                    $filename = "temp/" . $portNum . "/conf/sc_trans.conf";
                }
                
                $dirName = dirname($filename);
                if (!file_exists($dirName)) {
                    @mkdir($dirName, 0777, true);
                }

                $handle = @fopen($filename, "w");
                if ($handle) {
                    @chmod($filename, 0777);
                    if (@fwrite($handle, $ini_content) === false) {
                        @fclose($handle);
                    } else {
                        @fclose($handle);
                        $pid = '';

                        if (($setting['os'] ?? '') === 'linux') {
                            @shell_exec("pkill -9 -f " . escapeshellarg("sc_trans.*" . $portNum));
                            sleep(1);

                            $binDir = rtrim($dirToCpanel, '/') . '/files/linux/';
                            $confFile = rtrim($dirToCpanel, '/') . '/' . $filename;

                            $cmd = "cd " . escapeshellarg($binDir) . " && ./sc_trans.bin " . escapeshellarg($confFile) . " > " . escapeshellarg($logFileErr) . " 2>&1 &";
                            shell_exec($cmd);
                            sleep(2);
                            
                            $pid = trim((string)shell_exec("pgrep -f " . escapeshellarg("sc_trans.*" . $portNum)));

                            if ($pid === '') {
                                DB::insert('notices', [
                                    'username' => $loginun,
                                    'reason' => 'Server failure',
                                    'message' => 'The AutoDJ for server id ' . $serverId . ' cannot start on port ' . $serverdata['portbase'],
                                    'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
                                ]);
                            }
                        }

                        DB::update('servers', ['autopid' => $pid], 'id=%i', $serverId);
                        $correc[] = "" . ($messages["333"] ?? 'AutoDJ started successfully') . "";
                    }
                }
            }
        } elseif ($action === "stop") {
            if (($setting["os"] ?? '') === "linux" && $radioport > 0) {
                @shell_exec("pkill -9 -f " . escapeshellarg("sc_trans.*" . $radioport));
                sleep(1);
            }
            DB::update('servers', ['autopid' => ''], 'id=%i', $serverId);
            $notifi[] = "" . ($messages["335"] ?? 'AutoDJ stopped') . "";
        } elseif ($action === "edit") {
            if (isset($_POST['submit'])) {
                DB::update('servers', [
                    'streamtitle' => $_POST['titl'] ?? '',
                    'streamurl' => $_POST['surl'] ?? '',
                    'genre' => $_POST['genr'] ?? '',
                    'shuffle' => $_POST['shuf'] ?? '',
                    'samplerate_1' => $_POST['samp_1'] ?? '',
                    'public' => $_POST['publ'] ?? '',
                    'channels_1' => $_POST['chan'] ?? '',
                    'bitrate_1' => $_POST['bitr_1'] ?? '',
                    'bitrate_2' => $_POST['bitr_2'] ?? '',
                    'displaymetadatapattern_1' => $_POST['displaymetadatapattern_1'] ?? '',
                    'xfade' => $_POST['xfade'] ?? '',
                    'xfadethreshol' => $_POST['xfadethreshol'] ?? ''
                ], 'id=%i AND owner=%s', $serverId, $loginun);
                
                $correc[] = "<h2>" . ($messages["336"] ?? 'Settings updated for port') . " " . (int)$radioport . " " . ($messages["336_pre"] ?? '') . "</h2>";
            }

            $editsqlrow = DB::queryFirstRow("SELECT * FROM servers WHERE id=%i AND portbase=%s", $serverId, $radioport);
            if ($editsqlrow) {
                $formedit_port = $editsqlrow["portbase"] ?? '';
                $formedit_pass = $editsqlrow["password"] ?? '';
                $formedit_bitr_1 = $editsqlrow["bitrate_1"] ?? '';
                $formedit_bitr_2 = $editsqlrow["bitrate_2"] ?? '';
                $formedit_titl = $editsqlrow["streamtitle"] ?? '';
                $formedit_surl = $editsqlrow["streamurl"] ?? '';
                $formedit_genr = $editsqlrow["genre"] ?? '';
                $formedit_shuf = $editsqlrow["shuffle"] ?? '';
                $formedit_samp_1 = $editsqlrow["samplerate_1"] ?? '';
                $formedit_publ = $editsqlrow["public"] ?? '';
                $formedit_chan = $editsqlrow["channels_1"] ?? '';
                $formedit_xfade = $editsqlrow["xfade"] ?? '';
                $formedit_xfadethreshol = $editsqlrow["xfadethreshol"] ?? '';
            }
        }
    } catch (Exception $e) {
        $errors[] = "<h2>Database execution error</h2>";
    }
}