---
title: Tables and infolists
weight: 30
description: Show a Record's Terms with TermsColumn and TermsEntry, and filter tables with TermsFilter.
---

## TermsColumn

Shows a Record's Terms in one Vocabulary as badges, in the order they were given:

```php
use Filament\Tables\Table;
use Webwizardsusa\Taxonomy\Filament\Tables\Columns\TermsColumn;

public static function configure(Table $table): Table
{
    return $table->columns([
        // ...
        TermsColumn::make('tags'),
        TermsColumn::make('section')->vocabulary('categories'),
    ]);
}
```

The column eager loads the Records' `terms` relation, so the whole table's Terms come in one query, however many
`TermsColumn`s it has. It is a Filament `TextColumn`, so `->limitList()`, `->color()`, `->toggleable()` and the like
work as usual.

The table's model must use `HasTerms`.

## TermsEntry

The infolist counterpart: a Record's Terms in one Vocabulary as badges, in order.

```php
use Webwizardsusa\Taxonomy\Filament\Infolists\Components\TermsEntry;

TermsEntry::make('tags')->placeholder('No tags');
```

It is a Filament `TextEntry`. A Record without Terms in the Vocabulary, or without `HasTerms`, shows the placeholder.

## TermsFilter

A table filter on a Vocabulary's Terms. It's a searchable Filament `SelectFilter` whose options are listed in tree
order and indented by depth, like [`TermSelect`](form-fields.md#termselect). It works with Open and Closed
Vocabularies.

```php
use Webwizardsusa\Taxonomy\Filament\Tables\Filters\TermsFilter;

$table->filters([
    TermsFilter::make('categories'),
    TermsFilter::make('tags')->multiple(),
]);
```

It applies [`withAnyTerms()`](../usage/querying-records.md):

- **Descendants**: in a hierarchical Vocabulary, choosing a Term also finds Records with a Term under it. Turn that
  off with `->includingDescendants(false)`. Flat Vocabularies have no descendants, so the setting has no effect there.
- **Several Terms**: with `->multiple()`, Records with any of the chosen Terms match.

```php
TermsFilter::make('categories')->includingDescendants(false);   // exactly the chosen category
```
