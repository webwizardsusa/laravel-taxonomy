---
title: Querying Records
weight: 50
description: Find Records with any, all or none of a set of Terms, optionally including the Terms under them.
---

The `HasTerms` trait adds three query scopes to your model.

```php
Post::withAnyTerms(['php', 'laravel'], 'tags')->get();   // at least one of them
Post::withAllTerms([$php, $laravel])->get();             // every one of them
Post::withoutTerms(['php'], 'tags')->get();              // none of them
```

They chain like any scope:

```php
Post::query()
    ->where('published', true)
    ->withAnyTerms($category, includingDescendants: true)
    ->withoutTerms(['draft-ideas'], 'tags')
    ->latest()
    ->paginate();
```

## Signature

All three take the same arguments:

```php
withAnyTerms($terms, $vocabulary = null, bool $includingDescendants = false)
```

- **`$terms`**: one item or a list. Each item is a Term model, an integer id, or a string.
- **`$vocabulary`**: a key, class or instance. Required as soon as `$terms` contains a string, or the scope throws
  an `InvalidArgumentException` ("Pass a vocabulary to find Terms by slug").
- **`$includingDescendants`**: also match the Terms under each given Term. Use it with hierarchical Vocabularies.

### Strings are slugs and Term Paths

A string is looked up with [`Term::findByPath()`](terms.md#finding-terms):

- In a flat Vocabulary, including every Open Vocabulary, it's a **slug**, not a name. `'php'` finds the tag "PHP"
  because its slug is `php`; "Laravel Tips" has the slug `laravel-tips`.
- In a hierarchical Vocabulary, it's a **Term Path**. A bare slug like `'accessories'` only finds a top-level Term;
  for a nested one, pass the path: `'cars/accessories'`.

A string that matches no Term counts as a Term no Record has. `withAllTerms(['php', 'missing'], 'tags')` finds
nothing, and `withoutTerms(['missing'], 'tags')` excludes nothing.

The `$vocabulary` argument is only used to resolve strings. Term models and ids are taken as they are, from whichever
Vocabulary they belong to.

## The three scopes

**`withAnyTerms()`** finds Records with at least one of the Terms. An empty list matches no Records.

**`withAllTerms()`** finds Records with every one of the Terms. An empty list adds no condition.

**`withoutTerms()`** finds Records with none of the Terms. An empty list adds no condition. Records with no Terms at
all match.

## Including descendants

In a hierarchical Vocabulary you usually want "everything in Cars" to include Cars › Accessories and anything deeper:

```php
$cars = Term::findByPath('categories', 'cars');

Post::withAnyTerms($cars, includingDescendants: true)->get();       // Cars, Cars › Accessories, …
Post::withAllTerms([$cars, $phones], includingDescendants: true);   // something under Cars and something under Phones
Post::withoutTerms($cars, includingDescendants: true);              // nothing anywhere under Cars
```

With `withAllTerms()`, each given Term is satisfied by itself or any Term below it, separately.

Descendants are found with a recursive CTE inside the query, so there's one query no matter how deep the tree is.
That needs MySQL 8, MariaDB 10.2+, PostgreSQL or SQLite. The CTE stops even on bad data with a cycle in `parent_id`.

## Filament

In Filament tables, the [`TermsFilter`](../filament/tables-and-infolists.md#termsfilter) applies `withAnyTerms()` for
you, with descendants included by default.
