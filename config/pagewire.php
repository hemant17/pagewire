<?php

return [
    'admin_middleware' => ['web', 'auth'],
    'admin_prefix' => 'admin/pages',
    'route_names' => [
        'index' => 'admin.pages.index',
        'builder' => 'admin.pages.builder',
        'dynamic' => 'dynamic.page',
    ],
    // Blade layout for Livewire pages (e.g., 'layouts.app'). Set to null to use caller/default.
    'layout' => null,
    // Table and model used for admin/user references on pages/global sections
    'user_table' => env('PAGEWIRE_USER_TABLE', 'users'),
    // User model for admin_id association
    'user_model' => env('PAGEWIRE_USER_MODEL', config('auth.providers.users.model') ?? 'App\\Models\\User'),
];
