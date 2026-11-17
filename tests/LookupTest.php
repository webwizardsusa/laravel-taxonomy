<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Webwizardsusa\Taxonomy\Models\Term;
use Webwizardsusa\Taxonomy\Tests\TestCase;

uses(TestCase::class);

it('finds a term by slug within its vocabulary', function (): void {
    Term::create(['vocabulary' => 'topics', 'name' => 'PHP']);
    $tag = Term::create(['vocabulary' => 'tags', 'name' => 'PHP']);

    expect(Term::findBySlug('tags', 'php')->is($tag))->toBeTrue()
        ->and(Term::findBySlug('tags', 'nope'))->toBeNull();
});

it('finds only top-level terms by slug in a hierarchical vocabulary', function (): void {
    $phones = Term::create(['vocabulary' => 'categories', 'name' => 'Phones']);
    Term::create(['vocabulary' => 'categories', 'name' => 'Accessories', 'parent_id' => $phones->id]);

    expect(Term::findBySlug('categories', 'accessories'))->toBeNull();

    $accessories = Term::create(['vocabulary' => 'categories', 'name' => 'Accessories']);

    expect(Term::findBySlug('categories', 'accessories')->is($accessories))->toBeTrue()
        ->and(Term::findBySlug('categories', 'phones')->is($phones))->toBeTrue();
});

it('finds the right one of two same-named categories by path', function (): void {
    $cars = Term::create(['vocabulary' => 'categories', 'name' => 'Cars']);
    $phones = Term::create(['vocabulary' => 'categories', 'name' => 'Phones']);
    Term::create(['vocabulary' => 'categories', 'name' => 'Accessories', 'parent_id' => $cars->id]);
    $phoneAccessories = Term::create(['vocabulary' => 'categories', 'name' => 'Accessories', 'parent_id' => $phones->id]);

    expect(Term::findByPath('categories', 'phones/accessories')->is($phoneAccessories))->toBeTrue()
        ->and(Term::findByPath('categories', 'accessories'))->toBeNull()
        ->and(Term::findByPath('categories', 'phones/nope'))->toBeNull();
});

it('treats a flat vocabulary\'s path as its slug', function (): void {
    $tag = Term::create(['vocabulary' => 'tags', 'name' => 'PHP']);

    expect(Term::findByPath('tags', 'php')->is($tag))->toBeTrue()
        ->and(Term::findByPath('tags', 'php/more'))->toBeNull();
});

it('throws for a missing path when asked to', function (): void {
    Term::findByPathOrFail('categories', 'nope');
})->throws(ModelNotFoundException::class);

it('lists terms in the vocabulary\'s order', function (): void {
    foreach (['Banana' => 1, 'Apple' => 2, 'Cherry' => 1] as $name => $weight) {
        Term::create(['vocabulary' => 'topics', 'name' => $name, 'weight' => $weight]);
        Term::create(['vocabulary' => 'tags', 'name' => $name, 'weight' => $weight]);
    }

    expect(Term::query()->inVocabulary('topics')->ordered('topics')->pluck('name')->all())->toBe(['Banana', 'Cherry', 'Apple'])
        ->and(Term::query()->inVocabulary('tags')->ordered('tags')->pluck('name')->all())->toBe(['Apple', 'Banana', 'Cherry']);
});
