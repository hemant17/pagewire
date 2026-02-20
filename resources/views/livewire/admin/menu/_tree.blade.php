@props([
    'nodes' => [],
    'parentId' => 0,
])

<ul
    class="space-y-2"
    data-parent-id="{{ (int) $parentId }}"
    x-data="{ parent: {{ (int) $parentId }} }"
    x-init="
        if (typeof Sortable !== 'undefined' && ! $el._pwSortable) {
            $el._pwSortable = Sortable.create($el, {
                handle: '[data-pw-handle]',
                animation: 150,
                onEnd: () => {
                    const ids = Array.from($el.children).map(li => parseInt(li.dataset.id, 10)).filter(n => !Number.isNaN(n));
                    $wire.reorder(parent, ids);
                }
            });
        }
    "
>
    @foreach($nodes as $node)
        <li data-id="{{ $node['id'] }}">
            <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white p-3">
                <div class="flex items-center gap-3 min-w-0">
                    <span data-pw-handle class="cursor-move select-none text-gray-400" aria-hidden="true">⋮⋮</span>
                    <div class="min-w-0">
                        <div class="text-sm font-semibold text-gray-900 truncate">{{ $node['title'] }}</div>
                        <div class="text-xs text-gray-500 truncate">{{ $node['url'] }}</div>
                    </div>
                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-gray-100 text-gray-700">{{ $node['type'] }}</span>
                </div>

                <div class="flex items-center gap-1">
                    <x-button size="xs" icon="o-chevron-up" class="btn-ghost" wire:click="moveUp({{ $node['id'] }})" tooltip="Move up" />
                    <x-button size="xs" icon="o-chevron-down" class="btn-ghost" wire:click="moveDown({{ $node['id'] }})" tooltip="Move down" />
                    <x-button size="xs" icon="o-chevron-right" class="btn-ghost" wire:click="indent({{ $node['id'] }})" tooltip="Indent" />
                    <x-button size="xs" icon="o-chevron-left" class="btn-ghost" wire:click="outdent({{ $node['id'] }})" tooltip="Outdent" />
                    <x-button
                        size="xs"
                        icon="o-trash"
                        class="btn-ghost text-red-600"
                        onclick="return confirm('Delete this menu item (and its children)?')"
                        wire:click="deleteItem({{ $node['id'] }})"
                        tooltip="Delete"
                    />
                </div>
            </div>

            @if(!empty($node['children']))
                <div class="ml-6 mt-2">
                    @include('pagewire::livewire.admin.menu._tree', ['nodes' => $node['children'], 'parentId' => $node['id']])
                </div>
            @endif
        </li>
    @endforeach
</ul>
