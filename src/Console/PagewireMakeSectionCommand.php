<?php

namespace Hemant\Pagewire\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class PagewireMakeSectionCommand extends Command
{
    protected $signature = 'pagewire:make-section
        {name : Section filename (e.g. hero-area)}
        {--view : Create the front-end section view in resources/views/sections}
        {--editor : Create the admin editor partial in resources/views/livewire/pagewire/section-editors}
        {--force : Overwrite existing files}';

    protected $description = 'Create Pagewire section stubs (front-end section + optional Livewire editor partial).';

    public function handle(): int
    {
        $rawName = (string) $this->argument('name');
        $name = trim($rawName);
        $name = str_replace(['.blade.php', '.php'], '', $name);
        $name = Str::kebab(str_replace(['/', '\\'], '-', $name));

        if ($name === '' || ! preg_match('/^[a-z0-9][a-z0-9\-]*[a-z0-9]$|^[a-z0-9]$/', $name)) {
            $this->error('Invalid section name. Use letters/numbers and dashes, e.g. hero-area.');

            return self::FAILURE;
        }

        $makeView = (bool) $this->option('view');
        $makeEditor = (bool) $this->option('editor');

        // If no flags passed, create both.
        if (! $makeView && ! $makeEditor) {
            $makeView = true;
            $makeEditor = true;
        }

        $force = (bool) $this->option('force');

        $sectionDir = config('pagewire.sections_make_path', resource_path('views/sections'));
        $editorDir = config('pagewire.editor_make_path', resource_path('views/livewire/pagewire/section-editors'));

        if (! is_string($sectionDir) || $sectionDir === '') {
            $sectionDir = resource_path('views/sections');
        }
        if (! is_string($editorDir) || $editorDir === '') {
            $editorDir = resource_path('views/livewire/pagewire/section-editors');
        }

        $createdAny = false;

        if ($makeView) {
            $viewPath = rtrim($sectionDir, '/')."/{$name}.blade.php";
            $ok = $this->writeFile(
                $viewPath,
                $this->frontEndStub($name),
                $force
            );
            if ($ok === false) {
                return self::FAILURE;
            }
            $createdAny = true;
            $this->info("Created: {$viewPath}");
        }

        if ($makeEditor) {
            $editorPath = rtrim($editorDir, '/')."/{$name}.blade.php";
            $ok = $this->writeFile(
                $editorPath,
                $this->editorStub($name),
                $force
            );
            if ($ok === false) {
                return self::FAILURE;
            }
            $createdAny = true;
            $this->info("Created: {$editorPath}");
            $this->line('Editor auto-detected at view: livewire.pagewire.section-editors.'.$name);
        }

        if (! $createdAny) {
            $this->warn('Nothing to do.');
        }

        return self::SUCCESS;
    }

    private function writeFile(string $path, string $contents, bool $force): bool
    {
        $dir = dirname($path);

        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            $this->error("Could not create directory: {$dir}");

            return false;
        }

        if (is_file($path) && ! $force) {
            $this->error("File already exists: {$path} (use --force to overwrite)");

            return false;
        }

        if (file_put_contents($path, $contents) === false) {
            $this->error("Could not write file: {$path}");

            return false;
        }

        return true;
    }

    private function frontEndStub(string $name): string
    {
        // `$content` is passed by the default renderer view.
        return <<<BLADE
<section class="py-12">
    <div class="container mx-auto px-4">
        <h2 class="text-2xl font-semibold">
            {{ data_get(
                \$content,
                'title',
                '{$this->titleFromName($name)}'
            ) }}
        </h2>
        <p class="mt-3 text-base-content/70">
            {{ data_get(\$content, 'description', 'Edit this section in Pagewire builder.') }}
        </p>
    </div>
</section>
BLADE;
    }

    private function editorStub(string $name): string
    {
        return <<<BLADE
<div class="space-y-4">
    <x-input
        label="Title"
        wire:model.live="pageContents.{{ \$index }}.content.title"
        placeholder="{$this->titleFromName($name)}"
    />

    <x-textarea
        label="Description"
        rows="3"
        wire:model.live="pageContents.{{ \$index }}.content.description"
        placeholder="Short description"
    />
</div>
BLADE;
    }

    private function titleFromName(string $name): string
    {
        return ucwords(str_replace('-', ' ', $name));
    }
}
