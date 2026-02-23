<?php

use Hemant\Pagewire\Livewire\Admin\Page\Builder;
use Hemant\Pagewire\Livewire\Admin\Page\Index;
use Hemant\Pagewire\Livewire\Admin\Menu\Manager as MenuManager;
use Hemant\Pagewire\Models\Page;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

Route::middleware(config('pagewire.admin_middleware', ['web']))
    ->prefix(config('pagewire.admin_prefix', 'admin/pages'))
    ->name('admin.pages.')
    ->group(function () {
        Route::get('/', Index::class)->name('index');
        Route::get('/builder/{slug?}', Builder::class)->name('builder');
    });

Route::middleware(config('pagewire.admin_middleware', ['web']))
    ->prefix(config('pagewire.menu.admin_prefix', 'admin/menus'))
    ->group(function () {
        Route::get('/', MenuManager::class)->name(config('pagewire.menu.route_names.manager', 'admin.menus.manager'));
    });

// Public dynamic page renderer (optional)
$publicPrefix = trim((string) config('pagewire.public_prefix', 'pages'), '/');
$dynamicRouteName = config('pagewire.route_names.dynamic', 'dynamic.page');

if ($publicPrefix === '') {
    // "/{slug}" should never shadow app routes, so make it a fallback route.
    Route::middleware('web')
        ->get('/{slug}', function ($slug) {
            /** @var Page|null $page */
            $page = Page::with(['contents' => function ($q) {
                $q->orderBy('sort_order');
            }, 'contents.globalSection'])->where('slug', $slug)->where('is_published', true)->firstOrFail();

            return view('pagewire::page', [
                'page' => $page,
            ]);
        })
        ->where('slug', '[^/]+')
        ->fallback()
        ->name($dynamicRouteName);
} else {
    Route::middleware('web')
        ->name($dynamicRouteName)
        ->get('/'.$publicPrefix.'/{slug}', function ($slug) {
            /** @var Page|null $page */
            $page = Page::with(['contents' => function ($q) {
                $q->orderBy('sort_order');
            }, 'contents.globalSection'])->where('slug', $slug)->where('is_published', true)->firstOrFail();

            return view('pagewire::page', [
                'page' => $page,
            ]);
        });
}

// Optional home page renderer at "/"
if (config('pagewire.home.register_route', false)) {
    Route::middleware('web')
        ->name(config('pagewire.home.route_name', 'dynamic.home'))
        ->get('/', function () {
            $query = Page::with(['contents' => function ($q) {
                $q->orderBy('sort_order');
            }, 'contents.globalSection']);

            if (config('pagewire.home.require_published', true)) {
                $query->where('is_published', true);
            }

            // Prefer the explicitly-marked homepage when the column exists.
            $page = null;
            if (Schema::hasColumn('pages', 'is_home')) {
                $page = (clone $query)->where('is_home', true)->first();
            }

            // Fallback to a conventional slug like "home".
            if (! $page) {
                $fallbackSlug = (string) config('pagewire.home.fallback_slug', 'home');
                if ($fallbackSlug !== '') {
                    $page = (clone $query)->where('slug', $fallbackSlug)->first();
                }
            }

            abort_unless($page, 404);

            return view('pagewire::page', [
                'page' => $page,
            ]);
        });
}
