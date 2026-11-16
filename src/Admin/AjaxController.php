<?php
declare(strict_types=1);

namespace LimeColumns\Admin;

use LimeColumns\Column\Column;
use LimeColumns\Container;
use LimeColumns\Contracts\ColumnType;
use LimeColumns\Contracts\ListScreen;
use LimeColumns\Integrations\Acf\AcfFieldColumn;
use LimeColumns\Storage\ColumnSet;

/**
 * 設定UI用の通信（admin-ajax.php / wp_ajax_*）。manage_options 権限と nonce が必須。
 * REST API を制限する環境でも動くよう、WordPress 標準の管理画面向け Ajax を使う。
 */
final class AjaxController
{
    public const NONCE = 'lime_columns_ajax';

    private const ACTIONS = [
        'lime_columns_screens' => 'listScreens',
        'lime_columns_get' => 'getScreen',
        'lime_columns_save' => 'saveScreen',
        'lime_columns_reset' => 'resetScreen',
        'lime_columns_import' => 'importAcp',
    ];

    public function __construct(private readonly Container $c)
    {
    }

    public function register(): void
    {
        foreach (self::ACTIONS as $action => $method) {
            add_action('wp_ajax_' . $action, function () use ($method): void {
                $this->dispatch($method);
            });
        }
    }

    /** 権限・nonce を確認し、ハンドラの結果を JSON で返す。 */
    private function dispatch(string $method): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Sorry, you are not allowed to do that.', 'default')], 403);
        }
        if (!check_ajax_referer(self::NONCE, '_ajax_nonce', false)) {
            wp_send_json_error(['message' => __('The link you followed has expired.', 'default')], 403);
        }
        $result = $this->$method($this->input());
        if ($result instanceof \WP_Error) {
            $status = (int) ($result->get_error_data()['status'] ?? 400);
            wp_send_json_error(['message' => $result->get_error_message()], $status);
        }
        wp_send_json_success($result);
    }

    /**
     * リクエスト入力。key は文字列、payload は JSON 文字列で受ける
     * （max_input_vars に左右されないよう、配列をフォーム展開しない）。
     * @return array<string,mixed>
     */
    private function input(): array
    {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- dispatch() で検証済み
        $in = [];
        if (isset($_POST['payload']) && is_string($_POST['payload'])) {
            $decoded = json_decode(wp_unslash($_POST['payload']), true);
            $in = is_array($decoded) ? $decoded : [];
        }
        $key = isset($_POST['key']) && is_string($_POST['key']) ? wp_unslash($_POST['key']) : '';
        $in['key'] = preg_match('/^[a-zA-Z0-9_:\-]+$/', $key) === 1 ? $key : '';
        // phpcs:enable
        return $in;
    }

    /** @param array<string,mixed> $in */
    public function listScreens(array $in = []): array
    {
        $repo = $this->c->get('repository');
        $out = [];
        foreach ($this->c->get('screens')->all() as $s) {
            $out[] = ['key' => $s->key(), 'label' => $s->label(), 'group' => $s->group(), 'configured' => $repo->find($s->key()) !== null];
        }
        return $out;
    }

    /** @param array<string,mixed> $in */
    public function getScreen(array $in): array|\WP_Error
    {
        $screen = $this->c->get('screens')->get((string) $in['key']);
        if (!$screen) {
            return new \WP_Error('lime_not_found', __('Unknown list screen', 'lime-columns'), ['status' => 404]);
        }
        $saved = $this->c->get('repository')->find($screen->key());
        $set = $saved ?? $this->defaultSet($screen);
        return [
            'saved' => $saved !== null,
            'config' => $set->toArray(),
            'types' => $this->describeTypes($screen),
            'foreign' => (object) (array) get_option('lime_columns_foreign_' . $screen->key(), []),
            'acf' => $this->acfFields($screen),
            'roles' => $this->roles(),
        ];
    }

    /** @return array{type:string,fields:list<array{key:string,label:string,group:string}>} */
    private function acfFields(ListScreen $screen): array
    {
        $types = $this->c->get('types')->forScreen($screen);
        foreach (['acf_field', 'user_acf_field'] as $id) {
            if (($types[$id] ?? null) instanceof AcfFieldColumn) {
                $postType = str_starts_with($screen->key(), 'post:') ? substr($screen->key(), 5) : ($screen->key() === 'media' ? 'attachment' : '');
                return ['type' => $id, 'fields' => $types[$id]->pickable($postType)];
            }
        }
        return ['type' => '', 'fields' => []];
    }

    /** @param array<string,mixed> $req */
    public function saveScreen(array $req): array|\WP_Error
    {
        $screen = $this->c->get('screens')->get((string) $req['key']);
        if (!$screen) {
            return new \WP_Error('lime_not_found', __('Unknown list screen', 'lime-columns'), ['status' => 404]);
        }
        $types = $this->c->get('types')->forScreen($screen);
        $columns = [];
        $seen = [];
        foreach ((array) ($req['columns'] ?? []) as $raw) {
            if (!is_array($raw) || !is_string($raw['type'] ?? null) || !isset($types[$raw['type']])) {
                continue;
            }
            foreach (['name', 'label', 'width'] as $k) {
                if (isset($raw[$k]) && !is_scalar($raw[$k])) {
                    unset($raw[$k]); // 配列等の不正値は無視（Array to string 警告の防止）
                }
            }
            $type = $types[$raw['type']];
            $col = Column::fromArray($raw);
            $name = $type->nativeKey() ?? $col->name;
            if ($type->nativeKey() !== null && isset($seen[$name])) {
                continue; // WP標準列は1画面に1つだけ
            }
            if ($name === '' || isset($seen[$name])) {
                $name = 'c' . substr(md5(uniqid('', true)), 0, 10);
            }
            $seen[$name] = true;
            $caps = $type->capabilities();
            $columns[] = new Column(
                name: $name,
                type: $type->id(),
                label: wp_kses($col->label, ['span' => ['class' => true, 'title' => true], 'strong' => [], 'em' => [], 'br' => []]),
                width: is_numeric($col->width) ? (string) $col->width : '',
                widthUnit: $col->widthUnit,
                sort: $col->sort && $caps['sort'],
                search: $col->search && $caps['search'],
                export: $col->export && $caps['export'],
                settings: $this->cleanSettings($type, $col->settings),
            );
        }
        $in = (array) ($req['settings'] ?? []);
        $settings = [];
        $names = array_map(static fn (Column $c) => $c->name, $columns);
        if (!empty($in['default_orderby']) && in_array($in['default_orderby'], $names, true)) {
            $settings['default_orderby'] = (string) $in['default_orderby'];
            $settings['default_order'] = strtolower((string) ($in['default_order'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        }
        $validRoles = array_keys(wp_roles()->roles);
        $settings['roles'] = array_values(array_intersect((array) ($in['roles'] ?? []), $validRoles));

        $set = new ColumnSet(
            $screen->key(),
            $columns,
            $settings,
            sanitize_text_field((string) ($req['title'] ?? '')),
            ($req['status'] ?? '') === 'inactive' ? 'inactive' : 'active'
        );
        if (!$this->c->get('repository')->save($set)) {
            return new \WP_Error('lime_save_failed', __('Failed to save', 'lime-columns'), ['status' => 500]);
        }
        return ['saved' => true, 'config' => $set->toArray()];
    }

    /** @param array<string,mixed> $in */
    public function resetScreen(array $in): array
    {
        $this->c->get('repository')->delete((string) $in['key']);
        return ['deleted' => true];
    }

    /** @param array<string,mixed> $in */
    public function importAcp(array $in): array
    {
        $importer = $this->c->get('importer');
        return ['source_exists' => $importer->sourceExists()] + $importer->run(
            (bool) ($in['dry_run'] ?? false),
            (bool) ($in['overwrite'] ?? false),
        );
    }

    private function defaultSet(ListScreen $screen): ColumnSet
    {
        $byNative = [];
        foreach ($this->c->get('types')->forScreen($screen) as $t) {
            if ($t->nativeKey() !== null) {
                $byNative[$t->nativeKey()] = $t;
            }
        }
        $cols = [];
        foreach ($screen->nativeColumns() as $key => $label) {
            if (isset($byNative[$key])) {
                $caps = $byNative[$key]->capabilities();
                $cols[] = new Column($key, $byNative[$key]->id(), $label, sort: $caps['sort'], export: true);
            }
        }
        return new ColumnSet($screen->key(), $cols, [], $screen->label(), 'active');
    }

    /** @return list<array<string,mixed>> */
    private function describeTypes(ListScreen $screen): array
    {
        $out = [];
        foreach ($this->c->get('types')->forScreen($screen) as $t) {
            $out[] = [
                'id' => $t->id(), 'label' => $t->label(), 'group' => $t->group(),
                'native_key' => $t->nativeKey(), 'caps' => $t->capabilities(), 'fields' => $t->fields(),
            ];
        }
        return $out;
    }

    /** @return array<string,string> */
    private function roles(): array
    {
        $r = [];
        foreach (wp_roles()->roles as $slug => $def) {
            $r[$slug] = translate_user_role($def['name']);
        }
        return $r;
    }

    /**
     * 型が宣言した設定項目だけを、型に応じてサニタイズして残す。
     * @param array<string,mixed> $in
     * @return array<string,mixed>
     */
    private function cleanSettings(ColumnType $type, array $in): array
    {
        $out = [];
        foreach ($type->fields() as $f) {
            if (!array_key_exists($f['name'], $in)) {
                continue;
            }
            $v = $in[$f['name']];
            $out[$f['name']] = match ($f['type']) {
                'number' => (int) $v,
                'checkbox' => (bool) $v,
                'select' => isset($f['options'][(string) $v]) ? (string) $v : (string) ($f['default'] ?? ''),
                default => sanitize_text_field((string) $v),
            };
        }
        if (!empty($in['edit'])) {
            $out['edit'] = true;
        }
        return $out;
    }
}
