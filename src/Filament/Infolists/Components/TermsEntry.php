<?php

namespace Webwizardsusa\Taxonomy\Filament\Infolists\Components;

use Filament\Infolists\Components\TextEntry;
use Illuminate\Database\Eloquent\Model;
use Webwizardsusa\Taxonomy\Concerns\HasTerms;
use Webwizardsusa\Taxonomy\Filament\Concerns\HasVocabulary;

/**
 * A record's Terms in one Vocabulary as badges, in the order they were given.
 */
class TermsEntry extends TextEntry
{
    use HasVocabulary;

    protected function setUp(): void
    {
        parent::setUp();

        $this->badge();

        $this->state(static function (TermsEntry $entry, ?Model $record): array {
            if ($record === null || ! static::usesTerms($record)) {
                return [];
            }

            /** @var HasTerms $record */
            return $record->termsIn($entry->getVocabulary())->pluck('name')->all();
        });
    }
}
