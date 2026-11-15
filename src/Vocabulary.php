<?php

namespace Webwizardsusa\Taxonomy;

use Filament\Schemas\Components\Component;
use Filament\Tables\Columns\Column;

/**
 * A Vocabulary: a named set of Terms, defined in code and registered with Taxonomy. Its key is what Terms store, so it
 * must never change once Terms exist.
 */
abstract class Vocabulary
{
    abstract public static function key(): string;

    abstract public function label(): string;

    /**
     * Whether Terms can be created by typing them while tagging a record (free tagging).
     */
    public function isOpen(): bool
    {
        return false;
    }

    /**
     * Whether Terms can have a Parent Term. Open Vocabularies are always flat.
     */
    public function isHierarchical(): bool
    {
        return false;
    }

    public function sortOrder(): TermSortOrder
    {
        return $this->isOpen() ? TermSortOrder::Alphabetical : TermSortOrder::Weight;
    }

    /**
     * Extra Filament form components for this Vocabulary's Terms on the management page, bound to columns on the
     * configured Term model.
     *
     * @return array<Component>
     */
    public function formComponents(): array
    {
        return [];
    }

    /**
     * Extra Filament table columns for this Vocabulary's Terms on the management page (flat Vocabularies only).
     *
     * @return array<Column>
     */
    public function tableColumns(): array
    {
        return [];
    }

    /**
     * The management page's navigation icon: a Heroicon or any icon name Filament takes.
     */
    public function navigationIcon(): mixed
    {
        return null;
    }
}
