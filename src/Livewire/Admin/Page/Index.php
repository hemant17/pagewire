<?php

namespace Hemant\Pagewire\Livewire\Admin\Page;

use Hemant\Pagewire\Models\Page;
use Hemant\Pagewire\Models\PageContent;
use Hemant\Pagewire\Traits\WithPermissions;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast, WithPagination, WithPermissions, AuthorizesRequests;

    public $search = '';

    public $perPage = 10;

    public $status = 'all'; // all, published, draft

    protected $queryString = [
        'search' => ['except' => ''],
        'perPage' => ['except' => ''],
        'status' => ['except' => ''],
    ];

    protected $listeners = ['pageDeleted' => '$refresh'];

    public function deletePage($pageId)
    {
        $page = Page::findOrFail($pageId);

        // Server-side authorization check
        $this->authorize('delete', $page);

        $page->contents()->delete();
        $page->delete();

        $this->dispatch('pageDeleted');
        $this->success(
            'Page Deleted',
            'Page has been deleted successfully!'
        );
    }

    public function togglePublish($pageId)
    {
        $page = Page::findOrFail($pageId);

        // Server-side authorization check
        $this->authorize('publish', $page);

        $page->update([
            'is_published' => ! $page->is_published,
            'published_at' => ! $page->is_published ? now() : null,
        ]);

        $status = $page->is_published ? 'published' : 'unpublished';
        $this->success(
            'Page Updated',
            "Page {$status} successfully!"
        );
    }

    public function duplicatePage($pageId)
    {
        $originalPage = Page::with('contents')->findOrFail($pageId);

        // Server-side authorization check
        $this->authorize('create', Page::class);

        // Create duplicate page
        $newPage = Page::create([
            'title' => $originalPage->title.' (Copy)',
            'slug' => $originalPage->slug.'-copy-'.time(),
            'meta_description' => $originalPage->meta_description,
            'meta_keywords' => $originalPage->meta_keywords,
            'is_published' => false,
            'published_at' => null,
            'user_id' => auth()->id(),
        ]);

        // Duplicate all page contents
        foreach ($originalPage->contents as $content) {
            PageContent::create([
                'page_id' => $newPage->id,
                'global_section_id' => $content->global_section_id,
                'is_global_override' => $content->is_global_override,
                'section_name' => $content->section_name,
                'content' => $content->content,
                'sort_order' => $content->sort_order,
            ]);
        }

        $this->success(
            'Page Duplicated',
            'Page has been duplicated successfully!'
        );
    }

    #[Computed]
    public function canCreatePages(): bool
    {
        return $this->can('create_pages');
    }

    #[Computed]
    public function actions(): array
    {
        $actions = [
            [
                'type' => 'link',
                'icon' => 'o-arrow-top-right-on-square',
                'title' => 'View Page',
                'route' => config('pagewire.route_names.dynamic', 'dynamic.page'),
                'target' => '_blank',
                'class' => 'text-gray-400 hover:text-gray-600',
            ],
        ];

        // Edit button - requires edit_pages permission
        if ($this->can('edit_pages')) {
            $actions[] = [
                'type' => 'link',
                'icon' => 'o-pencil',
                'title' => 'Edit',
                'route' => config('pagewire.route_names.builder', 'admin.pages.builder'),
                'class' => 'text-primary-600 hover:text-primary-900',
            ];
        }

        // Duplicate button - requires create_pages permission
        if ($this->can('create_pages')) {
            $actions[] = [
                'type' => 'button',
                'icon' => 'o-document-duplicate',
                'title' => 'Duplicate',
                'action' => 'duplicatePage',
                'confirm' => false,
                'class' => 'text-info-600 hover:text-info-900',
            ];
        }

        // Publish button - requires publish_pages permission
        if ($this->can('publish_pages')) {
            $actions[] = [
                'type' => 'button',
                'icon' => 'o-eye',
                'title' => 'Publish',
                'action' => 'togglePublish',
                'confirm' => false,
                'class' => 'text-success-600 hover:text-success-900',
                'condition' => fn ($page) => ! $page->is_published,
            ];
            $actions[] = [
                'type' => 'button',
                'icon' => 'o-eye-slash',
                'title' => 'Unpublish',
                'action' => 'togglePublish',
                'confirm' => false,
                'class' => 'text-warning-600 hover:text-warning-900',
                'condition' => fn ($page) => $page->is_published,
            ];
        }

        // Delete button - requires delete_pages permission
        if ($this->can('delete_pages')) {
            $actions[] = [
                'type' => 'button',
                'icon' => 'o-trash',
                'title' => 'Delete',
                'action' => 'deletePage',
                'confirm' => 'Are you sure you want to delete this page?',
                'class' => 'text-danger-600 hover:text-danger-900',
            ];
        }

        return $actions;
    }

    #[Computed]
    public function pages()
    {
        $query = Page::with('admin');

        // Apply status filter
        if ($this->status === 'published') {
            $query->published();
        } elseif ($this->status === 'draft') {
            $query->where('is_published', false);
        }

        // Apply search
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('title', 'like', '%'.$this->search.'%')
                    ->orWhere('slug', 'like', '%'.$this->search.'%');
            });
        }

        return $query->latest()->paginate($this->perPage);
    }

    public function render()
    {
        $view = view('pagewire::livewire.admin.page.index', [
            'pages' => $this->pages,
        ]);

        $layout = config('pagewire.layout');

        return $layout ? $view->layout($layout) : $view;
    }
}
