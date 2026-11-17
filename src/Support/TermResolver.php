<?php

namespace Webwizardsusa\Taxonomy\Support;

use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use Webwizardsusa\Taxonomy\Facades\Taxonomy;
use Webwizardsusa\Taxonomy\Models\Term;
use Webwizardsusa\Taxonomy\Vocabulary;

/**
 * Turns what callers pass for a Vocabulary's Terms into Term models, in the order given, without repeats. Term models
 * and integer ids are taken as they are. Strings are names in an Open Vocabulary, and in a Closed one, which takes no
 * names, ids when they're all digits and Term Paths otherwise.
 */
final class TermResolver
{
    /**
     * Resolves every value, creating the Terms of new names in an Open Vocabulary. A value that matches no Term, or a
     * Term of another Vocabulary, throws.
     *
     * @param  iterable<Term|int|string>  $terms
     * @return Collection<int, Term>
     */
    public static function resolve(string|Vocabulary $vocabulary, iterable $terms): Collection
    {
        $vocabulary = Taxonomy::vocabulary($vocabulary);
        $items = self::parse($vocabulary, $terms);
        $ids = array_column(array_filter($items, fn (array $item): bool => $item[0] === 'id'), 1);
        $found = $ids === [] ? collect() : Taxonomy::newTermQuery()->whereKey(array_unique($ids))->get()->keyBy('id');
        $resolved = new Collection;

        foreach ($items as [$kind, $value]) {
            $term = match ($kind) {
                'term' => $value,
                'id' => $found->get($value) ?? throw new InvalidArgumentException("There's no Term with the id [{$value}]."),
                'name' => Term::findOrCreateByName($vocabulary, $value),
                default => Term::findByPath($vocabulary, $value)
                    ?? throw new InvalidArgumentException("There's no Term at the path [{$value}] in {$vocabulary->label()}."),
            };

            if ($term->vocabulary !== $vocabulary::key()) {
                throw new InvalidArgumentException("[{$term->name}] isn't in {$vocabulary->label()}.");
            }

            self::pushUnique($resolved, $term);
        }

        return $resolved;
    }

    /**
     * Finds the Terms the values stand for, the way resolve() reads them. Nothing is created: values that match no
     * Term, and Terms of another Vocabulary, are skipped.
     *
     * @param  iterable<Term|int|string>  $terms
     * @return Collection<int, Term>
     */
    public static function find(string|Vocabulary $vocabulary, iterable $terms): Collection
    {
        $vocabulary = Taxonomy::vocabulary($vocabulary);
        $found = new Collection;

        foreach (self::parse($vocabulary, $terms) as [$kind, $value]) {
            $term = match ($kind) {
                'term' => $value,
                'id' => Taxonomy::newTermQuery()->find($value),
                'name' => Taxonomy::newTermQuery()->inVocabulary($vocabulary)->where('name_key', Term::nameKey($value))->first(),
                default => Term::findByPath($vocabulary, $value),
            };

            if ($term !== null && $term->vocabulary === $vocabulary::key()) {
                self::pushUnique($found, $term);
            }
        }

        return $found;
    }

    /**
     * Sorts each value into what it stands for: a Term model, an id, a name (normalized, Open Vocabularies) or a Term
     * Path (Closed ones). Strings of digits are ids in a Closed Vocabulary, and names in an Open one.
     *
     * @param  iterable<Term|int|string>  $terms
     * @return list<array{0: 'term', 1: Term}|array{0: 'id', 1: int}|array{0: 'name'|'path', 1: string}>
     */
    private static function parse(Vocabulary $vocabulary, iterable $terms): array
    {
        $items = [];

        foreach ($terms as $term) {
            if ($term instanceof Term) {
                $items[] = ['term', $term];
            } elseif (is_int($term) || (! $vocabulary->isOpen() && is_string($term) && ctype_digit($term))) {
                $items[] = ['id', (int) $term];
            } elseif (is_string($term) && $vocabulary->isOpen()) {
                if (($name = Term::normalizeName($term)) !== '') {
                    $items[] = ['name', $name];
                }
            } elseif (is_string($term)) {
                $items[] = ['path', $term];
            } else {
                throw new InvalidArgumentException('Terms must be Term models, ids or names, not '.get_debug_type($term).'.');
            }
        }

        return $items;
    }

    /**
     * @param  Collection<int, Term>  $terms
     */
    private static function pushUnique(Collection $terms, Term $term): void
    {
        if (! $terms->contains(fn (Term $existing): bool => $existing->is($term))) {
            $terms->push($term);
        }
    }
}
