<div>
    <!-- Hero Section Editor -->
    <div class="space-y-5">
        <!-- Single Slider Mode Toggle -->
        <div class="flex items-center gap-3">
            <x-checkbox
                id="hero_single_mode_{{ $index }}"
                wire:model.live="pageContents.{{ $index }}.content.use_single_slider"
                label="Use Single Slider Mode (disable for multiple sliders)"
            />
        </div>

        @if(!empty($pageContents[$index]['content']['use_single_slider']))
            <!-- Single Slider Mode -->
            <x-card class="bg-primary-50/50 border-primary-100">
                <x-slot:title>Single Slider Configuration</x-slot:title>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-input
                        label="Background Image"
                        type="file"
                        accept="image/*"
                        wire:model.live="pageContents.{{ $index }}.content.background_image"
                    />

                    <x-input
                        label="Icon (class)"
                        wire:model.live="pageContents.{{ $index }}.content.icon"
                        placeholder="e.g. fas fa-solar-panel"
                    />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <x-input
                        label="Title"
                        wire:model.live="pageContents.{{ $index }}.content.title"
                        placeholder="We Provide Best Solar & Renewable Energy For You"
                    />
                    <x-input
                        label="Subtitle"
                        wire:model.live="pageContents.{{ $index }}.content.subtitle"
                        placeholder="Easy and reliable"
                    />
                </div>

                <div class="mt-4">
                    <x-textarea
                        label="Description"
                        rows="3"
                        wire:model.live="pageContents.{{ $index }}.content.description"
                        placeholder="There are many variations of passages available but the majority have suffered alteration..."
                    />
                </div>

                <!-- Buttons -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <x-input
                        label="Button 1 Text"
                        wire:model.live="pageContents.{{ $index }}.content.button1_text"
                        placeholder="About More"
                    />
                    <x-input
                        label="Button 1 URL"
                        wire:model.live="pageContents.{{ $index }}.content.button1_url"
                        placeholder="/about"
                    />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <x-input
                        label="Button 2 Text"
                        wire:model.live="pageContents.{{ $index }}.content.button2_text"
                        placeholder="Learn More"
                    />
                    <x-input
                        label="Button 2 URL"
                        wire:model.live="pageContents.{{ $index }}.content.button2_url"
                        placeholder="/contact"
                    />
                </div>
            </x-card>
        @else
            <!-- Multiple Sliders Mode -->
            <x-card class="bg-emerald-50/60 border-emerald-100">
                <x-slot:title>Multiple Sliders Configuration</x-slot:title>
                <p class="text-sm text-emerald-700 mb-4">Configure multiple hero sliders that will rotate automatically</p>

                <!-- Sliders List -->
                @php
                    $sliders = $pageContents[$index]['content']['sliders'] ?? [];
                @endphp
                <div class="space-y-3 mb-4">
                    @forelse($sliders as $slider)
                        @php
                            $sliderIndex = $loop->index;
                        @endphp
                        <x-card class="border border-gray-200 shadow-sm">
                            <div class="flex items-center justify-between mb-3">
                                <h5 class="font-medium text-gray-900">Slider {{ $sliderIndex + 1 }}</h5>
                                <x-button icon="o-trash" label="Remove"
                                    class="px-3 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-md"
                                    wire:click="removeHeroSlider({{ $index }}, {{ $sliderIndex }})" />
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <x-input
                                    label="Background Image"
                                    type="file"
                                    accept="image/*"
                                    wire:model.live="pageContents.{{ $index }}.content.sliders.{{ $sliderIndex }}.background_image"
                                />
                                <x-input
                                    label="Icon (class)"
                                    wire:model.live="pageContents.{{ $index }}.content.sliders.{{ $sliderIndex }}.icon"
                                    placeholder="e.g. fas fa-solar-panel"
                                />
                            </div>

                            <div class="grid grid-cols-1 md/grid-cols-2 gap-4 mt-4">
                                <x-input
                                    label="Title"
                                    wire:model.live="pageContents.{{ $index }}.content.sliders.{{ $sliderIndex }}.title"
                                    placeholder="Slider title"
                                />
                                <x-input
                                    label="Subtitle"
                                    wire:model.live="pageContents.{{ $index }}.content.sliders.{{ $sliderIndex }}.subtitle"
                                    placeholder="Slider subtitle"
                                />
                            </div>

                            <div class="mt-4">
                                <x-textarea
                                    label="Description"
                                    rows="3"
                                    wire:model.live="pageContents.{{ $index }}.content.sliders.{{ $sliderIndex }}.description"
                                    placeholder="Slider description"
                                />
                            </div>

                            <!-- Buttons for this slider -->
                            <div class="grid grid-cols-1 md/grid-cols-2 gap-4 mt-4">
                                <x-input
                                    label="Button 1 Text"
                                    wire:model.live="pageContents.{{ $index }}.content.sliders.{{ $sliderIndex }}.button1_text"
                                    placeholder="Button text"
                                />
                                <x-input
                                    label="Button 1 URL"
                                    wire:model.live="pageContents.{{ $index }}.content.sliders.{{ $sliderIndex }}.button1_url"
                                    placeholder="/about"
                                />
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                <x-input
                                    label="Button 2 Text"
                                    wire:model.live="pageContents.{{ $index }}.content.sliders.{{ $sliderIndex }}.button2_text"
                                    placeholder="Button text"
                                />
                                <x-input
                                    label="Button 2 URL"
                                    wire:model.live="pageContents.{{ $index }}.content.sliders.{{ $sliderIndex }}.button2_url"
                                    placeholder="/contact"
                                />
                            </div>
                        </x-card>
                    @empty
                        <p class="text-gray-500 text-sm">No sliders added yet. Add your first slider below.</p>
                    @endforelse
                </div>

                <x-button
                    class="w-full px-4 py-2 text-sm font-semibold text-white bg-primary-600 hover:bg-primary-700 rounded-md"
                    icon="o-plus"
                    label="Add New Slider"
                    wire:click="addHeroSlider({{ $index }})" />
            </x-card>
        @endif
    </div>
</div>
