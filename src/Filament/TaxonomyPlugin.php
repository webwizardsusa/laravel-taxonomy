<?php

namespace Webwizardsusa\Taxonomy\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Webwizardsusa\Taxonomy\Filament\Pages\ManageTerms;
use Webwizardsusa\Taxonomy\Vocabulary;

/**
 * Adds a management page for each Vocabulary to a panel, under a "Taxonomy" navigation group. Without
 * `->vocabularies()`, that's every Vocabulary in `taxonomy.vocabularies`; a Vocabulary another package registers at
 * runtime has to be listed explicitly, since panels are built before packages boot.
 */
class TaxonomyPlugin implements Plugin
{
    /**
     * @var array<string|class-string<Vocabulary>>|null
     */
    protected ?array $vocabularies = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'taxonomy';
    }

    /**
     * @param  array<string|class-string<Vocabulary>>  $vocabularies  keys or Vocabulary classes
     */
    public function vocabularies(array $vocabularies): static
    {
        $this->vocabularies = $vocabularies;

        return $this;
    }

    /**
     * @return array<string>
     */
    public function getVocabularyKeys(): array
    {
        $vocabularies = $this->vocabularies ?? config('taxonomy.vocabularies', []);

        return array_values(array_unique(array_map(
            static fn (string $vocabulary): string => is_a($vocabulary, Vocabulary::class, true) ? $vocabulary::key() : $vocabulary,
            $vocabularies,
        )));
    }

    public function register(Panel $panel): void
    {
        $panel->pages(array_map(
            static fn (string $key) => ManageTerms::make($key),
            $this->getVocabularyKeys(),
        ));
    }

    public function boot(Panel $panel): void {}
}
