{{-- One level of the tree. Every Term has a child list, empty or not, so Terms can be dropped under a leaf. --}}
<ul
    @if ($canReorder)
        x-sortable
        x-sortable-group="taxonomy-terms"
        x-on:end.stop="$wire.moveTerm(
            $event.item.getAttribute('x-sortable-item'),
            $event.to.dataset.parentId || null,
            Array.from($event.to.querySelectorAll(':scope > [x-sortable-item]')).map((item) => item.getAttribute('x-sortable-item')),
        )"
    @endif
    data-parent-id="{{ $parentId }}"
    style="min-height: 0.5rem; {{ $parentId ? 'margin-inline-start: 1.75rem;' : '' }}"
>
    @foreach ($branches[$parentId ?? 0] ?? [] as $term)
        <li x-sortable-item="{{ $term->getKey() }}" wire:key="taxonomy-term-{{ $term->getKey() }}">
            <div style="display: flex; align-items: center; gap: 0.5rem; padding-block: 0.375rem; border-bottom: 1px solid var(--gray-200)">
                @if ($canReorder)
                    <button type="button" x-sortable-handle style="cursor: move" aria-label="{{ __('taxonomy::taxonomy.tree.move') }}">
                        <x-filament::icon icon="heroicon-m-bars-2" style="width: 1rem; height: 1rem; color: var(--gray-400)" />
                    </button>
                @endif

                <span>{{ $term->name }}</span>
                <span style="font-size: 0.875rem; color: var(--gray-500)">{{ $term->slug }}</span>

                <div style="margin-inline-start: auto; display: flex; gap: 0.25rem">
                    {{ ($this->editTermAction)(['term' => $term->getKey()]) }}
                    {{ ($this->deleteTermAction)(['term' => $term->getKey()]) }}
                </div>
            </div>

            @include('taxonomy::filament.partials.term-tree-branch', [
                'branches' => $branches,
                'parentId' => $term->getKey(),
                'canReorder' => $canReorder,
            ])
        </li>
    @endforeach
</ul>
