<?php

namespace Webwizardsusa\Taxonomy\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Webwizardsusa\Taxonomy\TaxonomyServiceProvider;
use Webwizardsusa\Taxonomy\Tests\Fixtures\CategoriesVocabulary;
use Webwizardsusa\Taxonomy\Tests\Fixtures\TagsVocabulary;
use Webwizardsusa\Taxonomy\Tests\Fixtures\TopicsVocabulary;

/**
 * Boots the package, and Filament and Livewire around it when they are installed: Filament is optional, and the CI
 * runs the suite without it too. Registers the fixture Vocabularies (open `tags`, closed hierarchical `categories`,
 * closed flat `topics`) and creates the taxonomy tables and an `articles` table to tag.
 */
abstract class TestCase extends BaseTestCase
{
    public static function hasFilament(): bool
    {
        return class_exists(FilamentServiceProvider::class);
    }

    /**
     * @param  Application  $app
     * @return array<class-string>
     */
    protected function getPackageProviders($app): array
    {
        $filament = static::hasFilament() ? [
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            LivewireServiceProvider::class,
            SupportServiceProvider::class,
            ActionsServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            FilamentServiceProvider::class,
        ] : [];

        return [...$filament, TaxonomyServiceProvider::class];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('cache.default', 'array');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('taxonomy.vocabularies', [TagsVocabulary::class, CategoriesVocabulary::class, TopicsVocabulary::class]);
    }

    protected function defineDatabaseMigrations(): void
    {
        (include __DIR__.'/../database/migrations/create_taxonomy_tables.php.stub')->up();

        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });
    }
}
