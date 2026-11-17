<?php

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Livewire;
use Webwizardsusa\Taxonomy\Filament\Infolists\Components\TermsEntry;
use Webwizardsusa\Taxonomy\Filament\Tables\Columns\TermsColumn;
use Webwizardsusa\Taxonomy\Filament\Tables\Filters\TermsFilter;
use Webwizardsusa\Taxonomy\Models\Term;
use Webwizardsusa\Taxonomy\Tests\Fixtures\Article;
use Webwizardsusa\Taxonomy\Tests\Fixtures\User;
use Webwizardsusa\Taxonomy\Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    if (! TestCase::hasFilament()) {
        $this->markTestSkipped('Filament is not installed.');
    }
});

if (class_exists(Component::class) && interface_exists(HasTable::class)) {
    class DisplayArticlesTable extends Component implements HasActions, HasSchemas, HasTable
    {
        use InteractsWithActions;
        use InteractsWithSchemas;
        use InteractsWithTable;

        public bool $descendants = true;

        public function table(Table $table): Table
        {
            return $table
                ->query(Article::query())
                ->columns([TermsColumn::make('tags')])
                ->filters([
                    TermsFilter::make('categories')->includingDescendants($this->descendants),
                    TermsFilter::make('tags')->multiple(),
                ]);
        }

        public function render(): string
        {
            return '<div>{{ $this->table }}</div>';
        }
    }

    class DisplayUsersTable extends Component implements HasActions, HasSchemas, HasTable
    {
        use InteractsWithActions;
        use InteractsWithSchemas;
        use InteractsWithTable;

        public function table(Table $table): Table
        {
            return $table->query(User::query())->columns([TextColumn::make('name'), TermsColumn::make('tags')]);
        }

        public function render(): string
        {
            return '<div>{{ $this->table }}</div>';
        }
    }

    class DisplayArticleInfolist extends Component implements HasSchemas
    {
        use InteractsWithSchemas;

        public Article $article;

        public function infolist(Schema $schema): Schema
        {
            return $schema->record($this->article)->components([TermsEntry::make('tags')->placeholder('No tags')]);
        }

        public function render(): string
        {
            return '<div>{{ $this->infolist }}</div>';
        }
    }
}

function displayArticle(string $title, array $tags = [], array $categories = []): Article
{
    $article = Article::create(['title' => $title]);
    $article->syncTerms('tags', $tags);
    $article->syncTerms('categories', $categories);

    return $article;
}

it('shows each row\'s tags in order, loading them all in one query', function (): void {
    foreach (range(1, 5) as $number) {
        displayArticle("Article {$number}", ["Tag {$number}", 'Shared']);
    }

    DB::enableQueryLog();

    Livewire::test(DisplayArticlesTable::class)
        ->assertSeeInOrder(['Tag 1', 'Shared', 'Tag 2', 'Shared']);

    $termQueries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'taxonomy_termables'));

    expect($termQueries)->toHaveCount(1);
});

it('shows no terms for records without HasTerms', function (): void {
    $user = User::create(['name' => 'Jamie', 'email' => 'jamie@example.com', 'password' => 'secret']);

    Livewire::test(DisplayUsersTable::class)
        ->assertCanSeeTableRecords([$user])
        ->assertSee('Jamie');
});

it('filters by a category and everything under it', function (bool $descendants, array $visible): void {
    $cars = Term::create(['vocabulary' => 'categories', 'name' => 'Cars']);
    $accessories = Term::create(['vocabulary' => 'categories', 'name' => 'Accessories', 'parent_id' => $cars->id]);
    $articles = [
        'cars' => displayArticle('cars', categories: [$cars]),
        'accessories' => displayArticle('accessories', categories: [$accessories]),
        'none' => displayArticle('none'),
    ];

    Livewire::test(DisplayArticlesTable::class, ['descendants' => $descendants])
        ->filterTable('categories', $cars->id)
        ->assertCanSeeTableRecords(collect($articles)->only($visible))
        ->assertCanNotSeeTableRecords(collect($articles)->except($visible));
})->with([
    'with descendants' => [true, ['cars', 'accessories']],
    'without descendants' => [false, ['cars']],
]);

it('filters by any of several tags', function (): void {
    $php = displayArticle('php', ['PHP']);
    $laravel = displayArticle('laravel', ['Laravel']);
    $none = displayArticle('none', ['Other']);

    Livewire::test(DisplayArticlesTable::class)
        ->filterTable('tags', [Term::findBySlug('tags', 'php')->id, Term::findBySlug('tags', 'laravel')->id])
        ->assertCanSeeTableRecords([$php, $laravel])
        ->assertCanNotSeeTableRecords([$none]);
});

it('shows a record\'s tags in an infolist entry, or the placeholder', function (): void {
    Livewire::test(DisplayArticleInfolist::class, ['article' => displayArticle('one', ['PHP', 'Laravel'])])
        ->assertSeeInOrder(['PHP', 'Laravel']);

    Livewire::test(DisplayArticleInfolist::class, ['article' => displayArticle('two')])
        ->assertSee('No tags');
});
