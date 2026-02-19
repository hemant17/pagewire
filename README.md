# Pagewire

Livewire 4 page builder package for Laravel 12 using Mary UI components. Inspired by existing implementation with reusable/global sections.

## Installation

```bash
composer require hemant/pagewire
php artisan pagewire:install
php artisan vendor:publish --tag=pagewire-config --tag=pagewire-migrations --tag=pagewire-views
php artisan migrate
```

## Routes
- Admin index: `admin/pages` (name: `admin.pages.index`)
- Builder: `admin/pages/builder/{slug?}` (name: `admin.pages.builder`)
- Public page: `/pages/{slug}` (name: `dynamic.page`)

You can change prefix/middleware via `config/pagewire.php`.

## Layout
Set `layout` in `config/pagewire.php` (e.g., `'layout' => 'layouts.app'`) to force the Livewire pages to use a specific Blade layout. Leave it `null` to let the caller/default layout apply.

## Sections
Builder lists templates by scanning `config('pagewire.sections_paths')` (defaults include `resources/views/sections/*.blade.php`). Provide matching section editor partials at `resources/views/livewire/admin/page/section-editors/{section}.blade.php` (or rely on the package defaults like the `hero-area` example).

Quick scaffolding:
```bash
php artisan pagewire:make-section hero-area
```
This creates:
- Front-end section: `resources/views/sections/hero-area.blade.php`
- Builder editor override (namespaced): `resources/views/livewire/pagewire/section-editors/hero-area.blade.php`

## Dependencies
- Laravel ^12
- Livewire ^4
- [Mary UI](https://github.com/robsontenorio/mary)
- [spatie/livewire-filepond](https://github.com/spatie/livewire-filepond)

## Models
The package ships `Page`, `PageContent`, and `GlobalSection` models plus migrations. `admin_id`, `created_by`, and `updated_by` reference your default user provider; override via `PAGEWIRE_USER_MODEL` env/config if needed.

## Livewire components
- `pagewire.admin.page.index` – list, search, filter, duplicate, publish toggle, delete.
- `pagewire.admin.page.builder` – drag/drop sections, global reuse/override, publish/draft, file uploads.

## Rendering public pages
The included `pagewire::page` view loops the stored sections and includes `sections.{section_name}`. Provide your front-end section blades there.
