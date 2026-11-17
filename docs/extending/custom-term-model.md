---
title: Your own Term model
weight: 20
description: Subclass the Term model to add columns, casts and behaviour, and give single Vocabularies their own fields.
---

There's one Term model for every Vocabulary. To add columns, casts, relations or methods, extend it and point the
config at your class.

## 1. Add the columns

Either add them to the published migration before you first migrate, or write a migration of your own. Read the table
name from config, so it follows `taxonomy.tables.terms`:

```bash
php artisan make:migration add_display_columns_to_taxonomy_terms_table
```

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('taxonomy.tables.terms'), function (Blueprint $table): void {
            $table->string('color')->nullable();
            $table->boolean('is_featured')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table(config('taxonomy.tables.terms'), function (Blueprint $table): void {
            $table->dropColumn(['color', 'is_featured']);
        });
    }
};
```

Make the columns nullable or give them defaults: they exist on every Term, in every Vocabulary.

## 2. Extend the model

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Webwizardsusa\Taxonomy\Models\Term as BaseTerm;

class Term extends BaseTerm
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'is_featured' => 'boolean',
        ];
    }

    #[Scope]
    protected function featured(Builder $query): void
    {
        $query->where('is_featured', true);
    }
}
```

Things to keep from the parent:

- **`booted()`**: the base model's `booted()` normalizes names, makes slugs, enforces the uniqueness and parent rules,
  and moves children up on delete. If you override it, call `parent::booted()` first:

  ```php
  protected static function booted(): void
  {
      parent::booted();

      static::saved(function (Term $term): void {
          cache()->forget("taxonomy.{$term->vocabulary}");
      });
  }
  ```

- **`casts()`**: merge with `parent::casts()`, as above.
- **`$hidden`**: the base model hides `name_key`, an internal column. If you set `$hidden`, include it.
- **The table name** comes from `taxonomy.tables.terms` through `getTable()`. Setting `$table` on your model has no
  effect; change the config instead.

Only `id` is guarded, so the new columns are mass-assignable.

## 3. Point the config at it

```php
// config/taxonomy.php
'term_model' => App\Models\Term::class,
```

The class must extend `Webwizardsusa\Taxonomy\Models\Term`, or `Taxonomy::termModel()` throws.

From then on the package uses your class everywhere: `$post->terms`, `termsIn()`, `$term->parent` and
`$term->children`, `Term::findByPath()` and the other finders (which return your model even when called on the base
class), `Taxonomy::newTermQuery()`, the commands and the Filament components.

```php
use App\Models\Term;

Term::featured()->inVocabulary('topics')->ordered('topics')->get();
$post->termsIn('topics')->first()->color;
```

> [!TIP]
> Create Terms through your class (`App\Models\Term::create()`) or `Taxonomy::newTermQuery()`, not through the base
> class: `Webwizardsusa\Taxonomy\Models\Term::create()` builds a base model, which won't run your model's casts or
> events.

If you register a Term policy, register it for your class (or rely on discovery of `App\Policies\TermPolicy`). See
[Authorization](../filament/authorization.md).

## Fields for one Vocabulary only

Every Vocabulary shares the one Term model, so a column you add exists on all Terms. To use it for just one
Vocabulary, show it only there, through that Vocabulary's
[`formComponents()`](vocabulary-options.md#formcomponents) and, for flat Vocabularies,
[`tableColumns()`](vocabulary-options.md#tablecolumns). With a `menu_title` column added next to `is_featured`:

```php
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Webwizardsusa\Taxonomy\Vocabulary;

class CategoriesVocabulary extends Vocabulary
{
    public static function key(): string
    {
        return 'categories';
    }

    public function label(): string
    {
        return 'Categories';
    }

    public function isHierarchical(): bool
    {
        return true;
    }

    public function formComponents(): array
    {
        return [
            TextInput::make('menu_title')->maxLength(60),
            Toggle::make('is_featured'),
        ];
    }
}
```

The Tags management page doesn't show these fields, and Tags' Terms simply leave the columns at their defaults.
