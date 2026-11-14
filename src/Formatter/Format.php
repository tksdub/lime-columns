<?php
declare(strict_types=1);

namespace LimeColumns\Formatter;

/** 値整形の共通ヘルパ。列タイプから合成して使う。 */
final class Format
{
    public const EMPTY_HTML = '<span class="lime-empty" aria-hidden="true">&ndash;</span>';

    /**
     * @param string $mode wp|custom|diff|acf
     * @param string $custom custom 時の PHP date フォーマット
     */
    public static function date(string|int $value, string $mode = 'wp', string $custom = ''): string
    {
        if ($value === '' || $value === 0 || $value === '0' || $value === '0000-00-00 00:00:00') {
            return '';
        }
        $ts = self::timestamp($value);
        if ($ts === null) {
            return (string) $value;
        }
        return match ($mode) {
            'diff' => human_time_diff($ts, time()) . ' ' . ($ts > time() ? __('from now') : __('ago')),
            'custom' => wp_date($custom !== '' ? $custom : 'Y-m-d', $ts) ?: '',
            'acf' => wp_date(get_option('date_format'), $ts) ?: '',
            default => wp_date(get_option('date_format') . ' ' . get_option('time_format'), $ts) ?: '',
        };
    }

    /** `Ymd`（ACF保存形式）・`Y-m-d H:i:s`・UNIXタイム等を UNIX タイムスタンプへ。 */
    public static function timestamp(string|int $value): ?int
    {
        if (is_int($value) || (is_string($value) && ctype_digit($value) && strlen($value) !== 8)) {
            return (int) $value;
        }
        $value = (string) $value;
        $tz = wp_timezone();
        $fmt = ctype_digit($value) && strlen($value) === 8 ? 'Ymd' : null;
        $dt = $fmt
            ? \DateTimeImmutable::createFromFormat('!' . $fmt, $value, $tz)
            : (strtotime($value) !== false ? (new \DateTimeImmutable($value, $tz)) : false);
        return $dt ? $dt->getTimestamp() : null;
    }

    public static function words(string $text, int $limit): string
    {
        $text = wp_strip_all_tags(strip_shortcodes($text));
        return $limit > 0 ? wp_trim_words($text, $limit, '…') : $text;
    }
}
