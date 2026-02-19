<?php

namespace Hemant\Pagewire\Livewire\Admin\Page;

use Hemant\Pagewire\Models\Page;
use Hemant\Pagewire\Models\PageContent;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast, WithPagination;

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

        // Create duplicate page
        $newPage = Page::create([
            'title' => $originalPage->title.' (Copy)',
            'slug' => $originalPage->slug.'-copy-'.time(),
            'meta_description' => $originalPage->meta_description,
            'meta_keywords' => $originalPage->meta_keywords,
            'is_published' => false,
            'published_at' => null,
            'admin_id' => auth()->id(),
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
            'pages' => $this->pages(),
        ]);

        $layout = config('pagewire.layout');

        return $layout ? $view->layout($layout) : $view;
    }
}
