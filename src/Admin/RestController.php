<?php
declare(strict_types=1);

namespace LimeColumns\Admin;

use LimeColumns\Column\Column;
use LimeColumns\Container;
use LimeColumns\Contracts\ColumnType;
use LimeColumns\Contracts\ListScreen;
use LimeColumns\Integrations\Acf\AcfFieldColumn;
use LimeColumns\Storage\ColumnSet;

/** 設定UI用のREST API（namespace: lime-columns/v1）。manage_options 権限のみ。 */
final class RestController
{
    private const NS = 'lime-columns/v1';

    public function __construct(private readonly Container $c)
    {
    }

    public function register(): void
    {
        add_action('rest_api_init', function (): void {
            $perm = static fn () => current_user_can('manage_options');
            register_rest_route(self::NS, '/screens', ['methods' => 'GET', 'callback' => [$this, 'listScreens'], 'permission_callback' => $perm]);
            register_rest_route(self::NS, '/screens/(?P<key>[a-zA-Z0-9_:\-]+)', [
                ['methods' => 'GET', 'callback' => [$this, 'getScreen'], 'permission_callback' => $perm],
                ['methods' => 'PUT', 'callback' => [$this, 'saveScreen'], 'permission_callback' => $perm],
                ['methods' => 'DELETE', 'callback' => [$this, 'resetScreen'], 'permission_callback' => $perm],
            ]);
            register_rest_route(self::NS, '/import/acp', ['methods' => 'POST', 'callback' => [$this, 'importAcp'], 'permission_callback' => $perm]);
        });
    }

    public function listScreens(): array
    {
        $repo = $this->c->get('repository');
        $out = [];
        foreach ($this->c->get('screens')->all() as $s) {
            $out[] = ['key' => $s->key(), 'label' => $s->label(), 'group' => $s->group(), 'configured' => $repo->find($s->key()) !== null];
        }
        return $out;
    }

    public function getScreen(\WP_REST_Request $req): array|\WP_Error
    {
        $screen = $this->c->get('screens')->get((string) $req['key']);
        if (!$screen) {
            return new \WP_Error('lime_not_found', 'Unknown list screen', ['status' => 404]);
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
                $postType = str_starts_with($screen->key(), 'post:') ? substr($screen->key(), 5) : '';
                return ['type' => $id, 'fields' => $types[$id]->pickable($postType)];
            }
        }
        return ['type' => '', 'fields' => []];
    }

    public function saveScreen(\WP_REST_Request $req): array|\WP_Error
    {
        $screen = $this->c->get('screens')->get((string) $req['key']);
        if (!$screen) {
            return new \WP_Error('lime_not_found', 'Unknown list screen', ['status' => 404]);
        }
        $types = $this->c->get('types')->forScreen($screen);
        $columns = [];
        $seen = [];
        foreach ((array) $req->get_param('columns') as $raw) {
            if (!is_array($raw) || !isset($types[$raw['type'] ?? ''])) {
                continue;
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
        $in = (array) $req->get_param('settings');
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
            sanitize_text_field((string) $req->get_param('title')),
            $req->get_param('status') === 'inactive' ? 'inactive' : 'active'
        );
        if (!$this->c->get('repository')->save($set)) {
            return new \WP_Error('lime_save_failed', 'Failed to save', ['status' => 500]);
        }
        return ['saved' => true, 'config' => $set->toArray()];
    }

    public function resetScreen(\WP_REST_Request $req): array
    {
        $this->c->get('repository')->delete((string) $req['key']);
        return ['deleted' => true];
    }

    public function importAcp(\WP_REST_Request $req): array
    {
        $importer = $this->c->get('importer');
        return ['source_exists' => $importer->sourceExists()] + $importer->run(
            (bool) $req->get_param('dry_run'),
            (bool) $req->get_param('overwrite'),
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
