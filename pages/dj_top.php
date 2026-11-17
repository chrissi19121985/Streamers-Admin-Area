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

// --- RESTART / STOP AKTIONEN (Vorbereitung) ---
if ($action === "restart" && $serverId > 0) {
    try {
        $autopidVal = DB::queryFirstField("SELECT autopid FROM servers WHERE id=%i", $serverId);
        if ($autopidVal === null || $autopidVal === '') {
            $errors[] = "<h2>" . ($messages["334"] ?? 'AutoDJ PID not found') . "</h2>";
        } else {
            if (($setting["os"] ?? '') === "linux" && ($setting["shellset"] ?? '') === 'ssh2') {
                $sshPort = (int)($setting['ssh_port'] ?? 22);
                $sshUser = base64_decode($setting['ssh_user'] ?? '');
                $sshPass = base64_decode($setting['ssh_pass'] ?? '');
                
                $connectionSSH = @ssh2_connect('localhost', $sshPort);
                if ($connectionSSH && @ssh2_auth_password($connectionSSH, $sshUser, $sshPass)) {
                    $ssh2_exec_com = @ssh2_exec($connectionSSH, 'kill ' . (int)$autopidVal);
                    if ($ssh2_exec_com) {
                        stream_get_contents($ssh2_exec_com);
                    }
                    sleep(2);
                }
            }
        }
    } catch (Exception $e) {
        // Fehler abfangen
    }
}

// --- SERVER-ID SPEZIFISCHE AKTIONEN ---
if ($serverId > 0) {
    try {
        $radioport = DB::queryFirstField("SELECT portbase FROM servers WHERE id=%i AND owner=%s", $serverId, $loginun);
        $autopidCheck = DB::queryFirstField("SELECT autopid FROM servers WHERE id=%i AND owner=%s", $serverId, $loginun);

        if (!$radioport || $autopidCheck === null) {
            header('Location: content.php?include=music&error=sc_trans_access');
            exit();
        }

        if ((string)$autopidCheck !== "9999999") {
            if ($action === "restart") {
                $hostAdd = $setting['host_add'] ?? 'localhost';
                $portNum = (int)$radioport;
                
                $connection = @fsockopen($hostAdd, $portNum, $errno, $errstr, 1);
                $page = '';
                if ($connection) {
                    @fputs($connection, "GET /7.html HTTP/1.0\r\nUser-Agent: XML Getter (Mozilla Compatible)\r\n\r\n");
                    while (!feof($connection)) {
                        $page .= @fgets($connection, 1000);
                    }
                    @fclose($connection);
                    
                    $page = preg_replace("/.*<body>/is", "", $page);
                    $page = preg_replace("/<\/body>.*/is", ",", $page);
                    $numbers = explode(",", $page);
                }

                $numbers1 = $numbers[1] ?? '0';
                $postPlList = $_POST['pllist'] ?? null;

                if ($numbers1 === "1" || $postPlList === null) {
                    if ($numbers1 === "1") {
                        $errors[] = "<h2>" . ($messages["331"] ?? 'Server is running') . "</h2>";
                    }
                    if ($postPlList === null) {
                        $errors[] = "<h2>" . ($messages["332"] ?? 'Playlist not selected') . "</h2>";
                    }
                } else {
                    $serverdata = DB::queryFirstRow("SELECT * FROM servers WHERE id=%i AND portbase=%i", $serverId, $portNum);
                    if ($serverdata) {
                        $dirToCpanel = $setting['dir_to_cpanel'] ?? '';
                        $safePlaylist = strip_tags(str_replace(['/', '\\'], '', (string)$postPlList));
                        
                        $ini_content = "playlistfile=" . $dirToCpanel . "temp/" . $portNum . "/playlist/" . $safePlaylist . "\n";
                        
                        $excludedFields = [
                            'id', 'owner', 'maxuser', 'portbase', 'adminpassword', 'sitepublic', 'screenlog', 
                            'songhistory', 'tchlog', 'weblog', 'w3cenable', 'w3clog', 'srcip', 'destip', 'yport', 
                            'namelookups', 'relayport', 'relayserver', 'autodumpusers', 'autodumptime', 'contentdir', 
                            'streamintrofile', 'titleformat', 'publicserver', 'allowrelay', 'allowpublicrelay', 
                            'metainterval', 'abuse', 'pid', 'autopid', 'webspace', 'streamauthhash_1', 'streamid_1', 
                            'streampath_1', 'streamauthhash_2', 'streamid_2', 'streampath_2', 'logfile', 'banfile', 
                            'ripfile', 'yp2', 'uvox2sourcedebug', 'urlformat'
                        ];

                        foreach ($serverdata as $field => $value) {
                            if (!is_numeric((string)$field) && !in_array($field, $excludedFields, true)) {
                                $ini_content .= $field . "=" . $value . "\n";
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
                                    if (($setting["shellset"] ?? '') === 'ssh2') {
                                        $connectionSSH2 = @ssh2_connect('localhost', (int)($setting['ssh_port'] ?? 22));
                                        if ($connectionSSH2 && @ssh2_auth_password($connectionSSH2, base64_decode($setting['ssh_user'] ?? ''), base64_decode($setting['ssh_pass'] ?? ''))) {
                                            $execStream = @ssh2_exec($connectionSSH2, $dirToCpanel . 'files/linux/sc_trans.bin ' . $dirToCpanel . $filename . ' </dev/null 2>/dev/null >/dev/null & echo $!');
                                            if ($execStream) {
                                                sleep(4);
                                                $pid = trim(stream_get_contents($execStream));
                                            }
                                        }
                                    } elseif (($setting["shellset"] ?? '') === 'shellexec') {
                                        $pid = trim((string)shell_exec("nohup " . $dirToCpanel . "files/linux/sc_trans.bin " . $dirToCpanel . $filename . " > /dev/null & echo $!"));
                                        sleep(4);
                                    }

                                    if ($pid === '') {
                                        DB::insert('notices', [
                                            'username' => $loginun,
                                            'reason' => 'Server failure',
                                            'message' => 'The server with id ' . $serverId . ' cannot start on port ' . $serverdata['portbase'],
                                            'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
                                        ]);
                                    }
                                }

                                DB::update('servers', ['autopid' => $pid], 'id=%i', $serverId);
                                $correc[] = "<h2>" . ($messages["333"] ?? 'AutoDJ started successfully') . "</h2>";
                            }
                        }
                    }
                }
            } elseif ($action === "stop") {
                $pidVal = DB::queryFirstField("SELECT autopid FROM servers WHERE id=%i", $serverId);
                if ($pidVal === null || $pidVal === '') {
                    $errors[] = "<h2>" . ($messages["334"] ?? 'AutoDJ PID not found') . "</h2>";
                } else {
                    if (($setting["os"] ?? '') === "linux") {
                        if (($setting["shellset"] ?? '') === 'ssh2') {
                            $connectionSSH2 = @ssh2_connect('localhost', (int)($setting['ssh_port'] ?? 22));
                            if ($connectionSSH2 && @ssh2_auth_password($connectionSSH2, base64_decode($setting['ssh_user'] ?? ''), base64_decode($setting['ssh_pass'] ?? ''))) {
                                $execStream = @ssh2_exec($connectionSSH2, 'kill ' . (int)$pidVal);
                                if ($execStream) {
                                    stream_get_contents($execStream);
                                }
                                sleep(2);
                            }
                        } elseif (($setting["shellset"] ?? '') === 'shellexec') {
                            shell_exec("kill " . (int)$pidVal);
                            sleep(2);
                        }
                    }
                    $notifi[] = "<h2>" . ($messages["335"] ?? 'AutoDJ stopped') . "</h2>";
                }
            } elseif ($action === "edit") {
                if (isset($_POST['submit'])) {
                    $updateData = [
                        'calendarrewrite' => $_POST['calendarrewrite'] ?? '',
                        'djcapture' => $_POST['djcapture'] ?? ''
                    ];

                    for ($k = 1; $k <= 30; $k++) {
                        $updateData['djlogin_' . $k] = $_POST['djlogin_' . $k] ?? '';
                        $updateData['djpassword_' . $k] = $_POST['djpassword_' . $k] ?? '';
                        $updateData['djpriority_' . $k] = $_POST['djpriority_' . $k] ?? '';
                        
                        if ($k === 1) {
                            $updateData['djport_1'] = $_POST['djport_1'] ?? '';
                        }
                    }

                    try {
                        DB::update('servers', $updateData, 'id=%i AND owner=%s', $serverId, $loginun);
                        
                        // WICHTIG: Nach dem Speichern auf include=dj umleiten
                        header("Location: content.php?include=dj&id=" . $serverId . "&action=edit&saved=1");
                        exit();
                    } catch (Exception $e) {
                        $errors[] = "<h2>Database execution error</h2>";
                    }
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
                    $formedit_samp_2 = $editsqlrow["samplerate_2"] ?? '';
                    $formedit_publ = $editsqlrow["public"] ?? '';
                    $formedit_chan = $editsqlrow["channels_1"] ?? '';
                    $formedit_encoder_1 = $editsqlrow["encoder_1"] ?? '';
                    $formedit_encoder_2 = $editsqlrow["encoder_2"] ?? '';
                    $formedit_calendarrewrite = $editsqlrow["calendarrewrite"] ?? '';
                    $formedit_djcapture = $editsqlrow["djcapture"] ?? '';
                    $formedit_calendarfile = $editsqlrow["calendarfile"] ?? '';
                    $formedit_outprotocol_1 = $editsqlrow["outprotocol_1"] ?? '';
                    $formedit_outprotocol_2 = $editsqlrow["outprotocol_2"] ?? '';
                    $formedit_xfade = $editsqlrow["xfade"] ?? '';
                    $formedit_xfadethreshol = $editsqlrow["xfadethreshol"] ?? '';

                    // DJ-Slots 1 bis 30 inklusive DJ-Port aus der Datenbank für das Formular laden
                    for ($k = 1; $k <= 30; $k++) {
                        ${'formedit_djlogin_' . $k} = $editsqlrow["djlogin_" . $k] ?? '';
                        ${'formedit_djpassword_' . $k} = $editsqlrow["djpassword_" . $k] ?? '';
                        ${'formedit_djport_' . $k} = $editsqlrow["djport_" . $k] ?? '';
                        ${'formedit_djpriority_' . $k} = $editsqlrow["djpriority_" . $k] ?? '1';
                    }
                }
            }
        } else {
            $errors[] = "<h2>" . ($messages["337"] ?? 'Invalid AutoDJ configuration') . "</h2>";
        }
    } catch (Exception $e) {
        $errors[] = "<h2>Database execution error</h2>";
    }
}