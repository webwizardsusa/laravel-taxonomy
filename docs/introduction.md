---
title: Introduction
weight: 10
description: What Laravel Taxonomy does, a short example, and what it needs to run.
---

Laravel Taxonomy lets you tag any Eloquent model with Terms drawn from Vocabularies, the way Drupal's taxonomy does.

- An **Open Vocabulary** is for free tagging: typing a new name while tagging a Record creates the Term. Think "Tags".
- A **Closed Vocabulary** is for categories that administrators curate. Records can only take Terms that already
  exist. A Closed Vocabulary can also be **hierarchical**, so Terms sit under a Parent Term (Cars › Accessories).

Vocabularies are PHP classes in your code. Only Terms live in the database. One pivot table links Terms to every kind
of Record, and keeps the order the Terms were given in.

Filament is optional. With it you also get a tag input, a Term select, a table column, an infolist entry, a table
filter, and a management page for each Vocabulary (a table for flat Vocabularies, a drag-and-drop tree for
hierarchical ones).

## A short example

Define a Vocabulary:

```php
namespace App\Taxonomy;

use Webwizardsusa\Taxonomy\Vocabulary;

class TagsVocabulary extends Vocabulary
{
    public static function key(): string
    {
        return 'tags';
    }

    public function label(): string
    {
        return 'Tags';
    }

    public function isOpen(): bool
    {
        return true;
    }
}
```

Register it in `config/taxonomy.php`:

```php
'vocabularies' => [
    App\Taxonomy\TagsVocabulary::class,
],
```

Add the `HasTerms` trait to a model, then tag and query Records:

```php
use Illuminate\Database\Eloquent\Model;
use Webwizardsusa\Taxonomy\Concerns\HasTerms;

class Post extends Model
{
    use HasTerms;
}
```

```php
$post->syncTerms('tags', ['PHP', 'Laravel']);   // creates "Laravel" if it doesn't exist yet

$post->termsIn('tags')->pluck('name');          // ['PHP', 'Laravel']

Post::withAnyTerms(['php'], 'tags')->get();
```

In a Filament resource:

```php
use Webwizardsusa\Taxonomy\Filament\Forms\Components\TermTagsInput;
use Webwizardsusa\Taxonomy\Filament\Tables\Columns\TermsColumn;

TermTagsInput::make('tags');   // in the form
TermsColumn::make('tags');     // in the table
```

## Requirements

- PHP 8.3 or later
- Laravel 12 or 13
- A database with recursive CTE support for the descendant queries: MySQL 8, MariaDB 10.2+, PostgreSQL or SQLite
- Optional: Filament 5, for the fields, column, entry, filter and management pages

## What isn't included

Translated Terms, multi-tenancy (Vocabularies per team), synonyms, and Terms defined in code. Use your own seeders to
create starting Terms.

## Where to go next

- [Concepts](concepts.md) explains the vocabulary of the package itself.
- [Installation](installation.md) gets it running.
- [Defining Vocabularies](usage/vocabularies.md) and [Tagging Records](usage/tagging-records.md) cover daily use.
- [Filament](filament/_index.md) covers the admin side.
