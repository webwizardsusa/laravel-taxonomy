<?php

use Webwizardsusa\Taxonomy\Models\Term;

return [
    /*
     * The Term model. Swap in your own to add columns: it must extend Webwizardsusa\Taxonomy\Models\Term.
     */
    'term_model' => Term::class,

    /*
     * The tables for Terms and for the pivot between Terms and the records tagged with them.
     */
    'tables' => [
        'terms' => 'taxonomy_terms',
        'termables' => 'taxonomy_termables',
    ],

    /*
     * The application's Vocabulary classes. Packages register theirs with Taxonomy::register().
     */
    'vocabularies' => [],

    /*
     * How many names the Filament tag input offers as suggestions.
     */
    'filament' => [
        'suggestion_limit' => 1000,
    ],
];
