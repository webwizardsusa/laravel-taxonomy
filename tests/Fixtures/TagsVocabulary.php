<?php

namespace Webwizardsusa\Taxonomy\Tests\Fixtures;

use Webwizardsusa\Taxonomy\Vocabulary;

class TagsVocabulary extends Vocabulary
{
    public static function key(): string
    {
        return 'tags';
    }

    public function label(): string
    {
        return 'Tags';
    }

    public function isOpen(): bool
    {
        return true;
    }
}
