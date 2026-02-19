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
        $sectionsPath = resource_path('views/sections');
        $sections = [];

        if (is_dir($sectionsPath)) {
            $files = glob($sectionsPath.'/*.blade.php');

            foreach ($files as $file) {
                $filename = basename($file, '.blade.php');
                $displayName = $this->formatSectionName($filename);
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
        $defaults = [
            'hero-area' => [
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
            ],
            'feature-area' => [
                'features' => [
                    ['icon' => 'staff', 'title' => 'Expert Team', 'description' => 'Professional experts in our field'],
                    ['icon' => 'money', 'title' => 'Quality Service', 'description' => 'High-quality solutions delivered'],
                    ['icon' => 'support', 'title' => '24/7 Support', 'description' => 'Round the clock assistance'],
                ],
            ],
            'about-area' => [
                'subtitle' => 'About Us',
                'title' => 'We Are The Best & Expert For <span>Your Solar</span> Solution',
                'description' => 'There are many variations of passages of Lorem Ipsum available, but the majority have suffered alteration in some form, by injected humour, or randomised words which don\'t look even.',
                'image1' => '',
                'image2' => '',
                'experience_years' => '30',
                'experience_text' => 'Years Of Experience',
                'features' => [
                    ['icon' => 'install', 'title' => 'Easy Installation', 'description' => 'Take a look at our up of the round shows'],
                    ['icon' => 'material', 'title' => 'Quality Material', 'description' => 'Take a look at our up of the round shows'],
                ],
                'button_text' => 'Discover More',
                'button_url' => '/about',
            ],
            'cta-area' => [
                'title' => 'Ready to Get Started?',
                'description' => 'Contact us today for a consultation',
                'background_image' => null,
                'button_text' => 'Contact Us',
                'button_url' => '/contact',
            ],
            'testimonial-area' => [
                'subtitle' => 'Testimonials',
                'title' => 'What Our <span>Clients</span> Say',
                'description' => 'Read what our satisfied customers have to say about our solar solutions',
                'button_text' => 'View All Reviews',
                'button_url' => '/testimonials',
                'testimonials' => [
                    [
                        'author_name' => 'John Smith',
                        'author_position' => 'Homeowner, California',
                        'author_image' => null,
                        'rating' => 5,
                        'testimonial_text' => 'The team did an amazing job installing our solar panels. We\'ve already seen significant savings on our energy bills!',
                    ],
                    [
                        'author_name' => 'Sarah Johnson',
                        'author_position' => 'Business Owner',
                        'author_image' => null,
                        'rating' => 5,
                        'testimonial_text' => 'Professional installation and excellent customer service. Our business is now powered by clean energy!',
                    ],
                ],
            ],
            'team-area' => [
                'subtitle' => 'Our Team',
                'title' => 'Meet Our <span>Expert</span> Team',
                'team_members' => [
                    [
                        'name' => 'John Anderson',
                        'position' => 'Chief Executive Officer',
                        'image' => null,
                        'social_links' => [
                            ['platform' => 'linkedin', 'url' => '#'],
                            ['platform' => 'email', 'url' => 'john@company.com'],
                        ],
                    ],
                    [
                        'name' => 'Sarah Wilson',
                        'position' => 'Technical Director',
                        'image' => null,
                        'social_links' => [
                            ['platform' => 'linkedin', 'url' => '#'],
                            ['platform' => 'twitter', 'url' => '#'],
                        ],
                    ],
                ],
            ],
            'portfolio-area' => [
                'subtitle' => 'Portfolio',
                'title' => 'Our Recent <span>Projects</span>',
                'description' => 'Explore our latest solar energy installations and renewable energy projects',
                'portfolio_items' => [
                    [
                        'title' => 'Residential Solar Installation - California',
                        'category' => 'residential',
                        'image' => null,
                        'project_url' => '/projects/residential-california',
                    ],
                    [
                        'title' => 'Commercial Solar Farm - Texas',
                        'category' => 'commercial',
                        'image' => null,
                        'project_url' => '/projects/commercial-texas',
                    ],
                ],
            ],
            'process-area' => [
                'subtitle' => 'Working Process',
                'title' => 'Easy steps for <span>solar and</span> renewable energy',
                'steps' => [
                    [
                        'icon' => 'hybrid',
                        'title' => 'Choose Service',
                        'description' => 'It is a long established fact that a reader will be distracted by the readable content of a page.',
                    ],
                    [
                        'icon' => 'consult',
                        'title' => 'Free Consultant',
                        'description' => 'It is a long established fact that a reader will be distracted by the readable content of a page.',
                    ],
                    [
                        'icon' => 'plan',
                        'title' => 'Planing & Analysis',
                        'description' => 'It is a long established fact that a reader will be distracted by the readable content of a page.',
                    ],
                    [
                        'icon' => 'install-3',
                        'title' => 'Solar Installation',
                        'description' => 'It is a long established fact that a reader will be distracted by the readable content of a page.',
                    ],
                ],
            ],
            'skill-area' => [
                'subtitle' => 'Our Skills',
                'title' => 'We offers solar <span>that\'s easy</span> and efficient.',
                'description' => 'There are many variations of passages of Lorem Ipsum available, but the majority have suffered alteration in some form, by injected humour, or randomised words which don\'t look even slightly believable.',
                'feature_image' => null,
                'skills' => [
                    [
                        'name' => 'Solar Panels',
                        'percentage' => 85,
                    ],
                    [
                        'name' => 'Solar Installation',
                        'percentage' => 65,
                    ],
                    [
                        'name' => 'Renewable Energy',
                        'percentage' => 75,
                    ],
                ],
                'button_text' => 'Learn More',
                'button_url' => '/about',
            ],
            'counter-area' => [
                'counters' => [
                    [
                        'icon' => 'solar',
                        'number' => '150',
                        'number_suffix' => 'k',
                        'title' => 'Projects Done',
                    ],
                    [
                        'icon' => 'rating',
                        'number' => '25',
                        'number_suffix' => 'K',
                        'title' => 'Happy Clients',
                    ],
                    [
                        'icon' => 'staff',
                        'number' => '120',
                        'number_suffix' => '+',
                        'title' => 'Experts Staff',
                    ],
                    [
                        'icon' => 'award',
                        'number' => '50',
                        'number_suffix' => '+',
                        'title' => 'Win Awards',
                    ],
                ],
            ],
            'blog-area' => [
                'subtitle' => 'Our Blog',
                'title' => 'Our Latest News & <span>Blog</span>',
                'number_to_show' => 3,
                'sort_order' => 'latest',
                'button_text' => 'View All Blogs',
                'button_url' => '/blog',
                'fallback_title' => 'Coming Soon',
                'fallback_description' => 'Our blog posts are coming soon. Stay tuned for amazing content!',
            ],
            'map-area' => [
                'embed_url' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d96708.34194156103!2d-74.03927096447748!3d40.759040329405195!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x4a01c8df6fb3cb8!2sSolomon%20R.%20Guggenheim%20Museum!5e0!3m2!1sen!2sbd!4v1619410634508!5m2!1sen!2s',
            ],
            'choose-area' => [
                'subtitle' => 'Why Choose Us',
                'title' => 'We deliver <span>expertise you can trust</span> our service',
                'description' => 'It is a long established fact that a reader will be distracted by the readable content of a page when looking at its layout.',
                'image1' => '',
                'image2' => '',
                'items' => [
                    [
                        'icon' => 'money-2',
                        'title' => 'Affordable Cost',
                        'description' => 'There are many variations of passages available the majority have suffered alteration in some by injected humour.',
                    ],
                    [
                        'icon' => 'staff',
                        'title' => 'Our Experience Team',
                        'description' => 'There are many variations of passages available the majority have suffered alteration in some by injected humour.',
                    ],
                    [
                        'icon' => 'certified',
                        'title' => 'Certified Company',
                        'description' => 'There are many variations of passages available the majority have suffered alteration in some by injected humour.',
                    ],
                ],
            ],
            'get-solar-quote' => [
                'subtitle' => 'Get Your Free Quote',
                'title' => 'Switch to Solar Energy Today',
                'description' => 'Join thousands of homeowners who are saving money and helping the environment with solar power. Get a customized quote for your property.',
                'slider_images' => [
                    'https://static.vecteezy.com/system/resources/thumbnails/040/995/143/small/ai-generated-fields-of-solar-panels-and-systems-to-produce-green-electricity-ai-generated-photo.jpg',
                    'https://www.soleosenergy.com/wp-content/uploads/2024/09/1650368737-5-environmental-benefits-of-solar-energy.jpg',
                ],
            ],
            'contact-area' => [
                'title' => 'Get In Touch',
                'description' => 'It is a long established fact that a reader will be distracted by the readable content of a page randomised words which don\'t look even slightly when looking at its layout.',
                'image' => null,
            ],
            'contact-info' => [
                'contact_items' => [
                    [
                        'icon' => 'map-location-dot',
                        'title' => 'Office Address',
                        'content' => '25/B Milford, New York, USA',
                    ],
                    [
                        'icon' => 'phone-volume',
                        'title' => 'Call Us',
                        'content' => '+2 123 4565 789',
                    ],
                    [
                        'icon' => 'envelopes',
                        'title' => 'Email Us',
                        'content' => 'info@example.com',
                    ],
                    [
                        'icon' => 'alarm-clock',
                        'title' => 'Open Time',
                        'content' => 'Mon - Sat (10.00AM - 05.30PM)',
                    ],
                ],
            ],
            'service-dynamic' => [
                'subtitle' => 'Services',
                'title' => 'What Services we are <span>provide</span> to you',
                'number_to_show' => 8,
                'sort_order' => 'manual',
                'fallback_title' => 'Coming Soon',
            ],
            'partner-area' => [
                'partners' => [
                    ['image' => null, 'alt' => 'Partner 1'],
                    ['image' => null, 'alt' => 'Partner 2'],
                    ['image' => null, 'alt' => 'Partner 3'],
                    ['image' => null, 'alt' => 'Partner 4'],
                ],
            ],
            'solar-calculator' => [
                'subtitle' => 'Get Your Free Quote',
                'title' => 'Calculate Your Solar Savings',
                'description' => 'Join thousands of homeowners who are saving money and helping the environment with solar power. Get a customized quote for your property.',
                'swap_columns' => false,
            ],
        ];

        return $defaults[$sectionName] ?? [];
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
                'published_at' => $this->is_published ? ($this->published_at ?: now()) : null,
                'user_id' => $admin->id,
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
                'published_at' => $this->is_published ? now() : null,
                'user_id' => $admin->id,
            ]);
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

    public function addFeature($sectionIndex)
    {
        if (! isset($this->pageContents[$sectionIndex]['content']['features'])) {
            $this->pageContents[$sectionIndex]['content']['features'] = [];
        }

        $this->pageContents[$sectionIndex]['content']['features'][] = [
            'icon' => 'staff',
            'title' => 'New Feature',
            'description' => 'Feature description',
        ];
    }

    public function removeFeature($sectionIndex, $featureIndex)
    {
        if (isset($this->pageContents[$sectionIndex]['content']['features'][$featureIndex])) {
            unset($this->pageContents[$sectionIndex]['content']['features'][$featureIndex]);
            $this->pageContents[$sectionIndex]['content']['features'] = array_values(
                $this->pageContents[$sectionIndex]['content']['features']
            );
        }
    }

    public function addHeroSlider($sectionIndex)
    {
        if (! isset($this->pageContents[$sectionIndex]['content']['sliders'])) {
            $this->pageContents[$sectionIndex]['content']['sliders'] = [];
        }

        $sliderIndex = count($this->pageContents[$sectionIndex]['content']['sliders']);

        $this->pageContents[$sectionIndex]['content']['sliders'][] = [
            'background_image' => '',
            'icon' => 'far fa-solar-panel',
            'subtitle' => 'Professional Service',
            'title' => 'Save Money With <span>Solar Energy</span> Solutions',
            'description' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore.',
            'button1_text' => 'Our Services',
            'button1_url' => '/services',
            'button2_text' => 'Get Quote',
            'button2_url' => '/contact',
        ];
    }

    public function removeHeroSlider($sectionIndex, $sliderIndex)
    {
        if (isset($this->pageContents[$sectionIndex]['content']['sliders'][$sliderIndex])) {
            unset($this->pageContents[$sectionIndex]['content']['sliders'][$sliderIndex]);
            $this->pageContents[$sectionIndex]['content']['sliders'] = array_values(
                $this->pageContents[$sectionIndex]['content']['sliders']
            );
        }
    }

    // Testimonial methods
    public function addTestimonial($sectionIndex)
    {
        if (! isset($this->pageContents[$sectionIndex]['content']['testimonials'])) {
            $this->pageContents[$sectionIndex]['content']['testimonials'] = [];
        }

        $this->pageContents[$sectionIndex]['content']['testimonials'][] = [
            'author_name' => '',
            'author_position' => '',
            'author_image' => null,
            'rating' => 5,
            'testimonial_text' => '',
        ];
    }

    public function removeTestimonial($sectionIndex, $testimonialIndex)
    {
        if (isset($this->pageContents[$sectionIndex]['content']['testimonials'][$testimonialIndex])) {
            unset($this->pageContents[$sectionIndex]['content']['testimonials'][$testimonialIndex]);
            $this->pageContents[$sectionIndex]['content']['testimonials'] = array_values(
                $this->pageContents[$sectionIndex]['content']['testimonials']
            );
        }
    }

    // Team member methods
    public function addTeamMember($sectionIndex)
    {
        if (! isset($this->pageContents[$sectionIndex]['content']['team_members'])) {
            $this->pageContents[$sectionIndex]['content']['team_members'] = [];
        }

        $this->pageContents[$sectionIndex]['content']['team_members'][] = [
            'name' => '',
            'position' => '',
            'image' => null,
            'social_links' => [],
        ];
    }

    public function removeTeamMember($sectionIndex, $memberIndex)
    {
        if (isset($this->pageContents[$sectionIndex]['content']['team_members'][$memberIndex])) {
            unset($this->pageContents[$sectionIndex]['content']['team_members'][$memberIndex]);
            $this->pageContents[$sectionIndex]['content']['team_members'] = array_values(
                $this->pageContents[$sectionIndex]['content']['team_members']
            );
        }
    }

    public function addSocialLink($sectionIndex, $memberIndex)
    {
        if (! isset($this->pageContents[$sectionIndex]['content']['team_members'][$memberIndex]['social_links'])) {
            $this->pageContents[$sectionIndex]['content']['team_members'][$memberIndex]['social_links'] = [];
        }

        $this->pageContents[$sectionIndex]['content']['team_members'][$memberIndex]['social_links'][] = [
            'platform' => '',
            'url' => '',
        ];
    }

    public function removeSocialLink($sectionIndex, $memberIndex, $linkIndex)
    {
        if (isset($this->pageContents[$sectionIndex]['content']['team_members'][$memberIndex]['social_links'][$linkIndex])) {
            unset($this->pageContents[$sectionIndex]['content']['team_members'][$memberIndex]['social_links'][$linkIndex]);
            $this->pageContents[$sectionIndex]['content']['team_members'][$memberIndex]['social_links'] = array_values(
                $this->pageContents[$sectionIndex]['content']['team_members'][$memberIndex]['social_links']
            );
        }
    }

    // Portfolio methods
    public function addPortfolioItem($sectionIndex)
    {
        if (! isset($this->pageContents[$sectionIndex]['content']['portfolio_items'])) {
            $this->pageContents[$sectionIndex]['content']['portfolio_items'] = [];
        }

        $this->pageContents[$sectionIndex]['content']['portfolio_items'][] = [
            'title' => '',
            'category' => '',
            'image' => null,
            'project_url' => '',
        ];
    }

    public function removePortfolioItem($sectionIndex, $itemIndex)
    {
        if (isset($this->pageContents[$sectionIndex]['content']['portfolio_items'][$itemIndex])) {
            unset($this->pageContents[$sectionIndex]['content']['portfolio_items'][$itemIndex]);
            $this->pageContents[$sectionIndex]['content']['portfolio_items'] = array_values(
                $this->pageContents[$sectionIndex]['content']['portfolio_items']
            );
        }
    }

    // Process Area Methods
    public function addProcessStep($sectionIndex)
    {
        if (! isset($this->pageContents[$sectionIndex]['content']['steps'])) {
            $this->pageContents[$sectionIndex]['content']['steps'] = [];
        }

        $this->pageContents[$sectionIndex]['content']['steps'][] = [
            'icon' => 'hybrid',
            'title' => 'New Step',
            'description' => 'Step description',
        ];
    }

    public function removeProcessStep($sectionIndex, $stepIndex)
    {
        if (isset($this->pageContents[$sectionIndex]['content']['steps'][$stepIndex])) {
            unset($this->pageContents[$sectionIndex]['content']['steps'][$stepIndex]);
            $this->pageContents[$sectionIndex]['content']['steps'] = array_values(
                $this->pageContents[$sectionIndex]['content']['steps']
            );
        }
    }

    // Skill Area Methods
    public function addSkill($sectionIndex)
    {
        if (! isset($this->pageContents[$sectionIndex]['content']['skills'])) {
            $this->pageContents[$sectionIndex]['content']['skills'] = [];
        }

        $this->pageContents[$sectionIndex]['content']['skills'][] = [
            'name' => 'New Skill',
            'percentage' => 50,
        ];
    }

    public function removeSkill($sectionIndex, $skillIndex)
    {
        if (isset($this->pageContents[$sectionIndex]['content']['skills'][$skillIndex])) {
            unset($this->pageContents[$sectionIndex]['content']['skills'][$skillIndex]);
            $this->pageContents[$sectionIndex]['content']['skills'] = array_values(
                $this->pageContents[$sectionIndex]['content']['skills']
            );
        }
    }

    // Counter Area Methods
    public function addCounter($sectionIndex)
    {
        if (! isset($this->pageContents[$sectionIndex]['content']['counters'])) {
            $this->pageContents[$sectionIndex]['content']['counters'] = [];
        }

        $this->pageContents[$sectionIndex]['content']['counters'][] = [
            'icon' => 'solar',
            'number' => '100',
            'number_suffix' => '+',
            'title' => 'New Counter',
        ];
    }

    public function removeCounter($sectionIndex, $counterIndex)
    {
        if (isset($this->pageContents[$sectionIndex]['content']['counters'][$counterIndex])) {
            unset($this->pageContents[$sectionIndex]['content']['counters'][$counterIndex]);
            $this->pageContents[$sectionIndex]['content']['counters'] = array_values(
                $this->pageContents[$sectionIndex]['content']['counters']
            );
        }
    }

    // Map Area Methods
    public function addMapLocation($sectionIndex)
    {
        if (! isset($this->pageContents[$sectionIndex]['content']['locations'])) {
            $this->pageContents[$sectionIndex]['content']['locations'] = [];
        }

        $this->pageContents[$sectionIndex]['content']['locations'][] = [
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
        ];
    }

    public function removeMapLocation($sectionIndex, $locationIndex)
    {
        if (isset($this->pageContents[$sectionIndex]['content']['locations'][$locationIndex])) {
            unset($this->pageContents[$sectionIndex]['content']['locations'][$locationIndex]);
            $this->pageContents[$sectionIndex]['content']['locations'] = array_values(
                $this->pageContents[$sectionIndex]['content']['locations']
            );
        }
    }

    // Choose Area Methods
    public function addChooseItem($sectionIndex)
    {
        if (! isset($this->pageContents[$sectionIndex]['content']['items'])) {
            $this->pageContents[$sectionIndex]['content']['items'] = [];
        }

        $this->pageContents[$sectionIndex]['content']['items'][] = [
            'icon' => 'star',
            'title' => 'New Item',
            'description' => 'Item description',
        ];
    }

    public function removeChooseItem($sectionIndex, $itemIndex)
    {
        if (isset($this->pageContents[$sectionIndex]['content']['items'][$itemIndex])) {
            unset($this->pageContents[$sectionIndex]['content']['items'][$itemIndex]);
            $this->pageContents[$sectionIndex]['content']['items'] = array_values(
                $this->pageContents[$sectionIndex]['content']['items']
            );
        }
    }

    // Contact Info Methods
    public function addContactItem($sectionIndex)
    {
        if (! isset($this->pageContents[$sectionIndex]['content']['contact_items'])) {
            $this->pageContents[$sectionIndex]['content']['contact_items'] = [];
        }

        $this->pageContents[$sectionIndex]['content']['contact_items'][] = [
            'icon' => 'map-marker-alt',
            'title' => 'New Contact Item',
            'content' => 'Contact information',
        ];
    }

    public function removeContactItem($sectionIndex, $itemIndex)
    {
        if (isset($this->pageContents[$sectionIndex]['content']['contact_items'][$itemIndex])) {
            unset($this->pageContents[$sectionIndex]['content']['contact_items'][$itemIndex]);
            $this->pageContents[$sectionIndex]['content']['contact_items'] = array_values(
                $this->pageContents[$sectionIndex]['content']['contact_items']
            );
        }
    }

    public function addSliderImage($sectionIndex)
    {
        if (! isset($this->pageContents[$sectionIndex]['content']['slider_images'])) {
            $this->pageContents[$sectionIndex]['content']['slider_images'] = [];
        }
        $this->pageContents[$sectionIndex]['content']['slider_images'][] = '';
    }

    public function removeSliderImage($sectionIndex, $imageIndex)
    {
        if (isset($this->pageContents[$sectionIndex]['content']['slider_images'][$imageIndex])) {
            unset($this->pageContents[$sectionIndex]['content']['slider_images'][$imageIndex]);
            $this->pageContents[$sectionIndex]['content']['slider_images'] = array_values(
                $this->pageContents[$sectionIndex]['content']['slider_images']
            );
        }
    }

    // Partner Area Methods
    public function addPartner($sectionIndex)
    {
        if (! isset($this->pageContents[$sectionIndex]['content']['partners'])) {
            $this->pageContents[$sectionIndex]['content']['partners'] = [];
        }

        $this->pageContents[$sectionIndex]['content']['partners'][] = [
            'image' => null,
            'alt' => '',
        ];
    }

    public function removePartner($sectionIndex, $partnerIndex)
    {
        if (isset($this->pageContents[$sectionIndex]['content']['partners'][$partnerIndex])) {
            unset($this->pageContents[$sectionIndex]['content']['partners'][$partnerIndex]);
            $this->pageContents[$sectionIndex]['content']['partners'] = array_values(
                $this->pageContents[$sectionIndex]['content']['partners']
            );
        }
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
        $appView = 'livewire.admin.page.section-editors.'.$sectionName;
        $packageView = 'pagewire::livewire.admin.page.section-editors.'.$sectionName;

        if (view()->exists($appView)) {
            return $appView;
        }

        if (view()->exists($packageView)) {
            return $packageView;
        }

        return 'pagewire::livewire.admin.page.section-editors.generic';
    }
}
