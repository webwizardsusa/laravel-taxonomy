# Laravel Taxonomy

Drupal-style taxonomy for Laravel. Records are tagged with **Terms** from **Vocabularies**. A Vocabulary is either
**open**, for free tagging, where typing a new name creates the Term, or **closed**, for categories that administrators
curate, and closed Vocabularies can be hierarchical. Vocabularies are classes in your code; only Terms live in the
database. Filament is optional: with it you also get a tag input, a Term select, a table column, an infolist entry, a
table filter and a management page for each Vocabulary.

Requires PHP 8.3+ and Laravel 12 or 13 (Filament 5 for the optional Filament pieces).

```bash
composer require webwizardsusa/laravel-taxonomy
php artisan vendor:publish --tag=taxonomy-config
php artisan vendor:publish --tag=taxonomy-migrations
php artisan migrate
```

## Usage

```php
use Webwizardsusa\Taxonomy\Vocabulary;

class TagsVocabulary extends Vocabulary
{
    public static function key(): string
    {
        return 'tags';
    }

    public function label(): string
    {
        return 'Tags';
    }

    public function isOpen(): bool
    {
        return true;
    }
}
```

List it in `config/taxonomy.php` (`'vocabularies' => [TagsVocabulary::class]`), add the `HasTerms` trait to a model,
and tag and query Records:

```php
$post->syncTerms('tags', ['PHP', 'Laravel']);

Post::withAnyTerms(['php'], 'tags')->get();
```

## Documentation

Read the full documentation in the [`docs` folder](https://github.com/webwizardsusa/laravel-taxonomy/tree/main/docs).

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
