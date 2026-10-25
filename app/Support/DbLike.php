<?php

namespace App\Support;

/**
 * Case-insensitive LIKE that behaves the same on SQLite and MySQL.
 *
 * SQLite's LIKE ignores ASCII case everywhere. MySQL compares JSON values
 * (a whole JSON column, or a `column->key` path, which compiles to
 * json_unquote(json_extract(…))) with a binary collation, so "sohar" would
 * not find "Sohar Fort" there. On MySQL / MariaDB the value is cast to text
 * with the table's case-insensitive collation first.
 */
final class DbLike
{
    /**
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     */
    public static function orWhere($query, string $column, string $pattern)
    {
        if (in_array($query->getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return $query->orWhereRaw(
                'CAST('.$query->getGrammar()->wrap($column).' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci LIKE ?',
                [$pattern]
            );
        }

        return $query->orWhere($column, 'like', $pattern);
    }

    /** "%term%" with the user's own wildcards removed. */
    public static function contains(string $term): string
    {
        return '%'.str_replace(['%', '_', '\\'], '', $term).'%';
    }
}
