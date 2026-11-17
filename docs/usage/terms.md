---
title: Terms
weight: 30
description: Create, find and delete Terms, and the rules for their names, slugs and Parent Terms.
---

Terms are rows in the terms table, handled by the `Webwizardsusa\Taxonomy\Models\Term` model (or
[your own subclass](../extending/custom-term-model.md)). Each Term has:

| Attribute | Notes |
| --- | --- |
| `vocabulary` | The Vocabulary key. `$term->getVocabulary()` gives the instance. |
| `name` | Trimmed, with inner whitespace collapsed, on every save. |
| `slug` | Made from the name when left empty. |
| `description` | Optional text. |
| `weight` | Manual order, `0` by default. Used when the Vocabulary sorts by weight. |
| `parent_id` | The Parent Term, in hierarchical Vocabularies only. |

Only `id` is guarded, so you can mass-assign all of them.

## Creating Terms

```php
use Webwizardsusa\Taxonomy\Models\Term;

$cars = Term::create(['vocabulary' => 'categories', 'name' => 'Cars']);

$accessories = Term::create([
    'vocabulary' => 'categories',
    'name' => 'Accessories',
    'parent_id' => $cars->id,
    'description' => 'Roof racks, mats, chargers',
]);
```

The `vocabulary` must be a registered key; saving a Term of an unknown Vocabulary throws an
`UnknownVocabularyException`.

> [!TIP]
> If you configured your own Term model, create Terms through it (`App\Models\Term::create()`) or through
> `Taxonomy::newTermQuery()->create()`, so your model's own events and casts apply.

For Open Vocabularies, `findOrCreateByName()` is usually what you want:

```php
$php = Term::findOrCreateByName('tags', 'PHP');
$same = Term::findOrCreateByName('tags', '  php ');   // the same Term: names match ignoring case
```

It normalizes the name, finds the Term whose name matches ignoring case, and creates it when there isn't one. Two
requests creating the same name at once end up with one Term, thanks to the unique index on the lowercased name.
Called on a Closed Vocabulary, it throws an `InvalidArgumentException`: Closed Vocabularies don't take Terms by name.

## Slugs and Term Paths

A slug left empty is made from the name with `Str::slug()`. If that slug is taken, a number is added: `accessories`,
`accessories-2`, `accessories-3`. A name that slugs to nothing (only punctuation, say) gets `term`.

A slug you give yourself must be free, or saving throws a `DuplicateTermException`. Renaming a Term doesn't change its
slug; set `slug` yourself if you want a new one.

A Term's **Term Path** is the slugs from the top-level Term down to it:

```php
$accessories->path();   // 'cars/accessories'
```

`path()` loads each ancestor in turn, so it runs one query per level. In a flat Vocabulary the path is just the slug.

## Finding Terms

```php
Term::findBySlug('tags', 'php');                                  // ?Term
Term::findByPath('categories', 'cars/accessories');                // ?Term
Term::findByPathOrFail('categories', 'cars/accessories');          // Term, or ModelNotFoundException
Term::findOrCreateByName('tags', 'PHP');                           // Open Vocabularies only
```

All of them return instances of the configured Term model.

- **`findBySlug()`** looks at the whole of a flat Vocabulary. In a hierarchical one, where the same slug can exist
  under different parents, it only matches top-level Terms, as a bare slug does in the
  [query scopes](querying-records.md#strings-are-slugs-and-term-paths). Use `findByPath()` for nested Terms.
- **`findByPath()`** walks the path from the top level down. Leading and trailing slashes are ignored. In a flat
  Vocabulary only a single slug matches; a path with more segments returns `null`.
- **`findByPathOrFail()`** throws Laravel's `ModelNotFoundException`, which renders as a 404 in a controller:

```php
Route::get('/categories/{path}', function (string $path) {
    $category = Term::findByPathOrFail('categories', $path);

    return view('categories.show', [
        'category' => $category,
        'posts' => Post::withAnyTerms($category, includingDescendants: true)->paginate(),
    ]);
})->where('path', '.*');
```

### Relations and scopes

```php
$term->parent;      // the Parent Term, or null
$term->children;    // the Terms directly under it

Term::query()->inVocabulary('categories')->ordered('categories')->get();
```

- **`inVocabulary($vocabulary)`** limits a query to one Vocabulary.
- **`ordered($vocabulary)`** sorts by the Vocabulary's order: weight, then name, then id for
  `TermSortOrder::Weight`; name, then id for `TermSortOrder::Alphabetical`. It takes the Vocabulary as an argument
  because the order is part of its definition.

## Uniqueness rules

Names and slugs must be unique **where they're compared**:

- In an **Open Vocabulary**, across the whole Vocabulary.
- In a **Closed Vocabulary**, among siblings: Terms with the same Parent Term (or, at the top level, other top-level
  Terms). Cars › Accessories and Phones › Accessories can coexist; two top-level "Cars" can't.

Names are compared ignoring case and after normalizing whitespace. The checks run whenever a Term is created, or its
name, slug, parent or Vocabulary changes.

A clash throws a `DuplicateTermException`. Its `attribute` property says which field clashed, so you can show the
error on the right input:

```php
use Webwizardsusa\Taxonomy\Exceptions\DuplicateTermException;

try {
    $term->update($request->validated());
} catch (DuplicateTermException $exception) {
    return back()->withErrors([$exception->attribute => $exception->getMessage()]);
}
```

To check before saving, as a form validator would, use the two public helpers. They work on unsaved Terms too:

```php
$probe = Taxonomy::newTermQuery()->newModelInstance([
    'vocabulary' => 'categories',
    'parent_id' => $cars->id,
    'name' => $request->input('name'),
    'slug' => $request->input('slug'),
]);

$probe->isNameTaken();   // another sibling already has this name
$probe->isSlugTaken();   // another sibling already has this slug (false when the slug is empty)
```

`Term::normalizeName($name)` gives you the name the way it will be stored: trimmed, with runs of whitespace collapsed
to one space.

### Parent Terms

Setting `parent_id` throws an `InvalidParentException` when:

- the Vocabulary is flat,
- the parent doesn't exist or belongs to another Vocabulary,
- the parent is the Term itself or one of its descendants.

## Deleting Terms

```php
$cars->delete();
```

Deleting a Term:

1. moves its children up to its own Parent Term (to the top level if it had none),
2. removes it from every Record tagged with it; the Records keep their other Terms,
3. deletes the row.

All of it runs in one transaction. Moving a child up is a save like any other, so it can fail: if Cars has a child
"Accessories" and there's already a top-level "Accessories", deleting Cars throws a `DuplicateTermException` and
nothing changes. Rename or move the child first.

> [!NOTE]
> This only happens through the model's `delete()`. A query-builder delete such as
> `Term::query()->where(...)->delete()` skips it: the database's foreign keys then remove the pivot rows and set the
> children's `parent_id` to null, without any uniqueness checks.
