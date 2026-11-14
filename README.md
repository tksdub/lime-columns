# Lime Columns

**English** | [日本語](README.ja.md)

An independent WordPress plugin for managing the columns of admin list tables. Add, remove, reorder and resize columns, make them sortable, searchable and exportable, and show custom fields (including ACF) — all from a single settings screen.

Lime Columns is written from scratch. It does not reuse any code or assets from other column-management plugins. Made in Japan.

> **Status:** early development (v0.1.0). The API and storage format may change.

## Features

- **Column manager UI** — drag & drop (or use the arrow buttons) to reorder; set a label and width (`%` / `px`) per column.
- **Post and user list tables** — per post type, plus the Users screen.
- **28 column types** — native WordPress columns (title, author, date, comments, …), post fields, taxonomies, thumbnails, latest comment, custom fields, user fields, and more.
- **Sorting** — sort by any sortable column, including meta values (empty values always sort last, and rows are never dropped).
- **Search** — works with the native WordPress search box; there is no separate search form to learn. Include selected columns in the built-in search: custom field / ACF values, author names (display name and login) and taxonomy term names. Available on post list tables.
- **Default sort** — set the initial order per list screen.
- **CSV export** — export exactly what the list shows, using the same query and filters.
- **Role restriction** — apply a configuration only to selected roles.
- **ACF integration** — every field of the field groups that apply to a screen is offered directly in the column-type list, with formatting per field type (dates, selects, files, relationships, repeaters, …).
- **Admin Columns importer** — convert list-table settings saved by Admin Columns (Pro) into Lime Columns settings, with a dry-run preview. The source table is only read, never modified.

## Requirements

- WordPress 6.5 or later
- PHP 8.1 or later
- Advanced Custom Fields (optional, for the ACF integration)

## Installation

1. Download or clone this repository into `wp-content/plugins/lime-columns`.
2. Activate **Lime Columns** on the Plugins screen.
3. Open **Settings → Lime Columns**.

There is no build step; the settings UI is a plain ES module.

## Usage

1. Pick a list screen in the left sidebar (a dot marks screens that already have a saved configuration).
2. Choose a column type in **Choose a column type…** and click **Add column**. ACF fields appear in their own **ACF** group.
3. Expand a column to edit its label, width, and sortable / searchable / exportable flags.
4. Drag columns to reorder them, then click **Save**.

To migrate from Admin Columns, open **Import from Admin Columns**, click **Preview** to see which columns can be converted, then **Import**. Enable **Overwrite** to replace screens that already have a Lime Columns configuration.

> Lime Columns is not applied to list screens while Admin Columns (Pro) is active. Deactivate it to switch over; the settings screen and the importer remain available meanwhile.

## Roadmap

Inline edit, bulk edit, filtering, saved views, conditional formatting, and list screens for media, comments and taxonomies.

## Disclaimer

Lime Columns is not affiliated with, endorsed by, or sponsored by the developers of Admin Columns. "Admin Columns" is used in this project only to refer to the source of the settings that the importer reads.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
