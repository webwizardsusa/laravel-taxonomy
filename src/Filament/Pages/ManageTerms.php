<?php

namespace Webwizardsusa\Taxonomy\Filament\Pages;

use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use UnitEnum;
use Webwizardsusa\Taxonomy\Exceptions\DuplicateTermException;
use Webwizardsusa\Taxonomy\Exceptions\InvalidParentException;
use Webwizardsusa\Taxonomy\Facades\Taxonomy;
use Webwizardsusa\Taxonomy\Filament\Forms\Components\TermSelect;
use Webwizardsusa\Taxonomy\Filament\TaxonomyPlugin;
use Webwizardsusa\Taxonomy\Models\Term;
use Webwizardsusa\Taxonomy\Support\TermGate;
use Webwizardsusa\Taxonomy\Support\TermTree;
use Webwizardsusa\Taxonomy\TermSortOrder;
use Webwizardsusa\Taxonomy\Vocabulary;

/**
 * Manages one Vocabulary's Terms. It's registered once per Vocabulary (`ManageTerms::make('tags')`, which TaxonomyPlugin
 * does), at `taxonomy/{key}`. Flat Vocabularies get a table, reorderable when sorted by weight; hierarchical ones get a
 * tree whose Terms can be dragged to reorder and re-parent them. Every change goes through the Term policy.
 *
 * @extends Page<ManageTermsConfiguration>
 */
class ManageTerms extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $configurationClass = ManageTermsConfiguration::class;

    protected static ?string $slug = 'taxonomy';

    protected string $view = 'taxonomy::filament.pages.manage-terms';

    #[Locked]
    public string $vocabularyKey = '';

    /**
     * Bumped after every change, so the tree is rendered afresh instead of morphed over what SortableJS moved.
     */
    public int $treeVersion = 0;

    public function mount(): void
    {
        $key = static::getConfiguration(Filament::getCurrentOrDefaultPanel())?->getKey();

        abort_unless($key !== null && Taxonomy::has($key), 404);

        $this->vocabularyKey = $key;
    }

    public static function canAccess(): bool
    {
        $vocabulary = static::getConfiguredVocabulary();

        return $vocabulary === null || TermGate::allows('viewAny', $vocabulary);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::getConfiguredVocabulary() !== null;
    }

    public static function getNavigationLabel(): string
    {
        return static::getConfiguredVocabulary()?->label() ?? parent::getNavigationLabel();
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return TaxonomyPlugin::current()?->getNavigationGroup() ?? __('taxonomy::taxonomy.navigation_group');
    }

    public static function getNavigationSort(): ?int
    {
        return TaxonomyPlugin::current()?->getNavigationSort() ?? parent::getNavigationSort();
    }

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return static::getConfiguredVocabulary()?->navigationIcon() ?? Heroicon::OutlinedTag;
    }

    public function getTitle(): string|Htmlable
    {
        return $this->getVocabulary()->label();
    }

    public function getVocabulary(): Vocabulary
    {
        return Taxonomy::vocabulary($this->vocabularyKey);
    }

    public function table(Table $table): Table
    {
        $vocabulary = $this->getVocabulary();
        $terms = Taxonomy::newTermQuery()->getModel();

        return $table
            ->query(fn () => Taxonomy::newTermQuery()
                ->inVocabulary($vocabulary)
                ->select($terms->qualifyColumn('*'))
                ->selectSub(
                    DB::table(config('taxonomy.tables.termables', 'taxonomy_termables'))
                        ->selectRaw('count(*)')
                        ->whereColumn('term_id', $terms->qualifyColumn('id')),
                    'usage_count',
                )
                ->ordered($vocabulary))
            ->columns([
                TextColumn::make('name')->label(__('taxonomy::taxonomy.fields.name'))->searchable(),
                TextColumn::make('slug')->label(__('taxonomy::taxonomy.fields.slug'))->color('gray'),
                TextColumn::make('usage_count')->label(__('taxonomy::taxonomy.fields.usage'))->numeric(),
                ...$vocabulary->tableColumns(),
            ])
            ->reorderable('weight', $vocabulary->sortOrder() === TermSortOrder::Weight && TermGate::allows('reorder', $vocabulary))
            ->recordActions([
                $this->makeEditAction('edit'),
                $this->makeDeleteAction('delete'),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        $vocabulary = $this->getVocabulary();

        return [
            CreateAction::make()
                ->model(Taxonomy::termModel())
                ->modelLabel($this->getTermLabel())
                ->schema(fn (): array => $this->getTermFormComponents())
                ->mutateDataUsing(fn (array $data): array => [...$data, 'vocabulary' => $vocabulary::key()])
                ->visible(fn (): bool => TermGate::allows('create', $vocabulary))
                ->after(fn () => $this->treeVersion++),
        ];
    }

    public function editTermAction(): Action
    {
        return $this->makeEditAction('editTerm')
            ->record(fn (array $arguments): ?Term => $this->findTerm($arguments['term'] ?? null));
    }

    public function deleteTermAction(): Action
    {
        return $this->makeDeleteAction('deleteTerm')
            ->record(fn (array $arguments): ?Term => $this->findTerm($arguments['term'] ?? null));
    }

    /**
     * Moves a Term dropped in the tree under its new parent (null for the top level), and gives its new siblings
     * weights in the order they now appear. A move the Vocabulary's rules refuse changes nothing.
     *
     * @param  array<int|string>  $orderedSiblingIds
     */
    public function moveTerm(int|string $termId, int|string|null $parentId, array $orderedSiblingIds): void
    {
        $vocabulary = $this->getVocabulary();

        abort_unless(TermGate::allows('reorder', $vocabulary), 403);

        $term = $this->findTerm($termId);

        abort_if($term === null, 404);

        $parentId = filled($parentId) ? (int) $parentId : null;

        try {
            $term->getConnection()->transaction(function () use ($term, $parentId, $orderedSiblingIds, $vocabulary): void {
                $term->parent_id = $parentId;
                $term->save();

                foreach (array_values($orderedSiblingIds) as $weight => $id) {
                    Taxonomy::newTermQuery()
                        ->inVocabulary($vocabulary)
                        ->where('parent_id', $parentId)
                        ->whereKey((int) $id)
                        ->update(['weight' => $weight]);
                }
            });
        } catch (InvalidParentException|DuplicateTermException $exception) {
            Notification::make()
                ->danger()
                ->title(__('taxonomy::taxonomy.tree.move_refused'))
                ->body($exception->getMessage())
                ->send();
        }

        $this->treeVersion++;
    }

    /**
     * The tree's Terms by parent id (0 for the top level), each level in the Vocabulary's order.
     *
     * @return array<int, list<Term>>
     */
    public function getTreeBranches(): array
    {
        $branches = [];
        $ancestors = [];

        foreach (TermTree::flatten($this->getVocabulary()) as ['term' => $term, 'depth' => $depth]) {
            $ancestors[$depth] = $term->getKey();
            $branches[$depth === 0 ? 0 : $ancestors[$depth - 1]][] = $term;
        }

        return $branches;
    }

    public function canReorderTree(): bool
    {
        return TermGate::allows('reorder', $this->getVocabulary());
    }

    /**
     * @return array<Component|Field>
     */
    protected function getTermFormComponents(?Term $term = null): array
    {
        $vocabulary = $this->getVocabulary();

        return [
            TextInput::make('name')
                ->label(__('taxonomy::taxonomy.fields.name'))
                ->required()
                ->maxLength(255)
                ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $term): void {
                    if ($this->probeTerm($term, $get, ['name' => $value])->isNameTaken()) {
                        $fail(__('validation.unique', ['attribute' => __('taxonomy::taxonomy.fields.name')]));
                    }
                }),
            TextInput::make('slug')
                ->label(__('taxonomy::taxonomy.fields.slug'))
                ->alphaDash()
                ->maxLength(255)
                ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $term): void {
                    if ($this->probeTerm($term, $get, ['slug' => $value])->isSlugTaken()) {
                        $fail(__('validation.unique', ['attribute' => __('taxonomy::taxonomy.fields.slug')]));
                    }
                }),
            ...($vocabulary->isHierarchical() ? [
                TermSelect::make('parent_id')
                    ->label(__('taxonomy::taxonomy.fields.parent'))
                    ->vocabulary($vocabulary)
                    ->excludeSubtree($term)
                    ->live(),
            ] : []),
            Textarea::make('description')->label(__('taxonomy::taxonomy.fields.description')),
            ...$vocabulary->formComponents(),
        ];
    }

    /**
     * What one of this Vocabulary's Terms is called in action labels: "category" for Categories.
     */
    protected function getTermLabel(): string
    {
        return str($this->getVocabulary()->label())->singular()->lower()->toString();
    }

    protected function makeEditAction(string $name): EditAction
    {
        return EditAction::make($name)
            ->modelLabel($this->getTermLabel())
            ->schema(fn (Term $record): array => $this->getTermFormComponents($record))
            ->visible(fn (?Term $record): bool => $record !== null && TermGate::allows('update', $record))
            ->after(fn () => $this->treeVersion++);
    }

    protected function makeDeleteAction(string $name): Action
    {
        return Action::make($name)
            ->label(__('filament-actions::delete.single.label'))
            ->icon(Heroicon::Trash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (Term $record): string => __('filament-actions::delete.single.modal.heading', ['label' => $record->name]))
            ->visible(fn (?Term $record): bool => $record !== null && TermGate::allows('delete', $record))
            ->action(function (Term $record): void {
                try {
                    $record->delete();

                    Notification::make()->success()->title(__('filament-actions::delete.single.notifications.deleted.title'))->send();
                } catch (DuplicateTermException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();
                }

                $this->treeVersion++;
            });
    }

    /**
     * An unsaved copy of the Term (or a new Term) with the form's values, for checking names and slugs before saving.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function probeTerm(?Term $term, Get $get, array $attributes): Term
    {
        $probe = $term ? clone $term : Taxonomy::newTermQuery()->newModelInstance(['vocabulary' => $this->vocabularyKey]);
        $parentId = $this->getVocabulary()->isHierarchical() ? $get('parent_id') : $probe->parent_id;

        return $probe->forceFill([...$attributes, 'parent_id' => filled($parentId) ? (int) $parentId : null]);
    }

    protected function findTerm(int|string|null $id): ?Term
    {
        if (blank($id)) {
            return null;
        }

        return Taxonomy::newTermQuery()->inVocabulary($this->vocabularyKey)->find((int) $id);
    }

    protected static function getConfiguredVocabulary(): ?Vocabulary
    {
        $key = static::getConfiguration(Filament::getCurrentOrDefaultPanel())?->getKey();

        return $key !== null && Taxonomy::has($key) ? Taxonomy::vocabulary($key) : null;
    }
}
