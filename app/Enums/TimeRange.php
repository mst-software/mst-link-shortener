<?php

namespace App\Enums;

use Illuminate\Database\Eloquent\Builder;

/**
 * Preset date ranges for admin list filters.
 */
enum TimeRange: string
{
    case Today = 'today';
    case Yesterday = 'yesterday';
    case SevenDays = '7_days';
    case OneMonth = '1_month';

    public function label(): string
    {
        return match ($this) {
            self::Today => 'Today',
            self::Yesterday => 'Yesterday',
            self::SevenDays => 'Last 7 days',
            self::OneMonth => 'Last month',
        };
    }

    /**
     * Limit the query to rows whose date column falls in this range.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function apply(Builder $query, string $column = 'created_at'): Builder
    {
        return match ($this) {
            self::Today => $query->where($column, '>=', today()),
            self::Yesterday => $query->where($column, '>=', today()->subDay())->where($column, '<', today()),
            self::SevenDays => $query->where($column, '>=', now()->subDays(7)),
            self::OneMonth => $query->where($column, '>=', now()->subMonth()),
        };
    }
}
