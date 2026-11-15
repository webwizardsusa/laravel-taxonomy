# Vocabularies are classes registered in code, not database rows

Unlike Drupal, where vocabularies are created in the admin UI, a Vocabulary here is a PHP class registered through config or `Taxonomy::register()`. That way its behaviour (open or closed, hierarchical, ordering, extra management fields) lives in version control alongside the code that depends on it. Only Terms are stored in the database, and they refer to their Vocabulary by the key the class declares, never by class name, so renaming or moving the class doesn't cut its Terms off.

## Considered Options

- **Database-stored Vocabularies with machine names**: admins could add Vocabularies without deploying, but behaviour would have to be data-driven, and code that depends on a Vocabulary could find it deleted from the UI.

## Consequences

- The management pages manage Terms only. Adding a Vocabulary takes a deploy.
- Because only one Term model can be swapped, extra fields for each Vocabulary are columns on that model, exposed through the Vocabulary class's own form fields and table columns.
- A definition can change under existing data (Closed to Open, hierarchical to flat). The data is left as it is, and `taxonomy:check` reports the conflicts.
- Terms for a key that's no longer registered stay in place until `taxonomy:prune` removes them.
