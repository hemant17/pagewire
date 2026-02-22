<div>
<x-toast />
@push('header')
    <x-pagewire::assets />
@endpush

<x-card x-data="{dragItem: null,dragOverItem: null,editingSection: null}">
    <x-header :title="$page ? 'Edit Page' : 'Build New Page'"
        subtitle="Create and arrange page sections with drag-and-drop" separator>
        <x-slot:actions>
            <x-button label="Back to Pages" icon="o-arrow-left" link="{{ route(config('pagewire.route_names.index', 'admin.pages.index')) }}" class="btn-primary"  />
        </x-slot:actions>
    </x-header>

    <!-- Builder Container -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- Sidebar -->

        <div class="lg:col-span-1">

            <!-- Global Sections -->
            <x-card class="mb-6">
                <x-slot:title>Global Sections</x-slot:title>
                <div class="space-y-2 max-h-64 overflow-y-auto">
                    @forelse($globalSections as $global)
                    <div class="p-3 border border-gray-200 rounded-lg bg-white hover:bg-gray-50 transition-colors">
                        <div class="flex items-center gap-3">
                            <div
                                class="h-8 w-8 rounded-md bg-primary-50 text-primary-700 flex items-center justify-center">
                                <span class="text-xs font-bold" aria-hidden="true">G</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-gray-900 truncate">{{ $global['name'] }}</p>
                                <p class="text-xs text-gray-500 truncate">
                                    {{ $availableSections[$global['section_name']]['name'] ?? $global['section_name'] }}
                                </p>
                            </div>
                            <button type="button" wire:click="addGlobalSection({{ $global['id'] }})"
                                class="text-xs font-medium px-2 py-1 rounded-md bg-primary-600 text-white hover:bg-primary-700">
                                Add
                            </button>
                        </div>
                    </div>
                    @empty
                    <p class="text-sm text-gray-500">No global sections yet.</p>
                    @endforelse
                </div>
                <p class="mt-2 text-xs text-gray-500">Global sections can be reused across pages.</p>
            </x-card>

            <!-- Available Sections -->
            <x-card>
                <x-slot:title>Available Sections</x-slot:title>
                    <div class="space-y-2 max-h-96 overflow-y-auto">
                    @forelse($availableSections as $key => $section)
                    <div class="p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-move transition-colors"
                        draggable="true" @dragstart="dragItem = '{{ $key }}'">
                        <div class="flex items-center">
                            <span class="text-gray-400 mr-3 select-none" aria-hidden="true">⋮⋮</span>
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $section['name'] }}</p>
                                <p class="text-xs text-gray-500">{{ $section['file'] }}</p>
                            </div>
                        </div>
                    </div>
                    @empty
                    <p class="text-sm text-gray-500">No sections found</p>
                    @endforelse
                </div>
                <p class="mt-2 text-xs text-gray-500">Drag sections to the page builder</p>
            </x-card>

            <!-- Page Settings -->
            <x-card class="mb-6">
                <x-slot:title>Page Settings</x-slot:title>
                <form wire:submit.prevent="save" class="space-y-4">
                    <x-input label="Title " wire:model.live="title" placeholder="Page title" required />

                    <x-input label="Slug " wire:model="slug" placeholder="page-slug" required />
                    <p class="mt-1 text-xs text-gray-500">URL: /{{ $slug }}</p>

                    <x-textarea label="Meta Description" wire:model="meta_description"
                        placeholder="SEO meta description" rows="2" maxlength="255" />

                    <x-input label="Meta Keywords" wire:model="meta_keywords"
                        placeholder="keyword1, keyword2, keyword3" />

                    <x-checkbox label="Set as Homepage (/) " wire:model="is_home" />
                    <x-checkbox label="Publish immediately" wire:model="is_published" />
                    <p class="mt-1 text-xs text-gray-500">Uncheck to save as draft</p>
                </form>
            </x-card>
        </div>

        <!-- Page Builder -->
        <div class="lg:col-span-3">
            <x-card>
                <x-slot:title>Page Builder</x-slot:title>
                <!-- Drop Zone -->
                <div class="min-h-[400px] border-2 border-dashed border-gray-300 rounded-lg p-4" @dragover.prevent="dragOverItem = 'page-builder'" @dragleave="dragOverItem = null" @drop.prevent="if (dragItem) {@this.call('addSection', dragItem);dragItem = null;dragOverItem = null;}" :class="{ 'border-primary-500 bg-primary-50': dragOverItem === 'page-builder' }">

                    <!-- Page Sections -->
                    <div class="space-y-4" id="page-builder">
                        @if (count($pageContents) === 0)
                        <div class="text-center py-12">
                            <p class="text-gray-500">Drag sections here to start building your page</p>
                        </div>
                        @endif
                        @forelse($pageContents as $index => $section)
                       <div class="relative group bg-white border border-gray-200 rounded-lg p-4 transition-all"
                            :class="editingSection === {{ $index }} ? 'cursor-default' : 'cursor-move'"
                            :draggable="editingSection !== {{ $index }}"
                            @dragstart="if (editingSection !== {{ $index }}) { dragItem = {{ $index }}; editingSection = null; }"
                            @dragover.prevent="dragOverItem = {{ $index }}; $el.classList.add('border-t-4', 'border-t-primary-500')"
                            @dragleave="dragOverItem = null; $el.classList.remove('border-t-4', 'border-t-primary-500')"
                            @drop.prevent="
                                        if (typeof dragItem === 'number') {
                                            @this.call('moveSection', dragItem, {{ $index }});
                                            dragItem = null;
                                            dragOverItem = null;
                                        }
                                    ">
                            <!-- Section Header -->
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center">
                                    <span x-show="editingSection !== {{ $index }}" class="text-gray-400 mr-3 select-none" aria-hidden="true">⋮⋮</span>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">
                                            {{ $availableSections[$section['section_name']]['name'] ??
                                            $section['section_name'] }}
                                        </p>
                                        <div class="flex items-center gap-2 text-xs text-gray-500">
                                            <span>Section {{ $index + 1 }}</span>
                                            @if(! empty($section['global_section_id']))
                                            <span
                                                class="px-2 py-0.5 rounded-full bg-blue-50 text-blue-700">Global</span>
                                            @if(! empty($section['is_global_override']))
                                            <span
                                                class="px-2 py-0.5 rounded-full bg-amber-50 text-amber-700">Override</span>
                                            @endif
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    @if(empty($section['global_section_id']))
                                        <x-button
                                            size="xs"
                                            icon="o-globe-alt"
                                            tooltip="Save as Global"
                                            wire:click="openSaveGlobalModal({{ $index }})"
                                        />
                                    @elseif(empty($section['is_global_override']))
                                        <x-button
                                            size="xs"
                                            icon="o-link-slash"
                                            tooltip="Override for this page"
                                            wire:click="overrideGlobalSection({{ $index }})"
                                        />
                                    @endif
                                    <x-button
                                        size="xs"
                                        icon="o-pencil"
                                        tooltip="Edit content"
                                        @click="editingSection = editingSection === {{ $index }} ? null : {{ $index }}"
                                    />
                                    <x-button
                                        size="xs"
                                        icon="o-trash"
                                        tooltip="Remove section"
                                        class="text-red-600"
                                        wire:click="removeSection({{ $index }})"
                                    />
                                </div>
                            </div>

                            <!-- Section Content Editor -->
                            <div x-show="editingSection === {{ $index }}" x-transition>
                                <div class="border-t pt-4">
                                    @if(! empty($section['global_section_id']) && empty($section['is_global_override']))
                                    <div
                                        class="mb-3 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-800">
                                        Editing this section will update the global section everywhere.
                                    </div>
                                    @endif
                                    <div wire:key="section-{{ $index }}">
                                        {{-- <!-- DEBUG: Section: {{ $section['section_name'] }}, Editor: {{ $editorView }}, Exists: {{ $editorExists ? 'YES' : 'NO' }} --> --}}
                                        @include($this->getEditorView($section['section_name']), ['index' => $index])
                                    </div>
                                </div>
                            </div>

                            <!-- Section Preview -->
                            <div x-show="editingSection !== {{ $index }}">
                                <div class="border border-gray-100 rounded p-3 bg-gray-50">
                                    <p class="text-xs text-gray-600 mb-2">Preview:</p>
                                    <div class="text-xs text-gray-500">
                                        {{ $section['section_name'] }} section with {{ count($section['content']) }}
                                        content fields
                                    </div>
                                    @if(! empty($section['global_section_id']))
                                    <div class="text-xs text-blue-600 mt-2">
                                        Global: {{ $globalSectionMap[$section['global_section_id']] ?? 'Global Section'
                                        }}
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div> 
                        @empty
                        <!-- Empty state handled above -->
                        @endforelse
                    </div>
                </div>
            </x-card>
            <!-- Actions -->
            <div class="flex space-x-3 mt-6">
                <x-button
                    outline
                    icon="o-document"
                    class="flex-1"
                    wire:click="saveAsDraft"
                >
                    Save as Draft
                </x-button>

                <x-button
                    primary
                    icon="o-paper-airplane"
                    class="flex-1"
                    wire:click="saveAndPublish"
                >
                    {{ $page ? 'Update & Publish' : 'Publish Page' }}
                </x-button>
            </div>
        </div>
    </div>

    <x-modal wire:model="showSaveGlobalModal" boxClass="max-w-lg">
        <x-slot:title>Save as Global Section</x-slot:title>
        <div class="space-y-4">
            <x-input label="Global Section Name" wire:model.defer="globalSectionName"
                placeholder="e.g., Home CTA Global" />
            <p class="text-xs text-gray-500">This section will be reusable across multiple pages.</p>
        </div>
        <x-slot:actions>
            <x-button label="Cancel" wire:click="$set('showSaveGlobalModal', false)" />
            <x-button label="Save Global" class="btn-primary" wire:click="saveSectionAsGlobal" />
        </x-slot:actions>
    </x-modal>
</x-card>
</div>
