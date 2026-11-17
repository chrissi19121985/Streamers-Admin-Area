<?php
/**
 * Streamers Admin Panel - TeamSpeak Server Management (Top)
 *
 * Modernisiert für PHP 8.x, MeekroDB & Bootstrap 5 (inkl. Token-Fallback, Snapshots & Backup-Upload)
 */

declare(strict_types=1);

if (stripos($_SERVER['PHP_SELF'], 'content.php') === false) {
    die("You can't access this file directly...");
}

$actionVal = $_GET['action'] ?? '';
$viewId = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$activeTab = $_GET['tab'] ?? 'clients';
$loginun = $_SESSION['username'] ?? '';
$hostAdd = htmlspecialchars($setting['host_add'] ?? 'localhost', ENT_QUOTES, 'UTF-8');

// Korrekte TS3 Escape-Funktion für ServerQuery
function ts3_escape(string $str): string {
    $search  = ['\\',   '/',   ' ',   '|',   "\a",  "\b",  "\f",  "\n",  "\r",  "\t",  "\v"];
    $replace = ['\\\\', '\\/', '\\s', '\\p', '\\a', '\\b', '\\f', '\\n', '\\r', '\\t', '\\v'];
    return str_replace($search, $replace, $str);
}

// Tabelle und Spalten sicherstellen
try {
    DB::query("CREATE TABLE IF NOT EXISTS ts_servers (
        id INT PRIMARY KEY,
        port INT NOT NULL,
        file_port INT NOT NULL,
        query_port INT NOT NULL,
        max_slots INT NOT NULL,
        version VARCHAR(20) NOT NULL,
        owner VARCHAR(100) NOT NULL,
        status INT DEFAULT 0,
        query_password VARCHAR(100) DEFAULT ''
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Exception $e) {}

try {
    $cols = DB::query("SHOW COLUMNS FROM ts_servers LIKE 'query_password'");
    if (empty($cols)) {
        DB::query("ALTER TABLE ts_servers ADD COLUMN query_password VARCHAR(100) DEFAULT ''");
    }
} catch (Exception $e) {}

$noticeMsg = '';
$noticeType = 'success';
$tokenResult = '';

// --- TEAMSPEAK QUERY HELFER KLASSE ---
class TS3QueryHelper {
    private $socket;
    public $error = '';

    public function __construct(string $host, int $port) {
        $this->socket = @fsockopen($host, $port, $errno, $errstr, 2);
        if (!$this->socket) {
            $this->error = "Verbindung zu Port {$port} fehlgeschlagen: {$errstr} ({$errno})";
            return;
        }
        @stream_set_timeout($this->socket, 2);
        @fgets($this->socket, 1024);
        @fgets($this->socket, 1024);
    }

    public function login(string $user, string $pass): bool {
        if (!$this->socket) return false;
        fputs($this->socket, "login {$user} {$pass}\n");
        $response = @fgets($this->socket, 1024);
        if (!is_string($response) || strpos($response, 'error id=0') === false) {
            $this->error = "Login fehlgeschlagen: " . trim((string)$response);
            return false;
        }
        return true;
    }

    public function selectServerByPort(int $voicePort): bool {
        if (!$this->socket) return false;
        fputs($this->socket, "use port={$voicePort}\n");
        $response = '';
        while ($line = @fgets($this->socket, 1024)) {
            $response .= $line;
            if (strpos($line, 'error id=') !== false) break;
        }

        if (strpos($response, 'error id=0') !== false) {
            return true;
        }

        $this->error = "Server-Auswahl auf Port {$voicePort} fehlgeschlagen: " . trim($response);
        return false;
    }

    public function getServerInfo(): array {
        if (!$this->socket) return [];
        fputs($this->socket, "serverinfo\n");
        $response = '';
        while ($line = @fgets($this->socket, 1024)) {
            $response .= $line;
            if (strpos($line, 'error id=') !== false) break;
        }
        $info = [];
        $cleanResponse = preg_replace('/error id=[0-9]+.*$/', '', $response);
        if (!empty(trim($cleanResponse))) {
            foreach (explode(' ', trim($cleanResponse)) as $pair) {
                $kv = explode('=', $pair, 2);
                if (count($kv) === 2) {
                    $info[$kv[0]] = rawurldecode($kv[1]);
                }
            }
        }
        return $info;
    }

    public function getClients(): array {
        if (!$this->socket) return [];
        
        fputs($this->socket, "clientlist -uid -away -voice -groups -icon -country\n");
        $response = '';
        while ($line = @fgets($this->socket, 1024)) {
            $response .= $line;
            if (strpos($line, 'error id=') !== false) break;
        }
        
        $clients = [];
        $cleanResponse = preg_replace('/error id=[0-9]+.*$/', '', $response);
        
        if (!empty(trim($cleanResponse))) {
            $items = explode('|', trim($cleanResponse));
            foreach ($items as $item) {
                if (empty($item)) continue;
                $params = [];
                foreach (explode(' ', trim($item)) as $pair) {
                    $kv = explode('=', $pair, 2);
                    if (count($kv) === 2) {
                        $params[$kv[0]] = rawurldecode($kv[1]);
                    }
                }
                
                $clientType = isset($params['client_type']) ? (int)$params['client_type'] : 0;
                $clientName = $params['client_nickname'] ?? '';
                
                if ($clientType !== 1 && !empty($clientName)) {
                    $clients[] = $params;
                }
            }
        }
        return $clients;
    }

    public function getChannels(): array {
        if (!$this->socket) return [];
        
        fputs($this->socket, "channellist -topic -description -flags -voice -limits\n");
        $response = '';
        while ($line = @fgets($this->socket, 1024)) {
            $response .= $line;
            if (strpos($line, 'error id=') !== false) break;
        }
        
        $channels = [];
        $cleanResponse = preg_replace('/error id=[0-9]+.*$/', '', $response);
        
        if (!empty(trim($cleanResponse))) {
            $items = explode('|', trim($cleanResponse));
            foreach ($items as $item) {
                if (empty($item)) continue;
                $params = [];
                foreach (explode(' ', trim($item)) as $pair) {
                    $kv = explode('=', $pair, 2);
                    if (count($kv) === 2) {
                        $params[$kv[0]] = rawurldecode($kv[1]);
                    }
                }
                if (isset($params['channel_name'])) {
                    $channels[] = $params;
                }
            }
        }
        return $channels;
    }

    public function getServerGroups(): array {
        if (!$this->socket) return [];
        fputs($this->socket, "servergrouplist\n");
        $response = '';
        while ($line = @fgets($this->socket, 1024)) {
            $response .= $line;
            if (strpos($line, 'error id=') !== false) break;
        }
        $groups = [];
        $cleanResponse = preg_replace('/error id=[0-9]+.*$/', '', $response);
        if (!empty(trim($cleanResponse))) {
            foreach (explode('|', trim($cleanResponse)) as $item) {
                if (empty($item)) continue;
                $params = [];
                foreach (explode(' ', trim($item)) as $pair) {
                    $kv = explode('=', $pair, 2);
                    if (count($kv) === 2) $params[$kv[0]] = rawurldecode($kv[1]);
                }
                if (isset($params['sgid']) && (int)$params['type'] === 1) {
                    $groups[] = $params;
                }
            }
        }
        return $groups;
    }

    public function getChannelGroups(): array {
        if (!$this->socket) return [];
        fputs($this->socket, "channelgrouplist\n");
        $response = '';
        while ($line = @fgets($this->socket, 1024)) {
            $response .= $line;
            if (strpos($line, 'error id=') !== false) break;
        }
        $groups = [];
        $cleanResponse = preg_replace('/error id=[0-9]+.*$/', '', $response);
        if (!empty(trim($cleanResponse))) {
            foreach (explode('|', trim($cleanResponse)) as $item) {
                if (empty($item)) continue;
                $params = [];
                foreach (explode(' ', trim($item)) as $pair) {
                    $kv = explode('=', $pair, 2);
                    if (count($kv) === 2) $params[$kv[0]] = rawurldecode($kv[1]);
                }
                if (isset($params['cgid']) && (int)$params['type'] === 1) {
                    $groups[] = $params;
                }
            }
        }
        return $groups;
    }

    public function execute(string $cmd): string {
        if (!$this->socket) return '';
        fputs($this->socket, $cmd . "\n");
        $response = '';
        while ($line = @fgets($this->socket, 1024)) {
            $response .= $line;
            if (strpos($line, 'error id=') !== false) break;
        }
        return $response;
    }

    public function __destruct() {
        if ($this->socket) {
            @fputs($this->socket, "quit\n");
            @fclose($this->socket);
        }
    }
}

// Token intelligent auslesen oder automatisch neu generieren via ServerQuery
function getTsToken(string $tsDir, int $queryPort = 0, int $voicePort = 0, string $queryPass = ''): string {
    if ($queryPort > 0 && $voicePort > 0 && !empty($queryPass)) {
        $qHelper = new TS3QueryHelper('127.0.0.1', $queryPort);
        if (empty($qHelper->error) && $qHelper->login('serveradmin', $queryPass) && $qHelper->selectServerByPort($voicePort)) {
            $res = $qHelper->execute("tokenlist");
            if (strpos($res, 'error id=0') !== false) {
                if (preg_match('/token=([a-zA-Z0-9]{32,})/i', $res, $m)) {
                    return $m[1];
                }
                if (preg_match('/token[:=]([a-zA-Z0-9]{32,})/i', $res, $m)) {
                    return $m[1];
                }
            }
            
            $tokenRes = $qHelper->execute("tokenadd tokentype=0 tokenid1=6 tokenid2=0");
            if (strpos($tokenRes, 'error id=0') !== false) {
                if (preg_match('/token=([a-zA-Z0-9]{32,})/i', $tokenRes, $m)) {
                    return $m[1];
                }
            }
        }
    }

    $logDir = $tsDir . "logs/";
    if (is_dir($logDir)) {
        $logFiles = glob($logDir . "*.log");
        if (!empty($logFiles)) {
            rsort($logFiles);
            foreach ($logFiles as $logFile) {
                $content = @file_get_contents($logFile);
                if ($content !== false) {
                    if (preg_match('/token=([a-zA-Z0-9]{32,})/i', $content, $matches)) {
                        return $matches[1];
                    }
                    if (preg_match('/key=([a-zA-Z0-9]{32,})/i', $content, $matches)) {
                        return $matches[1];
                    }
                    if (preg_match('/initial token[:\s]+([a-zA-Z0-9]{32,})/i', $content, $matches)) {
                        return $matches[1];
                    }
                }
            }
        }
    }
    return '';
}

// --- AKTIONEN ---
if ($actionVal === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $tsVersion = $_POST['ts_version'] ?? 'ts3';
    $owner = $_POST['owner'] ?? $loginun;
    $port = (int)($_POST['port'] ?? 9987);
    $filePort = (int)($_POST['file_port'] ?? 30033);
    $queryPort = (int)($_POST['query_port'] ?? 10011);
    $maxSlots = (int)($_POST['max_slots'] ?? 32);

    $generatedQueryPass = 'tsadmin_' . bin2hex(random_bytes(4));

    try {
        $exists = DB::queryFirstField("SELECT COUNT(*) FROM ts_servers WHERE port=%i", $port);
        if ($exists > 0) {
            throw new Exception("Ein TeamSpeak Server mit dem Port {$port} existiert bereits!");
        }

        $existingIds = DB::queryFirstColumn("SELECT id FROM ts_servers ORDER BY id ASC");
        $newId = 1;
        foreach ($existingIds as $id) {
            if ((int)$id === $newId) {
                $newId++;
            } else {
                break;
            }
        }

        DB::insert('ts_servers', [
            'id' => $newId,
            'port' => $port,
            'file_port' => $filePort,
            'query_port' => $queryPort,
            'max_slots' => $maxSlots,
            'version' => $tsVersion,
            'owner' => $owner,
            'status' => 0,
            'query_password' => $generatedQueryPass
        ]);

        $tsDir = getcwd() . "/ts3_servers/" . $port . "/";
        
        if (is_dir($tsDir)) {
            $it = new RecursiveDirectoryIterator($tsDir, RecursiveDirectoryIterator::SKIP_DOTS);
            $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
            foreach($files as $file) {
                if ($file->isDir()) @rmdir($file->getRealPath()); else @unlink($file->getRealPath());
            }
            @rmdir($tsDir);
        }

        if (!@mkdir($tsDir, 0777, true) && !is_dir($tsDir)) {
            throw new Exception("Konnte das Server-Verzeichnis {$tsDir} nicht erstellen.");
        }
        
        $tsDownloadUrl = "https://files.teamspeak-services.com/releases/server/3.13.7/teamspeak3-server_linux_amd64-3.13.7.tar.bz2";
        $tarFile = $tsDir . "ts3server.tar.bz2";

        $ch = curl_init($tsDownloadUrl);
        $fp = fopen($tarFile, 'w');
        if (!$fp) {
            throw new Exception("Konnte die Datei {$tarFile} nicht öffnen.");
        }
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch);
        curl_close($ch);
        fclose($fp);

        if (file_exists($tarFile)) {
            shell_exec("tar -xf " . escapeshellarg($tarFile) . " -C " . escapeshellarg($tsDir) . " --strip-components=1");
            @unlink($tarFile);
            
            @file_put_contents($tsDir . ".ts3server_license_accepted", "");
            
            @chmod($tsDir . "ts3server_startscript.sh", 0755);
            @chmod($tsDir . "ts3server", 0755);

            $defaultIni = "default_voice_port={$port}\nfiletransfer_port={$filePort}\nquery_port={$queryPort}\n";
            @file_put_contents($tsDir . "ts3server.ini", $defaultIni);
        }

        $noticeMsg = "TeamSpeak Server erfolgreich installiert!";
    } catch (Exception $e) {
        $noticeType = 'danger';
        $noticeMsg = "Fehler: " . $e->getMessage();
    }
}

if ($actionVal === 'start' && $viewId > 0) {
    try {
        $server = DB::queryFirstRow("SELECT * FROM ts_servers WHERE id=%i", $viewId);
        if ($server) {
            $port = (int)$server['port'];
            $qPort = (int)$server['query_port'];
            $tsDir = getcwd() . "/ts3_servers/" . $port . "/";
            $qPass = $server['query_password'] ?? '';
            
            $isStarted = false;
            
            if (file_exists($tsDir . "ts3server_startscript.sh")) {
                @chmod($tsDir . "ts3server_startscript.sh", 0755);
                @chmod($tsDir . "ts3server", 0755);

                @unlink($tsDir . "ts3server.pid");

                $startCommand = "cd " . escapeshellarg($tsDir) . " && export HOME=" . escapeshellarg($tsDir) . " && export LD_LIBRARY_PATH=" . escapeshellarg($tsDir) . " && export LANG=C.UTF-8 LC_ALL=C.UTF-8 && ./ts3server_startscript.sh start inifile=ts3server.ini";
                if (!empty($qPass)) {
                    $startCommand .= " serveradmin_password=" . escapeshellarg($qPass);
                }
                
                shell_exec($startCommand);
                sleep(2);
                
                $statusCheck = shell_exec("cd " . escapeshellarg($tsDir) . " && ./ts3server_startscript.sh status");
                if (strpos($statusCheck, 'Server is running') !== false) {
                    $isStarted = true;
                }
            }
            
            if ($isStarted) {
                DB::update('ts_servers', ['status' => 1], 'id=%i', $viewId);
                sleep(2);
                $tokenResult = getTsToken($tsDir, $qPort, $port, $qPass);
                $noticeMsg = "TeamSpeak Server erfolgreich gestartet.";
            } else {
                DB::update('ts_servers', ['status' => 0], 'id=%i', $viewId);
                $noticeType = 'danger';
                $noticeMsg = "Fehler: Server konnte nicht gestartet werden. Prozess läuft laut PID nicht!";
            }
        }
    } catch (Exception $e) {
        $noticeType = 'danger';
        $noticeMsg = "Fehler beim Starten: " . $e->getMessage();
    }
}

if ($actionVal === 'stop' && $viewId > 0) {
    try {
        $server = DB::queryFirstRow("SELECT * FROM ts_servers WHERE id=%i", $viewId);
        if ($server) {
            $port = (int)$server['port'];
            $tsDir = getcwd() . "/ts3_servers/" . $port . "/";
            
            if (file_exists($tsDir . "ts3server_startscript.sh")) {
                shell_exec("cd " . escapeshellarg($tsDir) . " && ./ts3server_startscript.sh stop");
            }
            
            @unlink($tsDir . "ts3server.pid");
            DB::update('ts_servers', ['status' => 0], 'id=%i', $viewId);
            $noticeMsg = "TeamSpeak Server wurde gestoppt.";
        }
    } catch (Exception $e) {
        $noticeType = 'danger';
        $noticeMsg = "Fehler beim Stoppen: " . $e->getMessage();
    }
}

if ($actionVal === 'restart' && $viewId > 0) {
    try {
        $server = DB::queryFirstRow("SELECT * FROM ts_servers WHERE id=%i", $viewId);
        if ($server) {
            $port = (int)$server['port'];
            $qPort = (int)$server['query_port'];
            $tsDir = getcwd() . "/ts3_servers/" . $port . "/";
            $qPass = $server['query_password'] ?? '';
            
            if (file_exists($tsDir . "ts3server_startscript.sh")) {
                shell_exec("cd " . escapeshellarg($tsDir) . " && ./ts3server_startscript.sh stop");
            }
            @unlink($tsDir . "ts3server.pid");
            sleep(1);

            $isStarted = false;
            if (file_exists($tsDir . "ts3server_startscript.sh")) {
                @chmod($tsDir . "ts3server_startscript.sh", 0755);
                @chmod($tsDir . "ts3server", 0755);

                $startCommand = "cd " . escapeshellarg($tsDir) . " && export HOME=" . escapeshellarg($tsDir) . " && export LD_LIBRARY_PATH=" . escapeshellarg($tsDir) . " && export LANG=C.UTF-8 LC_ALL=C.UTF-8 && ./ts3server_startscript.sh start inifile=ts3server.ini";
                if (!empty($qPass)) {
                    $startCommand .= " serveradmin_password=" . escapeshellarg($qPass);
                }
                
                shell_exec($startCommand);
                sleep(2);
                
                $statusCheck = shell_exec("cd " . escapeshellarg($tsDir) . " && ./ts3server_startscript.sh status");
                if (strpos($statusCheck, 'Server is running') !== false) {
                    $isStarted = true;
                }
            }
            
            if ($isStarted) {
                DB::update('ts_servers', ['status' => 1], 'id=%i', $viewId);
                sleep(2);
                $tokenResult = getTsToken($tsDir, $qPort, $port, $qPass);
                $noticeMsg = "TeamSpeak Server wurde neugestartet.";
            } else {
                DB::update('ts_servers', ['status' => 0], 'id=%i', $viewId);
                $noticeType = 'danger';
                $noticeMsg = "Fehler: Server konnte nach Neustart nicht gehalten werden!";
            }
        }
    } catch (Exception $e) {
        $noticeType = 'danger';
        $noticeMsg = "Fehler: " . $e->getMessage();
    }
}

// EINSTELLUNGEN SPEICHERN (INI & LIVE QUERY)
if ($actionVal === 'save_config' && $viewId > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $server = DB::queryFirstRow("SELECT * FROM ts_servers WHERE id=%i", $viewId);
        if ($server) {
            $tsDir = getcwd() . "/ts3_servers/" . $server['port'] . "/";
            $iniFile = $tsDir . "ts3server.ini";
            
            $iniData = file_exists($iniFile) ? file_get_contents($iniFile) : "";
            
            $settingsMap = [
                'virtualserver_name' => trim($_POST['virtualserver_name'] ?? ''),
                'virtualserver_password' => trim($_POST['virtualserver_password'] ?? ''),
                'virtualserver_maxclients' => (int)($_POST['virtualserver_maxclients'] ?? 32),
                'virtualserver_reserved_slots' => (int)($_POST['virtualserver_reserved_slots'] ?? 0),
                'virtualserver_welcomemessage' => trim($_POST['virtualserver_welcomemessage'] ?? ''),
                'virtualserver_hostmessage' => trim($_POST['virtualserver_hostmessage'] ?? ''),
                'virtualserver_hostmessage_mode' => (int)($_POST['virtualserver_hostmessage_mode'] ?? 0),
                'virtualserver_hostbanner_gfx_url' => trim($_POST['virtualserver_hostbanner_gfx_url'] ?? ''),
                'virtualserver_hostbanner_url' => trim($_POST['virtualserver_hostbanner_url'] ?? ''),
                'virtualserver_hostbanner_gfx_interval' => (int)($_POST['virtualserver_hostbanner_gfx_interval'] ?? 0),
                'virtualserver_hostbutton_tooltip' => trim($_POST['virtualserver_hostbutton_tooltip'] ?? ''),
                'virtualserver_hostbutton_url' => trim($_POST['virtualserver_hostbutton_url'] ?? ''),
                'virtualserver_hostbutton_gfx_url' => trim($_POST['virtualserver_hostbutton_gfx_url'] ?? '')
            ];

            foreach ($settingsMap as $key => $val) {
                if ($val === $key || $val === ($key . '=')) {
                    $val = '';
                }

                $pattern = '/^' . preg_quote($key, '/') . '\s*=\s*.*$/m';
                if (preg_match($pattern, $iniData)) {
                    $iniData = preg_replace($pattern, $key . '=' . $val, $iniData);
                } else {
                    $iniData .= "\n" . $key . "=" . $val;
                }
            }

            file_put_contents($iniFile, trim($iniData) . "\n");

            if ((int)$server['status'] === 1 && !empty($server['query_password'])) {
                $qHelper = new TS3QueryHelper('127.0.0.1', (int)$server['query_port']);
                if ($qHelper->login('serveradmin', $server['query_password']) && $qHelper->selectServerByPort((int)$server['port'])) {
                    $cmd = "serveredit";
                    foreach ($settingsMap as $k => $v) {
                        $cmd .= " {$k}=" . ts3_escape((string)$v);
                    }
                    $qHelper->execute($cmd);
                }
            }

            $noticeMsg = "Server-Einstellungen erfolgreich gespeichert und angewendet!";
        }
    } catch (Exception $e) {
        $noticeType = 'danger';
        $noticeMsg = "Fehler: " . $e->getMessage();
    }
}

// --- BACKUP / SNAPSHOT AKTIONEN ---
if ($actionVal === 'create_backup' && $viewId > 0) {
    try {
        $server = DB::queryFirstRow("SELECT * FROM ts_servers WHERE id=%i", $viewId);
        if ($server && (int)$server['status'] === 1 && !empty($server['query_password'])) {
            $port = (int)$server['port'];
            $qPort = (int)$server['query_port'];
            $qPass = $server['query_password'];
            $tsDir = getcwd() . "/ts3_servers/" . $port . "/";
            $backupDir = $tsDir . "backups/";
            if (!is_dir($backupDir)) {
                @mkdir($backupDir, 0777, true);
            }

            $qHelper = new TS3QueryHelper('127.0.0.1', $qPort);
            if (empty($qHelper->error) && $qHelper->login('serveradmin', $qPass) && $qHelper->selectServerByPort($port)) {
                $res = $qHelper->execute("serversnapshotcreate");
                if (strpos($res, 'error id=0') !== false) {
                    $snapshotData = trim(preg_replace('/error id=[0-9]+.*$/', '', $res));
                    if (!empty($snapshotData)) {
                        $filename = "backup_" . date('Y-m-d_H-i-s') . ".snapshot";
                        file_put_contents($backupDir . $filename, $snapshotData);
                        $noticeMsg = "Server-Snapshot erfolgreich erstellt: {$filename}";
                    } else {
                        $noticeType = 'danger';
                        $noticeMsg = "Fehler: Snapshot-Daten waren leer.";
                    }
                } else {
                    $noticeType = 'danger';
                    $noticeMsg = "Fehler beim Erstellen des Snapshots: " . trim($res);
                }
            } else {
                $noticeType = 'danger';
                $noticeMsg = "Query-Verbindung zum Server fehlgeschlagen.";
            }
        } else {
            $noticeType = 'danger';
            $noticeMsg = "Server muss online sein und ein Query-Passwort besitzen.";
        }
    } catch (Exception $e) {
        $noticeType = 'danger';
        $noticeMsg = "Fehler: " . $e->getMessage();
    }
    header("Location: content.php?include=admts&view={$viewId}&action=manage&tab=backups");
    exit;
}

if ($actionVal === 'upload_backup' && $viewId > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $server = DB::queryFirstRow("SELECT * FROM ts_servers WHERE id=%i", $viewId);
        if ($server) {
            $port = (int)$server['port'];
            $backupDir = getcwd() . "/ts3_servers/" . $port . "/backups/";
            if (!is_dir($backupDir)) {
                @mkdir($backupDir, 0777, true);
            }
            
            if (isset($_FILES['snapshot_file']) && $_FILES['snapshot_file']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['snapshot_file']['tmp_name'];
                $fileName = basename($_FILES['snapshot_file']['name']);
                
                if (strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) === 'snapshot') {
                    $destPath = $backupDir . $fileName;
                    if (move_uploaded_file($fileTmpPath, $destPath)) {
                        $noticeMsg = "Backup-Datei erfolgreich hochgeladen!";
                    } else {
                        $noticeType = 'danger';
                        $noticeMsg = "Fehler beim Speichern der hochgeladenen Datei.";
                    }
                } else {
                    $noticeType = 'danger';
                    $noticeMsg = "Ungültiges Format. Es sind nur .snapshot Dateien erlaubt.";
                }
            } else {
                $noticeType = 'danger';
                $noticeMsg = "Bitte wähle eine gültige Backup-Datei aus.";
            }
        }
    } catch (Exception $e) {
        $noticeType = 'danger';
        $noticeMsg = "Fehler beim Upload: " . $e->getMessage();
    }
    header("Location: content.php?include=admts&view={$viewId}&action=manage&tab=backups");
    exit;
}

if ($actionVal === 'restore_backup' && $viewId > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $server = DB::queryFirstRow("SELECT * FROM ts_servers WHERE id=%i", $viewId);
        $fileName = basename($_POST['backup_file'] ?? '');
        if ($server && (int)$server['status'] === 1 && !empty($server['query_password']) && !empty($fileName)) {
            $port = (int)$server['port'];
            $qPort = (int)$server['query_port'];
            $qPass = $server['query_password'];
            $filePath = getcwd() . "/ts3_servers/" . $port . "/backups/" . $fileName;

            if (file_exists($filePath)) {
                $snapshotContent = trim(file_get_contents($filePath));
                if (!empty($snapshotContent)) {
                    $qHelper = new TS3QueryHelper('127.0.0.1', $qPort);
                    if (empty($qHelper->error) && $qHelper->login('serveradmin', $qPass) && $qHelper->selectServerByPort($port)) {
                        $res = $qHelper->execute("serversnapshotdeploy " . $snapshotContent);
                        if (strpos($res, 'error id=0') !== false) {
                            $noticeMsg = "Snapshot erfolgreich eingespielt! Räume und Rechte wurden wiederhergestellt.";
                        } else {
                            $noticeType = 'danger';
                            $noticeMsg = "Fehler beim Wiederherstellen: " . trim($res);
                        }
                    } else {
                        $noticeType = 'danger';
                        $noticeMsg = "Query-Verbindung fehlgeschlagen.";
                    }
                }
            } else {
                $noticeType = 'danger';
                $noticeMsg = "Backup-Datei nicht gefunden.";
            }
        } else {
            $noticeType = 'danger';
            $noticeMsg = "Server muss online sein und eine gültige Backup-Datei ausgewählt werden.";
        }
    } catch (Exception $e) {
        $noticeType = 'danger';
        $noticeMsg = "Fehler: " . $e->getMessage();
    }
    header("Location: content.php?include=admts&view={$viewId}&action=manage&tab=backups");
    exit;
}

if ($actionVal === 'delete_backup' && $viewId > 0 && isset($_GET['file'])) {
    try {
        $server = DB::queryFirstRow("SELECT * FROM ts_servers WHERE id=%i", $viewId);
        if ($server) {
            $port = (int)$server['port'];
            $backupDir = getcwd() . "/ts3_servers/" . $port . "/backups/";
            $file = basename($_GET['file']);
            $filePath = $backupDir . $file;
            if (file_exists($filePath)) {
                @unlink($filePath);
                $noticeMsg = "Backup-Datei erfolgreich gelöscht.";
            }
        }
    } catch (Exception $e) {
        $noticeType = 'danger';
        $noticeMsg = "Fehler: " . $e->getMessage();
    }
    header("Location: content.php?include=admts&view={$viewId}&action=manage&tab=backups");
    exit;
}

if ($actionVal === 'download_backup' && $viewId > 0 && isset($_GET['file'])) {
    $server = DB::queryFirstRow("SELECT * FROM ts_servers WHERE id=%i", $viewId);
    if ($server) {
        $port = (int)$server['port'];
        $backupDir = getcwd() . "/ts3_servers/" . $port . "/backups/";
        $file = basename($_GET['file']);
        $filePath = $backupDir . $file;
        if (file_exists($filePath)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            exit;
        }
    }
    exit;
}

// --- CLIENT AKTIONEN ---
if ($actionVal === 'kick' && $viewId > 0 && isset($_GET['clid'])) {
    $server = DB::queryFirstRow("SELECT * FROM ts_servers WHERE id=%i", $viewId);
    $clid = (int)$_GET['clid'];
    if ($server && !empty($server['query_password'])) {
        $qHelper = new TS3QueryHelper('127.0.0.1', (int)$server['query_port']);
        if ($qHelper->login('serveradmin', $server['query_password']) && $qHelper->selectServerByPort((int)$server['port'])) {
            $res = $qHelper->execute("clientkick clid={$clid} reasonid=5 reasonmsg=AdminKick");
            if (strpos($res, 'error id=0') !== false) {
                $noticeMsg = "Benutzer wurde erfolgreich gekickt.";
            } else {
                $noticeType = 'warning';
                $noticeMsg = "Kick fehlgeschlagen: " . trim($res);
            }
        }
    }
    header("Location: content.php?include=admts&view={$viewId}&action=manage&tab=clients");
    exit;
}

if ($actionVal === 'ban_client' && $viewId > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $server = DB::queryFirstRow("SELECT * FROM ts_servers WHERE id=%i", $viewId);
    $clid = (int)($_POST['clid'] ?? 0);
    $banTime = (int)($_POST['ban_time'] ?? 0);
    $banReason = trim($_POST['ban_reason'] ?? 'AdminBan');
    
    if ($server && $clid > 0 && !empty($server['query_password'])) {
        $qHelper = new TS3QueryHelper('127.0.0.1', (int)$server['query_port']);
        if ($qHelper->login('serveradmin', $server['query_password']) && $qHelper->selectServerByPort((int)$server['port'])) {
            $cmd = "banclient clid={$clid}";
            if ($banTime > 0) $cmd .= " time={$banTime}";
            if (!empty($banReason)) $cmd .= " banreason=" . ts3_escape($banReason);
            
            $res = $qHelper->execute($cmd);
            if (strpos($res, 'error id=0') !== false) {
                $noticeMsg = "Benutzer wurde erfolgreich gebannt.";
            } else {
                $noticeType = 'danger';
                $noticeMsg = "Ban fehlgeschlagen: " . trim($res);
            }
        }
    } else {
        $noticeType = 'danger';
        $noticeMsg = "Ban-Fehler: Ungültige Client-ID.";
    }
    header("Location: content.php?include=admts&view={$viewId}&action=manage&tab=clients");
    exit;
}

if ($actionVal === 'move_client' && $viewId > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $server = DB::queryFirstRow("SELECT * FROM ts_servers WHERE id=%i", $viewId);
    $clid = (int)($_POST['clid'] ?? 0);
    $targetCid = (int)($_POST['cid'] ?? 0);
    if ($server && $clid > 0 && $targetCid > 0 && !empty($server['query_password'])) {
        $qHelper = new TS3QueryHelper('127.0.0.1', (int)$server['query_port']);
        if ($qHelper->login('serveradmin', $server['query_password']) && $qHelper->selectServerByPort((int)$server['port'])) {
            $res = $qHelper->execute("clientmove clid={$clid} cid={$targetCid}");
            if (strpos($res, 'error id=0') !== false) {
                $noticeMsg = "Benutzer wurde verschoben.";
            } else {
                $noticeType = 'danger';
                $noticeMsg = "Verschieben fehlgeschlagen: " . trim($res);
            }
        }
    }
    header("Location: content.php?include=admts&view={$viewId}&action=manage&tab=clients");
    exit;
}

if ($actionVal === 'set_servergroup' && $viewId > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $server = DB::queryFirstRow("SELECT * FROM ts_servers WHERE id=%i", $viewId);
    $cldbid = (int)($_POST['cldbid'] ?? 0);
    $sgid = (int)($_POST['sgid'] ?? 0);
    $clearOld = isset($_POST['clear_old_groups']) ? true : false;

    if ($server && $cldbid > 0 && $sgid > 0 && !empty($server['query_password'])) {
        $qHelper = new TS3QueryHelper('127.0.0.1', (int)$server['query_port']);
        if ($qHelper->login('serveradmin', $server['query_password']) && $qHelper->selectServerByPort((int)$server['port'])) {
            
            if ($clearOld) {
                $resGroups = $qHelper->execute("servergroupsbyclientid cldbid={$cldbid}");
                preg_match_all('/sgid=([0-9]+)/', $resGroups, $matches);
                if (!empty($matches[1])) {
                    foreach ($matches[1] as $oldSgid) {
                        @$qHelper->execute("servergroupdelclient sgid={$oldSgid} cldbid={$cldbid}");
                    }
                }
            }

            $res = $qHelper->execute("servergroupaddclient sgid={$sgid} cldbid={$cldbid}");
            if (strpos($res, 'error id=0') !== false) {
                $noticeMsg = "Servergruppe erfolgreich zugewiesen!";
            } else {
                $noticeType = 'warning';
                $noticeMsg = "Hinweis / Zuweisung: " . trim($res);
            }
        }
    }
    header("Location: content.php?include=admts&view={$viewId}&action=manage&tab=clients");
    exit;
}

if ($actionVal === 'set_channelgroup' && $viewId > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $server = DB::queryFirstRow("SELECT * FROM ts_servers WHERE id=%i", $viewId);
    $cldbid = (int)($_POST['cldbid'] ?? 0);
    $cgid = (int)($_POST['cgid'] ?? 0);
    $targetCid = (int)($_POST['cid'] ?? 0);
    if ($server && $cldbid > 0 && $cgid > 0 && $targetCid > 0 && !empty($server['query_password'])) {
        $qHelper = new TS3QueryHelper('127.0.0.1', (int)$server['query_port']);
        if ($qHelper->login('serveradmin', $server['query_password']) && $qHelper->selectServerByPort((int)$server['port'])) {
            $res = $qHelper->execute("setclientchannelgroup cgid={$cgid} cid={$targetCid} cldbid={$cldbid}");
            if (strpos($res, 'error id=0') !== false) {
                $noticeMsg = "Channelgruppe erfolgreich zugewiesen.";
            } else {
                $noticeType = 'danger';
                $noticeMsg = "Fehler: " . trim($res);
            }
        }
    }
    header("Location: content.php?include=admts&view={$viewId}&action=manage&tab=clients");
    exit;
}

// --- KANAL ERSTELLEN ---
if ($actionVal === 'create_channel' && $viewId > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $server = DB::queryFirstRow("SELECT * FROM ts_servers WHERE id=%i", $viewId);
    if ($server && !empty($server['query_password'])) {
        $qHelper = new TS3QueryHelper('127.0.0.1', (int)$server['query_port']);
        if ($qHelper->login('serveradmin', $server['query_password']) && $qHelper->selectServerByPort((int)$server['port'])) {
            
            $cName = trim($_POST['channel_name'] ?? '');
            $cPassword = trim($_POST['channel_password'] ?? '');
            $cTopic = trim($_POST['channel_topic'] ?? '');
            $cDesc = trim($_POST['channel_description'] ?? '');
            $cType = (int)($_POST['channel_type'] ?? 0);
            $cOrder = (int)($_POST['cpid'] ?? 0);
            $cTalkPower = (int)($_POST['channel_needed_talk_power'] ?? 0);
            $cIconId = (int)($_POST['channel_icon_id'] ?? 0);
            $isDefault = isset($_POST['channel_flag_default']) ? 1 : 0;
            
            $cmd = "channelcreate channel_name=" . ts3_escape($cName);
            if (!empty($cPassword)) $cmd .= " channel_password=" . ts3_escape($cPassword);
            if (!empty($cTopic)) $cmd .= " channel_topic=" . ts3_escape($cTopic);
            if (!empty($cDesc)) $cmd .= " channel_description=" . ts3_escape($cDesc);
            if ($cIconId > 0) $cmd .= " channel_icon_id={$cIconId}";
            
            if ($cType === 2) {
                $cmd .= " channel_flag_permanent=1 channel_flag_semi_permanent=0";
            } elseif ($cType === 1) {
                $cmd .= " channel_flag_permanent=0 channel_flag_semi_permanent=1";
            } else {
                $cmd .= " channel_flag_permanent=0 channel_flag_semi_permanent=0";
            }
            
            if ($isDefault) $cmd .= " channel_flag_default=1";
            if ($cOrder > 0) $cmd .= " cpid={$cOrder}";
            
            $cmd .= " channel_needed_talk_power={$cTalkPower}";

            $res = $qHelper->execute($cmd);
            if (strpos($res, 'error id=0') !== false) {
                $noticeMsg = "Raum erfolgreich erstellt!";
            } else {
                $noticeType = 'danger';
                $noticeMsg = "Fehler beim Erstellen: " . trim($res);
            }
        }
    }
    header("Location: content.php?include=admts&view={$viewId}&action=manage&tab=channels");
    exit;
}

// --- KANAL BEARBEITEN ---
if ($actionVal === 'edit_channel' && $viewId > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $server = DB::queryFirstRow("SELECT * FROM ts_servers WHERE id=%i", $viewId);
    $cid = (int)($_POST['cid'] ?? 0);
    
    if ($server && $cid > 0 && !empty($server['query_password'])) {
        $qHelper = new TS3QueryHelper('127.0.0.1', (int)$server['query_port']);
        if ($qHelper->login('serveradmin', $server['query_password']) && $qHelper->selectServerByPort((int)$server['port'])) {
            
            $cName        = trim($_POST['channel_name'] ?? '');
            $cPassword    = $_POST['channel_password'] ?? '';
            $cTopic       = trim($_POST['channel_topic'] ?? '');
            $cDesc        = trim($_POST['channel_description'] ?? '');
            $cIconId      = (int)($_POST['channel_icon_id'] ?? 0);
            $isDefault    = isset($_POST['channel_flag_default']) ? 1 : 0;
            $cOrder       = (int)($_POST['cpid'] ?? 0);
            $cTalkPower   = (int)($_POST['channel_needed_talk_power'] ?? 0);
            $cType        = (int)($_POST['channel_type'] ?? 0);

            if ($cName !== '') {
                $qHelper->execute("channeledit cid={$cid} channel_name=" . ts3_escape($cName));
            }

            if (isset($_POST['channel_password'])) {
                $passParam = ($cPassword !== '') ? "channel_password=" . ts3_escape($cPassword) : "channel_password=";
                $qHelper->execute("channeledit cid={$cid} {$passParam}");
            }

            $qHelper->execute("channeledit cid={$cid} channel_topic=" . ts3_escape($cTopic));
            $qHelper->execute("channeledit cid={$cid} channel_description=" . ts3_escape($cDesc));
            $qHelper->execute("channeledit cid={$cid} channel_icon_id={$cIconId}");
            $qHelper->execute("channeledit cid={$cid} channel_flag_default={$isDefault}");
            $qHelper->execute("channeledit cid={$cid} cpid={$cOrder}");
            $qHelper->execute("channeledit cid={$cid} channel_needed_talk_power={$cTalkPower}");

            if ($cType === 2) {
                $qHelper->execute("channeledit cid={$cid} channel_flag_permanent=1 channel_flag_semi_permanent=0");
            } elseif ($cType === 1) {
                $qHelper->execute("channeledit cid={$cid} channel_flag_permanent=0 channel_flag_semi_permanent=1");
            } else {
                $qHelper->execute("channeledit cid={$cid} channel_flag_permanent=0 channel_flag_semi_permanent=0");
            }

            $noticeMsg = "Raum erfolgreich aktualisiert!";
        }
    }
    header("Location: content.php?include=admts&view={$viewId}&action=manage&tab=channels");
    exit;
}

if ($actionVal === 'delete_channel' && $viewId > 0 && isset($_GET['cid'])) {
    $server = DB::queryFirstRow("SELECT * FROM ts_servers WHERE id=%i", $viewId);
    $cid = (int)$_GET['cid'];
    if ($server && $cid > 0 && !empty($server['query_password'])) {
        $qHelper = new TS3QueryHelper('127.0.0.1', (int)$server['query_port']);
        if ($qHelper->login('serveradmin', $server['query_password']) && $qHelper->selectServerByPort((int)$server['port'])) {
            $res = $qHelper->execute("channeldelete cid={$cid} force=1");
            if (strpos($res, 'error id=0') !== false) {
                $noticeMsg = "Kanal erfolgreich gelöscht.";
            } else {
                $noticeType = 'danger';
                $noticeMsg = "Löschen fehlgeschlagen: " . trim($res);
            }
        }
    }
    header("Location: content.php?include=admts&view={$viewId}&action=manage&tab=channels");
    exit;
}

if ($actionVal === 'token' && $viewId > 0) {
    try {
        $server = DB::queryFirstRow("SELECT * FROM ts_servers WHERE id=%i", $viewId);
        if ($server) {
            $port = (int)$server['port'];
            $qPort = (int)$server['query_port'];
            $qPass = $server['query_password'] ?? '';
            $tsDir = getcwd() . "/ts3_servers/" . $port . "/";
            
            $tokenResult = getTsToken($tsDir, $qPort, $port, $qPass);
            if (empty($tokenResult)) {
                $noticeType = 'warning';
                $noticeMsg = "Token konnte nicht ermittelt werden. Bitte sicherstellen, dass der Server gestartet ist.";
            } else {
                $noticeMsg = "Admin-Token erfolgreich bereitgestellt!";
            }
        }
    } catch (Exception $e) {
        $noticeType = 'danger';
        $noticeMsg = "Fehler: " . $e->getMessage();
    }
}

if ($actionVal === 'delete' && $viewId > 0) {
    try {
        $server = DB::queryFirstRow("SELECT * FROM ts_servers WHERE id=%i", $viewId);
        if ($server) {
            $port = (int)$server['port'];
            $tsDir = getcwd() . "/ts3_servers/" . $port . "/";
            
            if (file_exists($tsDir . "ts3server_startscript.sh")) {
                @shell_exec("cd " . escapeshellarg($tsDir) . " && ./ts3server_startscript.sh stop");
            }
            
            sleep(1);
            if (is_dir($tsDir)) {
                $it = new RecursiveDirectoryIterator($tsDir, RecursiveDirectoryIterator::SKIP_DOTS);
                $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
                foreach($files as $file) {
                    if ($file->isDir()) @rmdir($file->getRealPath()); else @unlink($file->getRealPath());
                }
                @rmdir($tsDir);
            }
            DB::delete('ts_servers', 'id=%i', $viewId);
            $noticeMsg = "TeamSpeak Server wurde gelöscht.";
            $actionVal = ''; 
        }
    } catch (Exception $e) {
        $noticeType = 'danger';
        $noticeMsg = "Fehler beim Löschen: " . $e->getMessage();
    }
}

// --- TEAMSPEAK VIEWER & EMBED DATEN ABRUFEN ---
$viewerChannels = [];
$viewerClients = [];
$viewerError = '';

if ($actionVal === 'embed_view' && $viewId > 0) {
    @include("admts_bottom.php");
    exit;
}

if ($actionVal === 'manage' && $viewId > 0 && $activeTab === 'viewer') {
    $activeServer = DB::queryFirstRow("SELECT * FROM ts_servers WHERE id=%i", $viewId);
    if ($activeServer && (int)$activeServer['status'] === 1 && !empty($activeServer['query_password'])) {
        $qPort = (int)$activeServer['query_port'];
        $sPort = (int)$activeServer['port'];
        $qPass = $activeServer['query_password'];

        $tSocket = @fsockopen('127.0.0.1', $qPort, $errno, $errstr, 1);
        if ($tSocket) {
            @stream_set_timeout($tSocket, 1);
            @fgets($tSocket, 1024);
            @fgets($tSocket, 1024);
            
            fputs($tSocket, "login serveradmin {$qPass}\n");
            @fgets($tSocket, 1024);
            
            fputs($tSocket, "use port={$sPort}\n");
            @fgets($tSocket, 1024);
            
            fputs($tSocket, "channellist -topic -flags\n");
            $cRes = '';
            while ($line = @fgets($tSocket, 1024)) {
                $cRes .= $line;
                if (strpos($line, 'error id=') !== false) break;
            }
            foreach (explode('|', trim(preg_replace('/error id=[0-9]+.*$/', '', $cRes))) as $item) {
                if (empty($item)) continue;
                $p = [];
                foreach (explode(' ', trim($item)) as $pair) {
                    $kv = explode('=', $pair, 2);
                    if (count($kv) === 2) $p[$kv[0]] = rawurldecode($kv[1]);
                }
                if (isset($p['cid'], $p['channel_name'])) {
                    $viewerChannels[] = $p;
                }
            }
            
            fputs($tSocket, "clientlist -uid -away -groups\n");
            $clRes = '';
            while ($line = @fgets($tSocket, 1024)) {
                $clRes .= $line;
                if (strpos($line, 'error id=') !== false) break;
            }
            foreach (explode('|', trim(preg_replace('/error id=[0-9]+.*$/', '', $clRes))) as $item) {
                if (empty($item)) continue;
                $p = [];
                foreach (explode(' ', trim($item)) as $pair) {
                    $kv = explode('=', $pair, 2);
                    if (count($kv) === 2) $p[$kv[0]] = rawurldecode($kv[1]);
                }
                if (isset($p['client_nickname']) && (int)($p['client_type'] ?? 0) !== 1) {
                    $viewerClients[] = $p;
                }
            }
            
            fputs($tSocket, "quit\n");
            @fclose($tSocket);
        } else {
            $viewerError = "Query-Verbindung fehlgeschlagen.";
        }
    } else {
        $viewerError = "Server ist offline oder kein Query-Passwort vorhanden.";
    }
}