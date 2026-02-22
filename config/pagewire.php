<?php

return [
    'admin_middleware' => ['web', 'auth'],
    'admin_prefix' => 'admin/pages',
    'route_names' => [
        'index' => 'admin.pages.index',
        'builder' => 'admin.pages.builder',
        'dynamic' => 'dynamic.page',
    ],
    // Home page support.
    // To avoid conflicts with existing apps, the "/" route is NOT registered by default.
    // Enable it to render the page marked as home (or the fallback slug) at "/".
    'home' => [
        'register_route' => env('PAGEWIRE_REGISTER_HOME_ROUTE', false),
        'route_name' => 'dynamic.home',
        // Used when no page is explicitly marked as home.
        'fallback_slug' => 'home',
        // Only render published pages on the front-end.
        'require_published' => true,
    ],
    // Section templates are discovered by scanning these directories for `*.blade.php`.
    // Typically you place your front-end section partials in `resources/views/sections`.
    // If you publish package stubs, you can also use `resources/views/vendor/pagewire/sections`.
    'sections_paths' => [
        resource_path('views/sections'),
        resource_path('views/vendor/pagewire/sections'),
    ],
    // Defaults used by `php artisan pagewire:make-section`.
    'sections_make_path' => resource_path('views/sections'),
    'editor_make_path' => resource_path('views/livewire/pagewire/section-editors'),
    // Per-section defaults/repeater schemas live here (one PHP file per section).
    'definitions_path' => resource_path('pagewire/sections'),
    // External assets used by built-in editors (CDN). Disable if your app bundles these yourself.
    'cdn_assets' => [
        'enabled' => true,
        'styles' => [
            'https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css',
            'https://cdn.jsdelivr.net/npm/quill-resize-module@2.0.8/dist/resize.min.css',
            'https://cdn.jsdelivr.net/npm/quill-table-better@1/dist/quill-table-better.css',
            'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css',
            'https://cdn.jsdelivr.net/npm/photoswipe@5.4.3/dist/photoswipe.min.css',
        ],
        'scripts' => [
            'https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js',
            'https://cdn.jsdelivr.net/npm/quill-resize-module@2.0.8/dist/resize.min.js',
            'https://cdn.jsdelivr.net/npm/quill-table-better@1/dist/quill-table-better.js',
            'https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js',
            'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js',
            'https://cdn.jsdelivr.net/npm/photoswipe@5.4.3/dist/umd/photoswipe.umd.min.js',
        ],
    ],
    // Menu Manager
    'menu' => [
        'admin_prefix' => 'admin/menus',
        'route_names' => [
            'manager' => 'admin.menus.manager',
        ],
        // Register locations here. Key is stored in DB assignments.
        'locations' => [
            'header' => 'Header',
            'footer' => 'Footer',
        ],
        // Which route to use when adding a Page as a menu item.
        // Defaults to Pagewire's dynamic page route.
        'page_route_name' => 'dynamic.page',
    ],
    // Blade layout for Livewire pages (e.g., 'layouts.app'). Set to null to use caller/default.
    'layout' => null,
    // Table and model used for admin/user references on pages/global sections
    'user_table' => env('PAGEWIRE_USER_TABLE', 'users'),
    // User model for admin_id association
    'user_model' => env('PAGEWIRE_USER_MODEL', config('auth.providers.users.model') ?? 'App\\Models\\User'),
];
