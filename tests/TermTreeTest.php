<?php

use Illuminate\Support\Facades\DB;
use Webwizardsusa\Taxonomy\Exceptions\DuplicateTermException;
use Webwizardsusa\Taxonomy\Exceptions\InvalidParentException;
use Webwizardsusa\Taxonomy\Models\Term;
use Webwizardsusa\Taxonomy\Support\TermTree;
use Webwizardsusa\Taxonomy\Tests\Fixtures\Article;
use Webwizardsusa\Taxonomy\Tests\TestCase;

uses(TestCase::class);

function treeMake(string $name, ?Term $parent = null, string $vocabulary = 'categories', int $weight = 0): Term
{
    return Term::create(['vocabulary' => $vocabulary, 'name' => $name, 'parent_id' => $parent?->id, 'weight' => $weight]);
}

it('refuses a parent in a flat vocabulary', function (): void {
    treeMake('Food', treeMake('Travel', vocabulary: 'topics'), 'topics');
})->throws(InvalidParentException::class, 'Topics is flat');

it('refuses a parent from another vocabulary', function (): void {
    $topic = Term::create(['vocabulary' => 'topics', 'name' => 'Travel']);

    treeMake('Cars', $topic);
})->throws(InvalidParentException::class, 'The parent must be a Term in Categories.');

it('refuses to move a term under itself or a descendant', function (bool $itself): void {
    $cars = treeMake('Cars');
    $accessories = treeMake('Accessories', $cars);

    expect(fn () => $cars->update(['parent_id' => $itself ? $cars->id : $accessories->id]))
        ->toThrow(InvalidParentException::class, "can't be moved under itself or one of its descendants");
})->with(['itself' => true, 'a descendant' => false]);

it('moves children up to the deleted term\'s parent', function (): void {
    $vehicles = treeMake('Vehicles');
    $cars = treeMake('Cars', $vehicles);
    $accessories = treeMake('Accessories', $cars);

    $cars->delete();
    expect($accessories->fresh()->parent_id)->toBe($vehicles->id);

    $vehicles->delete();
    expect($accessories->fresh()->parent_id)->toBeNull();
});

it('takes a deleted term off its records and leaves their other terms', function (): void {
    $article = Article::create(['title' => 'Hello']);
    $cars = treeMake('Cars');
    $phones = treeMake('Phones');
    $article->syncTerms('categories', [$cars, $phones]);

    $cars->delete();

    expect($article->termsIn('categories')->pluck('name')->all())->toBe(['Phones']);
});

it('leaves everything as it was when a child cannot move up', function (): void {
    treeMake('Accessories');
    $cars = treeMake('Cars');
    $accessories = treeMake('Accessories', $cars);

    expect(fn () => $cars->delete())->toThrow(DuplicateTermException::class);
    expect($cars->fresh())->not->toBeNull()
        ->and($accessories->fresh()->parent_id)->toBe($cars->id);
});

it('finds a term and everything under it', function (): void {
    $cars = treeMake('Cars');
    $accessories = treeMake('Accessories', $cars);
    $mats = treeMake('Mats', $accessories);
    treeMake('Phones');

    expect(TermTree::descendantIds([$cars->id]))->toEqualCanonicalizing([$cars->id, $accessories->id, $mats->id]);
});

it('stops on data with a cycle', function (): void {
    $cars = treeMake('Cars');
    $accessories = treeMake('Accessories', $cars);
    DB::table('taxonomy_terms')->where('id', $cars->id)->update(['parent_id' => $accessories->id]);

    expect(TermTree::descendantIds([$cars->id]))->toEqualCanonicalizing([$cars->id, $accessories->id])
        ->and(collect(TermTree::flatten('categories'))->pluck('term.name')->all())->toEqualCanonicalizing(['Cars', 'Accessories']);
});

it('flattens depth first with each level in order', function (): void {
    $phones = treeMake('Phones', weight: 2);
    $cars = treeMake('Cars', weight: 1);
    treeMake('Tyres', $cars, weight: 2);
    treeMake('Accessories', $cars, weight: 1);
    treeMake('Cases', $phones);

    expect(collect(TermTree::flatten('categories'))->map(fn (array $row): string => str_repeat('-', $row['depth']).$row['term']->name)->all())
        ->toBe(['Cars', '-Accessories', '-Tyres', 'Phones', '-Cases']);
});

it('builds the term path', function (): void {
    expect(treeMake('Mats', treeMake('Floor', treeMake('Cars')))->path())->toBe('cars/floor/mats');
});
