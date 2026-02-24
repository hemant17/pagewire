<?php

namespace Hemant\Pagewire\Livewire\Admin\Menu;

use Hemant\Pagewire\Models\Menu;
use Hemant\Pagewire\Models\MenuItem;
use Hemant\Pagewire\Models\MenuLocationAssignment;
use Hemant\Pagewire\Models\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use Mary\Traits\Toast;

class Manager extends Component
{
    use Toast;

    public ?int $menuId = null;

    public string $newMenuName = '';

    public string $locationKey = '';

    public string $customTitle = '';

    public string $customUrl = '';

    public string $customTarget = '_self';

    public string $pageSearch = '';

    public function mount(): void
    {
        $menus = Menu::orderBy('name')->get();

        $created = false;
        if ($menus->isEmpty()) {
            $menu = Menu::create([
                'name' => 'Main Menu',
                'slug' => 'main-menu',
                'admin_id' => Auth::id(),
            ]);
            $this->menuId = $menu->id;
            $created = true;
        } else {
            $this->menuId = $menus->first()->id;
        }

        $locations = $this->locations();
        if ($this->locationKey === '' && count($locations) > 0) {
            $this->locationKey = array_key_first($locations) ?? '';
        }

        // First install experience: if we created the first menu, assign it to the first location.
        if ($created && $this->menuId !== null && $this->locationKey !== '') {
            MenuLocationAssignment::updateOrCreate(
                ['location_key' => $this->locationKey],
                ['menu_id' => $this->menuId]
            );
        }
    }

    public function locations(): array
    {
        $locations = (array) config('pagewire.menu.locations', []);
        $out = [];
        foreach ($locations as $key => $label) {
            if (is_int($key)) {
                $out[(string) $label] = (string) $label;
                continue;
            }
            $out[(string) $key] = (string) $label;
        }

        return $out;
    }

    public function getMenusProperty()
    {
        return Menu::orderBy('name')->get();
    }

    public function updatedMenuId(): void
    {
        // no-op, just re-render
    }

    public function createMenu(): void
    {
        $name = trim($this->newMenuName);
        if ($name === '') {
            $this->error('Menu name required.');

            return;
        }

        $slugBase = Str::slug($name);
        $slug = $slugBase;
        $i = 2;
        while (Menu::where('slug', $slug)->exists()) {
            $slug = $slugBase.'-'.$i;
            $i++;
        }

        $menu = Menu::create([
            'name' => $name,
            'slug' => $slug,
            'admin_id' => Auth::id(),
        ]);

        $this->menuId = $menu->id;
        $this->newMenuName = '';
        $this->success('Menu created.');
    }

    public function assignToLocation(): void
    {
        if ($this->menuId === null) {
            $this->error('Select a menu first.');

            return;
        }

        $key = trim($this->locationKey);
        if ($key === '') {
            $this->error('Select a location.');

            return;
        }

        MenuLocationAssignment::updateOrCreate(
            ['location_key' => $key],
            ['menu_id' => $this->menuId]
        );

        $this->success('Location assigned.');
    }

    public function addCustomItem(?int $parentId = null): void
    {
        if ($this->menuId === null) {
            $this->error('Select a menu first.');

            return;
        }

        $title = trim($this->customTitle);
        $url = trim($this->customUrl);

        if ($title === '' || $url === '') {
            $this->error('Title and URL are required.');

            return;
        }

        $sort = $this->nextSortOrder($parentId);

        MenuItem::create([
            'menu_id' => $this->menuId,
            'parent_id' => $parentId,
            'type' => 'custom',
            'title' => $title,
            'url' => $url,
            'target' => $this->customTarget ?: '_self',
            'sort_order' => $sort,
        ]);

        $this->customTitle = '';
        $this->customUrl = '';
        $this->customTarget = '_self';
        $this->success('Menu item added.');
    }

    public function addPageItem(int $pageId, ?int $parentId = null): void
    {
        if ($this->menuId === null) {
            $this->error('Select a menu first.');

            return;
        }

        $page = Page::find($pageId);
        if (! $page) {
            $this->error('Page not found.');

            return;
        }

        $routeName = (string) config('pagewire.menu.page_route_name', config('pagewire.route_names.dynamic', 'dynamic.page'));
        $url = null;
        try {
            $url = route($routeName, $page->slug);
        } catch (\Throwable $e) {
            $publicPrefix = trim((string) config('pagewire.public_prefix', 'pages'), '/');
            $url = ($publicPrefix !== '' ? '/'.$publicPrefix : '').'/'.$page->slug;
        }

        $sort = $this->nextSortOrder($parentId);

        MenuItem::create([
            'menu_id' => $this->menuId,
            'parent_id' => $parentId,
            'type' => 'page',
            'title' => $page->title,
            'url' => $url,
            'page_id' => $page->id,
            'target' => '_self',
            'sort_order' => $sort,
        ]);

        $this->success('Page added to menu.');
    }

    public function deleteItem(int $itemId): void
    {
        $item = MenuItem::where('menu_id', $this->menuId)->find($itemId);
        if (! $item) {
            return;
        }

        $this->deleteItemRecursive($item);
        $this->success('Menu item deleted.');
    }

    private function deleteItemRecursive(MenuItem $item): void
    {
        foreach ($item->children()->get() as $child) {
            $this->deleteItemRecursive($child);
        }
        $item->delete();
    }

    public function moveUp(int $itemId): void
    {
        $item = MenuItem::where('menu_id', $this->menuId)->find($itemId);
        if (! $item) {
            return;
        }

        $prev = MenuItem::where('menu_id', $this->menuId)
            ->where('parent_id', $item->parent_id)
            ->where('sort_order', '<', $item->sort_order)
            ->orderByDesc('sort_order')
            ->first();

        if (! $prev) {
            return;
        }

        $tmp = $item->sort_order;
        $item->sort_order = $prev->sort_order;
        $prev->sort_order = $tmp;
        $item->save();
        $prev->save();
    }

    public function moveDown(int $itemId): void
    {
        $item = MenuItem::where('menu_id', $this->menuId)->find($itemId);
        if (! $item) {
            return;
        }

        $next = MenuItem::where('menu_id', $this->menuId)
            ->where('parent_id', $item->parent_id)
            ->where('sort_order', '>', $item->sort_order)
            ->orderBy('sort_order')
            ->first();

        if (! $next) {
            return;
        }

        $tmp = $item->sort_order;
        $item->sort_order = $next->sort_order;
        $next->sort_order = $tmp;
        $item->save();
        $next->save();
    }

    public function indent(int $itemId): void
    {
        $item = MenuItem::where('menu_id', $this->menuId)->find($itemId);
        if (! $item) {
            return;
        }

        // Make it a child of the previous sibling (same parent).
        $prev = MenuItem::where('menu_id', $this->menuId)
            ->where('parent_id', $item->parent_id)
            ->where('sort_order', '<', $item->sort_order)
            ->orderByDesc('sort_order')
            ->first();

        if (! $prev) {
            return;
        }

        $item->parent_id = $prev->id;
        $item->sort_order = $this->nextSortOrder($prev->id);
        $item->save();
    }

    public function outdent(int $itemId): void
    {
        $item = MenuItem::where('menu_id', $this->menuId)->find($itemId);
        if (! $item || $item->parent_id === null) {
            return;
        }

        $parent = MenuItem::where('menu_id', $this->menuId)->find($item->parent_id);
        if (! $parent) {
            $item->parent_id = null;
            $item->save();

            return;
        }

        $item->parent_id = $parent->parent_id;
        $item->sort_order = $this->nextSortOrder($item->parent_id);
        $item->save();
    }

    private function nextSortOrder(?int $parentId): int
    {
        if ($this->menuId === null) {
            return 0;
        }

        $max = MenuItem::where('menu_id', $this->menuId)
            ->where('parent_id', $parentId)
            ->max('sort_order');

        return is_numeric($max) ? ((int) $max + 1) : 0;
    }

    public function getMenuItemsProperty(): array
    {
        if ($this->menuId === null) {
            return [];
        }

        $items = MenuItem::where('menu_id', $this->menuId)
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->get()
            ->keyBy('id');

        $children = [];
        foreach ($items as $it) {
            $parent = $it->parent_id ?: 0;
            $children[$parent] ??= [];
            $children[$parent][] = $it->id;
        }

        $out = [];
        $walk = function (int $parentId, int $depth) use (&$walk, &$out, $children, $items) {
            foreach ($children[$parentId] ?? [] as $id) {
                $out[] = [
                    'id' => $id,
                    'depth' => $depth,
                    'title' => $items[$id]->title,
                    'url' => $items[$id]->url,
                    'target' => $items[$id]->target,
                    'type' => $items[$id]->type,
                ];
                $walk($id, $depth + 1);
            }
        };
        $walk(0, 0);

        return $out;
    }

    public function getMenuTreeProperty(): array
    {
        if ($this->menuId === null) {
            return [];
        }

        $items = MenuItem::where('menu_id', $this->menuId)
            ->orderBy('sort_order')
            ->get()
            ->groupBy(fn ($it) => $it->parent_id ?: 0);

        $build = function (int $parentId) use (&$build, $items): array {
            $nodes = [];
            foreach ($items[$parentId] ?? [] as $it) {
                $nodes[] = [
                    'id' => (int) $it->id,
                    'title' => (string) $it->title,
                    'url' => (string) ($it->url ?? ''),
                    'target' => (string) $it->target,
                    'type' => (string) $it->type,
                    'children' => $build((int) $it->id),
                ];
            }

            return $nodes;
        };

        return $build(0);
    }

    public function reorder(int $parentId, array $orderedIds): void
    {
        if ($this->menuId === null) {
            return;
        }

        $parent = $parentId === 0 ? null : $parentId;

        $items = MenuItem::where('menu_id', $this->menuId)
            ->where('parent_id', $parent)
            ->get()
            ->keyBy('id');

        $pos = 0;
        foreach ($orderedIds as $id) {
            $id = (int) $id;
            if (! isset($items[$id])) {
                continue;
            }
            $items[$id]->sort_order = $pos;
            $items[$id]->save();
            $pos++;
        }
    }

    public function getPagesProperty()
    {
        $q = Page::query()->orderBy('title');
        if ($this->pageSearch !== '') {
            $s = '%'.$this->pageSearch.'%';
            $q->where(function ($qq) use ($s) {
                $qq->where('title', 'like', $s)->orWhere('slug', 'like', $s);
            });
        }

        return $q->limit(50)->get();
    }

    public function render()
    {
        $view = view('pagewire::livewire.admin.menu.manager');

        $layout = config('pagewire.layout');
        if ($layout === null && view()->exists('layouts.admin')) {
            // Sensible default for admin pages when the host app has an admin layout.
            $layout = 'layouts.admin';
        }

        return $layout ? $view->layout($layout) : $view;
    }
}
