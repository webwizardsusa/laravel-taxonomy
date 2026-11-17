---
title: Views and translations
weight: 30
description: Publish and override the management page views and the package's strings, including the navigation group.
---

## Translations

```bash
php artisan vendor:publish --tag=taxonomy-translations
```

This copies the English strings to `lang/vendor/taxonomy/en/taxonomy.php`. Edit them there, or add another locale
next to `en` (for example `lang/vendor/taxonomy/de/taxonomy.php`). Laravel uses your copy over the package's.

| Key | Default | Used for |
| --- | --- | --- |
| `navigation_group` | Taxonomy | The navigation group of the management pages, unless the plugin sets one |
| `fields.name` | Name | The Term form, the table column, validation messages |
| `fields.slug` | Slug | The same |
| `fields.description` | Description | The Term form |
| `fields.parent` | Parent | The Term form, in hierarchical Vocabularies |
| `fields.usage` | Used | The usage count column in flat Vocabularies' tables |
| `validation.existing_only` | You can only choose existing :vocabulary. | `TermTagsInput`, when the policy denies `create` |
| `tree.empty` | No terms yet. | An empty tree |
| `tree.move` | Drag to move | The drag handle's accessible label |
| `tree.move_refused` | That move isn't allowed | The notification for a refused tree move |

`:vocabulary` is the Vocabulary's label, lowercased.

### Renaming the navigation group

The management pages take their navigation group from `navigation_group` unless
[`TaxonomyPlugin::navigationGroup()`](../filament/management-pages.md#changing-the-navigation-group-and-sort) sets
one; use the plugin option for a single panel, and the translation to rename the group everywhere or per locale. To
file them under "Content" instead of "Taxonomy":

```php
// lang/vendor/taxonomy/en/taxonomy.php
return [
    'navigation_group' => 'Content',
    // ...
];
```

Pages of your own in a group with the same label share it.

The labels of Vocabularies and Terms themselves (`label()`, Term names) aren't translation keys. A Vocabulary's
`label()` can return `__('...')` if you need it translated.

## Views

```bash
php artisan vendor:publish --tag=taxonomy-views
```

This copies the views to `resources/views/vendor/taxonomy`:

- `filament/pages/manage-terms.blade.php`: the management page. It shows the tree for hierarchical Vocabularies and
  the table for flat ones.
- `filament/partials/term-tree-branch.blade.php`: one level of the tree, included recursively for each Term's
  children.

Laravel uses your copies over the package's. Delete the files you don't change, so you keep getting fixes to the
others with package updates.

> [!WARNING]
> The tree's drag and drop depends on its markup. If you change `term-tree-branch.blade.php`, keep:
>
> - the `x-sortable`, `x-sortable-group`, `x-sortable-item` and `x-sortable-handle` attributes,
> - a child list (with `data-parent-id`) under every Term, even an empty one, so Terms can be dropped under a leaf,
> - the `x-on:end` call to `$wire.moveTerm(termId, parentId, orderedSiblingIds)`,
> - the `wire:key` on the tree wrapper in `manage-terms.blade.php`, which includes `treeVersion` so the tree is
>   rendered afresh after each change, and the `<x-filament-actions::modals />` that the tree's edit and delete
>   actions need.
