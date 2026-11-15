<?php

namespace Webwizardsusa\Taxonomy\Filament\Pages;

use Filament\Pages\PageConfiguration;

/**
 * One registration of the ManageTerms page: its key is the key of the Vocabulary it manages.
 */
class ManageTermsConfiguration extends PageConfiguration
{
    public function getVocabularyKey(): string
    {
        return $this->getKey();
    }
}
