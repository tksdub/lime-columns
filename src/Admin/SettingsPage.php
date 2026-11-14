<?php
declare(strict_types=1);

namespace LimeColumns\Admin;

/** 設定 > LimeColumns。UIは assets/js/settings.js（ビルド不要のESモジュール）。 */
final class SettingsPage
{
    private const SLUG = 'lime-columns';

    public function register(): void
    {
        add_action('admin_menu', function (): void {
            add_options_page('LimeColumns', 'LimeColumns', 'manage_options', self::SLUG, [$this, 'render']);
        });
        add_action('admin_enqueue_scripts', [$this, 'assets']);
    }

    public function assets(string $hook): void
    {
        if ($hook !== 'settings_page_' . self::SLUG) {
            return;
        }
        wp_enqueue_style('lime-columns-settings', LIME_COLUMNS_URL . 'assets/css/settings.css', [], LIME_COLUMNS_VERSION);
        wp_enqueue_script_module('lime-columns-settings', LIME_COLUMNS_URL . 'assets/js/settings.js', [], LIME_COLUMNS_VERSION);
    }

    public function render(): void
    {
        $boot = [
            'rest' => esc_url_raw(rest_url('lime-columns/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'i18n' => $this->strings(),
        ];
        printf(
            '<div class="wrap lime-columns"><h1 class="lc-brand"><img src="%s" alt="" width="80" height="80"> LimeColumns</h1><div id="lime-columns-app"></div></div>',
            esc_url(LIME_COLUMNS_URL . 'assets/images/logo.png')
        );
        printf('<script id="lime-columns-boot" type="application/json">%s</script>', wp_json_encode($boot)); // phpcs:ignore WordPress.Security.EscapeOutput
    }

    /** @return array<string,string> */
    private function strings(): array
    {
        return [
            'List screen' => __('List screen', 'lime-columns'),
            'Columns' => __('Columns', 'lime-columns'),
            'Add column' => __('Add column', 'lime-columns'),
            'Save' => __('Save', 'lime-columns'),
            'Saved' => __('Saved', 'lime-columns'),
            'Reset' => __('Reset to default', 'lime-columns'),
            'Remove' => __('Remove', 'lime-columns'),
            'Label' => __('Label', 'lime-columns'),
            'Width' => __('Width', 'lime-columns'),
            'Sortable' => __('Sortable', 'lime-columns'),
            'Searchable' => __('Searchable', 'lime-columns'),
            'Exportable' => __('Export (CSV)', 'lime-columns'),
            'Active' => __('Active', 'lime-columns'),
            'Inactive' => __('Inactive', 'lime-columns'),
            'Default sort' => __('Default sort', 'lime-columns'),
            'None' => __('None', 'lime-columns'),
            'Ascending' => __('Ascending', 'lime-columns'),
            'Descending' => __('Descending', 'lime-columns'),
            'Roles' => __('Apply to roles (empty = everyone)', 'lime-columns'),
            'Import from Admin Columns' => __('Import from Admin Columns', 'lime-columns'),
            'Preview' => __('Preview (dry run)', 'lime-columns'),
            'Import' => __('Import', 'lime-columns'),
            'Overwrite' => __('Overwrite existing settings', 'lime-columns'),
            'Choose a column type' => __('Choose a column type', 'lime-columns'),
            'Confirm reset' => __('Reset this list to defaults?', 'lime-columns'),
            'Not configured' => __('Not configured', 'lime-columns'),
            'Other plugins' => __('Other plugins', 'lime-columns'),
            'Discard unsaved changes?' => __('Discard unsaved changes?', 'lime-columns'),
            'Configured' => __('Configured', 'lime-columns'),
            'Drag to reorder' => __('Drag to reorder', 'lime-columns'),
            'Move up' => __('Move up', 'lime-columns'),
            'Move down' => __('Move down', 'lime-columns'),
            'Admin Columns table not found.' => __('Admin Columns table not found.', 'lime-columns'),
            'Mapped' => __('Mapped', 'lime-columns'),
            'Unmapped' => __('Unmapped', 'lime-columns'),
            'imported' => __('imported', 'lime-columns'),
            'dry-run ok' => __('dry-run ok', 'lime-columns'),
            'skipped: already configured (use overwrite)' => __('skipped: already configured (use overwrite)', 'lime-columns'),
            'skipped: list screen not supported' => __('skipped: list screen not supported', 'lime-columns'),
            'error: save failed' => __('error: save failed', 'lime-columns'),
        ];
    }
}
