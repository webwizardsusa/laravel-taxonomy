---
title: Installation
weight: 30
description: Install the package, publish its config and migration, and create the taxonomy tables.
---

## Install the package

```bash
composer require webwizardsusa/laravel-taxonomy
```

The service provider and the `Taxonomy` facade alias are registered through package discovery.

## Publish the config

```bash
php artisan vendor:publish --tag=taxonomy-config
```

This writes `config/taxonomy.php`. See the [configuration reference](configuration.md) for each key.

## Publish and run the migration

The package never runs its migration from the vendor folder. You publish it into your application, where you can
change it before running it:

```bash
php artisan vendor:publish --tag=taxonomy-migrations
```

This creates `database/migrations/{timestamp}_create_taxonomy_tables.php`. Publishing it again finds the existing file
instead of adding a second one.

> [!IMPORTANT]
> The migration reads the table names from `taxonomy.tables.terms` and `taxonomy.tables.termables` when it runs, not
> when it's published. If you want table names other than `taxonomy_terms` and `taxonomy_termables`, set them in
> `config/taxonomy.php` **before** you migrate, and don't change them afterwards: the model and the queries read the
> same config at runtime.

Then run it:

```bash
php artisan migrate
```

It creates two tables:

- **`taxonomy_terms`**: `id`, `vocabulary` (the Vocabulary key), `parent_id` (nullable, deleting a parent row sets it
  to null), `name`, `name_key` (the lowercased name, for Open Vocabularies only), `slug`, `description` (nullable),
  `weight` (default 0) and timestamps. A unique index on `vocabulary` and `name_key` enforces case-insensitive unique
  names in Open Vocabularies at the database level.
- **`taxonomy_termables`**: the pivot between Terms and Records, with `term_id` (deleted with its Term), the
  polymorphic `termable_type` and `termable_id`, and a `position` that keeps the order Terms were given in.

If you want extra columns on Terms, you can add them to the published migration now, or in a migration of your own
later. See [your own Term model](extending/custom-term-model.md).

## Register your Vocabularies

Add your Vocabulary classes to `config/taxonomy.php`:

```php
'vocabularies' => [
    App\Taxonomy\TagsVocabulary::class,
    App\Taxonomy\CategoriesVocabulary::class,
],
```

[Defining Vocabularies](usage/vocabularies.md) shows how to write them.

## Add the Filament plugin (optional)

If you use Filament, add the plugin to a panel to get a management page for each Vocabulary:

```php
use Webwizardsusa\Taxonomy\Filament\TaxonomyPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(TaxonomyPlugin::make());
}
```

The form fields, column, entry and filter work without the plugin. See [Filament](filament/_index.md).

## Publish translations and views (optional)

You only need these to change the package's wording or markup:

```bash
php artisan vendor:publish --tag=taxonomy-translations
php artisan vendor:publish --tag=taxonomy-views
```

Translations land in `lang/vendor/taxonomy`, views in `resources/views/vendor/taxonomy`. See
[views and translations](extending/views-and-translations.md).

## Check the setup

`taxonomy:check` reports Terms that break their Vocabulary's rules. On a fresh install it should report nothing:

```bash
php artisan taxonomy:check
```
