<?php

namespace Webwizardsusa\Taxonomy\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Webwizardsusa\Taxonomy\Facades\Taxonomy;
use Webwizardsusa\Taxonomy\Models\Term;

/**
 * Reports Terms that break their Vocabulary's current rules, which happens when a definition changes under existing
 * Terms (Closed to Open, hierarchical to flat) or a Vocabulary is no longer registered. It changes nothing.
 *
 * A Term in an Open Vocabulary whose `name_key` is missing (it was made while the Vocabulary was closed) or doesn't
 * match its name can't be found by name, and typing its name again fails as a repeat. Saving the Term sets the key,
 * once no other Term repeats its name.
 */
class CheckCommand extends Command
{
    protected $signature = 'taxonomy:check';

    protected $description = "Report Terms that break their vocabulary's rules";

    public function handle(): int
    {
        $problems = [];

        Taxonomy::newTermQuery()->orderBy('id')->get()->groupBy('vocabulary')->each(function (Collection $terms, string $key) use (&$problems): void {
            if (! Taxonomy::has($key)) {
                foreach ($terms as $term) {
                    $problems[] = [$key, $term->getKey(), 'The vocabulary is not registered.'];
                }

                return;
            }

            $vocabulary = Taxonomy::vocabulary($key);

            if (! $vocabulary->isHierarchical()) {
                foreach ($terms->whereNotNull('parent_id') as $term) {
                    $problems[] = [$key, $term->getKey(), 'Has a parent, but the vocabulary is flat.'];
                }
            }

            if ($vocabulary->isOpen()) {
                foreach ($terms as $term) {
                    if ($term->getAttribute('name_key') !== Taxonomy::termModel()::nameKey(Term::normalizeName($term->name))) {
                        $problems[] = [$key, $term->getKey(), 'Its name key is missing or stale, so it is not found by name: save the Term to set it.'];
                    }
                }
            }

            $groups = $vocabulary->isOpen() ? collect([$terms]) : $terms->groupBy(fn (Term $term): string => (string) $term->parent_id);

            foreach ($groups as $group) {
                foreach (['name' => 'Name', 'slug' => 'Slug'] as $attribute => $label) {
                    $group->groupBy(fn (Term $term): string => mb_strtolower($term->{$attribute}))
                        ->filter(fn (Collection $same): bool => $same->count() > 1)
                        ->each(function (Collection $same) use (&$problems, $key, $label, $attribute, $vocabulary): void {
                            foreach ($same->skip(1) as $term) {
                                $where = $vocabulary->isOpen() ? 'in the vocabulary' : 'among its siblings';
                                $problems[] = [$key, $term->getKey(), "{$label} [{$term->{$attribute}}] is repeated {$where}."];
                            }
                        });
                }
            }
        });

        if ($problems === []) {
            $this->components->info('No problems found.');

            return self::SUCCESS;
        }

        usort($problems, fn (array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        $this->table(['Vocabulary', 'Term', 'Problem'], $problems);

        return self::FAILURE;
    }
}
