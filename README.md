# Shoutcast Admin Area - ehemals Shoutcast Admin Panel

Ein modernes, leichtgewichtiges und responsives Administrationspanel für Shoutcast-Streaming-Server und AutoDJ-Steuerung – frisch modernisiert für **PHP 8.x**, **MeekroDB / PDO** und **Bootstrap 5**.

---

## 🚀 Hauptmerkmale & Modernisierungen

- **Modernes UI/UX**: Komplett überarbeitet im sauberen **Bootstrap 5** Design mit dunkler, übersichtlicher Sidebar, Karten-Layouts und absolut zentrierten Schnellzugriffen.
- **Responsives Layout**: Integriertes Hamburgermenü – auf kleinen Bildschirmen (Handys/Tablets) automatisch eingeklappt und auf großen Bildschirmen per Klick ein- und ausblendbar.
- **PHP 8.x Ready**: Vollständig optimiert für moderne PHP-Versionen mit strikter Typisierung (`declare(strict_types=1);`).
- **Sicherer Installer (`install.php`)**: Moderner PDO-basierter Einrichtungsassistent mit integriertem System-Check (Prüfung von PHP-Extensions, Dateirechten und Binärdateien).
- **Zentraler Controller (`content.php`)**: Modulare Seitenstruktur mit bereinigter Navigation, Sprachunterstützung und Echtzeit-Systeminformationen.

---

## 📋 Systemanforderungen

- **PHP**: Version 8.0 oder höher
- **Datenbank**: MySQL / MariaDB (via MeekroDB & PDO)
- **Erforderliche PHP-Extensions**: 
  - `PDO` / `MySQLi`
  - `SSH2` (für Server- und AutoDJ-Steuerung)
- **Server**: Linux-Umgebung (empfohlen für Shoutcast `sc_serv` / `sc_trans`)

---

## 🛠️ Installation

1. Lade alle Panel-Dateien auf deinen Webspace hoch.
2. Benenne die blank_database_php in database_php um und geb ihr Schreibrechte (777).
3. Stelle sicher, dass folgende Ordner und Dateien beschreibbar sind bzw. existieren:
   - `/temp/` (beschreibbar)
   - `/uploads/` (beschreibbar)
4. Rufe den Installer in deinem Browser auf:
   [http://deine-domain.de/install.php](http://deine-domain.de/install.php)
5. Wichtig: Lösche die Datei install.php aus Sicherheitsgründen nach erfolgreicher Einrichtung von deinem Server und gib der database.php die Rechte 666.