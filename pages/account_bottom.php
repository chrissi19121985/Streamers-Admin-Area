<?php
/**
 * Streamers Admin Panel - User Profile View & Form (Bottom)
 *
 * Vollständig angepasst an den hellen und dunklen Modus.
 *
 * Modernisiert für PHP 8.x, MeekroDB & Bootstrap 5
 * Originally written by Sebastian Graebner <djcrackhome>
 * Fixed and edited by David Schomburg <dave> & Shippo21
 */

declare(strict_types=1);

if (stripos($_SERVER['PHP_SELF'], 'content.php') === false) {
    die("You can't access this file directly...");
}

// Hilfsfunktion für Nachrichten
$msg = static function(string $key, string $default = '') use ($messages): string {
    return $messages[$key] ?? $default;
};

// Benutzerdaten über MeekroDB und den Hash sicher abrufen
$userdata = [];
$hashVal = $hash ?? '';
if (!empty($hashVal)) {
    try {
        $userRow = DB::queryFirstRow("SELECT * FROM users WHERE md5_hash=%s", $hashVal);
        if ($userRow) {
            foreach ($userRow as $key => $pref) {
                if (!is_numeric((string)$key)) {
                    $userdata[$key] = ($pref !== null && $pref !== "") ? (string)$pref : "";
                }
            }
        }
    } catch (Exception $e) {
        // Fehler abfangen
    }
}
?>

<!-- Header / Titel -->
<div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom" style="border-color: var(--border-color) !important;">
    <h2 class="h4 mb-0 fw-bold" style="color: var(--text-primary);">
        <i class="fa-solid fa-user-gear text-primary me-2"></i><?php echo htmlspecialchars($msg("54", "User Account"), ENT_QUOTES, 'UTF-8'); ?>
    </h2>
</div>

<div class="row g-4">
    <!-- Formular-Bereich -->
    <div class="col-lg-8">
        <div class="card shadow-sm border-0" style="background-color: var(--bg-card); color: var(--text-primary); border-color: var(--border-color) !important;">
            <div class="card-body p-4">
                <form method="post" action="content.php?include=account">
                    <fieldset class="border-0 p-0 m-0">
                        <legend class="visually-hidden"><?php echo htmlspecialchars($msg("58", "Account Settings"), ENT_QUOTES, 'UTF-8'); ?></legend>

                        <div class="row g-3">
                            <!-- Username -->
                            <div class="col-md-6">
                                <label for="username" class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("59", "Username"), ENT_QUOTES, 'UTF-8'); ?></label>
                                <input class="form-control" style="background-color: var(--bg-surface); color: var(--text-primary); border-color: var(--border-color);" id="username" name="username" type="text" value="<?php echo htmlspecialchars($userdata['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" disabled />
                                <div class="form-text small" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("60", "Username cannot be changed"), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <!-- User Level -->
                            <div class="col-md-6">
                                <label for="level" class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("65", "User Level"), ENT_QUOTES, 'UTF-8'); ?></label>
                                <input class="form-control" style="background-color: var(--bg-surface); color: var(--text-primary); border-color: var(--border-color);" id="level" name="level" type="text" value="<?php echo htmlspecialchars($userdata['user_level'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" disabled />
                                <div class="form-text small" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("66", "Your role"), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <!-- Password -->
                            <div class="col-md-6">
                                <label for="u_user_password" class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("61", "Password"), ENT_QUOTES, 'UTF-8'); ?></label>
                                <input class="form-control" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" id="u_user_password" name="u_user_password" type="text" value="<?php echo htmlspecialchars($userdata['user_password'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                                <div class="form-text small" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("62", "Enter new password if you want to change it"), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <!-- Confirm Password -->
                            <div class="col-md-6">
                                <label for="u_cuser_password" class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("63", "Confirm Password"), ENT_QUOTES, 'UTF-8'); ?></label>
                                <input class="form-control" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" id="u_cuser_password" name="u_cuser_password" type="password" value="" />
                                <div class="form-text small" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("64", "Confirm new password"), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <!-- First Name -->
                            <div class="col-md-6">
                                <label for="u_name" class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("67", "First Name"), ENT_QUOTES, 'UTF-8'); ?></label>
                                <input class="form-control" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" id="u_name" name="u_name" type="text" value="<?php echo htmlspecialchars($userdata['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                                <div class="form-text small" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("68", "Your first name"), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <!-- Surname -->
                            <div class="col-md-6">
                                <label for="u_surname" class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("69", "Surname"), ENT_QUOTES, 'UTF-8'); ?></label>
                                <input class="form-control" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" id="u_surname" name="u_surname" type="text" value="<?php echo htmlspecialchars($userdata['surname'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                                <div class="form-text small" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("70", "Your surname"), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <!-- Email -->
                            <div class="col-md-6">
                                <label for="u_user_email" class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("71", "Email"), ENT_QUOTES, 'UTF-8'); ?></label>
                                <input class="form-control" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" id="u_user_email" name="u_user_email" type="email" value="<?php echo htmlspecialchars($userdata['user_email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                                <div class="form-text small" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("72", "Your email address"), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <!-- Age -->
                            <div class="col-md-6">
                                <label for="u_age" class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("77", "Age"), ENT_QUOTES, 'UTF-8'); ?></label>
                                <input class="form-control" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" id="u_age" name="u_age" type="number" value="<?php echo htmlspecialchars($userdata['age'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                                <div class="form-text small" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("78", "Your age"), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <!-- Contact Number -->
                            <div class="col-md-6">
                                <label for="u_contact_number" class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("73", "Contact Number"), ENT_QUOTES, 'UTF-8'); ?></label>
                                <input class="form-control" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" id="u_contact_number" name="u_contact_number" type="text" value="<?php echo htmlspecialchars($userdata['contact_number'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                                <div class="form-text small" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("74", "Phone number"), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <!-- Mobile Number -->
                            <div class="col-md-6">
                                <label for="u_mobile_number" class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("75", "Mobile Number"), ENT_QUOTES, 'UTF-8'); ?></label>
                                <input class="form-control" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" id="u_mobile_number" name="u_mobile_number" type="text" value="<?php echo htmlspecialchars($userdata['mobile_number'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                                <div class="form-text small" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("76", "Mobile phone number"), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <!-- Account Notes -->
                            <div class="col-12">
                                <label for="admin" class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($msg("79", "Account Notes"), ENT_QUOTES, 'UTF-8'); ?></label>
                                <input class="form-control" style="background-color: var(--bg-surface); color: var(--text-primary); border-color: var(--border-color);" id="admin" name="admin" type="text" value="<?php echo htmlspecialchars(strip_tags($userdata['account_notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" disabled />
                                <div class="form-text small" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("80", "Admin notes"), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="mt-4">
                            <button class="btn btn-primary px-4" type="submit" name="submit">
                                <i class="fa-solid fa-floppy-disk me-1"></i> <?php echo htmlspecialchars($msg("81", "Save Changes"), ENT_QUOTES, 'UTF-8'); ?>
                            </button>
                        </div>
                    </fieldset>
                </form>
            </div>
        </div>
    </div>

    <!-- Info Sidebar Widget -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 h-100" style="background-color: var(--bg-card); border: 1px solid var(--border-color) !important;">
            <div class="card-body p-4">
                <h5 class="card-title fw-bold mb-3 text-primary">
                    <i class="fa-solid fa-circle-info me-1"></i> <?php echo htmlspecialchars($msg("56", "Information"), ENT_QUOTES, 'UTF-8'); ?>
                </h5>
                <p class="card-text mb-0" style="color: var(--text-muted);"><?php echo htmlspecialchars($msg("57", "Update your account details and password here."), ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </div>
    </div>
</div>