<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Búsqueda "contiene" escapando los comodines de LIKE (% y _) con un ESCAPE explícito,
 * portable entre MySQL, MariaDB y SQLite.
 */
final class Like
{
    public static function escape(string $term): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);
    }

    /**
     * @param  list<string>  $columns  se combinan con OR
     */
    public static function contains(Builder $query, array $columns, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $value = '%'.self::escape($term).'%';

        return $query->where(function (Builder $q) use ($columns, $value) {
            foreach ($columns as $column) {
                $q->orWhereRaw($q->getQuery()->getGrammar()->wrap($column)." LIKE ? ESCAPE '!'", [$value]);
            }
        });
    }
}
