---
title: Management pages
weight: 10
description: Add a page per Vocabulary to a panel, with a sortable table for flat Vocabularies and a drag-and-drop tree for hierarchical ones.
---

## Adding the plugin

```php
use Filament\Panel;
use Webwizardsusa\Taxonomy\Filament\TaxonomyPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(TaxonomyPlugin::make());
}
```

This adds one page per Vocabulary at `taxonomy/{key}` in the panel (`/admin/taxonomy/tags`,
`/admin/taxonomy/categories`), each in the navigation under a "Taxonomy" group. The navigation item uses the
Vocabulary's `label()` and `navigationIcon()`, and the page title is the label too.

### Choosing the Vocabularies

Without `->vocabularies()`, the plugin adds a page for every class in `taxonomy.vocabularies`. Pass a list to choose,
using keys or classes:

```php
TaxonomyPlugin::make()->vocabularies(['tags', CategoriesVocabulary::class]);
```

> [!IMPORTANT]
> Panels are built before other packages boot, so the plugin can't see a Vocabulary that a package registers with
> `Taxonomy::register()`. List such Vocabularies here explicitly.

A key in the list that isn't registered when the page is used gives a 404 and no navigation item.

### Registering pages without the plugin

The plugin only registers `ManageTerms` once per Vocabulary. You can do that yourself, for example to add a single
Vocabulary to a panel alongside your own pages:

```php
use Webwizardsusa\Taxonomy\Filament\Pages\ManageTerms;

$panel->pages([
    ManageTerms::make('tags'),
    ManageTerms::make('categories'),
]);
```

The argument to `make()` is the Vocabulary key.

### Changing the navigation group

The group label comes from the `taxonomy::taxonomy.navigation_group` translation; there's no method to set it. To
rename it, publish the translations and edit `navigation_group` (see
[views and translations](../extending/views-and-translations.md)). The plugin has no option for the group, and the
pages have no navigation sort set.

## The Term form

Creating and editing a Term opens a modal with:

- **Name**: required, checked for uniqueness where the Vocabulary requires it.
- **Slug**: optional (made from the name when empty), letters, numbers, dashes and underscores, checked for
  uniqueness the same way.
- **Parent**: hierarchical Vocabularies only. A [`TermSelect`](form-fields.md#termselect) that leaves out the Term
  itself and everything under it.
- **Description**: optional.
- Any fields the Vocabulary's [`formComponents()`](../extending/vocabulary-options.md#formcomponents) returns.

Name and slug clashes show as validation errors on their field, before anything is saved. The weight isn't in the
form; it's set by dragging.

Action labels name the Terms after the Vocabulary's label, singular and lowercased: "New category", "Edit category".

## Flat Vocabularies: the table

Open Vocabularies and flat Closed ones get a table with:

- the name (searchable), the slug, and how many Records use each Term ("Used"),
- the Vocabulary's [`tableColumns()`](../extending/vocabulary-options.md#tablecolumns), after those,
- edit and delete actions on each row.

Rows are in the Vocabulary's order. When it sorts by `TermSortOrder::Weight` and the user may `reorder`, the table is
reorderable: dragging rows saves their new weights. Alphabetical Vocabularies can't be reordered.

## Hierarchical Vocabularies: the tree

Hierarchical Vocabularies get a tree instead of a table. Each Term shows its name and slug, with edit and delete
buttons, and its children indented below it.

When the user may `reorder`, each Term has a drag handle. Drag a Term:

- up or down among its siblings to reorder them,
- into another Term's list (every Term has one, even a leaf) to move it under that parent,
- to the top level to make it a top-level Term.

The new parent and the order of the new siblings are saved in one transaction. A move the rules refuse changes
nothing and shows a "That move isn't allowed" notification with the reason:

- moving a Term under itself or one of its descendants,
- moving it next to a sibling with the same name or slug.

The tree doesn't show `tableColumns()` or usage counts, and it has no search.

## Deleting Terms

Delete asks for confirmation, then deletes the Term the [usual way](../usage/terms.md#deleting-terms): its children
move up to its parent and the Records tagged with it lose it. If a child can't move up because its name is taken
there, the page shows the error and nothing is deleted.

## Access

Each page and action asks the Term policy, if you have one. See [Authorization](authorization.md).
