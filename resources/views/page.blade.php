@extends('layouts.app')

@section('content')
    <div class="container mx-auto py-8">
        <h1 class="text-3xl font-bold mb-6">{{ $page->title }}</h1>

        @foreach($page->contents as $content)
            @php $sectionView = 'sections.' . $content->section_name; @endphp
            @if(view()->exists($sectionView))
                @include($sectionView, ['content' => $content->content])
            @else
                <div class="border rounded p-4 mb-6 bg-gray-50">
                    <p class="text-sm text-gray-600">Missing front-end view for section: {{ $content->section_name }}</p>
                    <pre class="text-xs mt-2">{{ json_encode($content->content, JSON_PRETTY_PRINT) }}</pre>
                </div>
            @endif
        @endforeach
    </div>
@endsection
