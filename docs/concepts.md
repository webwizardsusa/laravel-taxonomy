---
title: Concepts
weight: 20
description: The terms these docs use, how Open and Closed Vocabularies differ, and why Vocabularies are defined in code.
---

The package uses a small, fixed set of words. The docs, the code and the error messages all use them the same way.

## Glossary

**Vocabulary**: A named set of Terms, such as "Tags" or "Categories". A developer defines it as a class and registers
it with the application. Vocabularies can't be created or deleted at runtime.

**Term**: A single label within one Vocabulary that Records can be tagged with. A Term belongs to exactly one
Vocabulary. "Tag" is fine for a Term in an Open Vocabulary.

**Open Vocabulary**: A Vocabulary whose Terms can be created by typing them while tagging a Record (free tagging). It
is flat, and Term names are unique within it, ignoring case.

**Closed Vocabulary**: A Vocabulary whose Terms can only be chosen from those an administrator already created
(categories). Two Terms may share a name, but not under the same Parent Term.

**Parent Term**: The single Term that a Term sits under in a hierarchical Vocabulary. Parent and child always share a
Vocabulary.

**Term Path**: The slugs from the top-level Term down to a Term, such as `cars/accessories`. It identifies a Term
within its Vocabulary.

**Record**: Any application model that can be tagged with Terms, by using the `HasTerms` trait.

## Open and Closed Vocabularies

The choice between open and closed decides how Terms come into existence and how strict their names are.

| | Open Vocabulary | Closed Vocabulary |
| --- | --- | --- |
| Typical use | Tags | Categories, sections, regions |
| New Terms | Created by typing a name while tagging | Created explicitly (management page, seeder or code), never by typing |
| Name uniqueness | Across the whole Vocabulary, ignoring case | Among siblings only (same Parent Term), ignoring case |
| Slug uniqueness | Across the whole Vocabulary | Among siblings only |
| Hierarchy | Never: always flat | Optional |
| Default order | Alphabetical | By weight, then name |
| Filament field | `TermTagsInput` | `TermSelect` |

Because names in an Open Vocabulary are unique ignoring case, typing "php" while tagging finds the existing "PHP"
instead of creating a second Term.

In a Closed Vocabulary, Cars › Accessories and Phones › Accessories can both exist. You tell them apart by their Term
Path: `cars/accessories` and `phones/accessories`.

## Hierarchical Vocabularies

A hierarchical Vocabulary lets each Term have one Parent Term. Only Closed Vocabularies can be hierarchical: an open,
hierarchical Vocabulary is refused when it is registered. Free tagging creates Terms with no place in a tree, so the
combination makes no sense.

In a hierarchical Vocabulary:

- A Term has at most one Parent Term, in the same Vocabulary.
- A Term can't be moved under itself or one of its descendants.
- Deleting a Term moves its children up to its own Parent Term.
- Queries can include descendants, so asking for Records in "Cars" also finds Records in "Cars › Accessories".

A Closed Vocabulary that isn't hierarchical is a flat, curated list, useful for things like "Topics" or "Regions"
where administrators control the choices but nesting isn't needed.

## Why Vocabularies are code

In Drupal, administrators create vocabularies in the admin UI. Here a Vocabulary is a PHP class, registered through
config or `Taxonomy::register()`.

That keeps a Vocabulary's behaviour (open or closed, hierarchical, ordering, extra management fields) in version
control, next to the code that depends on it. Code that expects a `categories` Vocabulary can't find it deleted from
an admin screen, and behaviour doesn't have to be stored as data.

Terms refer to their Vocabulary by the **key** the class declares, never by class name. You can rename or move the
class freely, but you must never change the key once Terms exist.

The consequences:

- The management pages manage Terms only. Adding a Vocabulary takes a deploy.
- There is one Term model for every Vocabulary. Extra fields for a single Vocabulary are columns on that model,
  shown through that Vocabulary's own form fields and table columns (see
  [per-Vocabulary fields](extending/custom-term-model.md#fields-for-one-vocabulary-only)).
- A definition can change under existing data, such as turning a Closed Vocabulary open or a hierarchical one flat.
  The data is left as it is, and [`taxonomy:check`](usage/commands.md#taxonomycheck) reports the conflicts.
- Terms for a key that's no longer registered stay in place until
  [`taxonomy:prune`](usage/commands.md#taxonomyprune) removes them.
