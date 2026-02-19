<?php

return [
    'admin_middleware' => ['web', 'auth'],
    'admin_prefix' => 'admin/pages',
    'route_names' => [
        'index' => 'admin.pages.index',
        'builder' => 'admin.pages.builder',
        'dynamic' => 'dynamic.page',
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
    // Blade layout for Livewire pages (e.g., 'layouts.app'). Set to null to use caller/default.
    'layout' => null,
    // Table and model used for admin/user references on pages/global sections
    'user_table' => env('PAGEWIRE_USER_TABLE', 'users'),
    // User model for admin_id association
    'user_model' => env('PAGEWIRE_USER_MODEL', config('auth.providers.users.model') ?? 'App\\Models\\User'),
];
