<?php
include '../../../include/db.php';
include '../../../include/authenticate.php';
require_once dirname(__FILE__, 2) . '/include/functions.php';

// Admins only
if (!checkperm('a')) {
    exit($lang['error-permissiondenied']);
}

$self        = $_SERVER['PHP_SELF'];
$plugins_url = $baseurl_short . 'pages/team/team_plugins.php';
$action      = (string) ($_POST['action'] ?? '');
$message     = '';
$errors      = array();
$relocated   = collection_access_manager_relocate();
collection_access_manager_ensure_first_version();

// ---- Handle form actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== '') {
    if (function_exists('enforcePostRequest') && !enforcePostRequest(false)) {
        $errors[] = 'The request could not be verified. Reload the page and try again.';
    } elseif ($action === 'save') {
        $changes = (string) ($_POST['changes'] ?? '');
        if ($changes !== '' && $changes !== '{}') {
            if (collection_access_manager_snapshot() === false) {
                $errors[] = 'The previous permissions could not be stored in the version history (check write access to ' . collection_access_manager_versions_dir() . ').';
            } else {
                $n = collection_access_manager_apply($changes, $errors);
                if (!$errors) {
                    redirect($self . '?done=saved&n=' . $n);
                    exit;
                }
                $message = $n . ' group(s) saved.';
            }
        }
    } elseif ($action === 'restore_version') {
        $vname = (string) ($_POST['version_name'] ?? '');
        if (!in_array($vname, collection_access_manager_versions(), true)) {
            $errors[] = 'Select a version.';
        } elseif (collection_access_manager_snapshot() === false) {
            $errors[] = 'The current permissions could not be stored in the version history; nothing was restored.';
        } else {
            $n = collection_access_manager_restore_version($vname, $errors);
            if (!$errors) {
                redirect($self . '?done=restored&n=' . $n . '&v=' . rawurlencode($vname));
                exit;
            }
        }
    } elseif ($action === 'clear_history') {
        $n = collection_access_manager_clear_history();
        redirect($self . '?done=cleared&n=' . $n);
        exit;
    } elseif ($action === 'reset') {
        if (trim((string) ($_POST['reset_confirm'] ?? '')) !== 'RESET') {
            $errors[] = 'Not reset: type RESET (capital letters) to confirm.';
        } elseif (!collection_access_manager_reset()) {
            $errors[] = 'The plugin could not be reset (check permissions of ' . collection_access_manager_base_dir() . ').';
        } else {
            redirect($self . '?done=reset');
            exit;
        }
    }
}

// Messages after a redirect
$n = (int) ($_GET['n'] ?? 0);
switch ((string) ($_GET['done'] ?? '')) {
    case 'saved':
        $message = 'Access saved for ' . $n . ' user group(s). The previous state is in the version history.';
        break;
    case 'restored':
        $message = 'Version ' . collection_access_manager_version_label((string) ($_GET['v'] ?? '')) . ' restored: ' . $n . ' user group(s) changed. The state before the restore is in the version history.';
        break;
    case 'cleared':
        $message = 'Version history cleared (' . $n . ' file(s) deleted, the newest version is kept).';
        break;
    case 'reset':
        $message = 'The plugin was reset: the version history was deleted. The featured collection access of the groups was not changed; the current state is version v1 again.';
        break;
}
if ($relocated !== '') {
    $message = trim($relocated . ' ' . $message);
}
if (in_array('fcaccess', (array) ($GLOBALS['plugins'] ?? array()), true)) {
    $message = trim($message . ' The old version of this plugin ("fcaccess", renamed in 1.04) is still active - deactivate it in System > Plugins and delete its folder; its data was taken over.');
}

// ---- Prepare the page ----
$tree   = collection_access_manager_tree();
$groups = collection_access_manager_groups();
$state  = array(); // group ref => decoded state
foreach ($groups as $ref => $g) {
    $state[$ref] = collection_access_manager_decode(collection_access_manager_effective($groups, $ref), $tree);
}
$parents = array();
foreach ($groups as $g) {
    if ($g['parent'] > 0 && isset($groups[$g['parent']])) {
        $parents[$g['parent']] = $groups[$g['parent']]['name'];
    }
}
asort($parents, SORT_NATURAL | SORT_FLAG_CASE);
$versions = collection_access_manager_versions();
$h        = function ($s) { return htmlspecialchars((string) $s); };
$lower    = function ($s) { return function_exists('mb_strtolower') ? mb_strtolower((string) $s) : strtolower((string) $s); };
$short    = function ($s, $max = 20) {
    $s = (string) $s;
    if (function_exists('mb_strlen') && mb_strlen($s) > $max) {
        return mb_substr($s, 0, $max - 1) . '…';
    }
    return strlen($s) > $max ? substr($s, 0, $max - 1) . '…' : $s;
};
$nonce = isset($CSP_nonce) ? ' nonce="' . htmlspecialchars($CSP_nonce) . '"' : '';
$tick  = function ($on, $inh) {
    return '<input type="checkbox"' . ($on ? ' checked' : '') . ($inh ? ' disabled' : '') . '>';
};

include '../../../include/header.php';
?>
<div class="BasicsBox fa-page">
    <h1>Collection Access Manager</h1>
    <?php
    renderBreadcrumbs([
        ['title' => $lang['systemsetup'],   'href' => $baseurl_short . 'pages/admin/admin_home.php', 'menu' => true],
        ['title' => $lang['pluginmanager'], 'href' => $plugins_url],
        ['title' => 'Collection Access Manager'],
    ]);
    ?>

    <?php if ($message) { ?><div class="PageInformal"><?php echo $h($message); ?></div><?php } ?>
    <?php foreach ($errors as $e) { ?><div class="FormError"><?php echo $h($e); ?></div><?php } ?>

    <style<?php echo $nonce; ?>>
        .fa-page { --fa-bg: #fff; --fa-acc: #1e88e5; --fa-grp: #8a8a8a; --fa-tint: rgba(128, 128, 128, 0.09); --fa-lw: 340px; --fa-cw: 80px; --fa-left: calc(var(--fa-lw) + var(--fa-cw) + 1px); }
        .fa-legend { opacity: 0.8; font-size: 0.9em; }
        .fa-filters { display: grid; grid-template-columns: var(--fa-left) 1fr; margin: 10px 0 0; }
        @media (max-width: 900px) { .fa-filters { grid-template-columns: 1fr; } }
        .fa-fbox { margin: 0; padding: 8px 12px 10px; border: 1px solid rgba(128, 128, 128, 0.45); border-top-width: 4px; border-radius: 6px 6px 0 0; min-width: 0; box-sizing: border-box; }
        .fa-fbox-fc { border-top-color: var(--fa-acc); background-color: var(--fa-tint); }
        .fa-fbox-grp { border-top-color: var(--fa-grp); }
        @media (min-width: 901px) { .fa-fbox-fc { border-top-right-radius: 0; } .fa-fbox-grp { border-left: 0; border-top-left-radius: 0; padding-left: 22px; } }
        .fa-fbox legend { padding: 0 6px; font-weight: bold; }
        .fa-fbox legend small { font-weight: normal; opacity: 0.75; }
        .fa-fbox-fc legend { color: var(--fa-acc); }
        .fa-fbox-grp legend { color: var(--fa-grp); }
        .fa-fstat { font-weight: normal; font-size: 0.85em; opacity: 0.8; margin-left: 6px; color: inherit; }
        .fa-fgrid { display: grid; gap: 8px 12px; align-items: end; }
        .fa-fbox-grp .fa-fgrid { grid-template-columns: minmax(180px, 2.5fr) minmax(150px, 2fr) minmax(110px, 130px) minmax(150px, 170px); }
        @media (max-width: 1200px) { .fa-fbox-grp .fa-fgrid { grid-template-columns: 1fr 1fr; } }
        .fa-field { display: flex; flex-direction: column; gap: 3px; min-width: 0; }
        .fa-field > label { font-size: 0.9em; opacity: 0.85; }
        .fa-field select, .fa-field input[type=text] { width: 100%; box-sizing: border-box; margin: 0; }
        .fa-checks { display: flex; flex-wrap: wrap; gap: 4px 14px; font-size: 0.9em; align-items: center; margin-top: 6px; }
        .fa-checks label { display: inline-flex; gap: 5px; align-items: center; }
        .fa-pager { display: flex; gap: 4px; align-items: center; }
        .fa-pager select { flex: 1 1 auto; min-width: 0; }
        .fa-pager button { flex: 0 0 auto; margin: 0; padding: 2px 8px; }
        .fa-wrap { overflow: auto; max-height: 72vh; border: 1px solid rgba(128, 128, 128, 0.45); border-top: 0; border-radius: 0 0 6px 6px; }
        .fa-table { border-collapse: separate; border-spacing: 0; min-width: 100%; }
        .fa-table th, .fa-table td, .fa-table button { text-transform: none; font-variant: normal; letter-spacing: normal; }
        .fa-table th, .fa-table td { padding: 4px 6px; border-bottom: 1px solid rgba(128, 128, 128, 0.25); background-color: var(--fa-bg); color: inherit; }
        .fa-head th { position: sticky; top: 0; z-index: 2; vertical-align: bottom; font-weight: normal; border-bottom: 1px solid rgba(128, 128, 128, 0.45); }
        .fa-head .fa-gh { text-align: center; font-size: 0.9em; padding: 4px 8px; }
        .fa-gh b, .fa-gh small { display: block; max-width: 200px; min-width: 48px; margin: 0 auto; overflow-wrap: anywhere; }
        .fa-gh small { opacity: 0.7; }
        .fa-table .fa-label { position: sticky; left: 0; z-index: 1; width: var(--fa-lw); min-width: var(--fa-lw); max-width: var(--fa-lw); box-sizing: border-box; text-align: left; font-weight: normal; }
        .fa-table .fa-code { position: sticky; left: var(--fa-lw); z-index: 1; width: var(--fa-cw); min-width: var(--fa-cw); max-width: var(--fa-cw); box-sizing: border-box; text-align: left; border-right: 2px solid rgba(128, 128, 128, 0.5); }
        .fa-table .fa-act { position: sticky; right: 0; z-index: 1; width: 96px; min-width: 96px; max-width: 96px; box-sizing: border-box; white-space: nowrap; text-align: center; border-left: 2px solid rgba(128, 128, 128, 0.5); }
        .fa-head .fa-label, .fa-head .fa-code, .fa-head .fa-act { z-index: 3; font-weight: bold; }
        .fa-head .fa-act { font-size: 0.85em; white-space: normal; }
        .fa-table tbody .fa-label, .fa-table tbody .fa-code, .fa-head .fa-label, .fa-head .fa-code { background-image: linear-gradient(var(--fa-tint), var(--fa-tint)); }
        .fa-code code { font-size: 0.9em; word-break: break-all; }
        .fa-table td { text-align: center; }
        .fa-table td input[type=checkbox] { cursor: pointer; width: 16px; height: 16px; margin: 0; }
        .fa-act button { background: transparent; border: 1px solid rgba(128, 128, 128, 0.7); border-radius: 3px; box-shadow: none; color: inherit; padding: 1px 6px; margin: 0; min-width: 0; font-size: 0.75em; line-height: 1.3; cursor: pointer; }
        .fa-act button:hover, .fa-act button:focus { background: rgba(128, 128, 128, 0.18); }
        .fa-act button + button { margin-left: 4px; }
        .fa-row:hover th, .fa-row:hover td { background-image: linear-gradient(rgba(128, 128, 128, 0.12), rgba(128, 128, 128, 0.12)); }
        .fa-sh td { padding: 6px 10px; text-align: left; font-weight: bold; background-image: linear-gradient(rgba(128, 128, 128, 0.1), rgba(128, 128, 128, 0.1)); border-bottom: 1px solid rgba(128, 128, 128, 0.45); }
        .fa-sh td span { position: sticky; left: 10px; }
        .fa-name { display: flex; align-items: center; gap: 4px; }
        .fa-tog { background: none; border: 0; box-shadow: none; padding: 0; margin: 0; min-width: 0; width: 1.2em; cursor: pointer; color: inherit; font-size: 0.9em; flex: 0 0 auto; }
        .fa-tog::before { content: "\25BE"; display: inline-block; transition: transform 0.15s; }
        .fa-closed .fa-tog::before { transform: rotate(-90deg); }
        .fa-sp { display: inline-block; width: 1.2em; flex: 0 0 auto; }
        .fa-badge { font-size: 0.8em; opacity: 0.65; white-space: nowrap; }
        .fa-chg { box-shadow: inset 0 0 0 2px #1e88e5; }
        .fa-inh { opacity: 0.55; }
        .fa-off input { opacity: 0.45; }
        .fa-hide, .fa-fold { display: none; }
        .fa-save { position: sticky; bottom: 0; z-index: 4; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; padding: 8px 0; background-color: var(--fa-bg); }
        #fa-count.fa-has { font-weight: bold; color: #1e88e5; }
        .fa-box { margin: 14px 0; padding: 10px 14px; border: 1px solid rgba(128, 128, 128, 0.45); border-radius: 6px; }
        .fa-box h3 { margin: 0 0 8px; font-size: 1.05em; }
        .fa-box p { margin: 0.5em 0; }
        .fa-row-ctl { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin: 0.6em 0; }
        <?php for ($d = 1; $d <= 15; $d++) { echo '.fa-d' . $d . ' { padding-left: ' . ($d * 18) . 'px; } '; } ?>

        .fa-link { background: none; border: 0; padding: 0; margin: 0; color: inherit; text-decoration: underline; cursor: pointer; font: inherit; font-size: 0.85em; box-shadow: none; min-width: 0; }
    </style>

    <?php if (!$groups) { ?>
        <p><i>No user groups found.</i></p>
    <?php } else { ?>

    <p class="fa-legend">Ticked = the group can see the featured collection. Ticking an entry also ticks its parent categories; unticking it hides everything below it.
        Blue frame = changed, not saved yet. Groups that inherit permissions from their parent are read-only.
        The <b>Code</b> column shows the ResourceSpace permission: <code>j&lt;ref&gt;</code> for top-level entries, <code>-j&lt;ref&gt;</code> (hides) below them.
        The codes are written for you when you save; all other permissions of the groups stay as they are.</p>

    <!-- Filters -->
    <div class="fa-filters">
        <fieldset class="fa-fbox fa-fbox-fc">
            <legend>Featured collections <small>(table rows)</small><span class="fa-fstat" id="fa-qstat"></span></legend>
            <div class="fa-field">
                <label for="fa-q">Search featured collections</label>
                <input type="text" id="fa-q" placeholder="Name or number" autocomplete="off">
            </div>
            <div class="fa-checks">
                <span>Show only:</span>
                <label title="Rows where the shown groups have different settings"><input type="checkbox" id="fa-diff"> differing</label>
                <label title="Rows with changes that are not saved yet"><input type="checkbox" id="fa-changed"> unsaved</label>
            </div>
            <div class="fa-checks">
                <span>Tree:</span>
                <button type="button" class="fa-link" id="fa-open-all">open all</button>
                <button type="button" class="fa-link" id="fa-close-all">close all</button>
            </div>
        </fieldset>
        <fieldset class="fa-fbox fa-fbox-grp">
            <legend>User groups <small>(table columns)</small><span class="fa-fstat" id="fa-gstat"></span></legend>
            <div class="fa-fgrid">
                <div class="fa-field">
                    <label for="fa-gq">Search groups</label>
                    <input type="text" id="fa-gq" placeholder="Group name" autocomplete="off">
                </div>
                <div class="fa-field">
                    <label for="fa-parent">Parent group</label>
                    <select id="fa-parent">
                        <option value="">All groups</option>
                        <option value="top">Top-level groups only</option>
                        <?php foreach ($parents as $pref => $pname) { ?><option value="<?php echo (int) $pref; ?>">Subgroups of <?php echo $h($pname); ?></option><?php } ?>
                    </select>
                </div>
                <div class="fa-field">
                    <label for="fa-size">Columns</label>
                    <select id="fa-size"><option>10</option><option selected>15</option><option>20</option></select>
                </div>
                <div class="fa-field">
                    <label for="fa-page">Page</label>
                    <div class="fa-pager">
                        <button type="button" id="fa-prev" title="Previous groups">&laquo;</button>
                        <select id="fa-page"></select>
                        <button type="button" id="fa-next" title="Next groups">&raquo;</button>
                    </div>
                </div>
            </div>
        </fieldset>
    </div>

    <!-- Table: rows = featured collections, columns = user groups -->
    <div class="fa-wrap">
        <table class="fa-table" id="fa-table">
            <thead>
                <tr class="fa-head">
                    <th class="fa-label">Featured collection</th>
                    <th class="fa-code">Code</th>
                    <?php foreach ($groups as $ref => $g) { ?>
                        <th class="fa-gh<?php echo $g['inherits'] ? ' fa-inh' : ''; ?>" data-ref="<?php echo (int) $ref; ?>" data-parent="<?php echo (int) $g['parent']; ?>"
                            data-name="<?php echo $h($lower($g['name'])); ?>"<?php echo $g['inherits'] ? ' data-inh="1"' : ''; ?>
                            title="<?php echo $h($g['name'] . ($g['inherits'] ? ' - inherits permissions from ' . ($groups[$g['parent']]['name'] ?? 'its parent') . ' (read-only)' : '')); ?>">
                            <b><?php echo $h($short($g['name'])); ?></b><small>#<?php echo (int) $ref; ?></small>
                        </th>
                    <?php } ?>
                    <th class="fa-act">Select</th>
                </tr>
            </thead>
            <tbody>
                <tr class="fa-sh"><td colspan="<?php echo count($groups) + 3; ?>"><span>General</span></td></tr>
                <tr class="fa-row" data-kind="all">
                    <th class="fa-label">All featured collections, also new ones</th>
                    <td class="fa-code"><code>j*</code></td>
                    <?php foreach ($groups as $ref => $g) { ?><td<?php echo $g['inherits'] ? ' class="fa-inh"' : ''; ?>><?php echo $tick($state[$ref]['all'], $g['inherits']); ?></td><?php } ?>
                    <td class="fa-act"><button type="button" class="fa-all">All</button><button type="button" class="fa-none">None</button></td>
                </tr>
                <tr class="fa-row" data-kind="search">
                    <th class="fa-label">Search shows only resources in featured collections the group can see</th>
                    <td class="fa-code"><code>J</code></td>
                    <?php foreach ($groups as $ref => $g) { ?><td<?php echo $g['inherits'] ? ' class="fa-inh"' : ''; ?>><?php echo $tick($state[$ref]['search'], $g['inherits']); ?></td><?php } ?>
                    <td class="fa-act"><button type="button" class="fa-all">All</button><button type="button" class="fa-none">None</button></td>
                </tr>
                <tr class="fa-sh"><td colspan="<?php echo count($groups) + 3; ?>"><span>Featured collections (<?php echo count($tree); ?>)</span></td></tr>
                <?php if (!$tree) { ?>
                    <tr class="fa-sh"><td colspan="<?php echo count($groups) + 3; ?>"><span><i>No featured collections yet.</i></span></td></tr>
                <?php } ?>
                <?php foreach ($tree as $ref => $n) {
                    $kind  = $n['children'] ? 'category' : 'collection';
                    $badge = $n['children'] ? count($n['children']) . ' inside' : $n['resources'] . ' resource' . ($n['resources'] === 1 ? '' : 's');
                    $code  = ($n['parent'] === 0 ? 'j' : '-j') . $ref; ?>
                    <tr class="fa-row fa-fc" data-kind="fc" data-ref="<?php echo (int) $ref; ?>" data-parent="<?php echo (int) $n['parent']; ?>"
                        data-path="<?php echo $h(implode(',', $n['path'])); ?>" data-text="<?php echo $h($lower($n['name']) . ' ' . $ref); ?>">
                        <th class="fa-label" title="<?php echo $h($n['name'] . ' (' . $kind . ' #' . $ref . ')'); ?>">
                            <span class="fa-name fa-d<?php echo min(15, (int) $n['depth']); ?>">
                                <?php if ($n['children']) { ?><button type="button" class="fa-tog" aria-label="Open or close"></button><?php } else { ?><span class="fa-sp"></span><?php } ?>
                                <span><?php echo $h($n['name']); ?></span>
                                <span class="fa-badge"><?php echo $h($badge); ?></span>
                            </span>
                        </th>
                        <td class="fa-code"><code><?php echo $h($code); ?></code></td>
                        <?php foreach ($groups as $gref => $g) { ?><td<?php echo $g['inherits'] ? ' class="fa-inh"' : ''; ?>><?php echo $tick(isset($state[$gref]['visible'][$ref]), $g['inherits']); ?></td><?php } ?>
                        <td class="fa-act"><button type="button" class="fa-all" title="Tick for all shown groups (with the parent categories)">All</button><button type="button" class="fa-none" title="Untick for all shown groups (with everything below)">None</button></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

    <!-- Save -->
    <form method="post" action="<?php echo $h($self); ?>" id="fa-form" class="fa-save">
        <?php generateFormToken('collection_access_manager'); ?>
        <input type="hidden" name="changes" id="fa-changes" value="">
        <button type="submit" name="action" value="save" id="fa-savebtn" disabled>Save changes</button>
        <button type="button" id="fa-discard" disabled>Discard changes</button>
        <span id="fa-count">No changes.</span>
    </form>

    <!-- Copy -->
    <div class="fa-box">
        <h3>Copy access</h3>
        <div class="fa-row-ctl">
            <label>From
                <select id="fa-copy-from">
                    <?php foreach ($groups as $ref => $g) { ?><option value="<?php echo (int) $ref; ?>"><?php echo $h($g['name']); ?></option><?php } ?>
                </select>
            </label>
            <label>to
                <select id="fa-copy-to">
                    <?php foreach ($groups as $ref => $g) { if ($g['inherits']) { continue; } ?><option value="<?php echo (int) $ref; ?>"><?php echo $h($g['name']); ?></option><?php } ?>
                </select>
            </label>
            <button type="button" id="fa-copy">Copy</button>
        </div>
        <p class="fa-legend">Copies all ticks of the table (including the two "General" rows) to another group. Nothing is saved until you click "Save changes".</p>
    </div>

    <!-- Version history -->
    <div class="fa-box">
        <h3>Version history</h3>
        <p>Every "Save changes" stores the previous featured collection access of all groups first. The first version is created when the plugin is activated.
            The last <?php echo (int) COLLECTION_ACCESS_MANAGER_KEEP_VERSIONS; ?> versions are kept.</p>
        <?php if ($versions) { ?>
            <form method="post" action="<?php echo $h($self); ?>" id="fa-vform">
                <?php generateFormToken('collection_access_manager'); ?>
                <select name="version_name" id="fa-version">
                    <?php foreach ($versions as $i => $v) { ?>
                        <option value="<?php echo $h($v); ?>"<?php echo $i === count($versions) - 1 ? ' selected' : ''; ?>><?php echo $h(collection_access_manager_version_label($v)); ?></option>
                    <?php } ?>
                </select>
                <div class="fa-row-ctl">
                    <button type="submit" name="action" value="restore_version">Restore this version</button>
                    <button type="submit" name="action" value="clear_history"<?php echo count($versions) < 2 ? ' disabled' : ''; ?>>Delete all history</button>
                </div>
                <p class="fa-legend">Restoring writes the featured collection codes of that version back to all groups at once (other permissions are not changed). The current state is stored in the history first.</p>
            </form>
        <?php } else { ?>
            <p><i>No versions yet. They are stored in <code><?php echo $h(collection_access_manager_versions_dir()); ?></code> - check that the web server can write there.</i></p>
        <?php } ?>
    </div>

    <!-- Reset -->
    <div class="fa-box">
        <h3>Reset plugin</h3>
        <p>Deletes everything the plugin stored in <code><?php echo $h(collection_access_manager_base_dir()); ?></code> (the version history).
            <b>The featured collection access of the user groups is not changed.</b> This cannot be undone.</p>
        <form method="post" action="<?php echo $h($self); ?>" id="fa-reset">
            <?php generateFormToken('collection_access_manager'); ?>
            <div class="fa-row-ctl">
                <label>To confirm, type <b>RESET</b> <input type="text" name="reset_confirm" autocomplete="off" spellcheck="false"></label>
                <button type="submit" name="action" value="reset">Reset plugin</button>
            </div>
        </form>
    </div>

    <script<?php echo $nonce; ?>>
    (function () {
        var table = document.getElementById('fa-table');
        if (!table) { return; }
        var $ = function (id) { return document.getElementById(id); };
        var page = document.querySelector('.fa-page');
        var leaving = false, pageNo = 0, shown = [];

        // ---- Background of the fixed header / columns: follows light and dark themes ----
        function updateBg() {
            var el = page.parentNode, bg = '';
            while (el && el.nodeType === 1) {
                bg = window.getComputedStyle(el).backgroundColor;
                if (bg && bg !== 'transparent' && !/rgba\(.*,\s*0\)$/.test(bg)) { break; }
                bg = '';
                el = el.parentNode;
            }
            page.style.setProperty('--fa-bg', bg || '#fff');
        }
        var bgTimer = null;
        function updateBgSoon() { clearTimeout(bgTimer); bgTimer = setTimeout(updateBg, 50); }
        updateBg();
        window.addEventListener('load', updateBg);
        document.addEventListener('load', function (e) { if (e.target && e.target.tagName === 'LINK') { updateBgSoon(); } }, true);
        if (window.MutationObserver) {
            var mo = new MutationObserver(updateBgSoon);
            mo.observe(document.head, { childList: true, subtree: true, attributes: true, attributeFilter: ['href', 'media', 'disabled'] });
        }
        if (window.matchMedia) {
            var mq = window.matchMedia('(prefers-color-scheme: dark)');
            if (mq.addEventListener) { mq.addEventListener('change', updateBgSoon); }
        }

        // ---- Model ----
        var groups = [].slice.call(table.querySelectorAll('tr.fa-head th.fa-gh')).map(function (th, i) {
            return { i: i, th: th, ref: th.getAttribute('data-ref'), parent: th.getAttribute('data-parent'),
                     name: th.getAttribute('data-name'), inh: th.hasAttribute('data-inh') };
        });
        var rows = [].slice.call(table.querySelectorAll('tbody tr.fa-row')).map(function (tr) {
            var cells = [].slice.call(tr.querySelectorAll('td')).filter(function (td) { return !td.classList.contains('fa-code') && !td.classList.contains('fa-act'); });
            var boxes = cells.map(function (td) { return td.firstElementChild; });
            return { tr: tr, kind: tr.getAttribute('data-kind'), ref: tr.getAttribute('data-ref'), parent: tr.getAttribute('data-parent'),
                     path: (tr.getAttribute('data-path') || '').split(',').filter(Boolean), text: tr.getAttribute('data-text') || '',
                     boxes: boxes, init: boxes.map(function (b) { return b.checked; }), children: [] };
        });
        var byRef = {};
        rows.forEach(function (r) { if (r.kind === 'fc') { byRef[r.ref] = r; } });
        rows.forEach(function (r) { if (r.kind === 'fc' && byRef[r.parent]) { byRef[r.parent].children.push(r); } });
        var fcRows = rows.filter(function (r) { return r.kind === 'fc'; });
        var rowAll = rows.filter(function (r) { return r.kind === 'all'; })[0];
        var rowSearch = rows.filter(function (r) { return r.kind === 'search'; })[0];

        function descendants(r) {
            var out = [];
            r.children.forEach(function (c) { out.push(c); out = out.concat(descendants(c)); });
            return out;
        }
        function mark(r, gi) { r.boxes[gi].parentNode.classList.toggle('fa-chg', r.boxes[gi].checked !== r.init[gi]); }

        // Tick = the parent categories too; untick = everything below too
        function setFc(r, gi, on) {
            r.boxes[gi].checked = on;
            mark(r, gi);
            if (on) {
                r.path.forEach(function (ref) { var p = byRef[ref]; if (p && !p.boxes[gi].checked) { p.boxes[gi].checked = true; mark(p, gi); } });
            } else {
                descendants(r).forEach(function (d) { if (d.boxes[gi].checked) { d.boxes[gi].checked = false; mark(d, gi); } });
            }
        }
        function setBox(r, gi, on) {
            if (groups[gi].inh) { return; }
            if (r.kind === 'fc') { setFc(r, gi, on); } else { r.boxes[gi].checked = on; mark(r, gi); }
        }
        // Cells whose parent category is hidden for the group are shown faded
        function refreshOff(gi) {
            fcRows.forEach(function (r) {
                var p = byRef[r.parent];
                r.boxes[gi].parentNode.classList.toggle('fa-off', !!p && !p.boxes[gi].checked);
            });
        }

        // ---- Changes ----
        function changedGroups() {
            return groups.filter(function (g) {
                return !g.inh && rows.some(function (r) { return r.boxes[g.i].checked !== r.init[g.i]; });
            });
        }
        function updateCount() {
            var n = 0;
            rows.forEach(function (r) { groups.forEach(function (g) { if (r.boxes[g.i].checked !== r.init[g.i]) { n++; } }); });
            var gs = changedGroups().length;
            $('fa-count').textContent = n ? n + ' change(s) in ' + gs + ' group(s) - not saved yet.' : 'No changes.';
            $('fa-count').classList.toggle('fa-has', n > 0);
            $('fa-savebtn').disabled = n === 0;
            $('fa-discard').disabled = n === 0;
        }
        function collect() {
            var out = {};
            changedGroups().forEach(function (g) {
                out[g.ref] = {
                    all: rowAll.boxes[g.i].checked,
                    search: rowSearch.boxes[g.i].checked,
                    visible: fcRows.filter(function (r) { return r.boxes[g.i].checked; }).map(function (r) { return parseInt(r.ref, 10); })
                };
            });
            return out;
        }
        function afterChange(gis) {
            gis.forEach(refreshOff);
            updateCount();
            if ($('fa-changed').checked || $('fa-diff').checked) { renderRows(); }
        }

        table.addEventListener('change', function (e) {
            var cb = e.target;
            if (!cb || cb.type !== 'checkbox') { return; }
            var tr = cb.closest('tr'), r = rows.filter(function (x) { return x.tr === tr; })[0];
            if (!r) { return; }
            var gi = r.boxes.indexOf(cb);
            setBox(r, gi, cb.checked);
            afterChange([gi]);
        });
        table.addEventListener('click', function (e) {
            var b = e.target;
            if (b.classList.contains('fa-all') || b.classList.contains('fa-none')) {
                var tr = b.closest('tr'), r = rows.filter(function (x) { return x.tr === tr; })[0];
                shown.forEach(function (gi) { setBox(r, gi, b.classList.contains('fa-all')); });
                afterChange(shown);
            } else if (b.classList.contains('fa-tog')) {
                var row = byRef[b.closest('tr').getAttribute('data-ref')];
                row.tr.classList.toggle('fa-closed');
                renderRows();
            }
        });

        $('fa-discard').addEventListener('click', function () {
            if (!confirm('Discard all changes that are not saved?')) { return; }
            rows.forEach(function (r) { r.boxes.forEach(function (b, gi) { b.checked = r.init[gi]; mark(r, gi); }); });
            afterChange(groups.map(function (g) { return g.i; }));
        });

        $('fa-copy').addEventListener('click', function () {
            var from = groups.filter(function (g) { return g.ref === $('fa-copy-from').value; })[0];
            var to = groups.filter(function (g) { return g.ref === $('fa-copy-to').value; })[0];
            if (!from || !to || from === to) { return; }
            if (!confirm('Replace the featured collection access of the target group in the table with that of the source group? (Not saved until you click Save changes.)')) { return; }
            rows.forEach(function (r) { r.boxes[to.i].checked = r.boxes[from.i].checked; mark(r, to.i); });
            afterChange([to.i]);
        });

        // ---- Columns: group filter and pages ----
        function filteredGroups() {
            var q = $('fa-gq').value.trim().toLowerCase(), p = $('fa-parent').value;
            return groups.filter(function (g) {
                if (q && g.name.indexOf(q) === -1 && g.ref !== q) { return false; }
                if (p === 'top') { return g.parent === '0' || !groups.some(function (x) { return x.ref === g.parent; }); }
                if (p) { return g.parent === p; }
                return true;
            });
        }
        var allTrs = [].slice.call(table.querySelectorAll('tr'));
        function renderColumns() {
            var list = filteredGroups(), size = parseInt($('fa-size').value, 10) || 15;
            var pages = Math.max(1, Math.ceil(list.length / size));
            pageNo = Math.min(pageNo, pages - 1);
            var sel = $('fa-page');
            sel.innerHTML = '';
            for (var p = 0; p < pages; p++) {
                var o = document.createElement('option');
                o.value = p;
                o.textContent = list.length ? (p * size + 1) + '-' + Math.min(list.length, (p + 1) * size) : '0';
                sel.appendChild(o);
            }
            sel.value = pageNo;
            var part = list.slice(pageNo * size, (pageNo + 1) * size), vis = {};
            shown = part.map(function (g) { vis[g.i] = true; return g.i; });
            groups.forEach(function (g) { g.th.classList.toggle('fa-hide', !vis[g.i]); });
            rows.forEach(function (r) { r.boxes.forEach(function (b, gi) { b.parentNode.classList.toggle('fa-hide', !vis[gi]); }); });
            allTrs.forEach(function (tr) { var td = tr.querySelector('td[colspan]'); if (td) { td.colSpan = shown.length + 3; } });
            $('fa-gstat').textContent = '(' + list.length + ' of ' + groups.length + ')';
            $('fa-prev').disabled = pageNo === 0;
            $('fa-next').disabled = pageNo >= pages - 1;
            renderRows();
        }

        // ---- Rows: search, differing / unsaved, open / closed categories ----
        function renderRows() {
            var q = $('fa-q').value.trim().toLowerCase(), diff = $('fa-diff').checked, chg = $('fa-changed').checked;
            var keep = {}, count = 0;
            if (q) {
                fcRows.forEach(function (r) {
                    if (r.text.indexOf(q) !== -1) { keep[r.ref] = true; r.path.forEach(function (p) { keep[p] = true; }); }
                });
            }
            rows.forEach(function (r) {
                var show = true;
                if (r.kind === 'fc') {
                    if (q && !keep[r.ref]) { show = false; }
                    // closed category: hide its children (not while searching)
                    if (show && !q && r.path.some(function (p) { return byRef[p] && byRef[p].tr.classList.contains('fa-closed'); })) { show = false; }
                }
                if (show && diff) {
                    var seen = {};
                    shown.forEach(function (gi) { seen[r.boxes[gi].checked ? 1 : 0] = true; });
                    show = seen[0] && seen[1];
                }
                if (show && chg) {
                    show = shown.some(function (gi) { return r.boxes[gi].checked !== r.init[gi]; });
                }
                r.tr.classList.toggle('fa-fold', !show);
                if (show && r.kind === 'fc') { count++; }
            });
            $('fa-qstat').textContent = '(' + count + ' of ' + fcRows.length + ' shown)';
        }

        ['fa-gq'].forEach(function (id) { $(id).addEventListener('input', function () { pageNo = 0; renderColumns(); }); });
        ['fa-parent', 'fa-size'].forEach(function (id) { $(id).addEventListener('change', function () { pageNo = 0; renderColumns(); }); });
        $('fa-page').addEventListener('change', function () { pageNo = parseInt(this.value, 10) || 0; renderColumns(); });
        $('fa-prev').addEventListener('click', function () { if (pageNo > 0) { pageNo--; renderColumns(); } });
        $('fa-next').addEventListener('click', function () { pageNo++; renderColumns(); });
        $('fa-q').addEventListener('input', renderRows);
        ['fa-diff', 'fa-changed'].forEach(function (id) { $(id).addEventListener('change', renderRows); });
        [$('fa-q'), $('fa-gq')].forEach(function (inp) {
            inp.addEventListener('keydown', function (e) { if (e.key === 'Escape') { inp.value = ''; inp.dispatchEvent(new Event('input')); } });
        });
        $('fa-open-all').addEventListener('click', function () { fcRows.forEach(function (r) { r.tr.classList.remove('fa-closed'); }); renderRows(); });
        $('fa-close-all').addEventListener('click', function () { fcRows.forEach(function (r) { if (r.children.length) { r.tr.classList.add('fa-closed'); } }); renderRows(); });

        // ---- Save / leave ----
        $('fa-form').addEventListener('submit', function () {
            $('fa-changes').value = JSON.stringify(collect());
            leaving = true;
        });
        var vform = $('fa-vform');
        if (vform) {
            vform.addEventListener('submit', function (e) {
                var act = (e.submitter && e.submitter.value) || '';
                var q = act === 'restore_version'
                    ? 'Restore the featured collection access of all groups to "' + $('fa-version').selectedOptions[0].textContent + '"?' + (changedGroups().length ? ' Unsaved changes in the table are lost.' : '')
                    : 'Delete the whole version history except the newest version? This cannot be undone.';
                if (!confirm(q)) { e.preventDefault(); return; }
                leaving = true;
            });
        }
        window.addEventListener('beforeunload', function (e) {
            if (!leaving && changedGroups().length) { e.preventDefault(); e.returnValue = ''; }
        });

        var resetForm = $('fa-reset');
        if (resetForm) {
            resetForm.addEventListener('submit', function (e) {
                if (resetForm.reset_confirm.value.trim() !== 'RESET') { alert('Type RESET to confirm.'); e.preventDefault(); return; }
                if (!confirm('Delete the version history of Collection Access Manager? The access of the groups stays as it is.')) { e.preventDefault(); return; }
                leaving = true;
            });
        }

        groups.forEach(function (g) { refreshOff(g.i); });
        renderColumns();
        updateCount();
    })();
    </script>
    <?php } ?>
</div>
<?php
include '../../../include/footer.php';
