---
title: Vocabulary options
weight: 10
description: Every method a Vocabulary class can override, with a full example.
---

A Vocabulary extends `Webwizardsusa\Taxonomy\Vocabulary`. Two methods are required; the rest have defaults.

| Method | Default | Purpose |
| --- | --- | --- |
| `static key(): string` | required | The identifier Terms store. Never change it once Terms exist. |
| `label(): string` | required | The human name: page title, navigation label, messages. |
| `isOpen(): bool` | `false` | Free tagging: typing a name creates the Term. |
| `isHierarchical(): bool` | `false` | Terms can have a Parent Term. Not allowed with `isOpen()`. |
| `sortOrder(): TermSortOrder` | `Alphabetical` if open, `Weight` if closed | How Terms are listed. |
| `formComponents(): array` | `[]` | Extra fields on the management page's Term form. |
| `tableColumns(): array` | `[]` | Extra columns on the management page's table (flat Vocabularies only). |
| `navigationIcon(): mixed` | `null` (an outlined tag) | The management page's navigation icon. |

The registry builds one instance of each Vocabulary through the container and reuses it, so the methods can use
injected services, and they may be called often: keep them cheap.

## A full example

A flat Closed Vocabulary of topics, sorted by name, where each Topic has a colour and can be featured. The two extra
columns live on your own Term model (see [your own Term model](custom-term-model.md)).

```php
namespace App\Taxonomy;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Webwizardsusa\Taxonomy\TermSortOrder;
use Webwizardsusa\Taxonomy\Vocabulary;

class TopicsVocabulary extends Vocabulary
{
    public static function key(): string
    {
        return 'topics';
    }

    public function label(): string
    {
        return 'Topics';
    }

    public function sortOrder(): TermSortOrder
    {
        return TermSortOrder::Alphabetical;
    }

    public function formComponents(): array
    {
        return [
            ColorPicker::make('color'),
            Toggle::make('is_featured')->label('Featured'),
        ];
    }

    public function tableColumns(): array
    {
        return [
            ColorColumn::make('color'),
            IconColumn::make('is_featured')->label('Featured')->boolean(),
        ];
    }

    public function navigationIcon(): mixed
    {
        return Heroicon::OutlinedHashtag;
    }
}
```

## `key()`

A static method returning a short, stable string. It's stored in every Term's `vocabulary` column and used to name
the Vocabulary in code, in Filament component names and in the management page's URL (`taxonomy/topics`).

Keys must be unique across all registered Vocabularies. Two classes with the same key throw an
`InvalidVocabularyException` when the second is registered.

You can rename the class or move it to another namespace at any time, because nothing stores the class name. Changing
the key, though, orphans every existing Term. If you really must, update the `vocabulary` column in a migration in the
same deploy.

## `label()`

The human name. It's the management page's title and navigation label, and appears in exception and validation
messages. The management page also derives the word for a single Term from it, singular and lowercased: "Topics"
gives "New topic" and "Edit topic". Pick a plural label that singularizes well in English.

## `isOpen()` and `isHierarchical()`

See [Defining Vocabularies](../usage/vocabularies.md#open-closed-and-hierarchical). Changing either under existing
data is allowed, and the data is left as it is; run [`taxonomy:check`](../usage/commands.md#taxonomycheck)
afterwards.

## `sortOrder()`

Returns a `Webwizardsusa\Taxonomy\TermSortOrder` case:

- **`TermSortOrder::Weight`**: by the `weight` column, then by name. Administrators set the weight by dragging on
  the management page. The default for Closed Vocabularies.
- **`TermSortOrder::Alphabetical`**: by name. The management table isn't reorderable. The default for Open
  Vocabularies.

The order applies to the `ordered()` scope, the management page, and the options of `TermSelect` and `TermsFilter`.
In a hierarchical Vocabulary it applies within each level of the tree. It doesn't change the order of a Record's
Terms, which always stay in the order they were given.

## `formComponents()`

Filament form components added to the end of the Term form on this Vocabulary's management page, after name, slug,
parent and description. They're bound to attributes on the configured Term model, so each one needs a column (or a
cast attribute) to save to.

Because every Vocabulary shares the one Term model, this is how you give a single Vocabulary its own fields: the
column exists on every Term, but only this Vocabulary's form shows it. See
[fields for one Vocabulary only](custom-term-model.md#fields-for-one-vocabulary-only).

Any Filament schema component works, including layout components such as `Section` or `Grid` wrapping fields.

## `tableColumns()`

Filament table columns added after name, slug and usage count on the management page's table. Only flat Vocabularies
have a table; a hierarchical Vocabulary's tree ignores these columns.

## `navigationIcon()`

Anything Filament accepts as a navigation icon: a `Filament\Support\Icons\Heroicon` case or an icon name string such
as `'heroicon-o-folder'`. Returning `null` keeps the default outlined tag.
