<?php

use Hemant\Pagewire\Livewire\Admin\Page\Builder;
use Hemant\Pagewire\Livewire\Admin\Page\Index;
use Hemant\Pagewire\Livewire\Admin\Menu\Manager as MenuManager;
use Hemant\Pagewire\Models\Page;
use Illuminate\Support\Facades\Route;

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
Route::middleware('web')
    ->name('dynamic.page')
    ->get('/pages/{slug}', function ($slug) {
        /** @var Page|null $page */
        $page = Page::with(['contents' => function ($q) {
            $q->orderBy('sort_order');
        }, 'contents.globalSection'])->where('slug', $slug)->where('is_published', true)->firstOrFail();

        return view('pagewire::page', [
            'page' => $page,
        ]);
    });
