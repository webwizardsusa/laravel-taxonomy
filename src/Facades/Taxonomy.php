<?php

namespace Webwizardsusa\Taxonomy\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void register(string ...$classes)
 * @method static \Webwizardsusa\Taxonomy\Vocabulary vocabulary(string|\Webwizardsusa\Taxonomy\Vocabulary $vocabulary)
 * @method static bool has(string|\Webwizardsusa\Taxonomy\Vocabulary $vocabulary)
 * @method static array<string, \Webwizardsusa\Taxonomy\Vocabulary> vocabularies()
 * @method static class-string<\Webwizardsusa\Taxonomy\Models\Term> termModel()
 * @method static \Illuminate\Database\Eloquent\Builder<\Webwizardsusa\Taxonomy\Models\Term> newTermQuery()
 *
 * @see \Webwizardsusa\Taxonomy\Taxonomy
 */
class Taxonomy extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Webwizardsusa\Taxonomy\Taxonomy::class;
    }
}
