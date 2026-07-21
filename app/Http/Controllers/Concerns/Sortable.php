<?php

namespace App\Http\Controllers\Concerns;

use Closure;
use Illuminate\Http\Request;

/**
 * Whitelisted, server-side column sorting driven by ?sort=<key>&dir=<asc|desc>.
 * A controller passes a map of public sort keys to either a DB column string or a
 * Closure(query, dir) for relation/computed sorts; only listed keys are honoured,
 * so the query string can never inject an arbitrary column. Returns the active
 * [key, dir] so the view can render the header arrows.
 */
trait Sortable
{
    protected function applySort($query, Request $request, array $columns, string $defaultKey, string $defaultDir = 'asc'): array
    {
        $key = (string) $request->query('sort', '');
        if (!array_key_exists($key, $columns)) {
            $key = $defaultKey;
        }

        $requestedDir = strtolower((string) $request->query('dir', ''));
        $dir = in_array($requestedDir, ['asc', 'desc'], true) ? $requestedDir : $defaultDir;

        $column = $columns[$key];
        if ($column instanceof Closure) {
            $column($query, $dir);
        } else {
            $query->orderBy($column, $dir);
        }

        return [$key, $dir];
    }

    /**
     * Sort an already-built Collection the same way (for reports/config tables that
     * render every row). $columns maps a key to a field name or a Closure item→value.
     */
    protected function sortCollection($items, Request $request, array $columns, string $defaultKey, string $defaultDir = 'asc'): array
    {
        $key = (string) $request->query('sort', '');
        if (!array_key_exists($key, $columns)) {
            $key = $defaultKey;
        }

        $requestedDir = strtolower((string) $request->query('dir', ''));
        $dir = in_array($requestedDir, ['asc', 'desc'], true) ? $requestedDir : $defaultDir;

        $field = $columns[$key];
        $sorted = $items->sortBy($field, SORT_REGULAR, $dir === 'desc')->values();

        return [$sorted, $key, $dir];
    }
}
