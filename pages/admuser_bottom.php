<?php
/**
 * Streamers Admin Panel - Admin User Management View & Form (Bottom)
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

$userLevelSession = $_SESSION['user_level'] ?? '';
$userTbIdSession = $_SESSION['user_tb_id'] ?? 0;
$portSafe = isset($port) ? htmlspecialchars((string)$port, ENT_QUOTES, 'UTF-8') : '';

$limit = isset($setting['display_limit']) ? (int)$setting['display_limit'] : 10;
$p = 0;
if (isset($_GET['p']) && is_numeric($_GET['p'])) {
    $p = max(0, (int)$_GET['p'] * $limit);
}
$l = $p + $limit;

// Benutzer aus Datenbank via MeekroDB laden
try {
    if ($userLevelSession === 'Super Administrator') {
        $get_users = DB::query("SELECT * FROM users ORDER BY id ASC LIMIT %i, %i", $p, $limit);
    } else {
        $get_users = DB::query("SELECT * FROM users WHERE dj_of_user = %i ORDER BY id ASC LIMIT %i, %i", $userTbIdSession, $p, $limit);
    }
} catch (Exception $e) {
    $get_users = [];
}
?>

<!-- Header / Titel -->
<div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom" style="border-color: var(--border-color) !important;">
    <h2 class="h4 mb-0 fw-bold" style="color: var(--text-primary);">
        <i class="fa-solid fa-users text-primary me-2"></i>
        <?php echo htmlspecialchars($messages["228"] ?? 'User Management', ENT_QUOTES, 'UTF-8'); ?> <?php echo $portSafe; ?>
    </h2>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 mb-4" style="background-color: var(--bg-card); color: var(--text-primary); border-color: var(--border-color) !important;">
            <div class="card-body p-4">
                
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title fw-bold small text-uppercase mb-0" style="color: var(--text-muted);">
                        <i class="fa-solid fa-list me-1 text-warning"></i> <?php echo htmlspecialchars($messages["229"] ?? 'Panel-Benutzer verwalten', ENT_QUOTES, 'UTF-8'); ?>
                    </h5>
                    <a class="btn btn-sm btn-success" href="content.php?include=admuser&action=newuser">
                        <i class="fa-solid fa-plus me-1"></i> <?php echo htmlspecialchars($messages["234"] ?? 'Neuer Benutzer', ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                </div>

                <?php
                if (empty($get_users)) {
                    echo '<div class="text-center py-4" style="color: var(--text-muted);">Keine Benutzer gefunden.</div>';
                } else {
                    echo '<div class="d-flex flex-column gap-3">';
                    foreach ($get_users as $data) {
                        $usernameSafe = htmlspecialchars($data['username'], ENT_QUOTES, 'UTF-8');
                        $userId = (int)$data['id'];
                        $userLevelType = htmlspecialchars($data['user_level'] ?? 'User', ENT_QUOTES, 'UTF-8');
                        
                        try {
                            $serverCount = DB::queryFirstField("SELECT COUNT(*) FROM servers WHERE owner=%s", $data['username']);
                        } catch (Exception $e) {
                            $serverCount = 0;
                        }
                        ?>
                        <div class="card border p-3 shadow-sm rounded-3" style="background-color: var(--bg-card); border-color: var(--border-color) !important;">
                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-2 pb-2 border-bottom" style="border-color: var(--border-color) !important;">
                                <div class="d-flex align-items-center gap-3">
                                    <span class="fw-bold fs-6" style="color: var(--text-primary);"><i class="fa-solid fa-user text-primary me-1"></i> <?php echo $usernameSafe; ?></span>
                                    <span class="badge bg-secondary px-2 py-1"><?php echo $userLevelType; ?></span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="small" style="color: var(--text-muted);">Server: <strong style="color: var(--text-primary);"><?php echo (int)$serverCount; ?></strong></span>
                                </div>
                            </div>

                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 my-2">
                                <div class="small" style="color: var(--text-muted);">
                                    Status: <span class="fw-semibold" style="color: var(--text-primary);">Aktiv</span>
                                </div>

                                <div class="d-flex flex-wrap gap-2 justify-content-end align-items-center">
                                    <a class="btn btn-sm btn-outline-secondary px-3" href="content.php?include=admuser&action=edit&id=<?php echo $userId; ?>" title="Bearbeiten" style="border-color: var(--border-color); color: var(--text-primary);">
                                        <i class="fa-solid fa-pen-to-square me-1"></i> Bearbeiten
                                    </a>
                                    <a class="btn btn-sm btn-outline-danger px-3" href="content.php?include=admuser&action=edit&id=<?php echo $userId; ?>&function=delete" onclick="return confirm('Sind Sie sicher?')" title="L&ouml;schen">
                                        <i class="fa-solid fa-trash me-1"></i> L&ouml;schen
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                    echo '</div>';
                }
                ?>

                <!-- Pagination -->
                <?php
                try {
                    $totalUsers = DB::queryFirstField("SELECT COUNT(*) FROM users");
                    $totalPages = ceil((int)$totalUsers / $limit);
                    
                    if ($totalPages > 1) {
                        echo '<nav aria-label="User Pagination" class="mt-4"><ul class="pagination justify-content-center mb-0">';
                        for ($i = 0; $i < $totalPages; $i++) {
                            $activeClass = (($p / $limit) === $i) ? ' active' : '';
                            echo '<li class="page-item' . $activeClass . '"><a class="page-link" href="content.php?include=admuser&p=' . $i . '">' . ($i + 1) . '</a></li>';
                        }
                        echo '</ul></nav>';
                    }
                } catch (Exception $e) {}
                ?>
            </div>
        </div>

        <!-- USER EDIT / NEW FORM -->
        <?php 
        $actionVal = $_GET['action'] ?? '';
        $userIdGet = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $userCheckVal = $user_check ?? '';

        if (($actionVal === "edit" && $userCheckVal !== "1") || $actionVal === "newuser") {
            $userdata = [];
            if ($actionVal !== "newuser" && $userIdGet > 0) {
                try {
                    $userq = DB::queryFirstRow("SELECT * FROM users WHERE id=%i", $userIdGet);
                    if ($userq) {
                        foreach ($userq as $key => $pref) {
                            if (!is_numeric((string)$key)) {
                                $userdata[$key] = ($pref !== "") ? $pref : "none";
                            }
                        }
                    }
                } catch (Exception $e) {}
            }
        ?>
        <div class="card shadow-sm border-0" style="background-color: var(--bg-card); color: var(--text-primary); border-color: var(--border-color) !important;">
            <div class="card-body p-4">
                <h5 class="card-title fw-bold mb-3 small text-uppercase" style="color: var(--text-muted);">
                    <i class="fa-solid fa-user-pen me-1 text-warning"></i> 
                    <?php echo htmlspecialchars(($actionVal === "newuser") ? ($messages["238"] ?? 'Benutzer erstellen') : ($messages["239"] ?? 'Benutzer bearbeiten'), ENT_QUOTES, 'UTF-8'); ?>
                </h5>

                <form method="post" action="content.php?include=admuser&action=<?php echo ($actionVal === "newuser") ? "newuser" : "edit"; ?>&function=update<?php echo ($actionVal === "edit") ? "&id=" . $userIdGet : ""; ?>">
                    <fieldset class="border-0 p-0 m-0">
                        <legend class="visually-hidden"><?php echo htmlspecialchars($messages["240"] ?? 'Benutzerdetails', ENT_QUOTES, 'UTF-8'); ?></legend>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($messages["241"] ?? 'Benutzername', ENT_QUOTES, 'UTF-8'); ?></label>
                                <input class="form-control" name="eusername" type="text" style="background-color: <?php echo ($actionVal === "edit") ? 'var(--bg-surface)' : 'var(--bg-base)'; ?>; color: var(--text-primary); border-color: var(--border-color);" value="<?php echo ($actionVal === "edit") ? htmlspecialchars($userdata['username'] ?? '', ENT_QUOTES, 'UTF-8') : ''; ?>" <?php echo ($actionVal === "edit") ? 'readonly' : ''; ?> />
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($messages["242"] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($messages["243"] ?? 'Passwort', ENT_QUOTES, 'UTF-8'); ?></label>
                                <input class="form-control" name="euser_password" type="text" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo ($actionVal === "edit") ? htmlspecialchars($userdata['user_password'] ?? '', ENT_QUOTES, 'UTF-8') : ''; ?>" />
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($messages["244"] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <?php if ($actionVal === "newuser") {?>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($messages["245"] ?? 'Passwort best&auml;tigen', ENT_QUOTES, 'UTF-8'); ?></label>
                                <input class="form-control" name="ceuser_password" type="password" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="" />
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($messages["246"] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                            <?php } ?>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($messages["265"] ?? 'Benutzerlevel', ENT_QUOTES, 'UTF-8'); ?></label>
                                <select class="form-select" name="euser_level" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);">
                                    <?php
                                    if ($actionVal === "newuser") {
                                        if ($userLevelSession === 'Super Administrator') {
                                            echo '<option value="Super Administrator">' . htmlspecialchars($messages["247"] ?? 'Super Administrator', ENT_QUOTES, 'UTF-8') . '</option>
                                                  <option selected value="User">' . htmlspecialchars($messages["248"] ?? 'User', ENT_QUOTES, 'UTF-8') . '</option>';
                                        }
                                    } else {
                                        if ($userLevelSession === 'Super Administrator') {
                                            $selectedSuper = (($userdata['user_level'] ?? '') === 'Super Administrator') ? 'selected' : '';
                                            $selectedUser = (($userdata['user_level'] ?? '') === 'User') ? 'selected' : '';
                                            echo '<option value="Super Administrator" ' . $selectedSuper . '>Super Administrator</option>
                                                  <option value="User" ' . $selectedUser . '>User</option>';
                                        }
                                        $selectedBanned = (($userdata['user_level'] ?? '') === 'banned') ? 'selected' : '';
                                        echo '<option value="banned" ' . $selectedBanned . '>Disable (sperren)</option>';
                                    }
                                    ?>
                                </select>
                                <div class="form-text" style="color: var(--text-muted);">DJ bitte einem Benutzer zuordnen!</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($messages["250"] ?? 'Telefonnummer', ENT_QUOTES, 'UTF-8'); ?></label>
                                <input class="form-control" name="econtact_number" type="text" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo ($actionVal === "edit") ? htmlspecialchars($userdata['contact_number'] ?? '', ENT_QUOTES, 'UTF-8') : ''; ?>" />
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($messages["251"] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($messages["252"] ?? 'Mobilnummer', ENT_QUOTES, 'UTF-8'); ?></label>
                                <input class="form-control" name="emobile_number" type="text" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo ($actionVal === "edit") ? htmlspecialchars($userdata['mobile_number'] ?? '', ENT_QUOTES, 'UTF-8') : ''; ?>" />
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($messages["253"] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($messages["254"] ?? 'E-Mail', ENT_QUOTES, 'UTF-8'); ?></label>
                                <input class="form-control" name="euser_email" type="text" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo ($actionVal === "edit") ? htmlspecialchars($userdata['user_email'] ?? '', ENT_QUOTES, 'UTF-8') : ''; ?>" />
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($messages["255"] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($messages["256"] ?? 'Vorname', ENT_QUOTES, 'UTF-8'); ?></label>
                                <input class="form-control" name="ename" type="text" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo ($actionVal === "edit") ? htmlspecialchars($userdata['name'] ?? '', ENT_QUOTES, 'UTF-8') : ''; ?>" />
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($messages["257"] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($messages["258"] ?? 'Nachname', ENT_QUOTES, 'UTF-8'); ?></label>
                                <input class="form-control" name="esurname" type="text" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo ($actionVal === "edit") ? htmlspecialchars($userdata['surname'] ?? '', ENT_QUOTES, 'UTF-8') : ''; ?>" />
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($messages["259"] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($messages["260"] ?? 'Alter', ENT_QUOTES, 'UTF-8'); ?></label>
                                <input class="form-control" name="eage" type="text" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo ($actionVal === "edit") ? htmlspecialchars($userdata['age'] ?? '', ENT_QUOTES, 'UTF-8') : ''; ?>" />
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($messages["261"] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-12">
                                <label class="form-label small fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($messages["262"] ?? 'Account-Notizen', ENT_QUOTES, 'UTF-8'); ?></label>
                                <input class="form-control" name="eaccount_notes" type="text" style="background-color: var(--bg-base); color: var(--text-primary); border-color: var(--border-color);" value="<?php echo ($actionVal === "edit") ? htmlspecialchars($userdata['account_notes'] ?? '', ENT_QUOTES, 'UTF-8') : ''; ?>" />
                                <div class="form-text" style="color: var(--text-muted);"><?php echo htmlspecialchars($messages["263"] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button class="btn btn-primary px-4" type="submit">
                                <i class="fa-solid fa-floppy-disk me-1"></i> <?php echo htmlspecialchars($messages["264"] ?? 'Speichern', ENT_QUOTES, 'UTF-8'); ?>
                            </button>
                        </div>
                    </fieldset>
                </form>
            </div>
        </div>
        <?php } ?>

    </div>

    <!-- Info Sidebar Widget -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 h-100" style="background-color: var(--bg-card); border: 1px solid var(--border-color) !important;">
            <div class="card-body p-4">
                <h5 class="card-title fw-bold mb-3 text-primary">
                    <i class="fa-solid fa-circle-info me-1"></i> <?php echo htmlspecialchars($messages["230"] ?? 'Information', ENT_QUOTES, 'UTF-8'); ?>
                </h5>
                <p class="card-text mb-0" style="color: var(--text-muted);"><?php echo htmlspecialchars($messages["231"] ?? 'Erstellen, bearbeiten oder l&ouml;schen Sie hier Benutzer.', ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </div>
    </div>
</div>