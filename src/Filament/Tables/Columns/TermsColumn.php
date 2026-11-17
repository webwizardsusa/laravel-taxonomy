<?php

namespace Webwizardsusa\Taxonomy\Filament\Tables\Columns;

use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Webwizardsusa\Taxonomy\Concerns\HasTerms;
use Webwizardsusa\Taxonomy\Filament\Concerns\HasVocabulary;

/**
 * A record's Terms in one Vocabulary as badges, in the order they were given. The table loads every row's Terms in
 * one query. A record without HasTerms shows none.
 */
class TermsColumn extends TextColumn
{
    use HasVocabulary;

    protected function setUp(): void
    {
        parent::setUp();

        $this->badge();

        $this->state(static function (TermsColumn $column, Model $record): array {
            if (! static::usesTerms($record)) {
                return [];
            }

            /** @var HasTerms $record */
            return $record->termsIn($column->getVocabulary())->pluck('name')->all();
        });
    }

    public function applyEagerLoading(Builder|Relation $query): Builder|Relation
    {
        if (! static::usesTerms($query->getModel()) || array_key_exists('terms', $query->getEagerLoads())) {
            return $query;
        }

        return $query->with('terms');
    }
}
