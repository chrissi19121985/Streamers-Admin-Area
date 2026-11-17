<?php
/**
 * Streamers Admin Panel - Update Management (Top)
 */

declare(strict_types=1);

if (stripos($_SERVER['PHP_SELF'], 'content.php') === false) {
    die("You can't access this file directly...");
}

// Nur für Super Administratoren erlaubt
if (!isset($_SESSION['user_level']) || $_SESSION['user_level'] !== 'Super Administrator') {
    header('Location: content.php?include=main');
    exit;
}