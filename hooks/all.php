<?php
// Function name format: Hook + <plugin> + <page> + <hook name>
// Stores the featured collection permissions of all groups as version v1 after the plugin has
// been activated. After that it only checks that one file exists.
function HookCollection_access_managerAllInitialise()
{
    require_once dirname(__FILE__, 2) . '/include/functions.php';
    try {
        collection_access_manager_ensure_first_version();
    } catch (Throwable $e) {
        // never break a page because of the version history
    }
}
