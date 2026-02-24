@extends('layouts.app')

@section('content')
    <div class="container mx-auto py-8">
        <div class="grid grid-cols-12 gap-6">
            @foreach($page->contents as $content)
                @php
                    $span = (int) ($content->col_span ?? 12);
                    $spanClasses = [
                        12 => 'lg:col-span-12',
                        9 => 'lg:col-span-9',
                        8 => 'lg:col-span-8',
                        6 => 'lg:col-span-6',
                        4 => 'lg:col-span-4',
                        3 => 'lg:col-span-3',
                    ];
                    $colClass = $spanClasses[$span] ?? 'lg:col-span-12';
                    $sectionView = 'sections.' . $content->section_name;
                @endphp

                <div class="col-span-12 {{ $colClass }}">
                    @if(view()->exists($sectionView))
                        @include($sectionView, ['content' => $content->content])
                    @else
                        <div class="border rounded p-4 bg-gray-50">
                            <p class="text-sm text-gray-600">Missing front-end view for section: {{ $content->section_name }}</p>
                            <pre class="text-xs mt-2">{{ json_encode($content->content, JSON_PRETTY_PRINT) }}</pre>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endsection
