<?php
/**
 * Streamers Admin Panel - Server Management & Overview
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

$limit = isset($setting['display_limit']) ? (int)$setting['display_limit'] : 10;
$p = 0;
if (isset($_GET['p'])) {
    $p = max(0, (int)$_GET['p'] * $limit);
}

$userLevel = $_SESSION['user_level'] ?? '';
$loginun = $_SESSION['username'] ?? '';
$hostAdd = htmlspecialchars($setting['host_add'] ?? 'localhost', ENT_QUOTES, 'UTF-8');

try {
    if ($userLevel === 'Super Administrator') {
        $serversList = DB::query("SELECT * FROM servers ORDER BY id ASC LIMIT %i, %i", $p, $limit);
        $totalServersCount = DB::count();
    } else {
        $serversList = DB::query("SELECT * FROM servers WHERE owner=%s ORDER BY id ASC LIMIT %i, %i", $loginun, $p, $limit);
    }
} catch (Exception $e) {
    $serversList = [];
}

$manageId = isset($_GET['manage']) ? (int)$_GET['manage'] : 0;
?>

<!-- Header / Titel -->
<div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom" style="border-color: var(--border-color) !important;">
    <h2 class="h4 mb-0 fw-bold" style="color: var(--text-primary);">
        <i class="fa-solid fa-server text-primary me-2"></i>
        <?php echo ($manageId === 0) ? htmlspecialchars($messages["433"] ?? 'Server Overview', ENT_QUOTES, 'UTF-8') : htmlspecialchars($messages["444"] ?? 'Edit Server', ENT_QUOTES, 'UTF-8'); ?>
    </h2>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0" style="background-color: var(--bg-card); color: var(--text-primary); border-color: var(--border-color) !important;">
            <div class="card-body p-4">

                <?php if ($manageId === 0): ?>
                    <!-- SERVER OVERVIEW CARDS (Modernes Design über volle Breite) -->
                    <h5 class="card-title fw-bold mb-3 small text-uppercase" style="color: var(--text-muted);">
                        <i class="fa-solid fa-list me-1 text-warning"></i> <?php echo htmlspecialchars($messages["434"] ?? 'Overview of your servers', ENT_QUOTES, 'UTF-8'); ?>
                    </h5>

                    <?php
                    if (empty($serversList)) {
                        echo '<div class="text-center py-4" style="color: var(--text-muted);">' . htmlspecialchars($messages["440"] ?? 'No servers found.', ENT_QUOTES, 'UTF-8') . '</div>';
                    } else {
                        echo '<div class="d-flex flex-column gap-3">';
                        foreach ($serversList as $data) {
                            $serverId = (int)$data['id'];
                            $portBase = (int)$data['portbase'];
                            $serverOwner = htmlspecialchars($data['owner'], ENT_QUOTES, 'UTF-8');
                            $serverWebspace = (float)($data['webspace'] > 0 ? $data['webspace'] : 1);
                            
                            // Verzeichnisgröße berechnen
                            $actual_dir_size = 0.0;
                            $dir = "./uploads/" . $portBase . "/";
                            if (file_exists($dir) && is_dir($dir)) {
                                $filesize = 0;
                                $dp = @opendir($dir);
                                if ($dp) {
                                    while (($filename = readdir($dp)) !== false) {
                                        if ($filename === '.' || $filename === '..') {
                                            continue;
                                        }
                                        $filedata = @stat($dir . "/" . $filename);
                                        if ($filedata && isset($filedata[7])) {
                                            $filesize += $filedata[7];
                                        }
                                    }
                                    closedir($dp);
                                    $actual_dir_size = $filesize / 1024;
                                }
                            }
                            
                            $negative_background_pos = ($actual_dir_size / $serverWebspace) * 120;
                            
                            // Online-Prüfung via fsockopen
                            $connection = @fsockopen($setting['host_add'] ?? 'localhost', $portBase, $errno, $errstr, 1);
                            if (!$connection) {
                                $online = '1';
                            } else {
                                if (is_resource($connection)) {
                                    fclose($connection);
                                }
                                $online = '';
                            }
                            ?>
                            <div class="card border p-3 shadow-sm rounded-3" style="background-color: var(--bg-card); border-color: var(--border-color) !important;">
                                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-2 pb-2 border-bottom" style="border-color: var(--border-color) !important;">
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="fw-bold fs-6" style="color: var(--text-primary);">Stream #<?php echo $serverId; ?></span>
                                        <a href="http://<?php echo $hostAdd . ':' . $portBase; ?>/" target="_blank" class="small text-decoration-none fw-bold text-primary">
                                            <i class="fa-solid fa-up-right-from-square me-1"></i>Port: <?php echo $portBase; ?>
                                        </a>
                                        <?php if ($userLevel === 'Super Administrator'): ?>
                                            <span class="small d-none d-md-inline" style="color: var(--text-muted);">|</span>
                                            <span class="small" style="color: var(--text-muted);">Besitzer: <strong style="color: var(--text-primary);"><?php echo $serverOwner; ?></strong></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if ($online): ?>
                                            <span class="badge bg-danger px-2 py-1"><i class="fa-solid fa-circle fa-2xs me-1"></i> Gestoppt</span>
                                        <?php else: ?>
                                            <span class="badge bg-success px-2 py-1"><i class="fa-solid fa-circle fa-2xs me-1"></i> L&auml;uft</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 my-2">
                                    <div class="small" style="color: var(--text-muted);">
                                        Speicherauslastung:
                                        <div class="space_show mt-1" style="background-position:-<?php echo round($negative_background_pos, 2); ?>px 0px; width: 150px; height: 10px; background-color: var(--border-color); border-radius: 3px;"></div>
                                    </div>

                                    <div class="d-flex flex-wrap gap-2 justify-content-end align-items-center">
                                        <?php if ($online): ?>
                                            <a class="btn btn-sm btn-outline-danger px-3" href="content.php?include=server&view=<?php echo $serverId; ?>&action=start" onclick="return confirm('Wollen Sie den Server <?php echo $portBase; ?> wirklich starten?')">
                                                <i class="fa-solid fa-power-off me-1"></i> Start
                                            </a>
                                        <?php else: ?>
                                            <a class="btn btn-sm btn-outline-success px-3" href="content.php?include=server&view=<?php echo $serverId; ?>&action=stop" onclick="return confirm('Wollen Sie den Server <?php echo $portBase; ?> wirklich stoppen?')">
                                                <i class="fa-solid fa-stop me-1"></i> Stop
                                            </a>
                                        <?php endif; ?>

                                        <a class="btn btn-sm btn-outline-primary px-3" href="content.php?include=server&manage=<?php echo $serverId; ?>" title="Server konfigurieren">
                                            <i class="fa-solid fa-gear me-1"></i> Edit
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
                        if ($userLevel === 'Super Administrator') {
                            $totalCountRows = DB::queryFirstField("SELECT COUNT(*) FROM servers");
                        } else {
                            $totalCountRows = DB::queryFirstField("SELECT COUNT(*) FROM servers WHERE owner=%s", $loginun);
                        }
                        
                        $totalPages = ceil((int)$totalCountRows / $limit);
                        if ($totalPages > 1) {
                            echo '<nav aria-label="Server Pagination" class="mt-4"><ul class="pagination justify-content-center mb-0">';
                            for ($i = 0; $i < $totalPages; $i++) {
                                echo '<li class="page-item"><a class="page-link" href="content.php?include=server&p=' . $i . '">' . ($i + 1) . '</a></li>';
                            }
                            echo '</ul></nav>';
                        }
                    } catch (Exception $e) {}
                    ?>

                <?php else: ?>
                    <!-- SERVER EDIT / CONFIG FORM -->
                    <?php
                    try {
                        if ($userLevel === "Super Administrator") {
                            $portquery = DB::queryFirstRow("SELECT portbase FROM servers WHERE id=%i", $manageId);
                        } else {
                            $portquery = DB::queryFirstRow("SELECT portbase FROM servers WHERE owner=%s AND id=%i", $loginun, $manageId);
                        }
                        $managedPortBase = $portquery ? $portquery['portbase'] : '';
                    } catch (Exception $e) {
                        $managedPortBase = '';
                    }
                    ?>
                    <h5 class="card-title fw-bold mb-3 small text-uppercase" style="color: var(--text-muted);">
                        <i class="fa-solid fa-sliders me-1 text-warning"></i> <?php echo htmlspecialchars($messages["445"] ?? 'Server settings', ENT_QUOTES, 'UTF-8'); ?> (Port: <?php echo $managedPortBase; ?>)
                    </h5>

                    <form method="post" action="content.php?include=server&manage=<?php echo $manageId; ?>&action=edit">
                        <fieldset class="border-0 p-0 m-0">
                            <legend class="visually-hidden"><?php echo htmlspecialchars($messages["448"] ?? 'Configuration', ENT_QUOTES, 'UTF-8'); ?></legend>

                            <div class="row g-3">
                                <?php
                                try {
                                    $serverEditData = DB::queryFirstRow("SELECT * FROM servers WHERE id=%i", $manageId);
                                    if ($serverEditData) {
                                        $excludedFields = [
                                            'id', 'abuse', 'pid', 'autopid', 'webspace', 'serverip_1', 'serverport_1', 'streamtitle', 
                                            'streamurl', 'shuffle', 'samplerate_1', 'channels_1', 'genre', 'public', 'aim', 'icq', 'irc', 
                                            'encoder_1', 'mp3quality', 'mp3mode', 'calendarrewrite', 'calendarfile', 'outprotocol_1', 
                                            'useMetadata_1', 'useMetadata_2', 'playlistfile', 'xfade', 'xfadethreshol', 'djport_1', 
                                            'djlogin_1', 'djpassword_1', 'djport_2', 'djlogin_2', 'djpassword_2', 'djlogin_3', 'djpassword_3', 
                                            'djlogin_4', 'djpassword_4', 'djlogin_5', 'djpassword_5', 'djlogin_6', 'djpassword_6', 'djlogin_7', 
                                            'djpassword_7', 'djlogin_8', 'djpassword_8', 'djlogin_9', 'djpassword_9', 'djlogin_10', 'djpassword_10', 
                                            'djlogin_11', 'djpassword_11', 'djlogin_12', 'djpassword_12', 'djlogin_13', 'djpassword_13', 'djlogin_14', 
                                            'djpassword_14', 'djlogin_15', 'djpassword_15', 'djlogin_16', 'djpassword_16', 'djlogin_17', 'djpassword_17', 
                                            'djlogin_18', 'djpassword_18', 'djlogin_19', 'djpassword_19', 'djlogin_20', 'djpassword_20', 'djlogin_21', 
                                            'djpassword_21', 'djlogin_22', 'djpassword_22', 'djlogin_23', 'djpassword_23', 'djlogin_24', 'djpassword_24', 
                                            'djlogin_25', 'djpassword_25', 'djlogin_26', 'djpassword_26', 'djlogin_27', 'djpassword_27', 'djlogin_28', 
                                            'djpassword_28', 'djlogin_29', 'djpassword_29', 'djlogin_30', 'djpassword_30', 'djpriority_1', 'djpriority_2', 
                                            'djpriority_3', 'djpriority_4', 'djpriority_5', 'djpriority_6', 'djpriority_7', 'djpriority_8', 'djpriority_9', 
                                            'djpriority_10', 'djpriority_11', 'djpriority_12', 'djpriority_13', 'djpriority_14', 'djpriority_15', 'djpriority_16', 
                                            'djpriority_17', 'djpriority_18', 'djpriority_19', 'djpriority_20', 'djpriority_21', 'djpriority_22', 'djpriority_23', 
                                            'djpriority_24', 'djpriority_25', 'djpriority_26', 'djpriority_27', 'djpriority_28', 'djpriority_29', 'djpriority_30', 
                                            'djbroadcasts', 'streamid_2', 'streampath_2', 'unlockkeyname', 'unlockkeycode', 'metadatapattern_1', 
                                            'metadatapattern_2', 'yport', 'yp2', 'uvox2sourcedebug', 'displaymetadatapattern_1', 'displaymetadatapattern_2', 
                                            'nextlookahead', 'useMetadata', 'log', 'srcip', 'destip', 'contentdir', 'metainterval', 'flashpolicyfile', 
                                            'streamid', 'streamid_1', 'streampath', 'streampath_1', 'yppath', 'uvoxcihperkey', 'serverip_2', 'serverport_2', 
                                            'uvoxstreamid_1', 'uvoxstreamid_2', 'outprotocol_2', 'samplerate_2', 'encoder_2', 'channels_2', 'djcapture', 
                                            'uvoxradiometadata_2', 'uvoxnewmetadata_2', 'uvoxradiometadata_1', 'uvoxnewmetadata_1', 'logfile', 'w3clog', 
                                            'banfile', 'ripfile', 'sitepublic', 'realtime', 'screenlog', 'tchlog', 'weblog', 'w3cenable', 'autodumpusers', 
                                            'autodumpsourcetime', 'publicserver'
                                        ];

                                        foreach ($serverEditData as $field => $value) {
                                            if (!is_numeric((string)$field) && !in_array($field, $excludedFields, true)) {
                                                $safeValue = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
                                                
                                                if ($field === "owner") $labelName = $messages["451"] ?? 'Owner';
                                                elseif ($field === "maxuser") $labelName = $messages["452"] ?? 'Max Users';
                                                elseif ($field === "portbase") $labelName = $messages["453"] ?? 'Port Base';
                                                elseif ($field === "bitrate_1") $labelName = "Streambitrate Sid1";
                                                elseif ($field === "bitrate_2") $labelName = "Streambitrate Sid2";
                                                elseif ($field === "adminpassword") $labelName = $messages["455"] ?? 'Admin Password';
                                                elseif ($field === "password") $labelName = $messages["456"] ?? 'Password';
                                                elseif ($field === "uvoxauth_1") $labelName = "Sid1 Password";
                                                elseif ($field === "uvoxauth_2") $labelName = "Sid2 Password";
                                                elseif ($field === "streamauthhash_1") $labelName = "Streamauthhash Sid1";
                                                elseif ($field === "streamauthhash_2") $labelName = "Streamauthhash Sid2";
                                                else $labelName = htmlspecialchars($field, ENT_QUOTES, 'UTF-8');

                                                $isDisabled = in_array($field, ["owner", "portbase", "banfile", "ripfile", "w3clog", "logfile", "maxuser", "bitrate_1", "bitrate_2"], true);
                                                
                                                $bgColor = $isDisabled ? 'var(--bg-surface)' : 'var(--bg-base)';
                                                $disabledAttr = $isDisabled ? 'disabled' : '';
                                                $safeField = htmlspecialchars($field, ENT_QUOTES, 'UTF-8');

                                                echo '<div class="col-md-6">
                                                        <label class="form-label small fw-bold" style="color: var(--text-primary);">' . $labelName . '</label>
                                                        <input class="form-control" name="' . $safeField . '" type="text" style="background-color: ' . $bgColor . '; color: var(--text-primary); border-color: var(--border-color);" value="' . $safeValue . '" ' . $disabledAttr . ' />
                                                      </div>';
                                            }
                                        }
                                    }
                                } catch (Exception $e) {
                                    echo '<p class="text-danger">Error loading server data.</p>';
                                }
                                ?>
                            </div>

                            <div class="mt-4 d-flex justify-content-between">
                                <button class="btn btn-primary px-4" type="submit" name="submit">
                                    <i class="fa-solid fa-floppy-disk me-1"></i> <?php echo htmlspecialchars($messages["449"] ?? 'Save', ENT_QUOTES, 'UTF-8'); ?>
                                </button>
                                <button class="btn btn-outline-secondary px-4" type="reset" onclick="document.location='content.php?include=server';" style="border-color: var(--border-color); color: var(--text-primary);">
                                    <i class="fa-solid fa-rotate-left me-1"></i> <?php echo htmlspecialchars($messages["450"] ?? 'Reset', ENT_QUOTES, 'UTF-8'); ?>
                                </button>
                            </div>
                        </fieldset>
                    </form>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <!-- Info Sidebar Widget -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 h-100" style="background-color: var(--bg-card); border: 1px solid var(--border-color) !important;">
            <div class="card-body p-4">
                <h5 class="card-title fw-bold mb-3 text-primary">
                    <i class="fa-solid fa-circle-info me-1"></i> 
                    <?php echo ($manageId === 0) ? htmlspecialchars($messages["435"] ?? 'Information', ENT_QUOTES, 'UTF-8') : htmlspecialchars($messages["446"] ?? 'Information', ENT_QUOTES, 'UTF-8'); ?>
                </h5>
                <p class="card-text mb-0" style="color: var(--text-muted);">
                    <?php echo ($manageId === 0) ? htmlspecialchars($messages["436"] ?? 'Manage your Shoutcast servers here.', ENT_QUOTES, 'UTF-8') : htmlspecialchars($messages["447"] ?? 'Modify your server configuration.', ENT_QUOTES, 'UTF-8'); ?>
                </p>
            </div>
        </div>
    </div>
</div>