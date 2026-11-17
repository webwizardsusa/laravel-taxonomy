---
title: Configuration
weight: 40
description: Every key in config/taxonomy.php, with its default and when to change it.
---

Publish the config with `php artisan vendor:publish --tag=taxonomy-config`. The full default file:

```php
use Webwizardsusa\Taxonomy\Models\Term;

return [
    'term_model' => Term::class,

    'tables' => [
        'terms' => 'taxonomy_terms',
        'termables' => 'taxonomy_termables',
    ],

    'vocabularies' => [],

    'filament' => [
        'suggestion_limit' => 1000,
    ],
];
```

## `term_model`

Default: `Webwizardsusa\Taxonomy\Models\Term::class`

The Eloquent model for Terms. Set it to your own subclass to add columns, casts, relations or methods. The class must
extend `Webwizardsusa\Taxonomy\Models\Term`, or `Taxonomy::termModel()` throws an `InvalidArgumentException`.

The package uses this class everywhere: the `terms` relation on Records, the lookups such as `Term::findByPath()`, the
Parent Term and children relations, the commands and the Filament components. Every Vocabulary shares the one model.

See [your own Term model](extending/custom-term-model.md).

## `tables`

Defaults: `terms` is `taxonomy_terms`, `termables` is `taxonomy_termables`.

The table for Terms and the pivot table between Terms and Records. The migration reads these when it runs, so set
them before `php artisan migrate`. The model and every query read them at runtime too, so changing them later means
renaming the tables yourself.

## `vocabularies`

Default: `[]`

Your application's Vocabulary classes. They are registered when the Taxonomy registry is first used:

```php
'vocabularies' => [
    App\Taxonomy\TagsVocabulary::class,
    App\Taxonomy\CategoriesVocabulary::class,
],
```

Packages that bring their own Vocabularies call `Taxonomy::register()` instead (see
[the Taxonomy facade](usage/registry.md#registering-vocabularies)).

This list is also what the Filament plugin uses to add management pages when you don't pass it
`->vocabularies()`.

## `filament.suggestion_limit`

Default: `1000`

How many existing names the Filament [`TermTagsInput`](filament/form-fields.md#termtagsinput) offers as suggestions,
in alphabetical order. You can always type a name that isn't suggested. Lower it if an Open Vocabulary has many
thousands of Terms and the form gets heavy.
