---
title: Filament
weight: 60
---

Filament is optional. When `filament/filament` (version 5) is installed, the package adds:

- a [management page](management-pages.md) for each Vocabulary, through the `TaxonomyPlugin`,
- [form fields](form-fields.md): `TermTagsInput` for Open Vocabularies and `TermSelect` for Closed ones,
- a [table column, an infolist entry and a table filter](tables-and-infolists.md): `TermsColumn`, `TermsEntry` and
  `TermsFilter`,
- [authorization](authorization.md) through a policy on the Term model.

The fields, column, entry and filter work in any panel, with or without the plugin.

## Choosing the Vocabulary

Every component works with one Vocabulary. By default it's the Vocabulary whose key matches the component's name:

```php
TermTagsInput::make('tags');                              // the "tags" Vocabulary
TermSelect::make('category')->vocabulary('categories');  // a name that isn't a key
```

`->vocabulary()` takes a key, a Vocabulary class, an instance or a closure. When the name matches no key and no
Vocabulary is given, the component throws a `LogicException`: "TermSelect [category] needs a vocabulary: call
->vocabulary()."
