<?php

namespace Webwizardsusa\Taxonomy\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Webwizardsusa\Taxonomy\Exceptions\DuplicateTermException;
use Webwizardsusa\Taxonomy\Exceptions\InvalidParentException;
use Webwizardsusa\Taxonomy\Facades\Taxonomy;
use Webwizardsusa\Taxonomy\Support\TermTree;
use Webwizardsusa\Taxonomy\TermSortOrder;
use Webwizardsusa\Taxonomy\Vocabulary;

/**
 * A Term in one Vocabulary. Apps that need more columns extend this class and point `taxonomy.term_model` at it.
 *
 * Names are unique ignoring case across an Open Vocabulary and among siblings in a Closed one, and so are slugs. A
 * slug left empty is made from the name, with a numbered suffix when that one is taken; a slug given explicitly must
 * be free. Deleting a Term moves its children up to its own parent.
 *
 * @property int $id
 * @property string $vocabulary
 * @property int|null $parent_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $weight
 */
class Term extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['name_key'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'weight' => 'integer',
        ];
    }

    public function getTable(): string
    {
        return config('taxonomy.tables.terms', 'taxonomy_terms');
    }

    protected static function booted(): void
    {
        static::saving(static function (Term $term): void {
            $term->prepareForSaving();
        });

        static::deleting(static function (Term $term): void {
            $term->moveChildrenUp();

            DB::connection($term->getConnectionName())
                ->table(config('taxonomy.tables.termables', 'taxonomy_termables'))
                ->where('term_id', $term->getKey())
                ->delete();
        });
    }

    /**
     * Deletes the Term in a transaction, so a child that can't move up (its name is taken there) leaves everything as
     * it was.
     */
    public function delete(): ?bool
    {
        return $this->getConnection()->transaction(fn (): ?bool => parent::delete());
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Taxonomy::termModel(), 'parent_id');
    }

    /**
     * @return HasMany<Term, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Taxonomy::termModel(), 'parent_id');
    }

    public function getVocabulary(): Vocabulary
    {
        return Taxonomy::vocabulary($this->vocabulary);
    }

    /**
     * The slugs from the top-level Term down to this one, such as `cars/accessories`.
     */
    public function path(): string
    {
        $slugs = [$this->slug];
        $seen = [$this->getKey() => true];
        $parentId = $this->parent_id;

        while ($parentId !== null && ! isset($seen[$parentId])) {
            $parent = Taxonomy::newTermQuery()->find($parentId);

            if ($parent === null) {
                break;
            }

            $seen[$parentId] = true;
            array_unshift($slugs, $parent->slug);
            $parentId = $parent->parent_id;
        }

        return implode('/', $slugs);
    }

    /**
     * @param  Builder<Term>  $query
     */
    #[Scope]
    protected function inVocabulary(Builder $query, string|Vocabulary $vocabulary): void
    {
        $query->where($query->qualifyColumn('vocabulary'), Taxonomy::vocabulary($vocabulary)::key());
    }

    /**
     * Sorts by the Vocabulary's order: weight then name, or name alone.
     *
     * @param  Builder<Term>  $query
     */
    #[Scope]
    protected function ordered(Builder $query, string|Vocabulary $vocabulary): void
    {
        if (Taxonomy::vocabulary($vocabulary)->sortOrder() === TermSortOrder::Weight) {
            $query->orderBy($query->qualifyColumn('weight'));
        }

        $query->orderBy($query->qualifyColumn('name'))->orderBy($query->qualifyColumn('id'));
    }

    public static function findBySlug(string|Vocabulary $vocabulary, string $slug): ?Term
    {
        return Taxonomy::newTermQuery()->inVocabulary($vocabulary)->where('slug', $slug)->orderBy('id')->first();
    }

    /**
     * Finds a Term by its Term Path (`cars/accessories`). In a flat Vocabulary the path is just the slug.
     */
    public static function findByPath(string|Vocabulary $vocabulary, string $path): ?Term
    {
        $vocabulary = Taxonomy::vocabulary($vocabulary);
        $slugs = array_values(array_filter(explode('/', trim($path, '/')), fn (string $slug): bool => $slug !== ''));

        if ($slugs === []) {
            return null;
        }

        if (! $vocabulary->isHierarchical()) {
            return count($slugs) === 1 ? static::findBySlug($vocabulary, $slugs[0]) : null;
        }

        $term = null;

        foreach ($slugs as $slug) {
            $term = Taxonomy::newTermQuery()
                ->inVocabulary($vocabulary)
                ->where('parent_id', $term?->getKey())
                ->where('slug', $slug)
                ->orderBy('id')
                ->first();

            if ($term === null) {
                return null;
            }
        }

        return $term;
    }

    public static function findByPathOrFail(string|Vocabulary $vocabulary, string $path): Term
    {
        return static::findByPath($vocabulary, $path)
            ?? throw (new ModelNotFoundException)->setModel(Taxonomy::termModel(), [$path]);
    }

    /**
     * Finds the Term in an Open Vocabulary whose name matches ignoring case, creating it when there isn't one. Two
     * requests creating the same name at once end with one Term.
     */
    public static function findOrCreateByName(string|Vocabulary $vocabulary, string $name): Term
    {
        $vocabulary = Taxonomy::vocabulary($vocabulary);

        if (! $vocabulary->isOpen()) {
            throw new InvalidArgumentException("{$vocabulary->label()} is closed: Terms can't be created by name.");
        }

        $name = static::normalizeName($name);

        // createOrFirst() (behind firstOrCreate) retries the lookup when the insert hits the unique index, inside a
        // savepoint when there's an open transaction.
        return Taxonomy::newTermQuery()->firstOrCreate(
            ['vocabulary' => $vocabulary::key(), 'name_key' => static::nameKey($name)],
            ['name' => $name],
        );
    }

    /**
     * Trims a name and collapses the whitespace inside it.
     */
    public static function normalizeName(?string $name): string
    {
        return (string) preg_replace('/\s+/u', ' ', trim((string) $name));
    }

    /**
     * The key an Open Vocabulary matches names by, stored as `name_key`: the normalized name in lower case.
     */
    public static function nameKey(string $name): string
    {
        return mb_strtolower($name);
    }

    protected function prepareForSaving(): void
    {
        $vocabulary = Taxonomy::vocabulary($this->vocabulary);

        $this->name = static::normalizeName($this->name);
        $this->setAttribute('name_key', $vocabulary->isOpen() ? static::nameKey($this->name) : null);
        $this->weight ??= 0;

        if ($this->isDirty(['vocabulary', 'parent_id'])) {
            $this->ensureValidParent($vocabulary);
        }

        if (! $this->exists || $this->isDirty(['vocabulary', 'parent_id', 'name'])) {
            $this->ensureNameIsFree($vocabulary);
        }

        if (blank($this->slug)) {
            $this->slug = $this->freeSlug($vocabulary, Str::slug($this->name) ?: 'term');
        } elseif (! $this->exists || $this->isDirty(['vocabulary', 'parent_id', 'slug'])) {
            if ($this->isSlugTaken()) {
                throw new DuplicateTermException("The slug [{$this->slug}] is already taken in {$vocabulary->label()}.", 'slug');
            }
        }
    }

    protected function ensureValidParent(Vocabulary $vocabulary): void
    {
        if ($this->parent_id === null) {
            return;
        }

        if (! $vocabulary->isHierarchical()) {
            throw new InvalidParentException("{$vocabulary->label()} is flat: its Terms can't have a parent.");
        }

        $parent = Taxonomy::newTermQuery()->find($this->parent_id);

        if ($parent === null || $parent->vocabulary !== $this->vocabulary) {
            throw new InvalidParentException("The parent must be a Term in {$vocabulary->label()}.");
        }

        if ($this->exists && in_array($parent->getKey(), TermTree::descendantIds([$this->getKey()]), true)) {
            throw new InvalidParentException("[{$this->name}] can't be moved under itself or one of its descendants.");
        }
    }

    /**
     * Whether another Term already has this Term's name where it must be unique. Works on unsaved Terms too, so forms
     * can check before saving.
     */
    public function isNameTaken(): bool
    {
        return $this->siblings(Taxonomy::vocabulary($this->vocabulary))
            ->whereRaw('lower('.$this->getConnection()->getQueryGrammar()->wrap('name').') = ?', [static::nameKey(static::normalizeName($this->name))])
            ->exists();
    }

    /**
     * Whether another Term already has this Term's slug where it must be unique.
     */
    public function isSlugTaken(): bool
    {
        return filled($this->slug) && $this->siblings(Taxonomy::vocabulary($this->vocabulary))->where('slug', $this->slug)->exists();
    }

    protected function ensureNameIsFree(Vocabulary $vocabulary): void
    {
        if ($this->isNameTaken()) {
            throw new DuplicateTermException("A Term named [{$this->name}] already exists in {$vocabulary->label()}.", 'name');
        }
    }

    protected function freeSlug(Vocabulary $vocabulary, string $base): string
    {
        $taken = $this->siblings($vocabulary)
            ->where(fn (Builder $query) => $query->where('slug', $base)->orWhere('slug', 'like', "{$base}-%"))
            ->pluck('slug')
            ->flip();

        $slug = $base;

        for ($suffix = 2; $taken->has($slug); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }

    /**
     * The Terms whose names and slugs this one must not repeat: the whole Vocabulary when it's open, the Terms under
     * the same parent when it's closed.
     *
     * @return Builder<Term>
     */
    protected function siblings(Vocabulary $vocabulary): Builder
    {
        $query = Taxonomy::newTermQuery()->inVocabulary($vocabulary);

        if (! $vocabulary->isOpen()) {
            $query->where('parent_id', $this->parent_id);
        }

        if ($this->exists) {
            $query->whereKeyNot($this->getKey());
        }

        return $query;
    }

    protected function moveChildrenUp(): void
    {
        /** @var Collection<int, Term> $children */
        $children = Taxonomy::newTermQuery()->where('parent_id', $this->getKey())->get();

        foreach ($children as $child) {
            $child->parent_id = $this->parent_id;
            $child->save();
        }
    }
}
