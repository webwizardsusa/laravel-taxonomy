<?php

namespace Webwizardsusa\Taxonomy\Tests\Fixtures;

use Webwizardsusa\Taxonomy\Vocabulary;

class CategoriesVocabulary extends Vocabulary
{
    public static function key(): string
    {
        return 'categories';
    }

    public function label(): string
    {
        return 'Categories';
    }

    public function isHierarchical(): bool
    {
        return true;
    }
}
