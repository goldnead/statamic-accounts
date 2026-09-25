<?php

namespace Goldnead\Accounts\Support;

use Illuminate\Support\Str;

/**
 * Stored codes, said in the Control Panel's language.
 *
 * A code without a translation is shown humanised rather than raw, so a new
 * status in a sibling reads "Refund pending", not "refund_pending".
 */
class Labels
{
    public static function status(?string $code): string
    {
        return static::lookup('status', $code);
    }

    public static function source(?string $code): string
    {
        return static::lookup('source', $code);
    }

    public static function role(?string $code, ?string $teamLabel = null): string
    {
        if (is_string($teamLabel) && $teamLabel !== '') {
            return $teamLabel;
        }

        return static::lookup('role', $code);
    }

    /**
     * Mollie writes `1 month`, `3 months`, `1 year`; Stripe `month`, `year`.
     */
    public static function interval(?string $raw): string
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return '';
        }

        if (preg_match('/^(\d+)?\s*(day|week|month|year)s?$/i', $raw, $m) !== 1) {
            return $raw;
        }

        $count = (int) ($m[1] !== '' ? $m[1] : 1);
        $unit = strtolower($m[2]);

        if ($count === 1) {
            return __('accounts::cp.labels.interval.every_'.$unit);
        }

        return __('accounts::cp.labels.interval.every_n_'.$unit, ['count' => $count]);
    }

    /**
     * An activity type. Ours come from the event catalogue's language file;
     * a sibling's from `activity::events.<type>` when the activity addon
     * ships one; anything else humanised ("commerce.purchase_completed" →
     * "Commerce: Purchase completed").
     */
    public static function activity(?string $type): string
    {
        $type = (string) $type;
        $key = 'accounts::cp.labels.activity.'.str_replace('.', '_', $type);

        if (trans()->has($key)) {
            return __($key);
        }

        if (trans()->has('activity::events.'.$type)) {
            return __('activity::events.'.$type);
        }

        $parts = explode('.', $type, 2);

        return count($parts) === 2
            ? Str::ucfirst(str_replace('_', ' ', $parts[0])).': '.Str::ucfirst(str_replace('_', ' ', $parts[1]))
            : Str::ucfirst(str_replace(['_', '.'], ' ', $type));
    }

    protected static function lookup(string $group, ?string $code): string
    {
        $code = (string) $code;

        if ($code === '') {
            return '';
        }

        $key = 'accounts::cp.labels.'.$group.'.'.$code;

        return trans()->has($key) ? __($key) : Str::ucfirst(str_replace(['_', '-'], ' ', $code));
    }
}
