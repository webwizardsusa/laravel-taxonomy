<?php

use Illuminate\Support\Facades\DB;
use Webwizardsusa\Taxonomy\Models\Term;
use Webwizardsusa\Taxonomy\Tests\Fixtures\Article;
use Webwizardsusa\Taxonomy\Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->article = Article::create(['title' => 'Hello']);
});

function hasTermsNames(Article $article, string $vocabulary): array
{
    return $article->termsIn($vocabulary)->pluck('name')->all();
}

it('keeps terms in the order they were given', function (): void {
    $this->article->syncTerms('tags', ['Laravel', 'PHP', 'Testing']);

    expect(hasTermsNames($this->article, 'tags'))->toBe(['Laravel', 'PHP', 'Testing']);

    $this->article->syncTerms('tags', ['Testing', 'Laravel']);

    expect(hasTermsNames($this->article, 'tags'))->toBe(['Testing', 'Laravel']);
});

it('creates tags by name, matching existing ones ignoring case', function (): void {
    $php = Term::findOrCreateByName('tags', 'PHP');

    $this->article->syncTerms('tags', ['php', 'New One', 'NEW one']);

    expect(hasTermsNames($this->article, 'tags'))->toBe(['PHP', 'New One'])
        ->and($this->article->termsIn('tags')->first()->is($php))->toBeTrue();
});

it('takes models, ids and names together', function (): void {
    $php = Term::findOrCreateByName('tags', 'PHP');
    $laravel = Term::findOrCreateByName('tags', 'Laravel');

    $this->article->syncTerms('tags', [$laravel->id, 'Testing', $php, 'laravel']);

    expect(hasTermsNames($this->article, 'tags'))->toBe(['Laravel', 'Testing', 'PHP']);
});

it('takes ids as strings for closed vocabularies, but not names', function (): void {
    $cars = Term::create(['vocabulary' => 'categories', 'name' => 'Cars']);

    $this->article->syncTerms('categories', [(string) $cars->id]);
    expect(hasTermsNames($this->article, 'categories'))->toBe(['Cars']);

    $this->article->syncTerms('categories', ['Cars']);
})->throws(InvalidArgumentException::class, 'Categories is closed: pass Term models or ids, not names.');

it('refuses terms from another vocabulary', function (): void {
    $cars = Term::create(['vocabulary' => 'categories', 'name' => 'Cars']);

    $this->article->syncTerms('tags', [$cars->id]);
})->throws(InvalidArgumentException::class, "[Cars] isn't in Tags.");

it('leaves the other vocabularies alone', function (): void {
    $cars = Term::create(['vocabulary' => 'categories', 'name' => 'Cars']);
    $this->article->syncTerms('categories', [$cars]);

    $this->article->syncTerms('tags', ['PHP']);
    $this->article->syncTerms('tags', []);

    expect(hasTermsNames($this->article, 'categories'))->toBe(['Cars'])
        ->and(hasTermsNames($this->article, 'tags'))->toBe([]);
});

it('appends new terms after the current ones, skipping those it has', function (): void {
    $this->article->syncTerms('tags', ['PHP', 'Laravel']);

    $this->article->attachTerms('tags', ['laravel', 'Testing']);

    expect(hasTermsNames($this->article, 'tags'))->toBe(['PHP', 'Laravel', 'Testing']);
});

it('removes terms by model, id or name', function (): void {
    $this->article->syncTerms('tags', ['PHP', 'Laravel', 'Testing', 'Pest']);
    $php = Term::findBySlug('tags', 'php');

    $this->article->detachTerms('tags', [$php, Term::findBySlug('tags', 'laravel')->id, 'TESTING']);

    expect(hasTermsNames($this->article, 'tags'))->toBe(['Pest']);
});

it('takes its terms off the pivot when deleted', function (): void {
    $this->article->syncTerms('tags', ['PHP']);

    $this->article->delete();

    expect(DB::table('taxonomy_termables')->count())->toBe(0);
});

it('reads a vocabulary\'s terms from the loaded relation', function (): void {
    $this->article->syncTerms('tags', ['PHP']);
    $this->article->syncTerms('topics', [Term::create(['vocabulary' => 'topics', 'name' => 'Travel'])]);
    $articles = Article::with('terms')->get();

    DB::enableQueryLog();
    $names = [hasTermsNames($articles->first(), 'tags'), hasTermsNames($articles->first(), 'topics')];

    expect($names)->toBe([['PHP'], ['Travel']])
        ->and(DB::getQueryLog())->toBeEmpty();
});
