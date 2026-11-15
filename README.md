# Laravel Taxonomy

Drupal-style taxonomy for Laravel. Records are tagged with **Terms** from **Vocabularies**. A Vocabulary is either
**open**, for free tagging, where typing a new name creates the Term, or **closed**, for categories that administrators
curate. Closed Vocabularies can also be hierarchical. Vocabularies are classes in your code, and only Terms live in
the database.

Filament is optional. With it, you also get a tag input, a term select, a table column, an infolist entry, a table
filter, and a management page for each Vocabulary.

```bash
composer require webwizardsusa/laravel-taxonomy
php artisan vendor:publish --tag=taxonomy-config
php artisan vendor:publish --tag=taxonomy-migrations
php artisan migrate
```

## Vocabularies

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
}
```

List your Vocabularies in `config/taxonomy.php` (`'vocabularies' => [TagsVocabulary::class, CategoriesVocabulary::class]`).
Packages can add their own with `Taxonomy::register(SomeVocabulary::class)`.

The **key** is what each Term stores, so never change it once Terms exist. You can rename or move the class freely.
A Vocabulary can also declare:

- `sortOrder()`: `TermSortOrder::Weight` (manual order, then name) or `TermSortOrder::Alphabetical`. Open Vocabularies
  default to alphabetical and closed ones to weight.
- `formComponents()` and `tableColumns()`: extra Filament fields and columns for its Terms on the management page.
- `navigationIcon()`: the icon for its management page.

Open Vocabularies are always flat, and registering an open hierarchical Vocabulary throws.

## Terms

Terms have a `name`, `slug`, `description`, `weight`, and a `parent_id` in hierarchical Vocabularies.

- **Open Vocabularies**: names are unique, ignoring case. Typing "php" finds the existing "PHP".
- **Closed Vocabularies**: two Terms can share a name, as long as they're under different parents (Cars › Accessories
  and Phones › Accessories). Names and slugs only have to be unique among siblings.

A slug left empty is made from the name, with a numbered suffix if that one is taken (`c`, `c-2`). A slug you give
must be free, or saving throws `DuplicateTermException`.

A Term has at most one parent, always in the same Vocabulary, and it can't be moved under itself or its descendants
(`InvalidParentException`). Deleting a Term moves its children up to its own parent, and the records tagged with it
just lose that Term.

```php
use Webwizardsusa\Taxonomy\Models\Term;

Term::findOrCreateByName('tags', 'PHP');           // open Vocabularies only
Term::findBySlug('tags', 'php');
Term::findByPath('categories', 'cars/accessories');   // the Term Path: slugs from the top down
Term::findByPathOrFail('categories', 'cars/accessories');
$term->path();                                      // 'cars/accessories'
$term->getVocabulary();                             // the Vocabulary instance ($term->vocabulary is the key)

Term::query()->inVocabulary('categories')->ordered('categories')->get();
```

### Your own Term model

To add columns, extend the base model, add the columns to the published migration (or write a migration of your own),
and point the config at your class:

```php
'term_model' => App\Models\Term::class,   // must extend Webwizardsusa\Taxonomy\Models\Term
```

The package uses that class everywhere: relations, lookups and the Filament pieces. Every Vocabulary shares the one
model. To show a column on only one Vocabulary's management page, add its field to that Vocabulary's
`formComponents()`.

## Tagging records

```php
use Webwizardsusa\Taxonomy\Concerns\HasTerms;

class Post extends Model
{
    use HasTerms;
}
```

```php
$post->syncTerms('tags', ['PHP', 'Laravel']);   // names (open), Term models or ids, in this order
$post->attachTerms('tags', ['Pest']);           // appended, skipping ones it has
$post->detachTerms('tags', ['laravel']);
$post->syncTerms('categories', [$cars]);        // closed: models or ids only

$post->termsIn('tags');                         // in the order they were given
$post->terms;                                   // every Vocabulary
```

Each method changes one Vocabulary only, so syncing tags leaves categories alone. Deleting a record removes its
Terms. A record can take any number of Terms from any Vocabulary. Limiting how many is up to your forms.

### Querying

```php
Post::withAnyTerms(['php', 'laravel'], 'tags')->get();
Post::withAllTerms([$php, $laravel])->get();
Post::withoutTerms(['php'], 'tags')->get();

Post::withAnyTerms($cars, includingDescendants: true)->get();   // Cars, Cars › Accessories, …
```

The scopes take Term models, ids, or slugs and Term Paths along with a Vocabulary. Descendants are found with a
recursive CTE, which runs on MySQL 8, MariaDB 10.2+, Postgres and SQLite.

## Commands

- `taxonomy:check` reports Terms that break their Vocabulary's current rules. That can happen when you change a
  definition under existing data, for example turning a closed Vocabulary open (duplicate names) or a hierarchical one
  flat (parents). It also reports Terms whose Vocabulary is no longer registered. It changes nothing and exits with 1
  when it finds a problem.
- `taxonomy:prune` deletes Terms of unregistered Vocabularies. Add `--unused` to also delete open-Vocabulary Terms
  that no record uses, and `--pretend` to only list what it would delete. It never deletes closed-Vocabulary Terms for
  being unused.

## Filament

```php
use Webwizardsusa\Taxonomy\Filament\Forms\Components\TermSelect;
use Webwizardsusa\Taxonomy\Filament\Forms\Components\TermTagsInput;
use Webwizardsusa\Taxonomy\Filament\Infolists\Components\TermsEntry;
use Webwizardsusa\Taxonomy\Filament\Tables\Columns\TermsColumn;
use Webwizardsusa\Taxonomy\Filament\Tables\Filters\TermsFilter;

TermTagsInput::make('tags')->maxItems(5);                // open Vocabularies
TermSelect::make('category')->vocabulary('categories');  // closed: one Term
TermSelect::make('categories')->multiple();               // or several

TermsColumn::make('tags');
TermsEntry::make('tags');
TermsFilter::make('categories');                          // includes descendants; ->includingDescendants(false)
```

Each piece uses the Vocabulary whose key matches its name, unless you pass `->vocabulary()`. Pointing the tag input at
a closed Vocabulary, or the select at an open one, throws.

On a model with `HasTerms`, the fields load and save the record's Terms, on create and edit pages alike, and leave
them out of the model's attributes. Without such a model, their state is plain names (tag input) or ids (select).

- The tag input suggests the Vocabulary's names, up to `taxonomy.filament.suggestion_limit` (1000 by default). You can
  always type a name that isn't suggested.
- The select lists Terms in tree order, indented by depth, with search.
- The column loads every row's Terms in one query.

### Management pages

```php
use Webwizardsusa\Taxonomy\Filament\TaxonomyPlugin;

$panel->plugin(TaxonomyPlugin::make());
// or: TaxonomyPlugin::make()->vocabularies(['tags', CategoriesVocabulary::class])
```

This adds a page for each Vocabulary at `taxonomy/{key}`, under a "Taxonomy" navigation group:

- **Flat Vocabularies** get a table showing how often each Term is used. You can drag rows to reorder them when the
  Vocabulary sorts by weight.
- **Hierarchical Vocabularies** get a tree. Drag Terms to reorder them or move them under another parent. A move the
  rules refuse (into its own subtree, or next to a sibling with the same name) changes nothing.

Without `->vocabularies()`, the plugin uses the Vocabularies in `taxonomy.vocabularies`. Panels are built before
packages boot, so a Vocabulary another package registers at runtime has to be listed explicitly.

### Authorization

Every page and action asks the policy for your Term model, if you register one. Without a policy, everything is
allowed.

```php
class TermPolicy
{
    public function viewAny(User $user, Vocabulary $vocabulary): bool {}   // the page

    public function create(User $user, Vocabulary $vocabulary): bool {}    // also: creating tags by typing them

    public function reorder(User $user, Vocabulary $vocabulary): bool {}   // dragging in the table or tree

    public function update(User $user, Term $term): bool {}

    public function delete(User $user, Term $term): bool {}
}
```

When `create` is denied, the tag input only accepts names that already exist.

## Not included

Translated Terms, multi-tenancy (Vocabularies per team), synonyms, and Terms defined in code. Use your own seeders to
create starting Terms.

## Testing

```bash
composer test
```
