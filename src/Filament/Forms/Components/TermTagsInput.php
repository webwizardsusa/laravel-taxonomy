<?php

namespace Webwizardsusa\Taxonomy\Filament\Forms\Components;

use Closure;
use Filament\Forms\Components\TagsInput;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Webwizardsusa\Taxonomy\Concerns\HasTerms;
use Webwizardsusa\Taxonomy\Facades\Taxonomy;
use Webwizardsusa\Taxonomy\Filament\Concerns\HasVocabulary;
use Webwizardsusa\Taxonomy\Models\Term;
use Webwizardsusa\Taxonomy\Support\TermGate;
use Webwizardsusa\Taxonomy\Vocabulary;

/**
 * Free tagging for an Open Vocabulary: type names, pick from the Vocabulary's names as suggestions, and new names
 * become Terms when the form saves (if the Term policy allows `create`). On a record that uses `HasTerms` it loads and
 * saves the record's Terms in this Vocabulary; anywhere else (no record, or a model without `HasTerms`) its state is
 * just the names.
 */
class TermTagsInput extends TagsInput
{
    use HasVocabulary;

    protected int|Closure|null $maxItems = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->suggestions(static fn (TermTagsInput $component): array => Taxonomy::newTermQuery()
            ->inVocabulary($component->getVocabulary())
            ->orderBy('name_key')
            ->limit((int) config('taxonomy.filament.suggestion_limit', 1000))
            ->pluck('name')
            ->all());

        $this->loadStateFromRelationshipsUsing(static function (TermTagsInput $component, Model $record): void {
            if (! static::usesTerms($record)) {
                return;
            }

            /** @var HasTerms $record */
            $component->state($record->termsIn($component->getVocabulary())->pluck('name')->all());
        });

        $this->saveRelationshipsUsing(static function (TermTagsInput $component, Model $record, ?array $state): void {
            if (! static::usesTerms($record)) {
                return;
            }

            /** @var HasTerms $record */
            $record->syncTerms($component->getVocabulary(), $state ?? []);
        });

        $this->dehydrated(static fn (TermTagsInput $component): bool => ! static::usesTerms($component->getRecord() ?? $component->getModel()));

        $this->rule(static fn (TermTagsInput $component): string => 'max:'.$component->getMaxItems(), static fn (TermTagsInput $component): bool => filled($component->getMaxItems()));

        $this->rule(static fn (TermTagsInput $component): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($component): void {
            $vocabulary = $component->getVocabulary();

            if (blank($value) || TermGate::allows('create', $vocabulary)) {
                return;
            }

            $keys = array_map(fn (string $name): string => mb_strtolower(Term::normalizeName($name)), (array) $value);
            $existing = Taxonomy::newTermQuery()->inVocabulary($vocabulary)->whereIn('name_key', $keys)->count();

            if ($existing < count(array_unique($keys))) {
                $fail(__('taxonomy::taxonomy.validation.existing_only', ['vocabulary' => str($vocabulary->label())->lower()]));
            }
        });
    }

    /**
     * The most Terms a record can take from this field.
     */
    public function maxItems(int|Closure|null $count): static
    {
        $this->maxItems = $count;

        return $this;
    }

    public function getMaxItems(): ?int
    {
        return $this->evaluate($this->maxItems);
    }

    protected function ensureVocabularyFits(Vocabulary $vocabulary): void
    {
        if (! $vocabulary->isOpen()) {
            throw new LogicException("TermTagsInput needs an open vocabulary, and [{$vocabulary::key()}] is closed: use TermSelect.");
        }
    }
}
