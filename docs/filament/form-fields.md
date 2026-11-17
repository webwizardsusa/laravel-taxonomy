---
title: Form fields
weight: 20
description: TermTagsInput for free tagging and TermSelect for choosing from Closed Vocabularies.
---

Two fields edit a Record's Terms: `TermTagsInput` for Open Vocabularies and `TermSelect` for Closed ones. Using the
wrong one throws a `LogicException` that names the right one ("TermTagsInput needs an open vocabulary, and
[categories] is closed: use TermSelect.").

```php
use Filament\Schemas\Schema;
use Webwizardsusa\Taxonomy\Filament\Forms\Components\TermSelect;
use Webwizardsusa\Taxonomy\Filament\Forms\Components\TermTagsInput;

public static function configure(Schema $schema): Schema
{
    return $schema->components([
        // ...
        TermTagsInput::make('tags')->maxItems(5),
        TermSelect::make('categories')->multiple(),
    ]);
}
```

## How they save

On a form whose model uses [`HasTerms`](../usage/tagging-records.md), both fields:

- load the Record's Terms in their Vocabulary when the form is filled,
- save them with `syncTerms()` after the Record is saved, so they work on create pages as well as edit pages,
- stay out of the data saved to the model's own columns. You don't need a `tags` column or a relationship name.

The field name only picks the Vocabulary. It doesn't have to match anything on the model.

Without such a model (a form with no model, or a model without `HasTerms`), the fields behave like their Filament
parents and their state is plain data: an array of names for `TermTagsInput`, an id (or array of ids) for
`TermSelect`. Nothing is created or synced; do it yourself:

```php
$data = $this->form->getState();

$post->syncTerms('tags', $data['tags']);
```

## TermTagsInput

A Filament `TagsInput` for an Open Vocabulary. Users type names; existing ones are offered as suggestions, and new
ones become Terms when the form saves.

```php
TermTagsInput::make('tags');
TermTagsInput::make('keywords')->vocabulary('tags');
```

- **Suggestions**: the Vocabulary's names in alphabetical order, up to `taxonomy.filament.suggestion_limit` (1000 by
  default). Users can always type a name that isn't suggested.
- **Matching**: names match ignoring case, so typing "php" tags the Record with the existing "PHP".
- **Order**: Terms are saved in the order the user entered them, and shown in that order elsewhere.

### Limiting the number of tags

```php
TermTagsInput::make('tags')->maxItems(5);
```

More than the limit fails validation. `maxItems()` also takes a closure, and `null` for no limit.

### When users may not create tags

If the Term policy denies `create` for the Vocabulary, the field still works but only accepts names that already
exist. A new name fails validation with "You can only choose existing tags." (using the Vocabulary's label,
lowercased). See [Authorization](authorization.md).

## TermSelect

A Filament `Select` for a Closed Vocabulary, flat or hierarchical. It's searchable, and lists Terms in tree order,
indented by depth:

```text
Cars
— Accessories
— — Roof racks
Phones
— Accessories
```

One Term by default. Add `->multiple()` for several:

```php
TermSelect::make('category')->vocabulary('categories');   // one Term
TermSelect::make('categories')->multiple();               // several
```

On a `HasTerms` model, a single select holds the Record's first Term in that Vocabulary, and saving replaces the
Record's Terms in the Vocabulary with the selected one (or none).

The select is an ordinary Filament `Select`, so `->required()`, `->preload()`, `->native(false)`, `->live()` and the
rest work as usual.

### Leaving out a subtree

`excludeSubtree()` leaves a Term and everything under it out of the options. The management page uses it for the
"Parent" field, so a Term can't be moved under itself. In a form for Terms, where `$record` is the Term being
edited:

```php
use Webwizardsusa\Taxonomy\Models\Term;

TermSelect::make('parent_id')
    ->vocabulary('categories')
    ->excludeSubtree(fn (?Term $record): ?Term => $record);
```

It takes a Term, `null`, or a closure returning either.
