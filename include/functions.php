<?php
// Helper functions for the Collection Access Manager page.
//
// ResourceSpace decides which featured collections a user group can see from these permission
// codes in usergroup.permissions (see include/collections_functions.php in ResourceSpace):
//   j*        the group can see all featured collections (also new ones), except hidden ones
//   j<ref>    the group can see the featured collection <ref> (used for top-level entries)
//   -j<ref>   the featured collection <ref> is hidden (used below top level, or with j*)
//   J         searches only show resources that are in featured collections the group can see
//
// The page works with "visible" ticks instead of codes. The set of visible entries is always
// closed upwards (a visible entry has visible parents); collection_access_manager_encode() turns it into codes.
// All other permission codes of a group are never changed.
//
// ResourceSpace shows its error page for every PHP warning, also for calls silenced with "@",
// so files and folders are checked before they are used.

const COLLECTION_ACCESS_MANAGER_KEEP_VERSIONS = 50;

// Translated text of multilingual values such as "~en:Title~pl:Tytuł"
function collection_access_manager_tr($text)
{
    return function_exists('i18n_get_translated') ? (string) i18n_get_translated((string) $text) : (string) $text;
}

// "a,b, c,,a" -> array('a', 'b', 'c')
function collection_access_manager_parse($csv)
{
    $out = array();
    foreach (explode(',', (string) $csv) as $c) {
        $c = trim($c);
        if ($c !== '' && !in_array($c, $out, true)) {
            $out[] = $c;
        }
    }
    return $out;
}

// Is the code one of the featured collection codes handled by this plugin?
function collection_access_manager_is_fc_code($code)
{
    return $code === 'j*' || $code === 'J' || preg_match('/^-?j\d+$/', (string) $code) === 1;
}

// ---------------------------------------------------------------------------
// Featured collections tree
// ---------------------------------------------------------------------------

// All featured collections in tree order: ref => [ref, name, parent, depth, children, resources, path]
// (parent 0 = top level; children = refs of the sub-entries)
function collection_access_manager_tree()
{
    $type = defined('COLLECTION_TYPE_FEATURED') ? COLLECTION_TYPE_FEATURED : 3;
    $rows = ps_query(
        'SELECT c.ref, c.name, c.parent, (SELECT COUNT(*) FROM collection_resource cr WHERE cr.collection = c.ref) AS resources
           FROM collection c WHERE c.type = ?',
        array('i', $type)
    );
    $all = array();
    foreach ((array) $rows as $r) {
        $ref = (int) $r['ref'];
        $all[$ref] = array(
            'ref'       => $ref,
            'name'      => collection_access_manager_tr($r['name'] ?? ('#' . $ref)),
            'raw'       => (string) ($r['name'] ?? ''), // stored name (may hold translations "~en:...~pl:...")
            'parent'    => (int) ($r['parent'] ?? 0),
            'resources' => (int) ($r['resources'] ?? 0),
            'children'  => array(),
        );
    }
    // A parent that is not a featured collection counts as top level
    foreach ($all as $ref => $n) {
        if ($n['parent'] !== 0 && !isset($all[$n['parent']])) {
            $all[$ref]['parent'] = 0;
        }
    }
    $by_parent = array();
    foreach ($all as $ref => $n) {
        $by_parent[$n['parent']][] = $ref;
    }
    foreach ($by_parent as $p => $refs) {
        usort($refs, function ($a, $b) use ($all) {
            return strnatcasecmp($all[$a]['name'], $all[$b]['name']);
        });
        $by_parent[$p] = $refs;
        if ($p !== 0 && isset($all[$p])) {
            $all[$p]['children'] = $refs;
        }
    }
    // Depth-first order, with depth and path (protects against parent loops)
    $out  = array();
    $walk = function ($parent, $depth, $path) use (&$walk, &$out, $all, $by_parent) {
        foreach ($by_parent[$parent] ?? array() as $ref) {
            if (isset($out[$ref]) || $depth > 30) {
                continue;
            }
            $n          = $all[$ref];
            $n['depth'] = $depth;
            $n['path']  = $path;
            $out[$ref]  = $n;
            $walk($ref, $depth + 1, array_merge($path, array($ref)));
        }
    };
    $walk(0, 0, array());
    return $out;
}

// ---------------------------------------------------------------------------
// Folders: create, rename, delete featured collections (since 1.05). Uses the same functions
// as ResourceSpace's own pages (pages/collection_manage.php "call to action" tile and
// collection_edit.php): create_collection() + save_collection() with "featured_collections_changes",
// delete_collection(). Group permissions are not changed.
// ---------------------------------------------------------------------------

// Cleans a folder name: trimmed, no control characters, 1-100 characters ('' = invalid)
function collection_access_manager_folder_name($name)
{
    $name = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $name) ?? '');
    $len  = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);
    return ($len >= 1 && $len <= 100) ? $name : '';
}

// Creates a featured collection (folder) below $parent (0 = top level). Returns its ref or 0.
function collection_access_manager_folder_create($name, $parent, &$error)
{
    $error  = '';
    $name   = collection_access_manager_folder_name($name);
    $parent = (int) $parent;
    $type   = defined('COLLECTION_TYPE_FEATURED') ? COLLECTION_TYPE_FEATURED : 3;
    if ($name === '') {
        $error = 'Enter a folder name (1-100 characters).';
        return 0;
    }
    if ($parent !== 0 && !isset(collection_access_manager_tree()[$parent])) {
        $error = 'The parent folder does not exist any more.';
        return 0;
    }
    if (!function_exists('create_collection') || !function_exists('save_collection')) {
        $error = 'This ResourceSpace version does not provide create_collection() / save_collection().';
        return 0;
    }
    $new = (int) create_collection((int) ($GLOBALS['userref'] ?? 0), $name);
    if ($new <= 0) {
        $error = 'The folder could not be created.';
        return 0;
    }
    $changes = array('update_parent' => $parent, 'force_featured_collection_type' => true);
    $bg      = $GLOBALS['FEATURED_COLLECTION_BG_IMG_SELECTION_OPTIONS']['most_popular_images'] ?? null;
    if ($bg !== null) {
        $changes['thumbnail_selection_method'] = $bg;
    }
    save_collection($new, array('name' => $name, 'featured_collections_changes' => $changes));
    // Check the result; fall back to setting type and parent directly
    $row = ps_query('SELECT type, parent FROM collection WHERE ref = ?', array('i', $new));
    $row = $row[0] ?? null;
    if (!$row || (int) $row['type'] !== $type || (int) ($row['parent'] ?? 0) !== $parent) {
        if ($parent === 0) {
            ps_query('UPDATE collection SET type = ?, parent = NULL WHERE ref = ?', array('i', $type, 'i', $new));
        } else {
            ps_query('UPDATE collection SET type = ?, parent = ? WHERE ref = ?', array('i', $type, 'i', $parent, 'i', $new));
        }
        if (function_exists('clear_query_cache')) {
            clear_query_cache('featured_collections');
        }
    }
    return $new;
}

// Renames a featured collection
function collection_access_manager_folder_rename($ref, $name, &$error)
{
    $error = '';
    $ref   = (int) $ref;
    $name  = collection_access_manager_folder_name($name);
    if ($name === '') {
        $error = 'Enter a folder name (1-100 characters).';
        return false;
    }
    if (!isset(collection_access_manager_tree()[$ref])) {
        $error = 'The folder does not exist any more.';
        return false;
    }
    if (function_exists('save_collection')) {
        save_collection($ref, array('name' => $name));
    }
    if ((string) ps_value('SELECT name value FROM collection WHERE ref = ?', array('i', $ref), '') !== $name) {
        ps_query('UPDATE collection SET name = ? WHERE ref = ?', array('s', $name, 'i', $ref));
        if (function_exists('clear_query_cache')) {
            clear_query_cache('featured_collections');
        }
    }
    return (string) ps_value('SELECT name value FROM collection WHERE ref = ?', array('i', $ref), '') === $name;
}

// Deletes an EMPTY featured collection (no sub-folders, no resources) - like ResourceSpace's
// can_delete_featured_collection(). Codes j<ref> / -j<ref> left in groups are ignored by ResourceSpace.
function collection_access_manager_folder_delete($ref, &$error)
{
    $error = '';
    $ref   = (int) $ref;
    $tree  = collection_access_manager_tree();
    if (!isset($tree[$ref])) {
        $error = 'The folder does not exist any more.';
        return false;
    }
    if ($tree[$ref]['children']) {
        $error = '"' . $tree[$ref]['name'] . '" has sub-folders - delete or move them first.';
        return false;
    }
    if ($tree[$ref]['resources'] > 0) {
        $error = '"' . $tree[$ref]['name'] . '" contains ' . $tree[$ref]['resources'] . ' resource(s) - remove them from the collection first.';
        return false;
    }
    if (!function_exists('delete_collection')) {
        $error = 'This ResourceSpace version does not provide delete_collection().';
        return false;
    }
    delete_collection($ref);
    if ((int) ps_value('SELECT COUNT(*) value FROM collection WHERE ref = ?', array('i', $ref), 0) > 0) {
        $error = 'ResourceSpace did not delete "' . $tree[$ref]['name'] . '".';
        return false;
    }
    return true;
}

// ---------------------------------------------------------------------------
// User groups
// ---------------------------------------------------------------------------

// All user groups: ref => [ref, name, parent, perms, inherits]
function collection_access_manager_groups()
{
    $out = array();
    foreach ((array) ps_query('SELECT * FROM usergroup ORDER BY name', array()) as $g) {
        $flags = collection_access_manager_parse($g['inherit_flags'] ?? '');
        $ref   = (int) $g['ref'];
        $out[$ref] = array(
            'ref'      => $ref,
            'name'     => collection_access_manager_tr($g['name'] ?? ('#' . $ref)),
            'parent'   => (int) ($g['parent'] ?? 0),
            'perms'    => collection_access_manager_parse($g['permissions'] ?? ''),
            'inherits' => in_array('permissions', $flags, true) && (int) ($g['parent'] ?? 0) > 0,
        );
    }
    uasort($out, function ($a, $b) {
        return strnatcasecmp($a['name'], $b['name']);
    });
    return $out;
}

// Permissions that apply to a group (follows "inherit permissions from parent")
function collection_access_manager_effective(array $groups, $ref, $depth = 0)
{
    if (!isset($groups[$ref])) {
        return array();
    }
    $g = $groups[$ref];
    if ($g['inherits'] && isset($groups[$g['parent']]) && $depth < 20) {
        return collection_access_manager_effective($groups, $g['parent'], $depth + 1);
    }
    return $g['perms'];
}

// ---------------------------------------------------------------------------
// Codes <-> visible entries
// ---------------------------------------------------------------------------

// Reads the codes of a group: array(all => bool (j*), search => bool (J), visible => [ref => true])
function collection_access_manager_decode(array $perms, array $tree)
{
    $all     = in_array('j*', $perms, true);
    $visible = array();
    foreach ($tree as $ref => $n) { // tree order: parents before children
        if (in_array('-j' . $ref, $perms, true)) {
            continue;
        }
        if ($n['parent'] === 0) {
            if ($all || in_array('j' . $ref, $perms, true)) {
                $visible[$ref] = true;
            }
        } elseif (isset($visible[$n['parent']])) {
            $visible[$ref] = true;
        }
    }
    return array('all' => $all, 'search' => in_array('J', $perms, true), 'visible' => $visible);
}

// Codes for a state (visible must be closed upwards; children of hidden entries are hidden):
//   top level:  without j*: j<ref> when visible        with j*: -j<ref> when hidden
//   below:      -j<ref> when hidden and its top-level entry is visible (or j* is set) - also for
//               deeper hidden entries, because ResourceSpace checks a direct link from the top down
function collection_access_manager_encode($all, $search, array $visible, array $tree)
{
    $codes = array();
    if ($all) {
        $codes[] = 'j*';
    }
    if ($search) {
        $codes[] = 'J';
    }
    foreach ($tree as $ref => $n) {
        $vis = isset($visible[$ref]) && ($n['parent'] === 0 || isset($visible[$n['parent']]));
        if (!$vis) {
            unset($visible[$ref]); // keep the set closed upwards for the children
        }
        if ($n['parent'] === 0) {
            if ($all && !$vis) {
                $codes[] = '-j' . $ref;
            } elseif (!$all && $vis) {
                $codes[] = 'j' . $ref;
            }
        } elseif (!$vis) {
            $top = $n['path'][0] ?? $ref;
            if ($all || isset($visible[$top])) {
                $codes[] = '-j' . $ref;
            }
        }
    }
    return $codes;
}

// New permission list of a group: all non-featured-collection codes are kept as they are,
// codes for featured collections that no longer exist are kept too
function collection_access_manager_merge(array $perms, array $fc_codes, array $tree)
{
    $out = array();
    foreach ($perms as $c) {
        if ($c === 'j*' || $c === 'J') {
            continue;
        }
        if (preg_match('/^-?j(\d+)$/', $c, $m) && isset($tree[(int) $m[1]])) {
            continue;
        }
        $out[] = $c;
    }
    return array_values(array_unique(array_merge($out, $fc_codes)));
}

// Writes the permissions of one group (same functions as ResourceSpace's own page)
function collection_access_manager_save_group($ref, array $new, array $old)
{
    $csv = implode(',', $new);
    if (function_exists('save_usergroup')) {
        save_usergroup((int) $ref, array('permissions' => $csv));
    } else {
        ps_query('UPDATE usergroup SET permissions = ? WHERE ref = ?', array('s', $csv, 'i', (int) $ref));
    }
    if (function_exists('log_activity') && defined('LOG_CODE_EDITED')) {
        try {
            log_activity(null, LOG_CODE_EDITED, $csv, 'usergroup', 'permissions', (int) $ref, null, implode(',', $old));
        } catch (Throwable $e) {
            // logging must not stop the save
        }
    }
    $stored = collection_access_manager_parse(ps_value('SELECT permissions value FROM usergroup WHERE ref = ?', array('i', (int) $ref), ''));
    $want   = $new;
    sort($stored);
    sort($want);
    return $stored === $want;
}

// Applies the states sent by the page: {"<group ref>": {"all": bool, "search": bool, "visible": [refs]}}
// Returns the number of saved groups; problems are added to $errors.
function collection_access_manager_apply($json, array &$errors)
{
    $data = json_decode((string) $json, true);
    if (!is_array($data)) {
        $errors[] = 'No changes received.';
        return 0;
    }
    $tree   = collection_access_manager_tree();
    $groups = collection_access_manager_groups();
    $saved  = 0;
    foreach ($data as $ref => $st) {
        $ref = (int) $ref;
        if (!isset($groups[$ref]) || !is_array($st)) {
            $errors[] = 'Unknown user group #' . $ref . '.';
            continue;
        }
        $g = $groups[$ref];
        if ($g['inherits']) {
            $errors[] = '"' . $g['name'] . '" inherits its permissions from its parent group and was not changed.';
            continue;
        }
        $visible = array();
        foreach ((array) ($st['visible'] ?? array()) as $v) {
            if (isset($tree[(int) $v])) {
                $visible[(int) $v] = true;
            }
        }
        $codes = collection_access_manager_encode(!empty($st['all']), !empty($st['search']), $visible, $tree);
        $new   = collection_access_manager_merge($g['perms'], $codes, $tree);
        $a     = $new;
        $b     = $g['perms'];
        sort($a);
        sort($b);
        if ($a === $b) {
            continue;
        }
        if (collection_access_manager_save_group($ref, $new, $g['perms'])) {
            $saved++;
        } else {
            $errors[] = 'The permissions of "' . $g['name'] . '" could not be saved.';
        }
    }
    return $saved;
}

// ---------------------------------------------------------------------------
// Version history: <filestore>/collection_access_manager/data_<key>/v<N>_<YYYYMMDD>_<HHMMSS>.json (since 1.04;
// before that the plugin was "fcaccess" - see collection_access_manager_relocate()). Each version stores the featured
// collection codes of every group. <filestore>/collection_access_manager holds everything the plugin stores.
// ---------------------------------------------------------------------------

// $storagedir of ResourceSpace (default: <ResourceSpace root>/filestore)
function collection_access_manager_storage_dir()
{
    $s = (string) ($GLOBALS['storagedir'] ?? '');
    return rtrim($s !== '' ? $s : dirname(__FILE__, 4) . '/filestore', '/');
}

// <filestore>/collection_access_manager - everything the plugin stores (removed by "Reset plugin")
function collection_access_manager_base_dir()
{
    return collection_access_manager_storage_dir() . '/collection_access_manager';
}

function collection_access_manager_data_key()
{
    $key = (string) ($GLOBALS['scramble_key'] ?? '');
    return $key !== '' ? '_' . substr(md5($key . 'collection_access_manager'), 0, 12) : '';
}

function collection_access_manager_versions_dir()
{
    return collection_access_manager_base_dir() . '/data' . collection_access_manager_data_key();
}

// Before 1.04 the plugin was called "fcaccess". Its data is copied (not moved - the old plugin may
// still be installed, and nothing is deleted) to <filestore>/collection_access_manager/data_<key>/ the first time the new
// data folder is needed. Sources, newest first:
//   <filestore>/fcaccess/data_<old key>/          (fcaccess with data in the filestore)
//   <filestore>/fcaccess_versions_<old key>/      (fcaccess version 1.00)
// The old key is derived from $scramble_key and the old plugin name. Returns a message or ''.
function collection_access_manager_relocate()
{
    $new = collection_access_manager_versions_dir();
    if (is_dir($new)) {
        return '';
    }
    $storage = collection_access_manager_storage_dir();
    $key     = (string) ($GLOBALS['scramble_key'] ?? '');
    $oldkey  = $key !== '' ? '_' . substr(md5($key . 'fcaccess'), 0, 12) : '';
    $old     = '';
    foreach (array($storage . '/fcaccess/data' . $oldkey, $storage . '/fcaccess_versions' . $oldkey) as $dir) {
        if (is_dir($dir) && is_readable($dir)) {
            $old = $dir;
            break;
        }
    }
    if ($old === '') {
        return '';
    }
    $base = collection_access_manager_base_dir();
    if (!is_dir($base) && (!is_writable($storage) || !mkdir($base, 0755, true))) {
        return 'The data of the old plugin "fcaccess" in ' . $old . ' could not be copied to ' . $base . ' (no write access).';
    }
    if (collection_access_manager_copy_dir($old, $new)) {
        return 'The data of the old plugin "fcaccess" was copied from ' . $old . ' to ' . $new . ' (the old folder was kept).';
    }
    return 'The data of the old plugin "fcaccess" in ' . $old . ' could not be copied to ' . $new . ' (check the permissions).';
}

// Copies a folder with all its content (no "@")
function collection_access_manager_copy_dir($from, $to)
{
    if (!is_dir($to) && !mkdir($to, 0755, true)) {
        return false;
    }
    $ok = true;
    foreach (scandir($from) ?: array() as $e) {
        if ($e === '.' || $e === '..' || is_link($from . '/' . $e)) {
            continue;
        }
        $src = $from . '/' . $e;
        if (is_dir($src)) {
            $ok = collection_access_manager_copy_dir($src, $to . '/' . $e) && $ok;
        } elseif (is_readable($src)) {
            $ok = copy($src, $to . '/' . $e) && $ok;
        } else {
            $ok = false;
        }
    }
    return $ok;
}

// "Reset plugin": deletes the version history (<filestore>/collection_access_manager). The permissions of the
// user groups are NOT changed.
function collection_access_manager_reset()
{
    $rm = function ($p) use (&$rm) {
        if (is_link($p) || is_file($p)) {
            return unlink($p);
        }
        if (!is_dir($p)) {
            return true;
        }
        foreach (scandir($p) ?: array() as $e) {
            if ($e !== '.' && $e !== '..') {
                $rm($p . '/' . $e);
            }
        }
        return rmdir($p);
    };
    return $rm(collection_access_manager_base_dir());
}

function collection_access_manager_version_parse($name)
{
    if (!is_string($name) || !preg_match('/^v(\d+)_(\d{8}_\d{6})(?:_\d+)?$/', $name, $m)) {
        return null;
    }
    return array('num' => (int) $m[1], 'stamp' => $m[2]);
}

// Version names, oldest first
function collection_access_manager_versions()
{
    $out = array();
    foreach (glob(collection_access_manager_versions_dir() . '/v*.json') ?: array() as $f) {
        $n = basename($f, '.json');
        if (collection_access_manager_version_parse($n) !== null) {
            $out[] = $n;
        }
    }
    usort($out, function ($a, $b) {
        $pa = collection_access_manager_version_parse($a);
        $pb = collection_access_manager_version_parse($b);
        return array($pa['num'], $pa['stamp'], $a) <=> array($pb['num'], $pb['stamp'], $b);
    });
    return $out;
}

function collection_access_manager_current_version()
{
    $v = collection_access_manager_versions();
    return $v ? collection_access_manager_version_parse(end($v))['num'] : 0;
}

function collection_access_manager_version_label($name)
{
    $p  = collection_access_manager_version_parse($name);
    $dt = $p ? DateTime::createFromFormat('Ymd_His', $p['stamp']) : false;
    return ($p ? 'v' . $p['num'] . ' - ' : '') . ($dt ? $dt->format('Y-m-d H:i:s') : $name);
}

// Stores the current featured collection codes of all groups as the next version.
// Returns the version name or false.
function collection_access_manager_snapshot()
{
    collection_access_manager_relocate();
    $dir = collection_access_manager_versions_dir();
    if (!is_dir($dir)) {
        $parent = is_dir(collection_access_manager_base_dir()) ? collection_access_manager_base_dir() : collection_access_manager_storage_dir();
        if (!is_dir($parent) || !is_writable($parent) || !mkdir($dir, 0755, true)) {
            return false;
        }
    }
    if (!is_writable($dir)) {
        return false;
    }
    $state = array();
    foreach (collection_access_manager_groups() as $ref => $g) {
        $state[$ref] = array(
            'name'  => $g['name'],
            'codes' => array_values(array_filter($g['perms'], 'collection_access_manager_is_fc_code')),
        );
    }
    $name = 'v' . (collection_access_manager_current_version() + 1) . '_' . date('Ymd_His');
    $file = $dir . '/' . $name . '.json';
    for ($i = 1; is_file($file); $i++) {
        $file = $dir . '/' . $name . '_' . $i . '.json';
    }
    if (file_put_contents($file, json_encode($state, JSON_PRETTY_PRINT), LOCK_EX) === false) {
        return false;
    }
    $versions = collection_access_manager_versions();
    foreach (array_slice($versions, 0, max(0, count($versions) - COLLECTION_ACCESS_MANAGER_KEEP_VERSIONS)) as $old) {
        unlink($dir . '/' . $old . '.json');
    }
    return basename($file, '.json');
}

// Creates version v1 the first time (called by the "initialise" hook after activation)
function collection_access_manager_ensure_first_version()
{
    collection_access_manager_relocate(); // take over the data of the old plugin "fcaccess"
    $dir = collection_access_manager_versions_dir();
    if (is_dir($dir) && glob($dir . '/v*.json')) {
        return;
    }
    collection_access_manager_snapshot();
}

// Restores the featured collection codes of a version (other codes are kept).
// Returns the number of changed groups.
function collection_access_manager_restore_version($name, array &$errors)
{
    $file  = collection_access_manager_versions_dir() . '/' . $name . '.json';
    $state = (collection_access_manager_version_parse($name) !== null && is_file($file)) ? json_decode((string) file_get_contents($file), true) : null;
    if (!is_array($state)) {
        $errors[] = 'The version could not be read.';
        return 0;
    }
    $changed = 0;
    foreach (collection_access_manager_groups() as $ref => $g) {
        if ($g['inherits'] || !isset($state[$ref]['codes']) || !is_array($state[$ref]['codes'])) {
            continue;
        }
        $codes = array_values(array_filter(array_map('strval', $state[$ref]['codes']), 'collection_access_manager_is_fc_code'));
        $keep  = array_values(array_filter($g['perms'], function ($c) {
            return !collection_access_manager_is_fc_code($c);
        }));
        $new = array_values(array_unique(array_merge($keep, $codes)));
        $a   = $new;
        $b   = $g['perms'];
        sort($a);
        sort($b);
        if ($a === $b) {
            continue;
        }
        if (collection_access_manager_save_group($ref, $new, $g['perms'])) {
            $changed++;
        } else {
            $errors[] = 'The permissions of "' . $g['name'] . '" could not be restored.';
        }
    }
    return $changed;
}

// Deletes all versions except the newest. Returns the number of deleted files.
function collection_access_manager_clear_history()
{
    $versions = collection_access_manager_versions();
    array_pop($versions);
    $count = 0;
    foreach ($versions as $v) {
        $f = collection_access_manager_versions_dir() . '/' . $v . '.json';
        if (is_file($f) && unlink($f)) {
            $count++;
        }
    }
    return $count;
}
