<?php

namespace Webwizardsusa\Taxonomy\Filament\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Webwizardsusa\Taxonomy\Concerns\HasTerms;
use Webwizardsusa\Taxonomy\Facades\Taxonomy;
use Webwizardsusa\Taxonomy\Vocabulary;

/**
 * The Vocabulary a Filament piece works with: set with `->vocabulary()`, or taken from the piece's name when a
 * Vocabulary has that key (`TermTagsInput::make('tags')`).
 */
trait HasVocabulary
{
    protected string|Vocabulary|Closure|null $vocabulary = null;

    public function vocabulary(string|Vocabulary|Closure $vocabulary): static
    {
        $this->vocabulary = $vocabulary;

        return $this;
    }

    public function getVocabulary(): Vocabulary
    {
        $vocabulary = $this->evaluate($this->vocabulary);

        if ($vocabulary === null && Taxonomy::has($this->getName())) {
            $vocabulary = $this->getName();
        }

        if ($vocabulary === null) {
            throw new LogicException(class_basename(static::class)." [{$this->getName()}] needs a vocabulary: call ->vocabulary().");
        }

        $vocabulary = Taxonomy::vocabulary($vocabulary);
        $this->ensureVocabularyFits($vocabulary);

        return $vocabulary;
    }

    protected function ensureVocabularyFits(Vocabulary $vocabulary): void {}

    /**
     * @param  Model|class-string<Model>|null  $model
     */
    protected static function usesTerms(Model|string|null $model): bool
    {
        return $model !== null && in_array(HasTerms::class, class_uses_recursive($model), true);
    }
}
