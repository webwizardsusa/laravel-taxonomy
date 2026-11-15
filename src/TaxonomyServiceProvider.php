<?php

namespace Webwizardsusa\Taxonomy;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Webwizardsusa\Taxonomy\Commands\CheckCommand;
use Webwizardsusa\Taxonomy\Commands\PruneCommand;

/**
 * Registers the config (`config/taxonomy.php`), translations, views, the registry with the config's Vocabularies, and
 * the `taxonomy:check` and `taxonomy:prune` commands. The migration is only published (`--tag=taxonomy-migrations`),
 * never run from the package.
 */
class TaxonomyServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('taxonomy')
            ->hasConfigFile()
            ->hasTranslations()
            ->hasViews()
            ->hasMigration('create_taxonomy_tables')
            ->hasCommands([CheckCommand::class, PruneCommand::class]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(Taxonomy::class);
    }

    public function packageBooted(): void
    {
        $this->app->make(Taxonomy::class)->register(...config('taxonomy.vocabularies', []));
    }
}
