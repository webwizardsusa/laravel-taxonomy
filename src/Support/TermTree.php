<?php

namespace Webwizardsusa\Taxonomy\Support;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Webwizardsusa\Taxonomy\Facades\Taxonomy;
use Webwizardsusa\Taxonomy\Models\Term;
use Webwizardsusa\Taxonomy\Vocabulary;

/**
 * Tree queries over `parent_id`: descendants through a recursive CTE (which ends even on bad data with a cycle), and a
 * depth-first flattening for the select field and the management tree.
 */
final class TermTree
{
    /**
     * The given Terms and every Term under them.
     *
     * @param  array<int>  $termIds
     * @return array<int>
     */
    public static function descendantIds(array $termIds): array
    {
        if ($termIds === []) {
            return [];
        }

        [$sql, $bindings] = self::descendantIdsSql($termIds);

        return array_map(
            fn (object $row): int => (int) $row->id,
            self::connection()->select($sql, $bindings),
        );
    }

    /**
     * A query selecting the given Terms and every Term under them, for use inside `in (…)`.
     *
     * @param  array<int>  $termIds
     * @return array{0: string, 1: array<int>}
     */
    public static function descendantIdsSql(array $termIds): array
    {
        $grammar = self::connection()->getQueryGrammar();
        $table = $grammar->wrapTable(config('taxonomy.tables.terms', 'taxonomy_terms'));
        $placeholders = implode(', ', array_fill(0, count($termIds), '?'));

        $sql = "with recursive taxonomy_term_tree(id) as (
            select id from {$table} where id in ({$placeholders})
            union
            select child.id from {$table} as child inner join taxonomy_term_tree on child.parent_id = taxonomy_term_tree.id
        ) select id from taxonomy_term_tree";

        return [$sql, array_values(array_map('intval', $termIds))];
    }

    /**
     * Every Term in the Vocabulary, depth first, each level in the Vocabulary's order. Terms whose parent is missing or
     * in a cycle are listed at the top level.
     *
     * @return list<array{term: Term, depth: int}>
     */
    public static function flatten(string|Vocabulary $vocabulary): array
    {
        $terms = Taxonomy::newTermQuery()->inVocabulary($vocabulary)->ordered($vocabulary)->get();
        $hierarchical = Taxonomy::vocabulary($vocabulary)->isHierarchical();
        $ids = $terms->modelKeys();

        $childrenByParent = [];

        foreach ($terms as $term) {
            $parentId = $hierarchical && in_array($term->parent_id, $ids, true) ? $term->parent_id : 0;
            $childrenByParent[$parentId][] = $term;
        }

        $flattened = [];
        $visited = [];

        $walk = function (int $parentId, int $depth) use (&$walk, &$flattened, &$visited, $childrenByParent): void {
            foreach ($childrenByParent[$parentId] ?? [] as $term) {
                if (isset($visited[$term->getKey()])) {
                    continue;
                }

                $visited[$term->getKey()] = true;
                $flattened[] = ['term' => $term, 'depth' => $depth];
                $walk($term->getKey(), $depth + 1);
            }
        };

        $walk(0, 0);

        foreach ($terms as $term) {
            if (! isset($visited[$term->getKey()])) {
                $visited[$term->getKey()] = true;
                $flattened[] = ['term' => $term, 'depth' => 0];
                $walk($term->getKey(), 1);
            }
        }

        return $flattened;
    }

    private static function connection(): Connection
    {
        $model = Taxonomy::termModel();

        return DB::connection((new $model)->getConnectionName());
    }
}
