---
title: The Taxonomy facade
weight: 20
description: Register and look up Vocabularies, reach the configured Term model, and the exceptions the registry throws.
---

`Webwizardsusa\Taxonomy\Facades\Taxonomy` is the registry of Vocabularies and the way to the configured Term model.
It is also aliased as `Taxonomy`. Behind it is the `Webwizardsusa\Taxonomy\Taxonomy` singleton, which you can inject
instead.

Wherever a method takes a Vocabulary, you can pass its key (`'categories'`), its class (`CategoriesVocabulary::class`)
or the instance. The same goes for the `HasTerms` methods, the Term lookups and the Filament components'
`->vocabulary()`.

## Registering Vocabularies

```php
use Webwizardsusa\Taxonomy\Facades\Taxonomy;

Taxonomy::register(BoardsVocabulary::class, TopicsVocabulary::class);
```

Each class is built through the container and checked. `register()` throws an `InvalidVocabularyException` when:

- the class doesn't extend `Webwizardsusa\Taxonomy\Vocabulary`,
- another class is already registered with the same key,
- the Vocabulary is both open and hierarchical.

The classes in `taxonomy.vocabularies` are registered the same way, the first time the registry is resolved.

## Looking up Vocabularies

```php
$vocabulary = Taxonomy::vocabulary('categories');      // the Vocabulary instance
$vocabulary = Taxonomy::vocabulary(CategoriesVocabulary::class);

Taxonomy::has('categories');                           // true or false

foreach (Taxonomy::vocabularies() as $key => $vocabulary) {
    // every registered Vocabulary, keyed by key, in registration order
}
```

`vocabulary()` returns the one shared instance and throws an `UnknownVocabularyException` ("No vocabulary is
registered with the key [...]") for an unknown key or class. Use `has()` first when the key comes from user input.

A Term gives you its Vocabulary instance too:

```php
$term->vocabulary;         // 'categories', the key
$term->getVocabulary();    // the CategoriesVocabulary instance
```

## The Term model

```php
Taxonomy::termModel();      // the class in taxonomy.term_model, such as App\Models\Term::class
Taxonomy::newTermQuery();   // a query builder on that model
```

Use these in code that should keep working when the application swaps in its own Term model:

```php
$recent = Taxonomy::newTermQuery()
    ->inVocabulary('tags')
    ->latest()
    ->limit(10)
    ->get();
```

`termModel()` throws an `InvalidArgumentException` when the configured class doesn't extend
`Webwizardsusa\Taxonomy\Models\Term`.

## Exceptions

All live in `Webwizardsusa\Taxonomy\Exceptions`.

| Exception | Extends | Thrown when |
| --- | --- | --- |
| `InvalidVocabularyException` | `InvalidArgumentException` | Registering a class that isn't a Vocabulary, a taken key, or an open hierarchical Vocabulary |
| `UnknownVocabularyException` | `InvalidArgumentException` | Asking for a Vocabulary that isn't registered, anywhere a Vocabulary is taken |
| `DuplicateTermException` | `RuntimeException` | Saving a Term whose name or slug is taken; `$exception->attribute` is `'name'` or `'slug'` |
| `InvalidParentException` | `InvalidArgumentException` | Saving a Term with a parent in a flat Vocabulary, in another Vocabulary, or under itself or a descendant |

[Terms](terms.md#uniqueness-rules) covers when the last two are thrown.
