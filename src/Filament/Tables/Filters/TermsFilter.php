<?php

namespace Webwizardsusa\Taxonomy\Filament\Tables\Filters;

use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Webwizardsusa\Taxonomy\Filament\Concerns\HasVocabulary;
use Webwizardsusa\Taxonomy\Support\TermTree;

/**
 * Filters records by their Terms in one Vocabulary. In a hierarchical Vocabulary, choosing a Term also finds records
 * with a Term under it, unless `->includingDescendants(false)`. With `->multiple()`, records with any chosen Term match.
 */
class TermsFilter extends SelectFilter
{
    use HasVocabulary;

    protected bool $includesDescendants = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->searchable();

        $this->options(static function (TermsFilter $filter): array {
            $options = [];

            foreach (TermTree::flatten($filter->getVocabulary()) as ['term' => $term, 'depth' => $depth]) {
                $options[$term->getKey()] = str_repeat('— ', $depth).$term->name;
            }

            return $options;
        });

        $this->query(static function (TermsFilter $filter, Builder $query, array $data): void {
            $ids = array_map('intval', array_filter($filter->isMultiple() ? ($data['values'] ?? []) : [$data['value'] ?? null], filled(...)));

            if ($ids === []) {
                return;
            }

            $query->withAnyTerms($ids, includingDescendants: $filter->includesDescendants && $filter->getVocabulary()->isHierarchical());
        });
    }

    public function includingDescendants(bool $condition = true): static
    {
        $this->includesDescendants = $condition;

        return $this;
    }
}
