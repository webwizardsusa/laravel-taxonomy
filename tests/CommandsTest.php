<?php

use Illuminate\Support\Facades\DB;
use Webwizardsusa\Taxonomy\Models\Term;
use Webwizardsusa\Taxonomy\Tests\Fixtures\Article;
use Webwizardsusa\Taxonomy\Tests\TestCase;

uses(TestCase::class);

function commandsInsert(string $vocabulary, string $name, ?int $parentId = null, ?string $slug = null, ?string $nameKey = null): int
{
    return DB::table('taxonomy_terms')->insertGetId([
        'vocabulary' => $vocabulary, 'name' => $name, 'name_key' => $nameKey, 'slug' => $slug ?? str($name)->slug(), 'parent_id' => $parentId, 'weight' => 0,
    ]);
}

it('reports nothing for valid terms', function (): void {
    Term::create(['vocabulary' => 'tags', 'name' => 'PHP']);

    $this->artisan('taxonomy:check')->expectsOutputToContain('No problems found.')->assertSuccessful();
});

it('reports terms that break their vocabulary\'s rules, changing nothing', function (): void {
    commandsInsert('tags', 'PHP', nameKey: 'php');
    $repeatedTag = commandsInsert('tags', 'php', slug: 'php-2');
    $parent = commandsInsert('topics', 'Travel');
    $child = commandsInsert('topics', 'Food', $parent);
    commandsInsert('categories', 'Cars');
    $repeatedCategory = commandsInsert('categories', 'cars', slug: 'cars-2');
    $orphan = commandsInsert('gone', 'Old');

    $this->artisan('taxonomy:check')
        ->expectsTable(['Vocabulary', 'Term', 'Problem'], [
            ['categories', $repeatedCategory, 'Name [cars] is repeated among its siblings.'],
            ['gone', $orphan, 'The vocabulary is not registered.'],
            ['tags', $repeatedTag, 'Its name key is missing or stale, so it is not found by name: save the Term to set it.'],
            ['tags', $repeatedTag, 'Name [php] is repeated in the vocabulary.'],
            ['topics', $child, 'Has a parent, but the vocabulary is flat.'],
        ])
        ->assertFailed();

    expect(DB::table('taxonomy_terms')->count())->toBe(7);
});

it('reports tags whose name key is missing or stale, until they are saved', function (): void {
    $missing = commandsInsert('tags', 'Travel');
    $stale = commandsInsert('tags', 'Food', nameKey: 'drink');
    commandsInsert('topics', 'Cars');

    $this->artisan('taxonomy:check')
        ->expectsTable(['Vocabulary', 'Term', 'Problem'], [
            ['tags', $missing, 'Its name key is missing or stale, so it is not found by name: save the Term to set it.'],
            ['tags', $stale, 'Its name key is missing or stale, so it is not found by name: save the Term to set it.'],
        ])
        ->assertFailed();

    Term::query()->inVocabulary('tags')->each(fn (Term $term) => $term->save());

    $this->artisan('taxonomy:check')->expectsOutputToContain('No problems found.')->assertSuccessful();
    expect(Term::findOrCreateByName('tags', 'travel')->id)->toBe($missing);
});

it('prunes the terms of unregistered vocabularies', function (): void {
    $orphan = commandsInsert('gone', 'Old');
    $article = Article::create(['title' => 'Hello']);
    DB::table('taxonomy_termables')->insert(['term_id' => $orphan, 'termable_type' => Article::class, 'termable_id' => $article->id, 'position' => 0]);
    Term::findOrCreateByName('tags', 'Unused');

    $this->artisan('taxonomy:prune')->expectsOutputToContain('Pruned 1 term.')->assertSuccessful();

    expect(DB::table('taxonomy_terms')->pluck('name')->all())->toBe(['Unused'])
        ->and(DB::table('taxonomy_termables')->count())->toBe(0);
});

it('prunes unused tags but never unused categories when asked', function (): void {
    $article = Article::create(['title' => 'Hello']);
    $article->syncTerms('tags', ['Used']);
    Term::findOrCreateByName('tags', 'Unused');
    Term::create(['vocabulary' => 'categories', 'name' => 'Empty category']);

    $this->artisan('taxonomy:prune --unused')->expectsOutputToContain('Pruned 1 term.')->assertSuccessful();

    expect(Term::query()->orderBy('id')->pluck('name')->all())->toBe(['Used', 'Empty category']);
});

it('only lists what it would prune when pretending', function (): void {
    Term::findOrCreateByName('tags', 'Unused');

    $this->artisan('taxonomy:prune --unused --pretend')->expectsOutputToContain('Would prune 1 term.')->assertSuccessful();

    expect(Term::query()->count())->toBe(1);
});
