<?php
/**
 * Streamers Admin Panel - Captcha Generator (Modernisiert für PHP 8.x)
 *
 * Originally written by Sebastian Graebner <djcrackhome>[cite: 18]
 * Fixed and edited by David Schomburg <dave>[cite: 18]
 */

declare(strict_types=1);

session_start();

$bgPath = 'captcha_bg.jpg';
if (file_exists($bgPath)) {
    $img = imagecreatefromjpeg($bgPath);
} else {
    // Fallback falls kein Hintergrundbild existiert (z.B. 120x40 Canvas)
    $img = imagecreatetruecolor(120, 40);
    $bgColor = imagecolorallocate($img, 240, 240, 240);
    imagefill($img, 0, 0, $bgColor);
}

if ($img) {
    $textColor = imagecolorallocate($img, 255 - rand(100, 255), 255 - rand(100, 255), 255 - rand(100, 255));
    $fontPath = "../css/font_source/delicious-roman-webfont.ttf";
    $captchaText = empty($_SESSION['captcha_streamerspanel']) ? 'error' : $_SESSION['captcha_streamerspanel'];

    if (file_exists($fontPath)) {
        imagettftext($img, 15, rand(-10, 10), rand(5, 80), rand(20, 21), $textColor, $fontPath, $captchaText);
    } else {
        // Fallback auf Built-in Font, falls TTF-Datei fehlt
        imagestring($img, 5, 10, 10, $captchaText, $textColor);
    }

    header("Content-type: image/jpeg");
    header("Content-Disposition: inline; filename=secure.jpg");
    imagejpeg($img);
    imagedestroy($img);
}