<x-filament-panels::page>
    @if ($this->getVocabulary()->isHierarchical())
        @php
            $branches = $this->getTreeBranches();
        @endphp

        <div wire:key="taxonomy-tree-{{ $this->treeVersion }}" class="fi-taxonomy-tree">
            @if ($branches === [])
                <p style="color: var(--gray-500)">{{ __('taxonomy::taxonomy.tree.empty') }}</p>
            @endif

            @include('taxonomy::filament.partials.term-tree-branch', [
                'branches' => $branches,
                'parentId' => null,
                'canReorder' => $this->canReorderTree(),
            ])
        </div>

        {{-- The page leaves action modals to the table on table pages, and the tree has no table. --}}
        <x-filament-actions::modals />
    @else
        {{ $this->table }}
    @endif
</x-filament-panels::page>
