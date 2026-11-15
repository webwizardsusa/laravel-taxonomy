<?php

use Illuminate\Support\Facades\DB;
use Webwizardsusa\Taxonomy\Exceptions\DuplicateTermException;
use Webwizardsusa\Taxonomy\Exceptions\UnknownVocabularyException;
use Webwizardsusa\Taxonomy\Facades\Taxonomy;
use Webwizardsusa\Taxonomy\Models\Term;
use Webwizardsusa\Taxonomy\Tests\Fixtures\CategoriesVocabulary;
use Webwizardsusa\Taxonomy\Tests\Fixtures\CustomTerm;
use Webwizardsusa\Taxonomy\Tests\TestCase;

uses(TestCase::class);

function termMake(string $vocabulary, string $name, ?Term $parent = null, array $attributes = []): Term
{
    return Term::create(['vocabulary' => $vocabulary, 'name' => $name, 'parent_id' => $parent?->id, ...$attributes]);
}

it('makes the slug from the name, keeping one that is given', function (): void {
    expect(termMake('tags', 'Laravel News')->slug)->toBe('laravel-news')
        ->and(termMake('tags', 'PHP', attributes: ['slug' => 'php-lang'])->slug)->toBe('php-lang');
});

it('numbers a made slug that is taken', function (): void {
    termMake('tags', 'C');

    expect(termMake('tags', 'C++')->slug)->toBe('c-2')
        ->and(termMake('tags', 'C#')->slug)->toBe('c-3');
});

it('refuses a given slug that is taken', function (): void {
    termMake('topics', 'Travel', attributes: ['slug' => 'travel']);

    termMake('topics', 'Trips', attributes: ['slug' => 'travel']);
})->throws(DuplicateTermException::class, 'The slug [travel] is already taken in Topics.');

it('refuses an unknown vocabulary', function (): void {
    termMake('nope', 'PHP');
})->throws(UnknownVocabularyException::class);

it('finds an existing tag by name ignoring case instead of creating one', function (): void {
    $php = termMake('tags', 'PHP');

    expect(Term::findOrCreateByName('tags', 'php')->is($php))->toBeTrue()
        ->and(Term::query()->count())->toBe(1);
});

it('trims tag names and collapses the whitespace in them', function (): void {
    expect(Term::findOrCreateByName('tags', '  Laravel   News ')->name)->toBe('Laravel News');
});

it('refuses a tag name that exists in another case', function (): void {
    termMake('tags', 'PHP');

    termMake('tags', 'Php');
})->throws(DuplicateTermException::class, 'A Term named [Php] already exists in Tags.');

it('refuses to create closed vocabulary terms by name', function (): void {
    Term::findOrCreateByName('categories', 'Cars');
})->throws(InvalidArgumentException::class, 'Categories is closed');

it('allows the same category name under different parents', function (): void {
    $accessories = termMake('categories', 'Accessories', termMake('categories', 'Cars'));
    $phoneAccessories = termMake('categories', 'Accessories', termMake('categories', 'Phones'));

    expect($accessories->slug)->toBe('accessories')->and($phoneAccessories->slug)->toBe('accessories');
});

it('refuses the same category name twice among siblings', function (bool $topLevel): void {
    $parent = $topLevel ? null : termMake('categories', 'Cars');
    termMake('categories', 'Accessories', $parent);

    expect(fn () => termMake('categories', 'ACCESSORIES', $parent))
        ->toThrow(DuplicateTermException::class, 'A Term named [ACCESSORIES] already exists in Categories.');
})->with(['at the top level' => true, 'under a parent' => false]);

it('refuses a rename onto a sibling name', function (): void {
    termMake('topics', 'Travel');
    $food = termMake('topics', 'Food');

    expect(fn () => $food->update(['name' => 'travel']))->toThrow(DuplicateTermException::class);
});

it('ends with one tag when another request creates the same name first', function (): void {
    Term::creating(function (Term $term): void {
        if ($term->name === 'PHP' && DB::table('taxonomy_terms')->doesntExist()) {
            DB::table('taxonomy_terms')->insert(['vocabulary' => 'tags', 'name' => 'PHP', 'name_key' => 'php', 'slug' => 'php', 'weight' => 0]);
        }
    });

    $term = Term::findOrCreateByName('tags', 'PHP');

    expect($term->exists)->toBeTrue()
        ->and(DB::table('taxonomy_terms')->count())->toBe(1)
        ->and($term->id)->toBe(DB::table('taxonomy_terms')->value('id'));
});

it('uses the configured term model everywhere', function (): void {
    config()->set('taxonomy.term_model', CustomTerm::class);

    $cars = CustomTerm::create(['vocabulary' => 'categories', 'name' => 'Cars']);
    CustomTerm::create(['vocabulary' => 'categories', 'name' => 'Accessories', 'parent_id' => $cars->id]);

    expect(Taxonomy::newTermQuery()->getModel())->toBeInstanceOf(CustomTerm::class)
        ->and(Term::findOrCreateByName('tags', 'PHP'))->toBeInstanceOf(CustomTerm::class)
        ->and($cars->children()->first())->toBeInstanceOf(CustomTerm::class)
        ->and(Term::findByPath('categories', 'cars/accessories'))->toBeInstanceOf(CustomTerm::class);
});

it('gives its vocabulary while the attribute stays the key', function (): void {
    $term = termMake('categories', 'Cars');

    expect($term->getVocabulary())->toBeInstanceOf(CategoriesVocabulary::class)
        ->and($term->vocabulary)->toBe('categories');
});
