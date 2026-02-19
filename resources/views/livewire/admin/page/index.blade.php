<x-card>
    <x-header title="Page Management" subtitle="Manage dynamic pages and their content" separator>
        <x-slot:actions>
            <x-button label="New Page" icon="o-plus" link="{{ route(config('pagewire.route_names.builder', 'admin.pages.builder')) }}" />
        </x-slot:actions>
    </x-header>

    @php
        $headers = [
            ['key' => 'title', 'label' => 'Title'],
            ['key' => 'slug', 'label' => 'Slug'],
            ['key' => 'is_published', 'label' => 'Status'],
            ['key' => 'updated_at', 'label' => 'Updated'],
        ];
    @endphp

    <div class="mt-4">
        <x-table :headers="$headers" :rows="$pages" with-pagination>
            @scope('cell_title', $page)
                <div class="font-medium text-gray-900">{{ $page->title }}</div>
            @endscope

            @scope('cell_slug', $page)
                <span class="text-gray-600">/{{ $page->slug }}</span>
            @endscope

            @scope('cell_is_published', $page)
                @if($page->is_published)
                    <span class="px-2 py-1 text-xs font-semibold text-green-700 bg-green-100 rounded-full">Published</span>
                @else
                    <span class="px-2 py-1 text-xs font-semibold text-amber-700 bg-amber-100 rounded-full">Draft</span>
                @endif
            @endscope

            @scope('cell_updated_at', $page)
                <span class="text-gray-600">{{ $page->updated_at?->format('M j, Y H:i') }}</span>
            @endscope

            @scope('actions', $page)
                <div class="flex items-center gap-2">
                    <x-button icon="o-arrow-top-right-on-square" link="{{ route(config('pagewire.route_names.dynamic', 'dynamic.page'), $page->slug) }}" target="_blank" class="btn-ghost btn-sm" tooltip="View" />

                    @if($this->can('edit_pages'))
                        <x-button icon="o-pencil" link="{{ route(config('pagewire.route_names.builder', 'admin.pages.builder'), $page->slug) }}" class="btn-ghost btn-sm text-primary-600" tooltip="Edit" />
                    @endif

                    @if($this->can('create_pages'))
                        <x-button icon="o-document-duplicate" wire:click="duplicatePage({{ $page->id }})" class="btn-ghost btn-sm text-info-600" tooltip="Duplicate" />
                    @endif

                    @if($this->can('publish_pages'))
                        <x-button :icon="$page->is_published ? 'o-eye-slash' : 'o-eye'" wire:click="togglePublish({{ $page->id }})" class="btn-ghost btn-sm text-success-600" tooltip="{{ $page->is_published ? 'Unpublish' : 'Publish' }}" />
                    @endif

                    @if($this->can('delete_pages'))
                        <x-button icon="o-trash" wire:click="deletePage({{ $page->id }})" class="btn-ghost btn-sm text-red-600" tooltip="Delete" />
                    @endif
                </div>
            @endscope
        </x-table>
    </div>
</x-card>
