<?php

namespace Webwizardsusa\Taxonomy\Filament\Forms\Components;

use Closure;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Webwizardsusa\Taxonomy\Concerns\HasTerms;
use Webwizardsusa\Taxonomy\Filament\Concerns\HasVocabulary;
use Webwizardsusa\Taxonomy\Models\Term;
use Webwizardsusa\Taxonomy\Support\TermTree;
use Webwizardsusa\Taxonomy\Vocabulary;

/**
 * Choosing Terms from a Closed Vocabulary, listed in tree order and indented by depth, with search. One Term by
 * default, several with `->multiple()`. On a record that uses `HasTerms` it loads and saves the record's Terms in this
 * Vocabulary; elsewhere its state is just the ids.
 */
class TermSelect extends Select
{
    use HasVocabulary;

    protected Term|Closure|null $excludedSubtree = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->searchable();

        $this->options(static function (TermSelect $component): array {
            $excluded = ($root = $component->getExcludedSubtree()) ? TermTree::descendantIds([$root->getKey()]) : [];
            $options = [];

            foreach (TermTree::flatten($component->getVocabulary()) as ['term' => $term, 'depth' => $depth]) {
                if (! in_array($term->getKey(), $excluded, true)) {
                    $options[$term->getKey()] = str_repeat('— ', $depth).$term->name;
                }
            }

            return $options;
        });

        $this->loadStateFromRelationshipsUsing(static function (TermSelect $component, Model $record): void {
            if (! static::usesTerms($record)) {
                return;
            }

            /** @var HasTerms $record */
            $ids = $record->termsIn($component->getVocabulary())->modelKeys();

            $component->state($component->isMultiple() ? $ids : ($ids[0] ?? null));
        });

        $this->saveRelationshipsUsing(static function (TermSelect $component, Model $record, mixed $state): void {
            if (! static::usesTerms($record)) {
                return;
            }

            /** @var HasTerms $record */
            $record->syncTerms($component->getVocabulary(), array_values(array_filter((array) $state, filled(...))));
        });

        $this->dehydrated(static fn (TermSelect $component): bool => ! static::usesTerms($component->getRecord() ?? $component->getModel()));
    }

    /**
     * Leaves a Term and everything under it out of the options, as when choosing a new parent for it.
     */
    public function excludeSubtree(Term|Closure|null $term): static
    {
        $this->excludedSubtree = $term;

        return $this;
    }

    public function getExcludedSubtree(): ?Term
    {
        return $this->evaluate($this->excludedSubtree);
    }

    protected function ensureVocabularyFits(Vocabulary $vocabulary): void
    {
        if ($vocabulary->isOpen()) {
            throw new LogicException("TermSelect needs a closed vocabulary, and [{$vocabulary::key()}] is open: use TermTagsInput.");
        }
    }
}
