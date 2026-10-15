<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class TableSort
{
    /**
     * Apply a request-driven sort using an explicit public-name => column map.
     * Unrecognized fields and directions always fall back to safe defaults.
     */
    public static function apply(
        Builder $query,
        Request $request,
        array $allowed,
        string $default,
        string $defaultDirection = 'asc'
    ): Builder {
        $requested = (string) $request->query('sort', $default);
        $key = array_key_exists($requested, $allowed) ? $requested : $default;
        $column = $allowed[$key] ?? $default;
        $requestedDirection = $request->query('sort_direction');
        $direction = in_array($requestedDirection, ['asc', 'desc'], true)
            ? $requestedDirection
            : (in_array($defaultDirection, ['asc', 'desc'], true) ? $defaultDirection : 'asc');

        $query->reorder($column, $direction);

        if ($column !== 'id') {
            $query->orderBy('id', $direction);
        }

        return $query;
    }
}
