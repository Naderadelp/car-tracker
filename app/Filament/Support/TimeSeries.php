<?php

namespace App\Filament\Support;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Bucketing for the dashboard's time-series widgets.
 *
 * Grouping rows into days or months needs a database function, and the drivers
 * this project runs against spell it three different ways: local development
 * and deployment are Postgres, while phpunit.xml points the suite at sqlite.
 * Hard-coding to_char() would make every chart widget unrunnable under the
 * test database, so the bucket expression is picked from the connection's
 * driver at query time and the column is quoted with that connection's own
 * grammar.
 *
 * Buckets are keyed by a sortable string — `Y-m-d` for days, `Y-m` for months —
 * because that is what all three date-format functions can emit natively, and
 * it lets a sparse result set be reindexed against a dense list of keys without
 * any date parsing.
 */
final class TimeSeries
{
    public const DAY = 'day';

    public const MONTH = 'month';

    /**
     * Aggregate a query into date buckets.
     *
     * Only `count` and `sum` are accepted, and $valueColumn is quoted by the
     * connection grammar rather than interpolated, so neither argument can
     * carry arbitrary SQL.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  self::DAY|self::MONTH  $unit
     * @param  'count'|'sum'  $function
     * @return array<string, float> bucket key => aggregate
     */
    public static function aggregate(
        Builder $query,
        string $dateColumn,
        string $unit,
        string $function = 'count',
        ?string $valueColumn = null,
    ): array {
        if (! in_array($function, ['count', 'sum'], true)) {
            throw new InvalidArgumentException("Unsupported aggregate function [{$function}].");
        }

        if ($function === 'sum' && $valueColumn === null) {
            throw new InvalidArgumentException('A sum needs a column to sum.');
        }

        // toBase() rather than getQuery(): it applies the model's global scopes
        // first, so a soft-deleted car never reaches the chart.
        $base = $query->toBase();
        $grammar = $base->getGrammar();

        $bucket = self::bucketExpression(
            $base->getConnection()->getDriverName(),
            $grammar->wrap($dateColumn),
            $unit,
        );

        $value = $function === 'count'
            ? 'count(*)'
            : 'sum('.$grammar->wrap($valueColumn).')';

        return $base
            ->select(DB::raw($bucket.' as bucket'))
            ->addSelect(DB::raw($value.' as aggregate'))
            ->groupBy(DB::raw($bucket))
            ->pluck('aggregate', 'bucket')
            ->mapWithKeys(fn ($aggregate, $bucket): array => [(string) $bucket => (float) $aggregate])
            ->all();
    }

    /**
     * Every bucket key between two dates, inclusive, with no gaps.
     *
     * A chart needs the empty buckets as much as the full ones — a month with
     * no fill-ups is a zero, not a missing point — and the database only
     * returns rows that exist.
     *
     * @param  self::DAY|self::MONTH  $unit
     * @return list<string>
     */
    public static function keys(CarbonImmutable $start, CarbonImmutable $end, string $unit): array
    {
        $isMonthly = $unit === self::MONTH;

        $cursor = $isMonthly ? $start->startOfMonth() : $start->startOfDay();
        $last = $isMonthly ? $end->startOfMonth() : $end->startOfDay();

        $keys = [];

        while ($cursor->lessThanOrEqualTo($last)) {
            $keys[] = $cursor->format(self::keyFormat($unit));
            $cursor = $isMonthly ? $cursor->addMonth() : $cursor->addDay();
        }

        return $keys;
    }

    /**
     * Human labels for a list of bucket keys.
     *
     * @param  list<string>  $keys
     * @param  self::DAY|self::MONTH  $unit
     * @return list<string>
     */
    public static function labels(array $keys, string $unit): array
    {
        return array_map(
            fn (string $key): string => CarbonImmutable::createFromFormat(
                self::keyFormat($unit),
                $key,
            )->format($unit === self::MONTH ? 'M y' : 'j M'),
            $keys,
        );
    }

    /**
     * Reindex a sparse aggregate onto a dense list of keys.
     *
     * @param  list<string>  $keys
     * @param  array<string, float>  $values
     * @return list<float>
     */
    public static function series(array $keys, array $values, int $precision = 2): array
    {
        return array_map(
            fn (string $key): float => round($values[$key] ?? 0.0, $precision),
            $keys,
        );
    }

    /**
     * @param  self::DAY|self::MONTH  $unit
     */
    public static function keyFormat(string $unit): string
    {
        return match ($unit) {
            self::DAY => 'Y-m-d',
            self::MONTH => 'Y-m',
            default => throw new InvalidArgumentException("Unsupported bucket unit [{$unit}]."),
        };
    }

    /**
     * The driver's own date-formatting call, wrapping an already-quoted column.
     *
     * @param  self::DAY|self::MONTH  $unit
     */
    private static function bucketExpression(string $driver, string $quotedColumn, string $unit): string
    {
        $isMonthly = $unit === self::MONTH;

        return match ($driver) {
            'pgsql' => "to_char({$quotedColumn}, '".($isMonthly ? 'YYYY-MM' : 'YYYY-MM-DD')."')",
            'sqlite' => "strftime('".($isMonthly ? '%Y-%m' : '%Y-%m-%d')."', {$quotedColumn})",
            'mysql', 'mariadb' => "date_format({$quotedColumn}, '".($isMonthly ? '%Y-%m' : '%Y-%m-%d')."')",
            'sqlsrv' => "format({$quotedColumn}, '".($isMonthly ? 'yyyy-MM' : 'yyyy-MM-dd')."')",
            default => throw new InvalidArgumentException("No date bucket expression for driver [{$driver}]."),
        };
    }
}
