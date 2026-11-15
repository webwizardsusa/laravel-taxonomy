<?php

namespace Webwizardsusa\Taxonomy\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Webwizardsusa\Taxonomy\Facades\Taxonomy;

/**
 * Deletes Terms whose Vocabulary is no longer registered, and with `--unused` the Terms of Open Vocabularies that no
 * record uses. Closed Vocabularies' Terms are never deleted for being unused.
 */
class PruneCommand extends Command
{
    protected $signature = 'taxonomy:prune
        {--unused : Also delete Terms in open vocabularies that no record uses}
        {--pretend : List the Terms that would be deleted, and delete nothing}';

    protected $description = 'Delete Terms of unregistered vocabularies, and optionally unused tags';

    public function handle(): int
    {
        $registered = array_keys(Taxonomy::vocabularies());
        $open = array_keys(array_filter(Taxonomy::vocabularies(), fn ($vocabulary): bool => $vocabulary->isOpen()));

        $query = Taxonomy::newTermQuery()->where(function (Builder $query) use ($registered, $open): void {
            $query->whereNotIn('vocabulary', $registered);

            if ($this->option('unused') && $open !== []) {
                $query->orWhere(fn (Builder $query) => $query
                    ->whereIn('vocabulary', $open)
                    ->whereNotExists(fn ($pivots) => $pivots
                        ->from(config('taxonomy.tables.termables', 'taxonomy_termables'))
                        ->whereColumn('term_id', $query->qualifyColumn('id'))));
            }
        });

        $terms = $query->orderBy('id')->get();

        if ($this->option('pretend')) {
            $this->table(['Vocabulary', 'Term', 'Name'], $terms->map(fn ($term): array => [$term->vocabulary, $term->getKey(), $term->name])->all());
            $this->components->info("Would prune {$terms->count()} ".str('term')->plural($terms->count()).'.');

            return self::SUCCESS;
        }

        $termables = config('taxonomy.tables.termables', 'taxonomy_termables');
        $connection = $query->getModel()->getConnection();

        foreach ($terms->pluck('id')->chunk(500) as $ids) {
            $connection->transaction(function () use ($connection, $termables, $ids): void {
                $connection->table($termables)->whereIn('term_id', $ids)->delete();
                $connection->table(config('taxonomy.tables.terms', 'taxonomy_terms'))->whereIn('parent_id', $ids)->update(['parent_id' => null]);
                $connection->table(config('taxonomy.tables.terms', 'taxonomy_terms'))->whereIn('id', $ids)->delete();
            });
        }

        $this->components->info("Pruned {$terms->count()} ".str('term')->plural($terms->count()).'.');

        return self::SUCCESS;
    }
}
