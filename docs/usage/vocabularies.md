---
title: Defining Vocabularies
weight: 10
description: Write a Vocabulary class, choose open or closed and flat or hierarchical, and register it.
---

A Vocabulary is a class that extends `Webwizardsusa\Taxonomy\Vocabulary`. It needs a key and a label; everything else
has a default.

## A minimal Vocabulary

```php
namespace App\Taxonomy;

use Webwizardsusa\Taxonomy\Vocabulary;

class CategoriesVocabulary extends Vocabulary
{
    public static function key(): string
    {
        return 'categories';
    }

    public function label(): string
    {
        return 'Categories';
    }
}
```

Without further methods this is a flat Closed Vocabulary, sorted by weight.

- **`key()`** (static, required): the identifier every Term of this Vocabulary stores in its `vocabulary` column. You
  use it everywhere you name a Vocabulary: `$post->syncTerms('categories', …)`, `TermSelect::make('categories')`.
- **`label()`** (required): the human name, used for the management page's title and navigation item and in
  messages. On the management page, its singular, lowercased form names the Terms in action labels ("New category").

> [!WARNING]
> Never change a key once Terms exist. Terms are linked to their Vocabulary by the key alone, so a new key cuts the
> existing Terms off: they no longer belong to any registered Vocabulary, `taxonomy:check` reports them, and
> `taxonomy:prune` deletes them. Renaming or moving the class is safe.

## Open, closed and hierarchical

Two methods decide the kind of Vocabulary. Both default to `false`.

```php
public function isOpen(): bool
{
    return true;    // free tagging: typing a name creates the Term
}

public function isHierarchical(): bool
{
    return true;    // Terms can have a Parent Term
}
```

The valid combinations:

| `isOpen()` | `isHierarchical()` | Result |
| --- | --- | --- |
| `true` | `false` | Open Vocabulary, flat (tags) |
| `false` | `false` | Closed Vocabulary, flat (a curated list) |
| `false` | `true` | Closed Vocabulary with a tree (categories) |
| `true` | `true` | Invalid |

> [!CAUTION]
> An open, hierarchical Vocabulary is refused when it's registered, with an `InvalidVocabularyException`: "[...] is
> open, and open vocabularies are flat." Vocabularies from config are registered the first time the registry is
> used, so the exception shows up on the first request or command that touches taxonomy.

See [Concepts](../concepts.md#open-and-closed-vocabularies) for what each choice means for Term names.

## Other options

A Vocabulary can also override:

- **`sortOrder()`**: a `TermSortOrder` case. `TermSortOrder::Weight` lists Terms by their manual `weight`, then name;
  `TermSortOrder::Alphabetical` lists them by name. Open Vocabularies default to alphabetical, closed ones to weight.
- **`formComponents()`**: extra Filament form components for this Vocabulary's Terms on its management page.
- **`tableColumns()`**: extra Filament table columns on the management page. Flat Vocabularies only; the tree of a
  hierarchical Vocabulary doesn't show them.
- **`navigationIcon()`**: the management page's navigation icon. Defaults to Filament's outlined tag Heroicon.

[Vocabulary options](../extending/vocabulary-options.md) covers each of these in depth, with a full example.

## Registering Vocabularies

List your application's Vocabularies in `config/taxonomy.php`:

```php
'vocabularies' => [
    App\Taxonomy\TagsVocabulary::class,
    App\Taxonomy\CategoriesVocabulary::class,
],
```

A package that ships its own Vocabulary registers it from a service provider instead:

```php
use Webwizardsusa\Taxonomy\Facades\Taxonomy;

public function boot(): void
{
    Taxonomy::register(\Acme\Forum\BoardsVocabulary::class);
}
```

`register()` takes any number of classes. Registering the same class twice is harmless; a different class with a key
that's already taken throws an `InvalidVocabularyException`.

> [!NOTE]
> The Filament plugin adds management pages for the Vocabularies in `taxonomy.vocabularies` only. Panels are built
> before packages boot, so a Vocabulary registered at runtime has to be listed in
> [`TaxonomyPlugin::make()->vocabularies()`](../filament/management-pages.md#choosing-the-vocabularies).

## Dependency injection

The registry builds each Vocabulary through the service container, once, and keeps that instance. A Vocabulary can
therefore take constructor dependencies:

```php
use App\Support\Settings;
use Webwizardsusa\Taxonomy\TermSortOrder;
use Webwizardsusa\Taxonomy\Vocabulary;

class RegionsVocabulary extends Vocabulary
{
    public function __construct(protected Settings $settings) {}

    public static function key(): string
    {
        return 'regions';
    }

    public function label(): string
    {
        return $this->settings->get('regions.label', 'Regions');
    }

    public function sortOrder(): TermSortOrder
    {
        return TermSortOrder::Alphabetical;
    }
}
```

`key()` is static and called without an instance, so it can't depend on anything injected. Keep it a constant string.
