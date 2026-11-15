<?php

namespace Webwizardsusa\Taxonomy\Support;

use Illuminate\Support\Facades\Gate;
use Webwizardsusa\Taxonomy\Facades\Taxonomy;
use Webwizardsusa\Taxonomy\Models\Term;
use Webwizardsusa\Taxonomy\Vocabulary;

/**
 * Asks the Term policy when one is registered, and allows everything when it isn't, matching Filament's own default.
 * Vocabulary abilities (`viewAny`, `create`, `reorder`) get the Vocabulary, and Term abilities (`update`, `delete`)
 * get the Term.
 */
final class TermGate
{
    public static function allows(string $ability, Vocabulary|Term $subject): bool
    {
        if (Gate::getPolicyFor(Taxonomy::termModel()) === null) {
            return true;
        }

        return $subject instanceof Term
            ? Gate::allows($ability, $subject)
            : Gate::allows($ability, [Taxonomy::termModel(), $subject]);
    }
}
