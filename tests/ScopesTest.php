<?php

use Webwizardsusa\Taxonomy\Models\Term;
use Webwizardsusa\Taxonomy\Tests\Fixtures\Article;
use Webwizardsusa\Taxonomy\Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $cars = Term::create(['vocabulary' => 'categories', 'name' => 'Cars']);
    $accessories = Term::create(['vocabulary' => 'categories', 'name' => 'Accessories', 'parent_id' => $cars->id]);
    $mats = Term::create(['vocabulary' => 'categories', 'name' => 'Mats', 'parent_id' => $accessories->id]);
    $phones = Term::create(['vocabulary' => 'categories', 'name' => 'Phones']);

    $this->terms = compact('cars', 'accessories', 'mats', 'phones');

    foreach ([
        'php' => [['PHP'], []],
        'php-laravel' => [['PHP', 'Laravel'], []],
        'laravel-mats' => [['Laravel'], [$mats]],
        'cars' => [[], [$cars]],
        'phones-red' => [['Red'], [$phones]],
        'mats-red' => [['Red'], [$mats]],
    ] as $title => [$tags, $categories]) {
        $article = Article::create(['title' => $title]);
        $article->syncTerms('tags', $tags);
        $article->syncTerms('categories', $categories);
    }
});

function scopeTitles($query): array
{
    return $query->orderBy('id')->pluck('title')->all();
}

it('finds records with any of the terms', function (): void {
    expect(scopeTitles(Article::withAnyTerms(['php', 'laravel'], 'tags')))->toBe(['php', 'php-laravel', 'laravel-mats']);
});

it('finds records with all of the terms', function (): void {
    expect(scopeTitles(Article::withAllTerms(['php', 'laravel'], 'tags')))->toBe(['php-laravel']);
});

it('finds records without the terms', function (): void {
    expect(scopeTitles(Article::withoutTerms(['php'], 'tags')))->toBe(['laravel-mats', 'cars', 'phones-red', 'mats-red']);
});

it('includes descendants when asked', function (): void {
    $cars = $this->terms['cars'];

    expect(scopeTitles(Article::withAnyTerms($cars)))->toBe(['cars'])
        ->and(scopeTitles(Article::withAnyTerms($cars, includingDescendants: true)))->toBe(['laravel-mats', 'cars', 'mats-red'])
        ->and(scopeTitles(Article::withoutTerms($cars, includingDescendants: true)))->toBe(['php', 'php-laravel', 'phones-red']);
});

it('needs some term under each given one with descendants', function (): void {
    $red = Term::findBySlug('tags', 'red');

    expect(scopeTitles(Article::withAllTerms([$this->terms['cars'], $red], includingDescendants: true)))->toBe(['mats-red']);
});

it('takes term paths with the vocabulary', function (): void {
    expect(scopeTitles(Article::withAnyTerms('cars/accessories/mats', 'categories')))->toBe(['laravel-mats', 'mats-red']);
});

it('needs a vocabulary for slugs', function (): void {
    Article::withAnyTerms('php')->get();
})->throws(InvalidArgumentException::class, 'Pass a vocabulary');

it('treats a slug that matches nothing as a term no record has', function (): void {
    expect(scopeTitles(Article::withAnyTerms(['nope'], 'tags')))->toBe([])
        ->and(scopeTitles(Article::withAllTerms(['php', 'nope'], 'tags')))->toBe([])
        ->and(Article::withoutTerms(['nope'], 'tags')->count())->toBe(6);
});

it('handles empty lists', function (): void {
    expect(Article::withAnyTerms([])->count())->toBe(0)
        ->and(Article::withAllTerms([])->count())->toBe(6)
        ->and(Article::withoutTerms([])->count())->toBe(6);
});
