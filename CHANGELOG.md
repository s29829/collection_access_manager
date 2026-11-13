# Changelog - Collection Access Manager

Plugin version numbers: 1.00, 1.01, 1.02 … (+0.01 for every change). The current version is in `collection_access_manager.yaml`.

## [1.08] - 2026-11-13
- Documentation: English is the main language - change history translated to English.

## [1.07] - 2026-11-13
- Documentation: installation on Apache (Ubuntu/Debian and the RHEL family) – user and group that run PHP (mod_php / PHP-FPM), permissions, no directory listings (Options -Indexes), SELinux.

## [1.06] - 2026-11-12
- Tree view: user group columns with checkboxes next to the folder tree (All / None, rows j* and J), group and row filters work in both views; the choice of a single group removed.

## [1.05] - 2026-11-12
- Tree view (Table / Tree switch) with the folder structure and the selections of the chosen group; creating (also subfolders at every entry), renaming and deleting empty folders (featured collections) in both views; collapsible "Reset plugin" box.

## [1.04] - 2026-11-12
- New name: Collection Access Manager (folder collection_access_manager, previously Featured Collection Access / fcaccess); on first use it copies the version history of the old plugin without deleting the old folder; the options page reminds you to deactivate fcaccess; new repository s29829/collection_access_manager.

## [1.03] - 2026-11-11
- Clean-up of the change history (no change in the plugin).

## [1.02] - 2026-11-11
- Documentation: installation with Plugin Manager or on the command line (curl, unzip, chown) without bash scripts; releases contain only the zip archive.

## [1.01] - 2026-11-11
- Version history in <filestore>/collection_access_manager/data_<key>/ with automatic move from <filestore>/collection_access_manager_versions_<key>/; fixed creation of the folder at the first save; "Reset plugin" (deletes the history, does not change group access).

## [1.00] - 2026-11-11
- First version: table of user group access to featured collections (rows = tree of categories and collections, columns = groups); selecting an entry selects the parent categories, deselecting hides the whole branch; general rows j* (all, also new ones) and J (search only in accessible collections).
- Codes j<ref> / -j<ref> calculated automatically with the ResourceSpace logic; other group permissions unchanged; inheriting groups read-only; filters (search, differing, unsaved, groups, column pages), copying between groups, version history (last 50, restore), saving with save_usergroup and an entry in the activity log.
