<?php

namespace Webwizardsusa\Taxonomy\Support;

use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use Webwizardsusa\Taxonomy\Facades\Taxonomy;
use Webwizardsusa\Taxonomy\Models\Term;
use Webwizardsusa\Taxonomy\Vocabulary;

/**
 * Turns what callers pass for a Vocabulary's Terms into Term models, in the order given, without repeats. Term models
 * and integer ids are taken as they are. Strings are names in an Open Vocabulary (created when they're new) and ids
 * in a Closed one, which takes no names.
 */
final class TermResolver
{
    /**
     * @param  iterable<Term|int|string>  $terms
     * @return Collection<int, Term>
     */
    public static function resolve(string|Vocabulary $vocabulary, iterable $terms): Collection
    {
        $vocabulary = Taxonomy::vocabulary($vocabulary);
        $items = [];
        $ids = [];

        foreach ($terms as $term) {
            if ($term instanceof Term) {
                $items[] = $term;
            } elseif (is_int($term) || (! $vocabulary->isOpen() && is_string($term) && ctype_digit($term))) {
                $items[] = (int) $term;
                $ids[] = (int) $term;
            } elseif (is_string($term) && $vocabulary->isOpen()) {
                if (Term::normalizeName($term) !== '') {
                    $items[] = $term;
                }
            } elseif (is_string($term)) {
                throw new InvalidArgumentException("{$vocabulary->label()} is closed: pass Term models or ids, not names.");
            } else {
                throw new InvalidArgumentException('Terms must be Term models, ids or names, not '.get_debug_type($term).'.');
            }
        }

        $found = $ids === [] ? collect() : Taxonomy::newTermQuery()->whereKey(array_unique($ids))->get()->keyBy('id');
        $resolved = new Collection;

        foreach ($items as $item) {
            $term = match (true) {
                $item instanceof Term => $item,
                is_int($item) => $found->get($item) ?? throw new InvalidArgumentException("There's no Term with the id [{$item}]."),
                default => Term::findOrCreateByName($vocabulary, $item),
            };

            if ($term->vocabulary !== $vocabulary::key()) {
                throw new InvalidArgumentException("[{$term->name}] isn't in {$vocabulary->label()}.");
            }

            if (! $resolved->contains(fn (Term $existing): bool => $existing->is($term))) {
                $resolved->push($term);
            }
        }

        return $resolved;
    }
}
