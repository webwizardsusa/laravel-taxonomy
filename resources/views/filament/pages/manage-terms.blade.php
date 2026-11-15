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
    @else
        {{ $this->table }}
    @endif
</x-filament-panels::page>
