<?php

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Webwizardsusa\Taxonomy\Filament\Pages\ManageTerms;
use Webwizardsusa\Taxonomy\Filament\TaxonomyPlugin;
use Webwizardsusa\Taxonomy\Models\Term;
use Webwizardsusa\Taxonomy\Tests\Fixtures\Article;
use Webwizardsusa\Taxonomy\Tests\Fixtures\TagsVocabulary;
use Webwizardsusa\Taxonomy\Tests\Fixtures\User;
use Webwizardsusa\Taxonomy\Tests\TestCase;
use Webwizardsusa\Taxonomy\Vocabulary;

uses(TestCase::class);

beforeEach(function (): void {
    if (! TestCase::hasFilament()) {
        $this->markTestSkipped('Filament is not installed.');
    }

    ManageTermsPolicy::$denied = [];
    $this->actingAs(User::create(['name' => 'Editor', 'email' => 'editor@example.com', 'password' => 'secret']));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

class ManageTermsPolicy
{
    /**
     * @var array<string>
     */
    public static array $denied = [];

    public function viewAny(User $user, Vocabulary $vocabulary): bool
    {
        return ! in_array("viewAny:{$vocabulary::key()}", static::$denied, true);
    }

    public function create(User $user, Vocabulary $vocabulary): bool
    {
        return ! in_array('create', static::$denied, true);
    }

    public function reorder(User $user, Vocabulary $vocabulary): bool
    {
        return ! in_array('reorder', static::$denied, true);
    }

    public function update(User $user, Term $term): bool
    {
        return ! in_array('update', static::$denied, true);
    }

    public function delete(User $user, Term $term): bool
    {
        return ! in_array('delete', static::$denied, true);
    }
}

function manageTerms(string $vocabulary)
{
    Filament::setCurrentPageConfigurationKey($vocabulary);

    return Livewire::test(ManageTerms::class);
}

function manageCategory(string $name, ?Term $parent = null, int $weight = 0): Term
{
    return Term::create(['vocabulary' => 'categories', 'name' => $name, 'parent_id' => $parent?->id, 'weight' => $weight]);
}

it('adds a page per vocabulary under the taxonomy group', function (): void {
    $this->get('/admin/taxonomy/tags')
        ->assertSuccessful()
        ->assertSeeInOrder(['Taxonomy', 'Tags', 'Categories', 'Topics']);

    $this->get('/admin/taxonomy/categories')->assertSuccessful();
});

it('only adds pages for the vocabularies it is given', function (): void {
    $panel = Panel::make()->id('limited');

    TaxonomyPlugin::make()->vocabularies([TagsVocabulary::class, 'topics'])->register($panel);

    expect(collect($panel->getPageConfigurations())->map->getKey()->values()->all())->toBe(['tags', 'topics']);
});

it('hides a vocabulary the user may not view', function (): void {
    Gate::policy(Term::class, ManageTermsPolicy::class);
    ManageTermsPolicy::$denied = ['viewAny:topics'];

    $this->get('/admin/taxonomy/topics')->assertForbidden();
    $this->get('/admin/taxonomy/tags')->assertSuccessful()->assertDontSee('Topics');
});

it('lists a flat vocabulary\'s terms with how often each is used', function (): void {
    $article = Article::create(['title' => 'Hello']);
    $article->syncTerms('tags', ['PHP']);
    $laravel = Term::findOrCreateByName('tags', 'Laravel');

    manageTerms('tags')
        ->assertCanSeeTableRecords([Term::findBySlug('tags', 'php'), $laravel])
        ->assertTableColumnStateSet('usage_count', 1, Term::findBySlug('tags', 'php'))
        ->assertTableColumnStateSet('usage_count', 0, $laravel);
});

it('creates a term in the page\'s vocabulary', function (): void {
    manageTerms('topics')
        ->callAction('create', ['name' => 'Travel', 'description' => 'Trips'])
        ->assertHasNoFormErrors();

    expect(Term::findBySlug('topics', 'travel'))->vocabulary->toBe('topics')->description->toBe('Trips');
});

it('refuses a name or slug that is taken', function (): void {
    Term::create(['vocabulary' => 'topics', 'name' => 'Travel']);

    manageTerms('topics')
        ->callAction('create', ['name' => 'travel'])
        ->assertHasFormErrors(['name']);

    manageTerms('topics')
        ->callAction('create', ['name' => 'Trips', 'slug' => 'travel'])
        ->assertHasFormErrors(['slug']);
});

it('creates a category under a parent, and allows its name under another parent', function (): void {
    $cars = manageCategory('Cars');
    $phones = manageCategory('Phones');
    manageCategory('Accessories', $cars);

    manageTerms('categories')
        ->callAction('create', ['name' => 'Accessories', 'parent_id' => $cars->id])
        ->assertHasFormErrors(['name']);

    manageTerms('categories')
        ->callAction('create', ['name' => 'Accessories', 'parent_id' => $phones->id])
        ->assertHasNoFormErrors();

    expect(Term::findByPath('categories', 'phones/accessories'))->not->toBeNull();
});

it('edits and deletes terms from the table', function (): void {
    $travel = Term::create(['vocabulary' => 'topics', 'name' => 'Travel']);

    manageTerms('topics')
        ->callAction(TestAction::make('edit')->table($travel), ['name' => 'Trips'])
        ->assertHasNoFormErrors();

    expect($travel->fresh()->name)->toBe('Trips');

    manageTerms('topics')->callAction(TestAction::make('delete')->table($travel));

    expect($travel->fresh())->toBeNull();
});

it('hides the actions the policy denies', function (): void {
    Gate::policy(Term::class, ManageTermsPolicy::class);
    ManageTermsPolicy::$denied = ['create', 'update', 'delete'];
    $travel = Term::create(['vocabulary' => 'topics', 'name' => 'Travel']);

    manageTerms('topics')
        ->assertActionHidden('create')
        ->assertActionHidden(TestAction::make('edit')->table($travel))
        ->assertActionHidden(TestAction::make('delete')->table($travel));
});

it('shows a hierarchical vocabulary as a tree', function (): void {
    $cars = manageCategory('Cars');
    manageCategory('Accessories', $cars);
    manageCategory('Phones', weight: 1);

    manageTerms('categories')->assertSeeInOrder(['Cars', 'Accessories', 'Phones']);
});

it('moves a dropped term under its new parent and reorders its new siblings', function (): void {
    $cars = manageCategory('Cars');
    $tyres = manageCategory('Tyres', $cars);
    $phones = manageCategory('Phones', weight: 1);

    manageTerms('categories')->call('moveTerm', $phones->id, $cars->id, [$phones->id, $tyres->id]);

    expect($phones->fresh())->parent_id->toBe($cars->id)->weight->toBe(0)
        ->and($tyres->fresh()->weight)->toBe(1);
});

it('refuses a drop the vocabulary does not allow and changes nothing', function (Closure $move): void {
    $cars = manageCategory('Cars');
    $accessories = manageCategory('Accessories', $cars);
    manageCategory('Accessories');

    [$termId, $parentId] = $move($cars, $accessories);

    manageTerms('categories')
        ->call('moveTerm', $termId, $parentId, [$termId])
        ->assertNotified();

    expect($cars->fresh()->parent_id)->toBeNull()
        ->and($accessories->fresh()->parent_id)->toBe($cars->id);
})->with([
    'into its own subtree' => [fn (Term $cars, Term $accessories): array => [$cars->id, $accessories->id]],
    'next to a sibling with its name' => [fn (Term $cars, Term $accessories): array => [$accessories->id, null]],
]);

it('refuses moves without the reorder ability', function (): void {
    Gate::policy(Term::class, ManageTermsPolicy::class);
    ManageTermsPolicy::$denied = ['reorder'];
    $cars = manageCategory('Cars');

    manageTerms('categories')
        ->assertDontSeeHtml('x-sortable-handle')
        ->call('moveTerm', $cars->id, null, [$cars->id])
        ->assertForbidden();
});

it('edits and deletes terms from the tree', function (): void {
    $cars = manageCategory('Cars');
    $accessories = manageCategory('Accessories', $cars);

    manageTerms('categories')
        ->callAction(TestAction::make('editTerm')->arguments(['term' => $accessories->id]), ['name' => 'Parts'])
        ->assertHasNoFormErrors();

    expect($accessories->fresh()->name)->toBe('Parts');

    manageTerms('categories')
        ->callAction(TestAction::make('deleteTerm')->arguments(['term' => $cars->id]))
        ->assertNotified();

    expect($cars->fresh())->toBeNull()
        ->and($accessories->fresh()->parent_id)->toBeNull();
});
