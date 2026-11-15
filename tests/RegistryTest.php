<?php

use Webwizardsusa\Taxonomy\Exceptions\InvalidVocabularyException;
use Webwizardsusa\Taxonomy\Exceptions\UnknownVocabularyException;
use Webwizardsusa\Taxonomy\Facades\Taxonomy;
use Webwizardsusa\Taxonomy\Models\Term;
use Webwizardsusa\Taxonomy\TermSortOrder;
use Webwizardsusa\Taxonomy\Tests\Fixtures\CategoriesVocabulary;
use Webwizardsusa\Taxonomy\Tests\Fixtures\TagsVocabulary;
use Webwizardsusa\Taxonomy\Tests\TestCase;
use Webwizardsusa\Taxonomy\Vocabulary;

uses(TestCase::class);

class RegistryOtherVocabulary extends Vocabulary
{
    public static function key(): string
    {
        return 'other';
    }

    public function label(): string
    {
        return 'Other';
    }
}

class RegistryDuplicateTagsVocabulary extends RegistryOtherVocabulary
{
    public static function key(): string
    {
        return 'tags';
    }
}

class RegistryOpenHierarchyVocabulary extends RegistryOtherVocabulary
{
    public static function key(): string
    {
        return 'open-hierarchy';
    }

    public function isOpen(): bool
    {
        return true;
    }

    public function isHierarchical(): bool
    {
        return true;
    }
}

it('registers the vocabularies listed in config', function (): void {
    expect(Taxonomy::vocabulary('tags'))->toBeInstanceOf(TagsVocabulary::class)
        ->and(array_keys(Taxonomy::vocabularies()))->toBe(['tags', 'categories', 'topics']);
});

it('registers vocabularies at runtime', function (): void {
    Taxonomy::register(RegistryOtherVocabulary::class);

    expect(Taxonomy::has('other'))->toBeTrue();
});

it('finds a vocabulary by key, class or instance', function (): void {
    $vocabulary = Taxonomy::vocabulary('categories');

    expect(Taxonomy::vocabulary(CategoriesVocabulary::class))->toBe($vocabulary)
        ->and(Taxonomy::vocabulary($vocabulary))->toBe($vocabulary);
});

it('refuses an unknown key', function (): void {
    Taxonomy::vocabulary('nope');
})->throws(UnknownVocabularyException::class, 'No vocabulary is registered with the key [nope].');

it('refuses a second class with a key that is taken', function (): void {
    Taxonomy::register(RegistryDuplicateTagsVocabulary::class);
})->throws(InvalidVocabularyException::class, 'both have the key [tags]');

it('refuses an open hierarchical vocabulary', function (): void {
    Taxonomy::register(RegistryOpenHierarchyVocabulary::class);
})->throws(InvalidVocabularyException::class, '[RegistryOpenHierarchyVocabulary] is open, and open vocabularies are flat.');

it('refuses a class that is not a vocabulary', function (): void {
    Taxonomy::register(Term::class);
})->throws(InvalidVocabularyException::class, "isn't a Vocabulary");

it('sorts open vocabularies by name and closed ones by weight unless told otherwise', function (): void {
    expect(Taxonomy::vocabulary('tags')->sortOrder())->toBe(TermSortOrder::Alphabetical)
        ->and(Taxonomy::vocabulary('categories')->sortOrder())->toBe(TermSortOrder::Weight);
});

it('refuses a term model that does not extend the base term', function (): void {
    config()->set('taxonomy.term_model', RegistryOtherVocabulary::class);

    Taxonomy::termModel();
})->throws(InvalidArgumentException::class, 'must extend');
