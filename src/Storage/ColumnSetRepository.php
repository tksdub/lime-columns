<?php
declare(strict_types=1);

namespace LimeColumns\Storage;

use LimeColumns\Column\Column;

final class ColumnSetRepository
{
    /** @var array<string, ColumnSet|null> */
    private array $memo = [];

    public function find(string $listKey): ?ColumnSet
    {
        if (array_key_exists($listKey, $this->memo)) {
            return $this->memo[$listKey];
        }
        global $wpdb;
        $table = Schema::table();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE list_key = %s", $listKey), ARRAY_A); // phpcs:ignore
        return $this->memo[$listKey] = $row ? $this->hydrate($row) : null;
    }

    /** @return list<ColumnSet> */
    public function all(): array
    {
        global $wpdb;
        $table = Schema::table();
        $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY list_key", ARRAY_A) ?: []; // phpcs:ignore
        return array_map([$this, 'hydrate'], $rows);
    }

    public function save(ColumnSet $set): bool
    {
        global $wpdb;
        $now = current_time('mysql', true);
        $data = [
            'list_key' => $set->listKey,
            'title' => $set->title,
            'columns' => wp_json_encode(array_map(static fn (Column $c) => $c->toArray(), $set->columns), JSON_UNESCAPED_UNICODE),
            'settings' => wp_json_encode((object) $set->settings, JSON_UNESCAPED_UNICODE),
            'status' => $set->status,
            'date_modified' => $now,
        ];
        $exists = $this->find($set->listKey) !== null;
        if ($exists) {
            $ok = $wpdb->update(Schema::table(), $data, ['list_key' => $set->listKey]) !== false;
        } else {
            $data['date_created'] = $now;
            $ok = $wpdb->insert(Schema::table(), $data) !== false;
        }
        unset($this->memo[$set->listKey]);
        return $ok;
    }

    public function delete(string $listKey): bool
    {
        global $wpdb;
        unset($this->memo[$listKey]);
        return $wpdb->delete(Schema::table(), ['list_key' => $listKey]) !== false;
    }

    /** @param array<string,mixed> $row */
    private function hydrate(array $row): ColumnSet
    {
        $cols = json_decode((string) ($row['columns'] ?? '[]'), true);
        $settings = json_decode((string) ($row['settings'] ?? '{}'), true);
        return new ColumnSet(
            listKey: (string) $row['list_key'],
            columns: array_map([Column::class, 'fromArray'], is_array($cols) ? $cols : []),
            settings: is_array($settings) ? $settings : [],
            title: (string) ($row['title'] ?? ''),
            status: (string) ($row['status'] ?? 'active'),
        );
    }
}
