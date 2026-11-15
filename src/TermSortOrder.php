<?php

namespace Webwizardsusa\Taxonomy;

/**
 * How a Vocabulary's Terms are listed: by their manual weight (then name), or by name.
 */
enum TermSortOrder: string
{
    case Weight = 'weight';
    case Alphabetical = 'alphabetical';
}
