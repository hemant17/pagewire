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

    <script>
        (() => {
            if (!window.toolbarOptions) {
                window.toolbarOptions = [
                    [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    ['blockquote', 'code-block'],
                    ['table-better'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }, { 'list': 'check' }],
                    [{ 'script': 'sub'}, { 'script': 'super' }],
                    [{ 'indent': '-1'}, { 'indent': '+1' }],
                    [{ 'direction': 'rtl' }],
                    [{ 'size': ['small', false, 'large', 'huge'] }],
                    [{ 'color': [] }, { 'background': [] }],
                    [{ 'font': [] }],
                    [{ 'align': [] }],
                    ['link', 'image', 'video', 'formula'],
                    ['clean']
                ];
            }

            if (!window.settings) {
                window.settings = {
                    theme: 'snow',
                    modules: {
                        resize: {
                            modules: ['DisplaySize', 'Toolbar', 'Resize', 'Keyboard'],
                            keyboardSelect: true,
                            selectedClass: 'selected',
                            activeClass: 'active',
                            embedTags: ['VIDEO', 'IFRAME'],
                            tools: ['left', 'center', 'right', 'full', 'edit'],
                            parchment: {
                                image: {
                                    attribute: ['width'],
                                    limit: {
                                        minWidth: 100
                                    }
                                },
                                video: {
                                    attribute: ['width', 'height'],
                                    limit: {
                                        minWidth: 200,
                                        ratio: 0.5625
                                    }
                                }
                            },
                        },
                        toolbar: {
                            container: window.toolbarOptions,
                            handlers: {
                                image: function() {
                                    selectLocalImage();
                                }
                            }
                        },
                        'table-better': {
                            language: 'en_US',
                            menus: ['column', 'row', 'merge', 'table', 'cell', 'wrap', 'copy', 'delete'],
                            toolbarTable: true
                        },
                    }
                };
            }

            if (!window.__quillRegistered) {
                Quill.register({'modules/table-better': QuillTableBetter}, true);
                window.__quillRegistered = true;
            }
        })();
    </script>
@endonce

