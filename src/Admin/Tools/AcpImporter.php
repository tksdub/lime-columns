<?php
declare(strict_types=1);

namespace LimeColumns\Admin\Tools;

use LimeColumns\Column\Column;
use LimeColumns\Column\ColumnRegistry;
use LimeColumns\ListScreen\ListScreenRegistry;
use LimeColumns\Storage\ColumnSet;
use LimeColumns\Storage\ColumnSetRepository;

/**
 * Admin Columns(Pro) が保存した一覧設定（{prefix}admin_columns）を Lime Columns 形式へ変換する。
 * ACP側のテーブル・設定は読み取るだけで一切変更しない。
 */
final class AcpImporter
{
    /** ACP列タイプ => [Lime型ID, 設定を作る関数名|null] */
    private const SIMPLE = [
        'title' => 'title', 'comments' => 'comments', 'date' => 'date',
        'column-content' => 'content', 'column-excerpt' => 'excerpt', 'column-slug' => 'slug',
        'column-postid' => 'post_id', 'column-modified' => 'modified', 'column-status' => 'status',
        'column-featured_image' => 'thumbnail',
        'username' => 'username', 'name' => 'user_name', 'email' => 'email', 'role' => 'role',
        'column-user_registered' => 'registered', 'column-user_id' => 'user_id',
        'column-user_url' => 'user_url', 'column-display_name' => 'display_name',
    ];

    public function __construct(
        private readonly ColumnSetRepository $repo,
        private readonly ColumnRegistry $types,
        private readonly ListScreenRegistry $screens,
    ) {
    }

    public static function sourceTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'admin_columns';
    }

    public function sourceExists(): bool
    {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', self::sourceTable())) === self::sourceTable();
    }

    /**
     * @return array{lists:list<array<string,mixed>>}
     */
    public function run(bool $dryRun = true, bool $overwrite = false): array
    {
        global $wpdb;
        if (!$this->sourceExists()) {
            return ['lists' => []];
        }
        $table = self::sourceTable();
        $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id", ARRAY_A) ?: []; // phpcs:ignore
        $report = [];
        foreach ($rows as $row) {
            $report[] = $this->importRow($row, $dryRun, $overwrite);
        }
        return ['lists' => $report];
    }

    /** @param array<string,mixed> $row */
    private function importRow(array $row, bool $dryRun, bool $overwrite): array
    {
        $key = $this->mapListKey((string) ($row['list_key'] ?? ''));
        $r = ['source' => (string) $row['list_key'], 'target' => $key, 'title' => (string) ($row['title'] ?? ''),
            'mapped' => [], 'unmapped' => [], 'status' => 'pending'];
        $screen = $key ? $this->screens->get($key) : null;
        if (!$screen) {
            $r['status'] = 'skipped: list screen not supported';
            return $r;
        }
        if (!$overwrite && $this->repo->find($key)) {
            $r['status'] = 'skipped: already configured (use overwrite)';
            return $r;
        }

        $cols = $this->decode((string) ($row['columns'] ?? ''));
        $settings = $this->decode((string) ($row['settings'] ?? ''));
        $out = [];
        foreach ($cols as $acp) {
            $col = is_array($acp) ? $this->mapColumn($acp, $screen->objectType()) : null;
            $label = is_array($acp) ? wp_strip_all_tags((string) ($acp['label'] ?? '')) : '?';
            $type = is_array($acp) ? (string) ($acp['type'] ?? '?') : '?';
            if ($col && $this->types->get($col->type)?->supports($screen)) {
                $out[] = $col;
                $r['mapped'][] = "{$type} → {$col->type} ({$label})";
            } else {
                $r['unmapped'][] = "{$type} ({$label})";
            }
        }

        $limeSettings = [];
        $sorting = (string) ($settings['sorting'] ?? '');
        if ($sorting !== '' && $sorting !== '0') {
            $limeSettings['default_orderby'] = $this->columnNameFromAcp($cols, $sorting);
            $limeSettings['default_order'] = strtolower((string) ($settings['sorting_order'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        }
        if (!empty($settings['roles']) && is_array($settings['roles'])) {
            $limeSettings['roles'] = array_values($settings['roles']);
        }

        if (!$dryRun) {
            $ok = $this->repo->save(new ColumnSet($key, $out, $limeSettings, (string) ($row['title'] ?? '')));
            $r['status'] = $ok ? 'imported' : 'error: save failed';
        } else {
            $r['status'] = 'dry-run ok';
        }
        return $r;
    }

    private function mapListKey(string $acpKey): string
    {
        return match (true) {
            $acpKey === 'wp-users' => 'user',
            $acpKey === 'wp-media' => 'media',
            str_starts_with($acpKey, 'wp-') => '',
            default => 'post:' . $acpKey,
        };
    }

    /** @return array<int|string,mixed> */
    private function decode(string $raw): array
    {
        $v = @unserialize($raw, ['allowed_classes' => false]);
        if (!is_array($v)) {
            $v = json_decode($raw, true);
        }
        return is_array($v) ? $v : [];
    }

    /** @param array<int|string,mixed> $acpCols */
    private function columnNameFromAcp(array $acpCols, string $acpName): string
    {
        foreach ($acpCols as $c) {
            if (is_array($c) && ($c['name'] ?? '') === $acpName) {
                return $this->limeName($c);
            }
        }
        return $acpName;
    }

    /** @param array<string,mixed> $a */
    private function limeName(array $a): string
    {
        $type = (string) ($a['type'] ?? '');
        $native = ['title' => 'title', 'comments' => 'comments', 'date' => 'date', 'username' => 'username', 'name' => 'name', 'email' => 'email', 'role' => 'role'];
        return $native[$type] ?? ('c' . preg_replace('/[^a-zA-Z0-9_]/', '', (string) ($a['name'] ?? uniqid())));
    }

    /** @param array<string,mixed> $a ACPの列設定 */
    public function mapColumn(array $a, string $object): ?Column
    {
        $acpType = (string) ($a['type'] ?? '');
        $settings = [];
        $typeId = self::SIMPLE[$acpType] ?? null;

        if ($acpType === 'author') {
            $prop = (string) ($a['display_author_as'] ?? 'display_name');
            $typeId = in_array($prop, ['', 'display_name'], true) ? 'author' : 'author_display';
            $settings = $typeId === 'author_display' ? ['property' => $prop] : [];
        } elseif ($acpType === 'column-author_name') {
            $typeId = 'author_display';
            $settings = ['property' => (string) ($a['display_author_as'] ?? 'display_name'), 'link' => (string) ($a['user_link_to'] ?? '') ?: 'none'];
        } elseif ($acpType === 'column-latest_comment') {
            $typeId = 'latest_comment';
            $settings = ['property' => (string) ($a['comment_property_display'] ?? 'date'), 'date_format' => (string) ($a['date_format'] ?? 'Y-m-d H:i:s')];
        } elseif ($acpType === 'column-taxonomy') {
            $typeId = 'taxonomy';
            $settings = ['taxonomy' => (string) ($a['taxonomy'] ?? 'category')];
        } elseif ($acpType === 'column-meta') {
            $typeId = $object === 'user' ? 'user_custom_field' : 'custom_field';
            $settings = ['meta_key' => (string) ($a['field'] ?? ''), 'field_type' => $this->metaType((string) ($a['field_type'] ?? ''))];
        } elseif (str_starts_with($acpType, 'field_')) {
            $typeId = $object === 'user' ? 'user_acf_field' : 'acf_field';
            $settings = ['field_key' => $acpType, 'sub_field' => (string) ($a['sub_field'] ?? ''), 'date_mode' => $this->dateMode((string) ($a['date_format'] ?? 'acf'))];
        } elseif (in_array($typeId, ['excerpt', 'content'], true)) {
            $settings = ['words' => (int) ($a['excerpt_length'] ?? 30) ?: 30];
        } elseif ($typeId === 'modified' || $typeId === 'registered') {
            $settings = ['date_mode' => 'wp'];
        }

        if ($typeId === null) {
            return null;
        }
        if (($a['edit'] ?? 'off') === 'on') {
            $settings['edit'] = true; // 将来のインライン編集機能で使用（現状は保持のみ）
        }
        return Column::fromArray([
            'name' => $this->limeName($a),
            'type' => $typeId,
            'label' => (string) ($a['label'] ?? ''),
            'width' => (string) ($a['width'] ?? ''),
            'width_unit' => (string) ($a['width_unit'] ?? '%'),
            'sort' => ($a['sort'] ?? 'off') === 'on',
            'search' => ($a['search'] ?? 'off') === 'on',
            'export' => ($a['export'] ?? 'off') === 'on',
            'settings' => $settings,
        ]);
    }

    private function metaType(string $acp): string
    {
        return match ($acp) {
            'numeric' => 'numeric', 'date' => 'date', 'image' => 'image', 'url' => 'url', 'checkmark' => 'boolean', 'array' => 'json',
            default => 'text',
        };
    }

    private function dateMode(string $acp): string
    {
        return match ($acp) {
            'diff' => 'diff', 'custom' => 'custom', 'wp_default' => 'wp', default => 'acf',
        };
    }
}
