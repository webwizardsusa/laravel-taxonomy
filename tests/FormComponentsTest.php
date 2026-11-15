<?php

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\Livewire;
use Webwizardsusa\Taxonomy\Filament\Forms\Components\TermSelect;
use Webwizardsusa\Taxonomy\Filament\Forms\Components\TermTagsInput;
use Webwizardsusa\Taxonomy\Models\Term;
use Webwizardsusa\Taxonomy\Tests\Fixtures\Article;
use Webwizardsusa\Taxonomy\Tests\TestCase;
use Webwizardsusa\Taxonomy\Vocabulary;

uses(TestCase::class);

beforeEach(function (): void {
    if (! TestCase::hasFilament()) {
        $this->markTestSkipped('Filament is not installed.');
    }
});

if (class_exists(Component::class) && interface_exists(HasSchemas::class)) {
    class FormComponentsArticleForm extends Component implements HasActions, HasSchemas
    {
        use InteractsWithActions;
        use InteractsWithSchemas;

        public ?Article $article = null;

        public bool $multipleCategories = false;

        public ?int $maxTags = null;

        public bool $withoutModel = false;

        /**
         * @var array<string, mixed> | null
         */
        public ?array $data = [];

        /**
         * @var array<string, mixed>
         */
        public array $saved = [];

        public function mount(): void
        {
            $this->form->fill($this->article?->attributesToArray());
        }

        public function form(Schema $schema): Schema
        {
            return $schema
                ->components([
                    TermTagsInput::make('tags')->maxItems($this->maxTags),
                    TermSelect::make('categories')->multiple($this->multipleCategories),
                ])
                ->model($this->withoutModel ? null : ($this->article ?? Article::class))
                ->statePath('data');
        }

        public function save(): void
        {
            $this->saved = $this->form->getState();

            if ($this->article) {
                $this->form->saveRelationships();
            }
        }

        public function render(): string
        {
            return '<div>{{ $this->form }}</div>';
        }
    }

    class FormComponentsTermPolicy
    {
        public function create(User $user, Vocabulary $vocabulary): bool
        {
            return false;
        }
    }
}

function formCategories(): array
{
    $cars = Term::create(['vocabulary' => 'categories', 'name' => 'Cars']);

    return [
        'cars' => $cars,
        'accessories' => Term::create(['vocabulary' => 'categories', 'name' => 'Accessories', 'parent_id' => $cars->id]),
        'phones' => Term::create(['vocabulary' => 'categories', 'name' => 'Phones', 'weight' => 1]),
    ];
}

it('loads a record\'s tags and category in order', function (): void {
    $categories = formCategories();
    $article = Article::create(['title' => 'Hello']);
    $article->syncTerms('tags', ['Laravel', 'PHP']);
    $article->syncTerms('categories', [$categories['accessories']]);

    Livewire::test(FormComponentsArticleForm::class, ['article' => $article])
        ->assertSchemaStateSet(['tags' => ['Laravel', 'PHP'], 'categories' => $categories['accessories']->id]);
});

it('saves tags by name, matching existing ones and creating new ones', function (): void {
    $php = Term::findOrCreateByName('tags', 'PHP');
    $article = Article::create(['title' => 'Hello']);

    Livewire::test(FormComponentsArticleForm::class, ['article' => $article])
        ->fillForm(['tags' => ['php', 'New one']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($article->termsIn('tags')->pluck('name')->all())->toBe(['PHP', 'New one'])
        ->and($article->termsIn('tags')->first()->is($php))->toBeTrue();
});

it('saves one category, or several when multiple', function (): void {
    $categories = formCategories();
    $article = Article::create(['title' => 'Hello']);

    Livewire::test(FormComponentsArticleForm::class, ['article' => $article])
        ->fillForm(['categories' => $categories['phones']->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($article->termsIn('categories')->pluck('name')->all())->toBe(['Phones']);

    Livewire::test(FormComponentsArticleForm::class, ['article' => $article, 'multipleCategories' => true])
        ->fillForm(['categories' => [$categories['cars']->id, $categories['phones']->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($article->termsIn('categories')->pluck('name')->all())->toBe(['Cars', 'Phones']);
});

it('offers the vocabulary\'s names as tag suggestions, up to the configured limit', function (): void {
    foreach (['Pest', 'Laravel', 'PHP'] as $name) {
        Term::findOrCreateByName('tags', $name);
    }
    config()->set('taxonomy.filament.suggestion_limit', 2);

    expect(TermTagsInput::make('tags')->getSuggestions())->toBe(['Laravel', 'Pest']);
});

it('lists categories in tree order, indented by depth', function (): void {
    $categories = formCategories();

    expect(TermSelect::make('categories')->getOptions())->toBe([
        $categories['cars']->id => 'Cars',
        $categories['accessories']->id => '— Accessories',
        $categories['phones']->id => 'Phones',
    ]);
});

it('refuses new tags when the term policy denies create', function (): void {
    Gate::policy(Term::class, FormComponentsTermPolicy::class);
    $this->actingAs(new User);
    Term::findOrCreateByName('tags', 'PHP');
    $article = Article::create(['title' => 'Hello']);

    Livewire::test(FormComponentsArticleForm::class, ['article' => $article])
        ->fillForm(['tags' => ['PHP', 'New one']])
        ->call('save')
        ->assertHasFormErrors(['tags'])
        ->assertSee('You can only choose existing tags.');

    Livewire::test(FormComponentsArticleForm::class, ['article' => $article])
        ->fillForm(['tags' => ['php']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Term::query()->where('vocabulary', 'tags')->count())->toBe(1);
});

it('saves terms after the record is created, keeping them out of its attributes', function (): void {
    $component = Livewire::test(FormComponentsArticleForm::class)
        ->fillForm(['tags' => ['PHP']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($component->get('saved'))->toBe([]);
});

it('limits how many tags a record takes', function (): void {
    Livewire::test(FormComponentsArticleForm::class, ['article' => Article::create(['title' => 'Hello']), 'maxTags' => 2])
        ->fillForm(['tags' => ['a', 'b', 'c']])
        ->call('save')
        ->assertHasFormErrors(['tags']);
});

it('refuses a category from another vocabulary', function (): void {
    formCategories();
    $topic = Term::create(['vocabulary' => 'topics', 'name' => 'Travel']);

    Livewire::test(FormComponentsArticleForm::class, ['article' => Article::create(['title' => 'Hello'])])
        ->fillForm(['categories' => $topic->id])
        ->call('save')
        ->assertHasFormErrors(['categories']);
});

it('keeps plain names and ids as state without a model', function (): void {
    $categories = formCategories();

    $component = Livewire::test(FormComponentsArticleForm::class, ['withoutModel' => true])
        ->fillForm(['tags' => ['PHP'], 'categories' => $categories['cars']->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($component->get('saved'))->toBe(['tags' => ['PHP'], 'categories' => $categories['cars']->id])
        ->and(Term::query()->where('vocabulary', 'tags')->count())->toBe(0);
});

it('refuses the wrong kind of vocabulary', function (Closure $component, string $message): void {
    expect(fn () => $component()->getVocabulary())->toThrow(LogicException::class, $message);
})->with([
    'tags input on a closed vocabulary' => [fn () => TermTagsInput::make('field')->vocabulary('categories'), 'TermTagsInput needs an open vocabulary, and [categories] is closed: use TermSelect.'],
    'select on an open vocabulary' => [fn () => TermSelect::make('field')->vocabulary('tags'), 'TermSelect needs a closed vocabulary, and [tags] is open: use TermTagsInput.'],
]);
