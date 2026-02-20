<div>
    <x-toast />
<x-pagewire::assets />

<x-card>
    <x-header title="Menu Manager" subtitle="Create menus, assign them to locations, and build nested items" separator>
        <x-slot:actions>
            <div class="flex items-center gap-2">
                <x-select
                    wire:model.live="menuId"
                    :options="$this->menus->map(fn($m) => ['id' => $m->id, 'name' => $m->name])->toArray()"
                    option-label="name"
                    option-value="id"
                    placeholder="Select menu"
                />

                <x-input wire:model.defer="newMenuName" placeholder="New menu name" />
                <x-button primary icon="o-plus" wire:click="createMenu">Create</x-button>
            </div>
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left: Add items -->
        <div class="lg:col-span-1 space-y-4">
            <x-card>
                <x-slot:title>Assign Location</x-slot:title>
                <div class="space-y-3">
                    <x-select
                        label="Location"
                        wire:model="locationKey"
                        :options="collect($this->locations())->map(fn($label, $key) => ['id' => $key, 'name' => $label])->values()->toArray()"
                        option-label="name"
                        option-value="id"
                    />
                    <x-button class="btn-primary w-full" wire:click="assignToLocation">Assign selected menu</x-button>
                    <p class="text-xs text-gray-500">A location can have only one menu. A menu can be assigned to multiple locations.</p>
                </div>
            </x-card>

            <x-card>
                <x-slot:title>Add Custom Link</x-slot:title>
                <div class="space-y-3">
                    <x-input label="Title" wire:model.defer="customTitle" placeholder="Menu title" />
                    <x-input label="URL" wire:model.defer="customUrl" placeholder="https://example.com or /about" />
                    <x-select
                        label="Target"
                        wire:model.defer="customTarget"
                        :options="[
                            ['id' => '_self', 'name' => '_self'],
                            ['id' => '_blank', 'name' => '_blank'],
                            ['id' => '_parent', 'name' => '_parent'],
                            ['id' => '_top', 'name' => '_top'],
                        ]"
                        option-label="name"
                        option-value="id"
                    />
                    <x-button primary class="w-full" icon="o-plus" wire:click="addCustomItem">Add to menu</x-button>
                </div>
            </x-card>

            <x-card>
                <x-slot:title>Pages</x-slot:title>
                <div class="space-y-3">
                    <x-input wire:model.live="pageSearch" placeholder="Search pages..." />

                    <div class="max-h-96 overflow-y-auto divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white">
                        @forelse($this->pages as $p)
                            <div class="p-3 flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="text-sm font-medium text-gray-900 truncate">{{ $p->title }}</div>
                                    <div class="text-xs text-gray-500 truncate">/{{ $p->slug }}</div>
                                </div>
                                <x-button size="xs" icon="o-plus" class="btn-primary" wire:click="addPageItem({{ $p->id }})">Add</x-button>
                            </div>
                        @empty
                            <div class="p-3 text-sm text-gray-500">No pages found.</div>
                        @endforelse
                    </div>
                </div>
            </x-card>
        </div>

        <!-- Right: Menu structure -->
        <div class="lg:col-span-2">
            <x-card>
                <x-slot:title>Menu Items</x-slot:title>

                @if(count($this->menuTree) === 0)
                    <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-8 text-center text-gray-500">
                        No items yet. Add links or pages from the left panel.
                    </div>
                @else
                    @include('pagewire::livewire.admin.menu._tree', ['nodes' => $this->menuTree, 'parentId' => 0])
                @endif
            </x-card>
        </div>
    </div>
</x-card>
</div>