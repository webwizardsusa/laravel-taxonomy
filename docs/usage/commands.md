---
title: Commands
weight: 60
description: Check Terms against their Vocabulary's current rules, and prune Terms of removed Vocabularies and unused tags.
---

Vocabularies live in code and Terms in the database, so the two can drift apart. You might turn a Closed Vocabulary
open, make a hierarchical one flat, or delete a Vocabulary class. The package never rewrites existing data when a
definition changes. These two commands help you find and clean up what no longer fits.

## `taxonomy:check`

```bash
php artisan taxonomy:check
```

Reports every Term that breaks its Vocabulary's current rules:

| Problem | Typical cause |
| --- | --- |
| The vocabulary is not registered. | The class was removed, or its key changed |
| Has a parent, but the vocabulary is flat. | A hierarchical Vocabulary was made flat |
| Name [...] is repeated in the vocabulary. | A Closed Vocabulary was made open: names that were unique among siblings now clash |
| Slug [...] is repeated in the vocabulary. | The same, for slugs |
| Name [...] is repeated among its siblings. | Data written around the model, such as raw inserts |

Names are compared ignoring case. For each group of duplicates, the first Term (lowest id) is left out of the report
and the others are listed, so the report shows which ones to rename or merge.

The output is a table of Vocabulary key, Term id and problem. The command changes nothing. It exits with `0` when
everything is fine ("No problems found.") and `1` when it finds a problem, so you can run it in CI or after a deploy:

```bash
php artisan taxonomy:check || echo "Fix the taxonomy data before going on"
```

### Turning a Closed Vocabulary open

Open Vocabularies match names through a stored lowercased copy of the name (`name_key`), which is only filled in for
Terms saved while their Vocabulary is open. Terms created while it was closed don't have it until they're saved
again, and `taxonomy:check` doesn't report that. Until then, tagging by one of their names can fail with a
`DuplicateTermException` instead of finding the Term.

After switching, fix the duplicates `taxonomy:check` reports, then save each Term once so the key is filled in:

```php
use Webwizardsusa\Taxonomy\Facades\Taxonomy;

Taxonomy::newTermQuery()->inVocabulary('topics')->each(fn ($term) => $term->save());
```

## `taxonomy:prune`

```bash
php artisan taxonomy:prune
```

Deletes the Terms of Vocabularies that are no longer registered, along with their pivot rows.

| Option | Effect |
| --- | --- |
| `--unused` | Also delete Terms in Open Vocabularies that no Record uses |
| `--pretend` | List the Terms that would be deleted (Vocabulary, id, name), and delete nothing |

```bash
php artisan taxonomy:prune --unused --pretend   # see what would go
php artisan taxonomy:prune --unused             # delete it
```

`--unused` never deletes Terms of Closed Vocabularies, even unused ones: an administrator created those on purpose,
and an empty category is still a category.

> [!WARNING]
> Pruning deletes rows directly in chunks of 500, without loading models or firing model events. If your own Term
> model reacts to `deleting` or `deleted`, those listeners don't run.

You can schedule the unused-tag cleanup:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('taxonomy:prune --unused')->weekly();
```
