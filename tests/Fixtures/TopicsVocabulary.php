<?php

namespace Webwizardsusa\Taxonomy\Tests\Fixtures;

use Webwizardsusa\Taxonomy\Vocabulary;

class TopicsVocabulary extends Vocabulary
{
    public static function key(): string
    {
        return 'topics';
    }

    public function label(): string
    {
        return 'Topics';
    }
}
