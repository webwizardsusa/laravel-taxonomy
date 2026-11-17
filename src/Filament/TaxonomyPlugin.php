<?php

namespace Webwizardsusa\Taxonomy\Filament;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Concerns\EvaluatesClosures;
use Throwable;
use UnitEnum;
use Webwizardsusa\Taxonomy\Filament\Pages\ManageTerms;
use Webwizardsusa\Taxonomy\Vocabulary;

/**
 * Adds a management page for each Vocabulary to a panel, under a "Taxonomy" navigation group unless
 * `->navigationGroup()` names another. Without `->vocabularies()`, that's every Vocabulary in `taxonomy.vocabularies`;
 * a Vocabulary another package registers at runtime has to be listed explicitly, since panels are built before
 * packages boot.
 */
class TaxonomyPlugin implements Plugin
{
    use EvaluatesClosures;

    /**
     * @var array<string|class-string<Vocabulary>>|null
     */
    protected ?array $vocabularies = null;

    protected string|UnitEnum|Closure|null $navigationGroup = null;

    protected ?int $navigationSort = null;

    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * The plugin registered in the current panel, if it is.
     */
    public static function current(): ?static
    {
        try {
            $plugin = Filament::getCurrentOrDefaultPanel()?->getPlugin('taxonomy');
        } catch (Throwable) {
            return null;
        }

        return $plugin instanceof static ? $plugin : null;
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
     * The navigation group of the management pages. Null keeps the `taxonomy::taxonomy.navigation_group` translation.
     */
    public function navigationGroup(string|UnitEnum|Closure|null $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function getNavigationGroup(): string|UnitEnum|null
    {
        return $this->evaluate($this->navigationGroup) ?? __('taxonomy::taxonomy.navigation_group');
    }

    /**
     * The navigation sort of the management pages, shared by all of them: within it, they keep the order of the
     * Vocabularies.
     */
    public function navigationSort(?int $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    public function getNavigationSort(): ?int
    {
        return $this->navigationSort;
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
