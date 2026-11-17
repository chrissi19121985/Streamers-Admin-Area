<?php
/**
 * Streamers Admin Panel - Server Control (Start, Stop, Restart, Manage)
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
    $errors[] = "" . ($messages["g5"] ?? 'Database error') . "";
}

$loginun = $_SESSION['username'] ?? '';
$viewId = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$manageId = isset($_GET['manage']) ? (int)$_GET['manage'] : 0;
$action = $_GET['action'] ?? '';

// --- SERVER NEUSTART ---
if ($action === "restart" && $viewId > 0) {
    try {
        $radioport = DB::queryFirstField("SELECT portbase FROM servers WHERE id=%i AND owner=%s", $viewId, $loginun);
        if (!$radioport) {
            $errors[] = "" . ($messages["175"] ?? 'Access denied or port not found') . "";
        } else {
            $hostAdd = $setting['host_add'] ?? 'localhost';
            $portNum = (int)$radioport;
            $connection = @fsockopen($hostAdd, $portNum, $errno, $errstr, 1);
            
            $pidData = DB::queryFirstField("SELECT pid FROM servers WHERE id=%i", $viewId);
            if (($setting["os"] ?? '') === "linux") {
                if (!empty($pidData)) {
                    @shell_exec("kill -9 " . (int)$pidData);
                }
                @shell_exec("pkill -9 -f " . escapeshellarg("sc_serv.*" . $portNum));
                sleep(1);
            }
            
            $connectionCheck = @fsockopen($hostAdd, $portNum, $errno, $errstr, 1);
            if ($connectionCheck) {
                if (is_resource($connectionCheck)) {
                    fclose($connectionCheck);
                }
                $notifi[] = "" . ($messages["177"] ?? 'Server running') . "";
            } else {
                $serverdata = DB::queryFirstRow("SELECT * FROM servers WHERE id=%i AND portbase=%i", $viewId, $portNum);
                if ($serverdata) {
                    $ini_content = "";
                    foreach ($serverdata as $field => $value) {
                        if (!is_numeric((string)$field) && $value !== "" && !in_array($field, ['id', 'owner', 'sitepublic', 'suspended', 'abuse', 'pid', 'autopid', 'webspace', 'serverip_1', 'serverport_1', 'serverip_2', 'serverport_2', 'streamtitle', 'streamurl', 'shuffle', 'samplerate_1', 'channels_1', 'samplerate_2', 'channels_2', 'genre', 'public', 'aim', 'icq', 'irc', 'encoder_1', 'encoder_2', 'mp3quality', 'mp3mode', 'calendarrewrite', 'calendarfile', 'outprotocol_1', 'outprotocol_2', 'screenlog', 'xfade', 'xfadethreshol', 'uvoxstreamid_1', 'uvoxstreamid_2', 'log', 'displaymetadatapattern_1', 'displaymetadatapattern_2', 'useMetadata_1', 'useMetadata_2', 'uvoxradiometadata_1', 'uvoxradiometadata_2', 'uvoxnewmetadata_1', 'uvoxnewmetadata_2', 'djcapture', 'unlockkeyname', 'unlockkeycode', 'djbroadcasts'], true) && strpos($field, 'djlogin_') !== 0 && strpos($field, 'djpassword_') !== 0 && strpos($field, 'djpriority_') !== 0 && strpos($field, 'djport_') !== 0) {
                            $ini_content .= $field . "=" . $value . "\n";
                        }
                    }
                    
                    $filename = "temp/" . $portNum . "/conf/sc_serv.conf";
                    $dirName = dirname($filename);
                    if (!file_exists($dirName)) {
                        @mkdir($dirName, 0777, true);
                    }
                    
                    $handle = @fopen($filename, 'w');
                    if ($handle === false) {
                        $errors[] = "" . ($messages["178"] ?? 'Could not open config file') . "";
                    } elseif (fwrite($handle, $ini_content) === false) {
                        @fclose($handle);
                        $errors[] = "" . ($messages["179"] ?? 'Could not write config file') . "";
                    } else {
                        @fclose($handle);
                        @chmod($filename, 0777);
                        $pid = '';
                        
                        if (($setting['os'] ?? '') === 'linux') {
                            $dirToCpanel = $setting["dir_to_cpanel"] ?? '';
                            $cmd = "cd " . escapeshellarg($dirToCpanel . "files/linux/") . " && ./sc_serv.bin " . escapeshellarg($dirToCpanel . $filename) . " > /dev/null 2>&1 &";
                            shell_exec($cmd);
                            sleep(1);
                            $pid = trim((string)shell_exec("pgrep -f " . escapeshellarg("sc_serv.*" . $portNum)));
                        }
                        
                        if ($pid === '') {
                            DB::insert('notices', [
                                'username' => $loginun,
                                'reason' => 'Server failure',
                                'message' => 'The server with id ' . $viewId . ' cannot start on port ' . $portNum,
                                'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
                            ]);
                            $errors[] = "" . ($messages["517"] ?? 'Server start failed') . "";
                        } else {
                            DB::update('servers', ['pid' => $pid], 'id=%i', $viewId);
                            $correc[] = "" . ($messages["518"] ?? 'Server started successfully') . "";
                            if (($setting["scs_config"] ?? '0') === "0") {
                                @unlink($filename);
                            }
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        $errors[] = "Database or execution error";
    }
}

// --- SERVER VIEW / START / STOP ---
if ($viewId > 0) {
    try {
        $serverq = DB::query("SELECT * FROM servers WHERE id=%i", $viewId);
        $serverdata = DB::queryFirstRow("SELECT * FROM servers WHERE id=%i", $viewId);
        
        $userLevel = $_SESSION['user_level'] ?? '';
        $serverOwner = $serverdata['owner'] ?? '';
        
        if (!$serverdata || ($serverOwner !== $loginun && $userLevel !== "Super Administrator")) {
            $errors[] = "" . ($messages["511"] ?? 'Access denied') . "";
        } else {
            if ($action === "start") {
                if ($userLevel === 'Super Administrator') {
                    $radioport = DB::queryFirstField("SELECT portbase FROM servers WHERE id=%i", $viewId);
                } else {
                    $radioport = DB::queryFirstField("SELECT portbase FROM servers WHERE id=%i AND owner=%s", $viewId, $loginun);
                }
                
                if (!$radioport) {
                    $errors[] = "" . ($messages["512"] ?? 'Port not found') . "";
                } else {
                    $hostAdd = $setting['host_add'] ?? 'localhost';
                    $portNum = (int)$radioport;
                    $connection = @fsockopen($hostAdd, $portNum, $errno, $errstr, 1);
                    
                    if ($connection) {
                        if (is_resource($connection)) {
                            fclose($connection);
                        }
                        $notifi[] = "" . ($messages["513"] ?? 'Server already running') . "";
                    } else {
                        $ini_content = "";
                        foreach ($serverdata as $field => $value) {
                            if (!is_numeric((string)$field) && $value !== "" && !in_array($field, ['id', 'owner', 'bitrate_1', 'uvoxauth_1', 'bitrate_2', 'uvoxauth_2', 'tchlog', 'yp2', 'djcapture', 'sitepublic', 'suspended', 'abuse', 'pid', 'autopid', 'webspace', 'serverip_1', 'serverport_1', 'serverip_2', 'serverport_2', 'streamtitle', 'streamurl', 'shuffle', 'samplerate_1', 'channels_1', 'samplerate_2', 'channels_2', 'genre', 'public', 'aim', 'icq', 'irc', 'encoder_1', 'encoder_2', 'mp3quality', 'mp3mode', 'calendarrewrite', 'calendarfile', 'outprotocol_1', 'outprotocol_2', 'screenlog', 'xfade', 'xfadethreshol', 'uvoxstreamid_1', 'uvoxstreamid_2', 'log', 'displaymetadatapattern_1', 'displaymetadatapattern_2', 'useMetadata_1', 'useMetadata_2', 'uvoxradiometadata_1', 'uvoxradiometadata_2', 'uvoxnewmetadata_1', 'uvoxnewmetadata_2', 'capture', 'unlockkeyname', 'unlockkeycode', 'djbroadcasts'], true) && strpos($field, 'djlogin_') !== 0 && strpos($field, 'djpassword_') !== 0 && strpos($field, 'djpriority_') !== 0 && strpos($field, 'djport_') !== 0) {
                                $ini_content .= $field . "=" . $value . "\n";
                            }
                        }
                        
                        $filename = "temp/" . $portNum . "/conf/sc_serv.conf";
                        $dirName = dirname($filename);
                        if (!file_exists($dirName)) {
                            @mkdir($dirName, 0777, true);
                        }
                        
                        $handle = @fopen($filename, 'w');
                        if ($handle === false) {
                            $errors[] = "" . ($messages["514"] ?? 'Could not open config') . "";
                        } elseif (fwrite($handle, $ini_content) === false) {
                            @fclose($handle);
                            $errors[] = "" . ($messages["515"] ?? 'Could not write config') . "";
                        } else {
                            @fclose($handle);
                            @chmod($filename, 0777);
                            $pid = '';
                            
                            if (($setting['os'] ?? '') === 'linux') {
                                $dirToCpanel = $setting["dir_to_cpanel"] ?? '';
                                $cmd = "cd " . escapeshellarg($dirToCpanel . "files/linux/") . " && ./sc_serv.bin " . escapeshellarg($dirToCpanel . $filename) . " > /dev/null 2>&1 &";
                                shell_exec($cmd);
                                sleep(1);
                                $pid = trim((string)shell_exec("pgrep -f " . escapeshellarg("sc_serv.*" . $portNum)));
                            }
                            
                            if ($pid === '') {
                                DB::insert('notices', [
                                    'username' => $loginun,
                                    'reason' => 'Server failure',
                                    'message' => 'The server with id ' . $viewId . ' cannot start on port ' . $portNum,
                                    'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
                                ]);
                                $errors[] = "" . ($messages["517"] ?? 'Start failed') . "";
                            } else {
                                DB::update('servers', ['pid' => $pid], 'id=%i', $viewId);
                                $correc[] = "" . ($messages["518"] ?? 'Started successfully') . "";
                                if (($setting["scs_config"] ?? '0') === "0") {
                                    @unlink($filename);
                                }
                            }
                        }
                    }
                }
            }
            
            if ($action === "stop") {
                $radioport = DB::queryFirstField("SELECT portbase FROM servers WHERE id=%i", $viewId);
                $pidData = DB::queryFirstField("SELECT pid FROM servers WHERE id=%i", $viewId);
                
                if (($setting["os"] ?? '') === "linux") {
                    if (!empty($pidData)) {
                        @shell_exec("kill -9 " . (int)$pidData);
                    }
                    if (!empty($radioport)) {
                        @shell_exec("pkill -9 -f " . escapeshellarg("sc_serv.*" . $radioport));
                    }
                    sleep(1);
                }
                
                DB::update('servers', ['pid' => ''], 'id=%i', $viewId);
                $notifi[] = "" . ($messages["521"] ?? 'Server stopped') . "";
            }
        }
    } catch (Exception $e) {
        $errors[] = "Execution error";
    }
}

// --- SERVER MANAGEMENT / UPDATE ---
if ($manageId > 0 && $action !== '') {
    try {
        $password = $_POST['password'] ?? '';
        $adminpassword = $_POST['adminpassword'] ?? '';
        $uvoxauth_1 = $_POST['uvoxauth_1'] ?? '';
        $uvoxauth_2 = $_POST['uvoxauth_2'] ?? '';
        $streamauthhash_1 = $_POST['streamauthhash_1'] ?? '';
        $streamauthhash_2 = $_POST['streamauthhash_2'] ?? '';
        $songhistory = (int)($_POST['songhistory'] ?? 10);
        $namelookups = (int)($_POST['namelookups'] ?? 0);
        $relayport = (int)($_POST['relayport'] ?? 0);
        $relayserver = $_POST['relayserver'] ?? '';
        $autodumptime = (int)($_POST['autodumptime'] ?? 0);
        $streamintrofile = $_POST['streamintrofile'] ?? '';
        $titleformat = $_POST['titleformat'] ?? '';
        $urlformat = $_POST['urlformat'] ?? '';
        $allowrelay = (int)($_POST['allowrelay'] ?? 0);
        $allowpublicrelay = (int)($_POST['allowpublicrelay'] ?? 0);

        // Führe eine explizit parametrisierte SQL-Abfrage aus, um Syntaxfehler durch Sonderzeichen sicher zu verhindern
        DB::query(
            "UPDATE servers SET 
                password=%s, 
                adminpassword=%s, 
                uvoxauth_1=%s, 
                uvoxauth_2=%s, 
                streamauthhash_1=%s, 
                streamauthhash_2=%s, 
                songhistory=%i, 
                namelookups=%i, 
                relayport=%i, 
                relayserver=%s, 
                autodumptime=%i, 
                streamintrofile=%s, 
                titleformat=%s, 
                urlformat=%s, 
                allowrelay=%i, 
                allowpublicrelay=%i 
            WHERE id=%i",
            $password, $adminpassword, $uvoxauth_1, $uvoxauth_2, 
            $streamauthhash_1, $streamauthhash_2, $songhistory, $namelookups, 
            $relayport, $relayserver, $autodumptime, $streamintrofile, 
            $titleformat, $urlformat, $allowrelay, $allowpublicrelay, 
            $manageId
        );

        $correc[] = "" . ($messages["522"] ?? 'Settings updated successfully') . "";
    } catch (Exception $e) {
        $errors[] = "" . ($messages["523"] ?? 'Update failed: ' . $e->getMessage()) . "";
    }
}