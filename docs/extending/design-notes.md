---
title: Design notes
weight: 40
description: Why Vocabularies are code, why there's one Term model, and the other decisions that shape the package.
---

These notes explain the choices behind the package, so you can tell what it's meant to do when you extend it.

## Vocabularies are classes, not rows

Drupal stores vocabularies in the database and lets administrators create them in the UI. Here a Vocabulary is a PHP
class registered through config or `Taxonomy::register()`.

Its behaviour (open or closed, hierarchical, ordering, extra management fields) is code, so it lives in version
control alongside the code that depends on it. A database-stored Vocabulary would let administrators add Vocabularies
without a deploy, but its behaviour would have to be data-driven, and code that depends on a Vocabulary could find it
deleted from the UI.

What follows from that:

- The management pages manage Terms only. Adding a Vocabulary takes a deploy.
- Terms point to their Vocabulary by the key the class declares, never by class name. Renaming or moving the class
  doesn't cut its Terms off; changing the key does.

## One Term model for every Vocabulary

Only one Term model can be swapped in (`taxonomy.term_model`), and every Vocabulary uses it. Extra fields for a single
Vocabulary are therefore columns on that model, shown through the Vocabulary's own `formComponents()` and
`tableColumns()`. It keeps the schema to two tables and every query simple, at the cost of columns that some
Vocabularies leave empty.

## Definitions can change under data

A definition can change while Terms exist: a Closed Vocabulary turned open, a hierarchical one made flat, a class
removed. The package never rewrites data when that happens. Rewriting would mean guessing (which of two "Accessories"
survives a merge?), so the data stays as it is:

- `taxonomy:check` reports the Terms that break the new rules, and changes nothing.
- Terms for a key that's no longer registered stay until `taxonomy:prune` removes them.

## Uniqueness where it matters

Open Vocabularies need names unique ignoring case, because free tagging finds Terms by name. That's enforced by the
database, through a unique index on the Vocabulary and the lowercased name, so two requests tagging with the same new
name at once still end with one Term.

Closed Vocabularies only need names unique among siblings, so the same label can appear in different branches
(Cars › Accessories, Phones › Accessories). That rule is enforced by the model when saving, and the Term Path tells
the Terms apart.

## One pivot, in order

All Records share one polymorphic pivot table, with a `position` column. The order a Record's Terms were given in is
kept, because it often carries meaning (the first tag is the main one). The `HasTerms` methods always work on one
Vocabulary at a time, so a form field for tags can never disturb the Record's categories.

## Hierarchy through `parent_id`

A Term stores only its Parent Term. Descendant queries use a recursive CTE, which keeps writes cheap (moving a branch
updates one row) and reads a single query. The CTE and the tree flattening both stop on bad data with a cycle instead
of looping.

## Filament is optional

The core (Vocabularies, Terms, `HasTerms`, the commands) needs only Laravel. The Filament pieces live under
`Webwizardsusa\Taxonomy\Filament` and are only used when you reference them. Authorization follows Filament's
convention: without a Term policy, everything is allowed.

## Not included, on purpose

Translated Terms, multi-tenancy (Vocabularies per team), synonyms, and Terms defined in code are out of scope. Seed
starting Terms with your own seeders.
