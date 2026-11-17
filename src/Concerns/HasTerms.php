<?php

namespace Webwizardsusa\Taxonomy\Concerns;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use InvalidArgumentException;
use Webwizardsusa\Taxonomy\Facades\Taxonomy;
use Webwizardsusa\Taxonomy\Models\Term;
use Webwizardsusa\Taxonomy\Support\TermResolver;
use Webwizardsusa\Taxonomy\Support\TermTree;
use Webwizardsusa\Taxonomy\Vocabulary;

/**
 * Lets a record take Terms from any Vocabulary, through one shared pivot that keeps the order they were given in. The
 * methods that change Terms work on one Vocabulary at a time and leave the others alone.
 *
 * @mixin Model
 */
trait HasTerms
{
    public static function bootHasTerms(): void
    {
        static::deleting(static function (Model $record): void {
            if (! method_exists($record, 'isForceDeleting') || $record->isForceDeleting()) {
                $record->terms()->detach();
            }
        });
    }

    /**
     * Every Term the record has, in every Vocabulary, in the order they were given.
     *
     * @return MorphToMany<Term, $this>
     */
    public function terms(): MorphToMany
    {
        return $this->morphToMany(Taxonomy::termModel(), 'termable', config('taxonomy.tables.termables', 'taxonomy_termables'), 'termable_id', 'term_id')
            ->withPivot('position')
            ->orderByPivot('position')
            ->orderBy((new (Taxonomy::termModel()))->qualifyColumn('id'));
    }

    /**
     * The record's Terms in one Vocabulary, in the order they were given. Uses the loaded `terms` relation when there
     * is one.
     *
     * @return Collection<int, Term>
     */
    public function termsIn(string|Vocabulary $vocabulary): Collection
    {
        $key = Taxonomy::vocabulary($vocabulary)::key();

        if ($this->relationLoaded('terms')) {
            return $this->getRelation('terms')->where('vocabulary', $key)->values();
        }

        return $this->terms()->where((new (Taxonomy::termModel()))->qualifyColumn('vocabulary'), $key)->get();
    }

    /**
     * Makes the given Terms the record's only Terms in this Vocabulary, in this order.
     *
     * @param  iterable<Term|int|string>  $terms
     */
    public function syncTerms(string|Vocabulary $vocabulary, iterable $terms): void
    {
        $vocabulary = Taxonomy::vocabulary($vocabulary);

        $this->getConnection()->transaction(function () use ($vocabulary, $terms): void {
            $resolved = TermResolver::resolve($vocabulary, $terms);

            $this->terms()->detach($this->currentTermIds($vocabulary));
            $this->terms()->attach($this->positioned($resolved->modelKeys(), 0));
        });

        $this->unsetRelation('terms');
    }

    /**
     * Adds the given Terms after the record's current ones in this Vocabulary, skipping those it already has.
     *
     * @param  iterable<Term|int|string>  $terms
     */
    public function attachTerms(string|Vocabulary $vocabulary, iterable $terms): void
    {
        $vocabulary = Taxonomy::vocabulary($vocabulary);

        $this->getConnection()->transaction(function () use ($vocabulary, $terms): void {
            $current = $this->termsIn($vocabulary);
            $new = array_values(array_diff(TermResolver::resolve($vocabulary, $terms)->modelKeys(), $current->modelKeys()));
            $start = $current->isEmpty() ? 0 : $current->max(fn (Term $term): int => (int) $term->pivot->position) + 1;

            $this->terms()->attach($this->positioned($new, $start));
        });

        $this->unsetRelation('terms');
    }

    /**
     * Removes the given Terms, read as syncTerms() reads them (Term Paths are taken in Closed Vocabularies too). Values
     * that match none of the record's Terms are ignored.
     *
     * @param  iterable<Term|int|string>  $terms
     */
    public function detachTerms(string|Vocabulary $vocabulary, iterable $terms): void
    {
        $vocabulary = Taxonomy::vocabulary($vocabulary);
        $ids = TermResolver::find($vocabulary, $terms)->modelKeys();

        $this->terms()->detach(array_values(array_intersect($this->currentTermIds($vocabulary), $ids)));
        $this->unsetRelation('terms');
    }

    /**
     * Records with at least one of the Terms (or, with descendants, a Term under one of them).
     *
     * @param  Builder<static>  $query
     * @param  iterable<Term|int|string>|Term|int|string  $terms  Term models, ids, or slugs and Term Paths with `$vocabulary`
     */
    #[Scope]
    protected function withAnyTerms(Builder $query, iterable|Term|int|string $terms, string|Vocabulary|null $vocabulary = null, bool $includingDescendants = false): void
    {
        $ids = static::termIdsForQuery($terms, $vocabulary);

        if ($ids === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->whereHas('terms', fn (Builder $terms) => static::constrainToTerms($terms, $ids, $includingDescendants));
    }

    /**
     * Records with every one of the Terms (with descendants: a Term under each of them).
     *
     * @param  Builder<static>  $query
     * @param  iterable<Term|int|string>|Term|int|string  $terms
     */
    #[Scope]
    protected function withAllTerms(Builder $query, iterable|Term|int|string $terms, string|Vocabulary|null $vocabulary = null, bool $includingDescendants = false): void
    {
        foreach (static::termIdsForQuery($terms, $vocabulary) as $id) {
            $query->whereHas('terms', fn (Builder $terms) => static::constrainToTerms($terms, [$id], $includingDescendants));
        }
    }

    /**
     * Records with none of the Terms (with descendants: nor any Term under them).
     *
     * @param  Builder<static>  $query
     * @param  iterable<Term|int|string>|Term|int|string  $terms
     */
    #[Scope]
    protected function withoutTerms(Builder $query, iterable|Term|int|string $terms, string|Vocabulary|null $vocabulary = null, bool $includingDescendants = false): void
    {
        $ids = static::termIdsForQuery($terms, $vocabulary);

        if ($ids !== []) {
            $query->whereDoesntHave('terms', fn (Builder $terms) => static::constrainToTerms($terms, $ids, $includingDescendants));
        }
    }

    /**
     * @param  Builder<Term>  $query
     * @param  array<int>  $ids
     */
    protected static function constrainToTerms(Builder $query, array $ids, bool $includingDescendants): void
    {
        $column = $query->getModel()->qualifyColumn('id');

        if (! $includingDescendants) {
            $query->whereIn($column, $ids);

            return;
        }

        [$sql, $bindings] = TermTree::descendantIdsSql($ids);

        $query->whereRaw($query->getGrammar()->wrap($column)." in ({$sql})", $bindings);
    }

    /**
     * @param  iterable<Term|int|string>|Term|int|string  $terms
     * @return array<int>
     */
    protected static function termIdsForQuery(iterable|Term|int|string $terms, string|Vocabulary|null $vocabulary): array
    {
        $ids = [];

        foreach (is_iterable($terms) ? $terms : [$terms] as $term) {
            if ($term instanceof Term) {
                $ids[] = $term->getKey();
            } elseif (is_int($term)) {
                $ids[] = $term;
            } elseif ($vocabulary === null) {
                throw new InvalidArgumentException("Pass a vocabulary to find Terms by slug, such as [{$term}].");
            } else {
                // A slug that matches nothing becomes an id no Term has, so it still counts in withAllTerms().
                $ids[] = Term::findByPath($vocabulary, $term)?->getKey() ?? 0;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return array<int>
     */
    protected function currentTermIds(Vocabulary $vocabulary): array
    {
        return $this->terms()
            ->where((new (Taxonomy::termModel()))->qualifyColumn('vocabulary'), $vocabulary::key())
            ->pluck((new (Taxonomy::termModel()))->qualifyColumn('id'))
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @param  array<int>  $ids
     * @return array<int, array{position: int}>
     */
    protected function positioned(array $ids, int $start): array
    {
        $positioned = [];

        foreach (array_values($ids) as $index => $id) {
            $positioned[$id] = ['position' => $start + $index];
        }

        return $positioned;
    }
}
