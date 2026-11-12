# Collection Access Manager – documentation

**English** | [Polski](README.pl.md)

Documentation for plugin version **1.04** (ResourceSpace 11).
*(since X.YY)* marks the plugin version in which a feature first appeared. Features without
a mark have been available since 1.00. Change history (in Polish): [CHANGELOG.md](CHANGELOG.md).

The plugin shows in one table **which user groups can see which featured collections**
(featured collection categories and collections, formerly "Themes"). Rows are the collection
tree and columns are user groups. Instead of going through the permissions of every group, you
tick boxes in the table.

**Where:** System > Plugins > Collection Access Manager > **Options**. Only
administrators (permission `a`) can open this page.


**Name** *(since 1.04)*: up to version 1.03 the plugin was called **Featured Collection Access**
(folder `fcaccess`). For ResourceSpace it is a different plugin: install
`collection_access_manager`, activate it, then deactivate and delete `fcaccess` (the options page
reminds you while it is still active). On first use the plugin copies the version history of the
old one; the old folder `<filestore>/fcaccess` is kept. The access of the groups is stored by
ResourceSpace and does not change.

---

## How it works

ResourceSpace stores featured collection access as codes in the group permissions:

| Code | Meaning |
|---|---|
| `j*` | the group can see all featured collections, also new ones, except hidden ones |
| `j<ref>` | the group can see the top-level entry `<ref>` with everything in it |
| `-j<ref>` | the entry `<ref>` (and everything below it) is hidden |
| `J` | searches only show resources in featured collections the group can see |

You do not need the codes in the table: **a ticked box means the group can see the entry**.
When you save, the plugin works out the codes the way ResourceSpace reads them:

- top-level entry: without `j*` a visible entry gets `j<ref>`; with `j*` a hidden entry gets
  `-j<ref>`,
- entry below the top level, hidden: gets `-j<ref>`, also when its parent category is hidden.
  ResourceSpace checks direct links from the top of the tree down, so without this code a hidden
  collection could still be opened through a link.

**All other permissions of the groups are left unchanged.** Codes for collections that no
longer exist are kept too.

## Table

- **"General" rows:**
  - *All featured collections, also new ones* (`j*`): new featured collections are visible to
    the group straight away. Without it, new top-level entries have to be ticked by hand,
  - *Search shows only resources in featured collections the group can see* (`J`).
- **Featured collection tree:** categories with indentation.
  - The arrow opens and closes a branch.
  - Next to the name you see the number of entries in a category, or the number of resources in
    a collection.
  - The **Code** column shows the code the row is about.
- **Ticking** an entry also ticks its parent categories, because a collection cannot be seen
  without the category it is in. **Unticking** hides the whole branch below it.
- A box in a branch whose parent category is hidden is shown faded.
- **Blue frame** means a change that is not saved yet.
- **Groups that inherit permissions** from their parent group are read-only (greyed) and show
  the parent group's state.
- **Select > All / None** ticks or unticks the row for all shown groups.

## Filters

**Featured collections (rows):**
- **Search featured collections** – name or number; shows the matches with their parent
  categories (Esc clears the field),
- **Show only: differing** – only rows where the shown groups have different settings,
- **Show only: unsaved** – only rows with unsaved changes,
- **Tree: open all / close all** – opens or closes all categories.

**User groups (columns):** search by name, parent group filter, columns per page (10, 15, 20)
and page selection with « » buttons.

## Saving, copying, history

- **Save changes** – saves the changed groups. Before that, the previous state of all groups is
  stored in the version history. Saving uses ResourceSpace's functions (`save_usergroup`) and is
  recorded in the activity log.
- **Discard changes** – reverts all unsaved changes in the table.
- **Copy access** – copies all ticks (also the two "General" rows) from one group to another.
  Nothing is saved until you click "Save changes".
- **Version history:**
  - the first version is created when the plugin is activated, a new one at every save (the
    last 50 are kept),
  - **Restore this version** writes the featured collection codes of all groups from that
    version back (other permissions are kept),
  - **Delete all history** keeps only the newest version.

## Rules of use

1. **Decide about `j*` first.** With `j*` the group sees everything and you only hide entries in
   the table. Without `j*` the group sees only what you tick, and new top-level entries stay
   hidden from it until you tick them.
2. **When comparing groups, tick "differing".** You see only the rows where the groups differ.
3. **Before saving, tick "unsaved"** to check what will change.
4. **New subcategories in a hidden branch** may be visible to a group with `j*` until you open
   this page and save the group. After adding categories, check the table.
5. **Inheriting groups are not changed here.** Change the parent group, or turn off inheritance
   on the group page in ResourceSpace.
6. **Other group permissions** (e.g. Admin access) are set on the group page in ResourceSpace
   or with the Permission Group Manager plugin.

## Installation and files on the server

### Download and installation

The plugin is distributed as a `collection_access_manager-<version>.zip` archive on the releases page:
<https://github.com/s29829/collection_access_manager/releases>. The archive contains the folder `collection_access_manager/` with the code,
license and documentation. Below, `<RS_ROOT>` is the ResourceSpace installation folder (e.g.
`/var/www/resourcespace`) and `www-data` is the web server user (e.g. `apache` on other systems).

**In the browser:** Plugin Manager > Upload a plugin > choose the `.zip` file > Upload (when
updating: **Overwrite**).

**From the command line (CLI)** – download, unpack and set the permissions:

```bash
VER=<version>                    # version number from the releases page, e.g. 1.00
cd /tmp
curl -fLO https://github.com/s29829/collection_access_manager/releases/download/v$VER/collection_access_manager-$VER.zip
sudo rm -rf <RS_ROOT>/plugins/collection_access_manager        # only when updating (the plugin data is in the filestore)
sudo unzip -q collection_access_manager-$VER.zip -d <RS_ROOT>/plugins/
sudo chown -R www-data:www-data <RS_ROOT>/plugins/collection_access_manager
sudo find <RS_ROOT>/plugins/collection_access_manager -type d -exec chmod 755 {} \;
sudo find <RS_ROOT>/plugins/collection_access_manager -type f -exec chmod 644 {} \;
```

**With git:** the repository <https://github.com/s29829/collection_access_manager> contains the complete plugin folder:

```bash
sudo git clone https://github.com/s29829/collection_access_manager <RS_ROOT>/plugins/collection_access_manager
sudo chown -R www-data:www-data <RS_ROOT>/plugins/collection_access_manager
```

Update: `sudo git -C <RS_ROOT>/plugins/collection_access_manager pull`.

Finally: System > Plugins, activate "Collection Access Manager" and click **Options**. The plugin creates its
data folder `<filestore>/collection_access_manager` itself – it only needs write access to the filestore, which
ResourceSpace requires anyway.

### Files

Everything the plugin stores is in `<filestore>/collection_access_manager/` *(since 1.04)*: the version history
in the subfolder `data_<key>` (the key derives from `scramble_key`), files
`v<N>_<date>_<time>.json` with the featured collection codes of every group. `<filestore>` is
ResourceSpace's `$storagedir` (default `<RS_ROOT>/filestore`). Updating or removing the plugin code never deletes these files. Versions 1.01–1.03 (`fcaccess`) stored the history in `<filestore>/fcaccess/`,
version 1.00 in `<filestore>/fcaccess_versions_<key>/` – version 1.04 copies it automatically.

**The plugin creates its folders itself** on first use (activation, opening the options page).
It only needs write access to the filestore, which ResourceSpace requires anyway.

## Reset plugin *(since 1.01)*

The **Reset plugin** box (after typing `RESET`) deletes the version history in
`<filestore>/collection_access_manager`. **The featured collection access of the groups is not changed** – the
current state becomes version v1 again. This cannot be undone.

## License

- The plugin is licensed under the **PolyForm Noncommercial License 1.0.0** (file
  [LICENSE](LICENSE)).
- It is **free** for private and noncommercial use, and for noncommercial organizations
  (education, public institutions, charities). You may change and share it for these purposes,
  keeping the license text.
- **Commercial use** (a company, services for clients, a paid product) requires the author's
  written permission: <https://github.com/s29829>. Details: [LICENSE-COMMERCIAL.md](LICENSE-COMMERCIAL.md).
