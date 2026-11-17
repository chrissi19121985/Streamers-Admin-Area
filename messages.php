<?php
/**
 * Streamers Admin Panel - Modernisiert für PHP 8.x
 *
 * Ursprünglich entwickelt von Sebastian Graebner <djcrackhome> & David Schomburg <dave>
 * Modernisiert & abgesichert gegen SQL-Injections und XSS
 */

declare(strict_types=1);

if (!@include("database.php")) {
    die("database.php could not be loaded!");
}

if (empty($db_host)) {
    die("Please reinstall this panel!");
}

// 1. Sichere Datenbankverbindung via PDO herstellen
try {
    $dsn = "mysql:host={$db_host};dbname={$database};charset=utf8mb4";
    $pdo = new PDO($dsn, $db_username, $db_password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

// 2. Sprach-Einstellung aus der Datenbank abfragen (Prepared Statement)
$language_setting = 'english';
try {
    $stmt = $pdo->prepare("SELECT language FROM settings WHERE id = :id");
    $stmt->execute(['id' => 0]);
    $row = $stmt->fetch();
    if ($row && !empty($row['language'])) {
        $language_setting = $row['language'];
    }
} catch (PDOException $e) {
    // Falls Tabelle oder Einstellung fehlt, greift der Fallback auf 'english'
}

// 3. Sprachdatei validieren und einbinden
$language_setting = basename(preg_replace('/[^a-zA-Z0-9_\-]/', '', $language_setting));
$lang_file = "./pages/messages/" . $language_setting . ".php";

if (file_exists($lang_file)) {
    include $lang_file;
} else {
    $errors[] = "<h2>The language file could not be found, English is the default language!</h2>";
    $language_setting = "english";
    include "./pages/messages/english.php";
}

// 4. Parameter 'id' sauber abfangen und validieren
$id = $_GET['id'] ?? 'error';
$id = trim((string)$id);

// 5. Benachrichtigung / Notice anzeigen
if ($id === 'error' || $id === '') {
    echo "<h2>" . htmlspecialchars($messages["50"] ?? 'An error occurred', ENT_QUOTES, 'UTF-8') . "</h2>";
} else {
    // Sichere Abfrage mit Prepared Statements gegen SQL-Injection
    $stmt = $pdo->prepare("SELECT username, reason, message, ip FROM notices WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $notice = $stmt->fetch();

    if ($notice) {
        $username = htmlspecialchars((string)($notice['username'] ?? ''), ENT_QUOTES, 'UTF-8');
        $reason   = htmlspecialchars((string)($notice['reason'] ?? ''), ENT_QUOTES, 'UTF-8');
        $message  = nl2br(htmlspecialchars((string)($notice['message'] ?? ''), ENT_QUOTES, 'UTF-8'));
        $ip       = htmlspecialchars((string)($notice['ip'] ?? ''), ENT_QUOTES, 'UTF-8');

        echo "<h2>" . htmlspecialchars($messages["51"] ?? 'Notice for', ENT_QUOTES, 'UTF-8') . " {$username}<hr /></h2>";
        echo "<h3>" . htmlspecialchars($messages["52"] ?? 'Reason', ENT_QUOTES, 'UTF-8') . ": <i>{$reason}</i></h3>";
        echo "<h3>" . htmlspecialchars($messages["53"] ?? 'Message', ENT_QUOTES, 'UTF-8') . ": {$message}</h3>";
        echo "<p>[{$ip}]</p>";
    } else {
        echo "<h2>" . htmlspecialchars($messages["50"] ?? 'Notice not found', ENT_QUOTES, 'UTF-8') . "</h2>";
    }
}