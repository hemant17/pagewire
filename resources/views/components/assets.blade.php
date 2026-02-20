@php
    $assets = config('pagewire.cdn_assets', []);
    $enabled = (bool) ($assets['enabled'] ?? false);
    $styles = $enabled ? (array) ($assets['styles'] ?? []) : [];
    $scripts = $enabled ? (array) ($assets['scripts'] ?? []) : [];
@endphp

@once
    @foreach($styles as $href)
        @if(is_string($href) && $href !== '')
            <link rel="stylesheet" href="{{ $href }}">
        @endif
    @endforeach

    @foreach($scripts as $src)
        @if(is_string($src) && $src !== '')
            <script src="{{ $src }}"></script>
        @endif
    @endforeach
@endonce

