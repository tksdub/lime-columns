=== Lime Columns ===
Contributors: takuma227
Tags: columns, list table, custom fields, acf, admin
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Freely configure the columns of admin list tables: add, reorder, resize, sort, search and export columns, including custom fields and ACF.

== Description ==

Lime Columns lets you manage the columns of the WordPress admin list tables from a single settings screen. It is written from scratch and does not reuse code or assets from other column-management plugins.

**Features**

* **Column manager** - drag and drop to reorder; set a label and width (`%` or `px`) per column.
* **Post and user list tables** - per post type, plus the Users screen.
* **28 column types** - native WordPress columns, post fields, taxonomies, thumbnails, latest comment, custom fields, user fields and more.
* **Sorting** - sort by any sortable column, including meta values. Empty values always sort last and rows are never dropped.
* **Search** - works with the native WordPress search box, so there is no separate search form to learn. Include selected columns in the built-in search: custom field / ACF values, author names and taxonomy term names. Available on post list tables.
* **Default sort** - set the initial order per list screen.
* **CSV export** - export exactly what the list shows, using the same query and filters.
* **Role restriction** - apply a configuration only to selected roles.
* **ACF integration** - every field of the field groups that apply to a screen is offered in the column-type list, formatted according to its field type. Requires Advanced Custom Fields (optional).
* **Importer** - convert list-table settings saved by Admin Columns (Pro) into Lime Columns settings, with a dry-run preview. The source data is only read, never modified.

Lime Columns is not affiliated with, endorsed by, or sponsored by the developers of Admin Columns. The name is used only to refer to the source of the settings that the importer reads.

Note: Lime Columns is not applied to list screens while Admin Columns (Pro) is active. Deactivate it to switch over; the settings screen and the importer remain available meanwhile.

== Installation ==

1. Upload the `lime-columns` folder to the `/wp-content/plugins/` directory, or install the plugin through the Plugins screen.
2. Activate **Lime Columns** on the Plugins screen.
3. Open **Settings > Lime Columns**, choose a list screen in the sidebar, then add, reorder and save your columns.

== Frequently Asked Questions ==

= Does it work together with Admin Columns? =

No. While Admin Columns (Pro) is active, Lime Columns is not applied to list screens. Deactivate Admin Columns to switch over. You can import its settings first from the settings screen.

= Does it need Advanced Custom Fields? =

No. ACF fields are listed as column types only when ACF is active.

= Which list screens are supported? =

Post list tables of every post type with a UI, and the Users list table. Media, comments and taxonomy list tables are planned.

= Does it change my data? =

Only its own settings are saved, in a dedicated database table. The importer reads Admin Columns data and never modifies it.

== Changelog ==

= 0.1.0 =
* Initial release: column manager, post and user list tables, 28 column types, sorting, search, default sort, CSV export, role restriction, ACF integration, Admin Columns importer, Japanese translation.

== Upgrade Notice ==

= 0.1.0 =
Initial release.
