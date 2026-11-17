---
title: Tagging Records
weight: 40
description: Add the HasTerms trait to a model, then read, sync, attach and detach its Terms.
---

Any Eloquent model becomes a Record by using the `HasTerms` trait. No migration is needed on the model's table: every
Record shares the `taxonomy_termables` pivot.

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Webwizardsusa\Taxonomy\Concerns\HasTerms;

class Post extends Model
{
    use HasTerms;
}
```

A Record can take any number of Terms from any number of Vocabularies. The package doesn't limit how many; limit it
in your validation or with [`TermTagsInput::maxItems()`](../filament/form-fields.md#limiting-the-number-of-tags).

## Reading Terms

```php
$post->terms;                 // every Term, in every Vocabulary
$post->termsIn('tags');       // the Terms in one Vocabulary
```

Both return Terms in the order they were given. `terms()` is an ordinary `MorphToMany` relation, ordered by the
pivot's `position`, so you can eager load it:

```php
$posts = Post::with('terms')->get();

foreach ($posts as $post) {
    $post->termsIn('tags');   // uses the loaded relation: no extra query
}
```

`termsIn()` reads from the loaded `terms` relation when there is one, and queries otherwise.

## Changing Terms

Each method changes **one Vocabulary only**. Syncing tags leaves the Record's categories alone.

```php
$post->syncTerms('tags', ['PHP', 'Laravel']);   // now exactly PHP and Laravel, in this order
$post->attachTerms('tags', ['Pest']);           // PHP, Laravel, Pest
$post->detachTerms('tags', ['laravel']);        // PHP, Pest

$post->syncTerms('categories', [$cars]);        // Closed: models, ids or Term Paths
$post->attachTerms('categories', ['cars/accessories']);
$post->detachTerms('categories', [$accessories->id]);
```

- **`syncTerms($vocabulary, $terms)`** makes the given Terms the Record's only Terms in that Vocabulary, in the
  given order.
- **`attachTerms($vocabulary, $terms)`** appends the given Terms after the current ones, skipping those the Record
  already has.
- **`detachTerms($vocabulary, $terms)`** removes the given Terms. Terms the Record doesn't have are ignored.

`syncTerms()` and `attachTerms()` run in a transaction, and all three reset the loaded `terms` relation, so the next
read is fresh.

### What you can pass

| Item | Open Vocabulary | Closed Vocabulary |
| --- | --- | --- |
| A Term model | Yes | Yes |
| An integer id | Yes | Yes |
| A numeric string (`'12'`) | A **name** | An id |
| Any other string | A name; new names create Terms | A [Term Path](terms.md#slugs-and-term-paths) (`'cars/accessories'`) |

All three methods read what you pass the same way. `syncTerms()` and `attachTerms()` also:

- skip blank names and repeated Terms,
- match names ignoring case, so `'php'` attaches the existing "PHP",
- throw an `InvalidArgumentException` for an id that doesn't exist, a Term Path that matches no Term, or a Term from
  another Vocabulary.

`detachTerms()` is more lenient: it creates nothing, and skips anything it can't find.

> [!NOTE]
> Creating tags by name through `syncTerms()` and `attachTerms()` is not subject to the Term policy. The policy's
> `create` ability is only checked by the Filament [`TermTagsInput`](../filament/form-fields.md#termtagsinput).

## Deleting Records

When a Record is deleted, its rows in the pivot are removed. Its Terms stay; only the links go.

For models using `SoftDeletes`, a soft delete keeps the links, so a restored Record still has its Terms. Only a force
delete removes them.

Like any model event, this only runs for `$post->delete()`, not for query-builder deletes. The pivot has no foreign
key to your Records' tables (it's polymorphic), so a mass delete leaves orphaned rows behind. Delete through the
models, or remove the pivot rows yourself.

## Querying by Term

Finding Records by their Terms (`withAnyTerms`, `withAllTerms`, `withoutTerms`) has its own page:
[Querying Records](querying-records.md).
