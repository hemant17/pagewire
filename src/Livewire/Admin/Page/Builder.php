<?php

namespace Hemant\Pagewire\Livewire\Admin\Page;

use Hemant\Pagewire\Models\GlobalSection;
use Hemant\Pagewire\Models\Page;
use Hemant\Pagewire\Models\PageContent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Mary\Traits\Toast;
use Spatie\LivewireFilepond\WithFilePond;

class Builder extends Component
{
    use Toast, WithFilePond;

    public $page;

    public $title = '';

    public $slug = '';

    public $meta_description = '';

    public $meta_keywords = '';

    public $is_published = false;

    public $is_home = false;

    public $published_at = null;

    public $availableSections = [];

    public $selectedSections = [];

    public $pageContents = [];

    public $globalSections = [];

    public $globalSectionMap = [];

    public bool $showSaveGlobalModal = false;

    public ?int $saveGlobalSectionIndex = null;

    public string $globalSectionName = '';

    // Dynamic file upload properties
    protected $fileUploads = [];

    protected function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:pages,slug,'.$this->page?->id,
            'meta_description' => 'nullable|string|max:255',
            'meta_keywords' => 'nullable|string|max:255',
            'is_published' => 'boolean',
            'is_home' => 'boolean',
        ];
    }

    public function mount($slug = null)
    {
        if ($slug) {
            // Edit mode
            $this->page = Page::with(['contents.globalSection'])->where('slug', $slug)->first();
            $this->title = $this->page->title;
            $this->slug = $this->page->slug;
            $this->meta_description = $this->page->meta_description;
            $this->meta_keywords = $this->page->meta_keywords;
            $this->is_published = $this->page->is_published;
            $this->is_home = (bool) ($this->page->is_home ?? false);
            $this->published_at = $this->page->published_at;

            // Load existing sections
            foreach ($this->page->contents as $content) {
                $contentPayload = $content->content;
                if ($content->global_section_id && ! $content->is_global_override && $content->globalSection) {
                    $contentPayload = $content->globalSection->content;
                }
                $this->pageContents[] = [
                    'id' => $content->id,
                    'section_name' => $content->section_name,
                    'content' => $this->convertExistingContent($contentPayload),
                    'sort_order' => $content->sort_order,
                    'global_section_id' => $content->global_section_id,
                    'is_global_override' => (bool) $content->is_global_override,
                ];
            }
        }
    }

    public function boot()
    {
        // Get all available section templates
        $this->availableSections = $this->getAvailableSections();
        $this->loadGlobalSections();
    }

    private function getAvailableSections(): array
    {
        $sections = [];

        $paths = config('pagewire.sections_paths', [resource_path('views/sections')]);

        foreach ($paths as $sectionsPath) {
            if (! is_string($sectionsPath) || ! is_dir($sectionsPath)) {
                continue;
            }

            $files = glob(rtrim($sectionsPath, '/')."/*.blade.php") ?: [];

            foreach ($files as $file) {
                $filename = basename($file, '.blade.php');
                $displayName = $this->formatSectionName($filename);

                // Later paths override earlier ones if filenames collide.
                $sections[$filename] = [
                    'name' => $displayName,
                    'file' => $filename,
                    'path' => 'sections.'.$filename,
                ];
            }
        }

        ksort($sections);

        return $sections;
    }

    private function formatSectionName($filename): string
    {
        return ucwords(str_replace(['-', '_'], ' ', str_replace('-area', '', $filename)));
    }

    public function addSection($sectionName)
    {
        $this->pageContents[] = [
            'id' => null,
            'section_name' => $sectionName,
            'content' => $this->getDefaultSectionContent($sectionName),
            'sort_order' => count($this->pageContents),
            'global_section_id' => null,
            'is_global_override' => false,
        ];

        $this->sortSectionsByOrder();
    }

    public function addGlobalSection(int $globalSectionId): void
    {
        $global = GlobalSection::find($globalSectionId);
        if (! $global) {
            $this->error('Global Section not found.');

            return;
        }

        $this->pageContents[] = [
            'id' => null,
            'section_name' => $global->section_name,
            'content' => $this->convertExistingContent($global->content ?? []),
            'sort_order' => count($this->pageContents),
            'global_section_id' => $global->id,
            'is_global_override' => false,
        ];

        $this->sortSectionsByOrder();
    }

    public function openSaveGlobalModal(int $index): void
    {
        if (! isset($this->pageContents[$index])) {
            return;
        }

        $section = $this->pageContents[$index];
        $sectionLabel = $this->availableSections[$section['section_name']]['name'] ?? $section['section_name'];
        $this->globalSectionName = $sectionLabel.' Global';
        $this->saveGlobalSectionIndex = $index;
        $this->showSaveGlobalModal = true;
    }

    public function saveSectionAsGlobal(): void
    {
        $index = $this->saveGlobalSectionIndex;
        if ($index === null || ! isset($this->pageContents[$index])) {
            $this->showSaveGlobalModal = false;

            return;
        }

        $name = trim($this->globalSectionName);
        if ($name === '') {
            $this->error('Please enter a global section name.');

            return;
        }

        $section = $this->pageContents[$index];
        $admin = Auth::user();

        $global = GlobalSection::create([
            'name' => $name,
            'section_name' => $section['section_name'],
            'content' => $this->processFileUploads($section['content']),
            'created_by' => $admin?->id,
            'updated_by' => $admin?->id,
        ]);

        $this->pageContents[$index]['global_section_id'] = $global->id;
        $this->pageContents[$index]['is_global_override'] = false;

        $this->showSaveGlobalModal = false;
        $this->saveGlobalSectionIndex = null;
        $this->globalSectionName = '';

        $this->loadGlobalSections();

        $this->success('Global Section Saved', 'This section is now available globally.');
    }

    public function overrideGlobalSection(int $index): void
    {
        if (! isset($this->pageContents[$index])) {
            return;
        }

        $this->pageContents[$index]['is_global_override'] = true;
        $this->success('Override Enabled', 'This section will now be editable only on this page.');
    }

    private function getDefaultSectionContent($sectionName): array
    {
        $definition = $this->getSectionDefinition($sectionName);
        if (isset($definition['defaults']) && is_array($definition['defaults'])) {
            return $definition['defaults'];
        }

        // Back-compat starter defaults for the bundled hero editor.
        return [
            'use_single_slider' => true,
            'background_image' => '',
            'icon' => 'far fa-solar-panel',
            'title' => 'We Provide Best Solar & <span>Renewable</span> Energy For You',
            'subtitle' => 'Easy and reliable',
            'description' => 'There are many variations of passages orem psum available but the majority have suffered alteration in some form by injected humour.',
            'button1_text' => 'About More',
            'button1_url' => '/about',
            'button2_text' => 'Learn More',
            'button2_url' => '/contact',
            'sliders' => [],
        ];
    }

    private function getSectionDefinition(string $sectionName): array
    {
        $base = config('pagewire.definitions_path', resource_path('pagewire/sections'));
        if (! is_string($base) || $base === '') {
            $base = resource_path('pagewire/sections');
        }

        $file = rtrim($base, '/')."/{$sectionName}.php";
        if (is_file($file)) {
            $data = require $file;
            if (is_array($data)) {
                return $data;
            }
        }

        return [];
    }

    private function getRepeaterDefaultItem(string $sectionName, string $key): mixed
    {
        $definition = $this->getSectionDefinition($sectionName);
        $repeaters = $definition['repeaters'] ?? null;

        if (is_array($repeaters) && array_key_exists($key, $repeaters)) {
            return $repeaters[$key];
        }

        // Fallback stubs to keep the bundled builder working even without a definition file.
        $fallback = [
            'features' => [
                'icon' => 'staff',
                'title' => 'New Feature',
                'description' => 'Feature description',
            ],
            'sliders' => [
                'background_image' => '',
                'icon' => 'far fa-solar-panel',
                'subtitle' => 'Professional Service',
                'title' => 'Save Money With <span>Solar Energy</span> Solutions',
                'description' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore.',
                'button1_text' => 'Our Services',
                'button1_url' => '/services',
                'button2_text' => 'Get Quote',
                'button2_url' => '/contact',
            ],
            'testimonials' => [
                'author_name' => '',
                'author_position' => '',
                'author_image' => null,
                'rating' => 5,
                'testimonial_text' => '',
            ],
            'team_members' => [
                'name' => '',
                'position' => '',
                'image' => null,
                'social_links' => [],
            ],
            'team_members.social_links' => [
                'platform' => '',
                'url' => '',
            ],
            'portfolio_items' => [
                'title' => '',
                'category' => '',
                'image' => null,
                'project_url' => '',
            ],
            'steps' => [
                'icon' => 'hybrid',
                'title' => 'New Step',
                'description' => 'Step description',
            ],
            'skills' => [
                'name' => 'New Skill',
                'percentage' => 50,
            ],
            'counters' => [
                'icon' => 'solar',
                'number' => '100',
                'number_suffix' => '+',
                'title' => 'New Counter',
            ],
            'locations' => [
                'name' => 'New Location',
                'address' => '',
                'phone' => '',
                'email' => '',
                'lat' => '',
                'lng' => '',
                'icon' => 'fas fa-map-marker-alt',
                'active' => true,
                'embed_url' => '',
                'marker_icon' => null,
                'directions_url' => '',
            ],
            'items' => [
                'icon' => 'star',
                'title' => 'New Item',
                'description' => 'Item description',
            ],
            'contact_items' => [
                'icon' => 'map-marker-alt',
                'title' => 'New Contact Item',
                'content' => 'Contact information',
            ],
            'slider_images' => '',
            'partners' => [
                'image' => null,
                'alt' => '',
            ],
        ];

        return $fallback[$key] ?? [];
    }

    public function repeaterAdd(int $sectionIndex, string $field): void
    {
        if (! isset($this->pageContents[$sectionIndex])) {
            return;
        }

        $sectionName = (string) ($this->pageContents[$sectionIndex]['section_name'] ?? '');
        if ($sectionName === '') {
            return;
        }

        if (! isset($this->pageContents[$sectionIndex]['content'][$field]) || ! is_array($this->pageContents[$sectionIndex]['content'][$field])) {
            $this->pageContents[$sectionIndex]['content'][$field] = [];
        }

        $this->pageContents[$sectionIndex]['content'][$field][] = $this->getRepeaterDefaultItem($sectionName, $field);
    }

    public function repeaterRemove(int $sectionIndex, string $field, int $itemIndex): void
    {
        if (! isset($this->pageContents[$sectionIndex]['content'][$field][$itemIndex])) {
            return;
        }

        unset($this->pageContents[$sectionIndex]['content'][$field][$itemIndex]);
        $this->pageContents[$sectionIndex]['content'][$field] = array_values($this->pageContents[$sectionIndex]['content'][$field]);
    }

    public function repeaterAddNested(int $sectionIndex, string $parentField, int $parentIndex, string $field): void
    {
        if (! isset($this->pageContents[$sectionIndex])) {
            return;
        }

        $sectionName = (string) ($this->pageContents[$sectionIndex]['section_name'] ?? '');
        if ($sectionName === '') {
            return;
        }

        if (! isset($this->pageContents[$sectionIndex]['content'][$parentField][$parentIndex])) {
            return;
        }

        if (! isset($this->pageContents[$sectionIndex]['content'][$parentField][$parentIndex][$field]) || ! is_array($this->pageContents[$sectionIndex]['content'][$parentField][$parentIndex][$field])) {
            $this->pageContents[$sectionIndex]['content'][$parentField][$parentIndex][$field] = [];
        }

        $key = $parentField.'.'.$field;
        $this->pageContents[$sectionIndex]['content'][$parentField][$parentIndex][$field][] = $this->getRepeaterDefaultItem($sectionName, $key);
    }

    public function repeaterRemoveNested(int $sectionIndex, string $parentField, int $parentIndex, string $field, int $itemIndex): void
    {
        if (! isset($this->pageContents[$sectionIndex]['content'][$parentField][$parentIndex][$field][$itemIndex])) {
            return;
        }

        unset($this->pageContents[$sectionIndex]['content'][$parentField][$parentIndex][$field][$itemIndex]);
        $this->pageContents[$sectionIndex]['content'][$parentField][$parentIndex][$field] = array_values(
            $this->pageContents[$sectionIndex]['content'][$parentField][$parentIndex][$field]
        );
    }

    public function removeSection($index)
    {
        unset($this->pageContents[$index]);
        $this->pageContents = array_values($this->pageContents);
        $this->sortSectionsByOrder();
    }

    public function updateSectionContent($index, $content)
    {
        if (isset($this->pageContents[$index])) {
            $this->pageContents[$index]['content'] = $content;
        }
    }

    public function updateContent($index, $content)
    {
        // dd($this->pageContents[$index]['content']);
        if (isset($this->pageContents[$index])) {
            $this->pageContents[$index]['content']['content'] = $content;
        }
    }

    public function moveSection($fromIndex, $toIndex)
    {
        if (
            $fromIndex >= 0 && $fromIndex < count($this->pageContents) &&
            $toIndex >= 0 && $toIndex < count($this->pageContents)
        ) {

            // If same index, nothing to do
            if ($fromIndex === $toIndex) {
                return;
            }

            $item = $this->pageContents[$fromIndex];
            unset($this->pageContents[$fromIndex]);

            // Re-index array
            $this->pageContents = array_values($this->pageContents);

            // Insert item at new position
            array_splice($this->pageContents, $toIndex, 0, [$item]);

            // Update sort_order based on new positions (don't re-sort!)
            foreach ($this->pageContents as $index => $section) {
                $this->pageContents[$index]['sort_order'] = $index;
            }
        }
    }

    private function sortSectionsByOrder()
    {
        usort($this->pageContents, function ($a, $b) {
            return $a['sort_order'] <=> $b['sort_order'];
        });

        // Re-index sort_order
        foreach ($this->pageContents as $index => $section) {
            $this->pageContents[$index]['sort_order'] = $index;
        }
    }

    public function updatedTitle()
    {
        $this->slug = Str::slug($this->title);
    }

    public function save()
    {
        $this->validate();

        $admin = Auth::user();

        if ($this->page) {
            // Update existing page
            $this->page->update([
                'title' => $this->title,
                'slug' => $this->slug,
                'meta_description' => $this->meta_description,
                'meta_keywords' => $this->meta_keywords,
                'is_published' => $this->is_published,
                'is_home' => $this->is_home,
                'published_at' => $this->is_published ? ($this->published_at ?: now()) : null,
                'admin_id' => $admin?->id,
            ]);

            // Delete existing contents
            $this->page->contents()->delete();
        } else {
            // Create new page
            $this->page = Page::create([
                'title' => $this->title,
                'slug' => $this->slug,
                'meta_description' => $this->meta_description,
                'meta_keywords' => $this->meta_keywords,
                'is_published' => $this->is_published,
                'is_home' => $this->is_home,
                'published_at' => $this->is_published ? now() : null,
                'admin_id' => $admin?->id,
            ]);
        }

        // Keep homepage unique (soft-enforced in app logic for portability).
        if ($this->is_home && $this->page?->id) {
            Page::where('id', '!=', $this->page->id)->where('is_home', true)->update(['is_home' => false]);
        }

        // Save page contents
        foreach ($this->pageContents as $section) {
            // dd($section['content']);
            // Process any file uploads in the section content
            $processedContent = $this->processFileUploads($section['content']);

            if (! empty($section['global_section_id']) && empty($section['is_global_override'])) {
                $global = GlobalSection::find($section['global_section_id']);
                if ($global) {
                    $global->update([
                        'section_name' => $section['section_name'],
                        'content' => $processedContent,
                        'updated_by' => $admin?->id,
                    ]);
                }
            }

            PageContent::create([
                'page_id' => $this->page->id,
                'global_section_id' => $section['global_section_id'] ?? null,
                'is_global_override' => (bool) ($section['is_global_override'] ?? false),
                'section_name' => $section['section_name'],
                'content' => $processedContent,
                'sort_order' => $section['sort_order'],
            ]);
        }

        $this->success(
            'Page Saved',
            'The page has been saved successfully.',
            redirectTo: route(config('pagewire.route_names.index', 'admin.pages.index'))
        );

        return redirect()->route(config('pagewire.route_names.index', 'admin.pages.index'));
    }

    public function saveAsDraft()
    {
        $this->is_published = false;

        return $this->save();
    }

    public function saveAndPublish()
    {
        $this->is_published = true;

        return $this->save();
    }

    /**
     * Convert existing content for editing (handle file paths)
     */
    private function convertExistingContent($content)
    {
        if (is_array($content)) {
            // Check if this is a FilePond file array (has file path inside)
            // Only convert if it's specifically a file upload array structure
            if (isset($content['value']) && is_string($content['value']) && strpos($content['value'], '/storage/') !== false) {
                // This looks like a FilePond file upload - convert to simple string
                // Only convert if array has typical FilePond keys (not a regular content array)
                $filePondKeys = ['value', 'name', 'extension', 'size', 'type'];
                $hasFilePondStructure = count(array_intersect(array_keys($content), $filePondKeys)) >= 2;

                if ($hasFilePondStructure) {
                    return $content['value'];
                }
            }

            // Handle array of file paths (e.g., image field with multiple uploads)
            // Check if this is a simple array of file paths (not an associative array)
            if (array_is_list($content) && count($content) > 0) {
                // Check if all items are file paths
                $allPaths = true;
                foreach ($content as $item) {
                    if (! is_string($item) || strpos($item, '/storage/') === false) {
                        $allPaths = false;
                        break;
                    }
                }

                if ($allPaths) {
                    // Return the last (most recent) file path
                    return end($content);
                }
            }

            // Recursively process arrays
            foreach ($content as $key => $value) {
                $content[$key] = $this->convertExistingContent($value);
            }

            return $content;
        }

        return $content;
    }

    /**
     * Validate uploaded file for Filepond
     */
    public function validateUploadedFile($file)
    {
        // Additional file validation can be added here
        // For now, just return true to allow all file types that passed Livewire's validation
        return true;
    }

    /**
     * Process file uploads in section content
     */
    private function processFileUploads($content)
    {
        if (is_array($content)) {
            // Check if this is a FilePond file array [fileObject]
            foreach ($content as $key => $value) {
                if ($value instanceof TemporaryUploadedFile) {
                    $content[$key] = $this->processFilePondArray($value);
                } elseif (is_array($value)) {
                    // Recursively process nested arrays (FilePond sometimes nests arrays)
                    $content[$key] = $this->processFileUploads($value);
                }
            }

            return $content;
        }

        // Check if this is a single file upload object (has path property)
        if (is_object($content) && isset($content->path)) {
            // Store the file and return the path
            $path = '/storage/'.$content->store('page-images', 'public');

            return $path;
        }

        return $content;
    }

    /**
     * Check if array is a FilePond file upload array
     */
    private function isFilePondArray($content)
    {
        if (! is_array($content) || count($content) === 0) {
            return false;
        }

        // FilePond arrays can have structure like: [fileObject] or [[], fileObject]
        // Let's check for valid file objects in the array
        foreach ($content as $item) {
            if (is_object($item) && isset($item->path) && ! empty($item->path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Process FilePond array to extract the actual file
     */
    private function processFilePondArray($value)
    {
        $path = '/storage/'.$value->store('page-images', 'public');

        return $path;
    }

    public function toggleCategory($sectionIndex, $categoryId)
    {
        // Initialize category_ids if not exists
        if (! isset($this->pageContents[$sectionIndex]['content']['category_ids'])) {
            $this->pageContents[$sectionIndex]['content']['category_ids'] = [];
        }

        $categoryIds = $this->pageContents[$sectionIndex]['content']['category_ids'];

        // Convert object to array if needed
        if (is_array($categoryIds) && array_filter($categoryIds, 'is_bool')) {
            // If it's an array of true/false, convert to empty array
            $categoryIds = [];
        } elseif (! is_array($categoryIds)) {
            $categoryIds = [];
        }

        // Toggle category ID
        if (in_array($categoryId, $categoryIds)) {
            // Remove category ID
            $categoryIds = array_values(array_filter($categoryIds, function ($id) use ($categoryId) {
                return $id != $categoryId;
            }));
        } else {
            // Add category ID
            $categoryIds[] = $categoryId;
        }

        $this->pageContents[$sectionIndex]['content']['category_ids'] = $categoryIds;
    }

    private function loadGlobalSections(): void
    {
        $sections = GlobalSection::orderBy('name')->get();

        $this->globalSections = $sections->map(function (GlobalSection $section) {
            return [
                'id' => $section->id,
                'name' => $section->name,
                'section_name' => $section->section_name,
                'updated_at' => $section->updated_at,
            ];
        })->toArray();

        $this->globalSectionMap = $sections->pluck('name', 'id')->toArray();
    }

    public function render()
    {
        $view = view('pagewire::livewire.admin.page.builder', [
            'availableSections' => $this->availableSections,
        ]);

        $layout = config('pagewire.layout');

        return $layout ? $view->layout($layout) : $view;
    }

    public function getEditorView($sectionName)
    {
        // Preferred override location (keeps package overrides namespaced).
        $namespacedAppView = 'livewire.pagewire.section-editors.'.$sectionName;
        $appView = 'livewire.admin.page.section-editors.'.$sectionName;
        $packageView = 'pagewire::livewire.admin.page.section-editors.'.$sectionName;

        if (view()->exists($namespacedAppView)) {
            return $namespacedAppView;
        }

        if (view()->exists($appView)) {
            return $appView;
        }

        if (view()->exists($packageView)) {
            return $packageView;
        }

        return 'pagewire::livewire.admin.page.section-editors.generic';
    }
}
