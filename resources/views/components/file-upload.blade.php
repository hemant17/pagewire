@props([
    'id' => null,
    'label' => 'Upload Image',
    'accept' => 'image/jpeg,image/jpg,image/png,image/gif',
    'preview' => true,
    'aspectRatio' => 1,
    'cropConfig' => [],
    'placeholder' => null,
])

@php
    // Keep modifiers like wire:model.live by passing the wire:model* attributes through.
    $wireModel = $attributes->wire('model')?->__toString();

    // Try to read the bound value from Livewire scope for preview.
    $scope = (isset($__livewire) && is_object($__livewire)) ? $__livewire : $this;
    $value = $wireModel ? data_get($scope, $wireModel) : null;

    if (is_array($value)) {
        $collection = collect($value);

        $value = $collection->first(fn ($v) => $v instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile)
            ?? $collection->first(fn ($v) => is_string($v) && filled($v));
    }

    $config = [
        'viewMode' => 1,
        'dragMode' => 'move',
        'autoCropArea' => 1,
        'background' => false,
        'guides' => true,
        'responsive' => true,
    ];

    if ($aspectRatio !== null) {
        $config['aspectRatio'] = $aspectRatio;
    }

    if (! empty($cropConfig)) {
        $config = array_merge($config, $cropConfig);
    }

    if (array_key_exists('aspectRatio', $config) && $config['aspectRatio'] === null) {
        unset($config['aspectRatio']);
    }

    $placeholderSrc = $placeholder ?? '';
@endphp

<div class="space-y-2">
    <x-file
        {{ $attributes->whereStartsWith('wire:model') }}
        id="{{ $id }}"
        label="{{ $label }}"
        accept="{{ $accept }}"
        crop-after-change
        :crop-config="$config"
    >
        @if($preview)
            @if($value && ! is_object($value))
                <img src="{{ $value }}" class="h-40 w-full rounded-lg object-cover" alt="Preview" />
            @elseif($placeholderSrc !== '')
                <img src="{{ $placeholderSrc }}" class="h-40 w-full rounded-lg object-cover" alt="Preview" />
            @else
                <div class="h-40 w-full rounded-lg bg-base-200 flex items-center justify-center text-base-content/60">
                    No image
                </div>
            @endif
        @endif
    </x-file>

    @if($wireModel && $value && ! is_object($value))
        <div class="flex justify-end">
            <x-button
                icon="o-trash"
                class="btn-ghost btn-xs text-red-600"
                label="Remove"
                wire:click="$set('{{ $wireModel }}', null)"
            />
        </div>
    @endif
</div>

