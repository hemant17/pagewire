<?php

namespace Hemant\Pagewire\Console;

use Illuminate\Console\Command;

class PagewireInstallCommand extends Command
{
    protected $signature = 'pagewire:install {--force : Re-insert directives even if already present}';

    protected $description = 'Configure Tailwind v4 sources for Pagewire (adds @source directives to resources/css/app.css).';

    public function handle(): int
    {
        $cssPath = base_path('resources/css/app.css');

        if (! is_file($cssPath)) {
            $this->error("Could not find {$cssPath}.");
            $this->line('Create a Tailwind entry CSS (usually resources/css/app.css) and then re-run this command.');

            return self::FAILURE;
        }

        $css = file_get_contents($cssPath);
        if ($css === false) {
            $this->error("Could not read {$cssPath}.");

            return self::FAILURE;
        }

        $needle = 'vendor/hemant/pagewire/resources/views';
        $already = str_contains($css, $needle);

        if ($already && ! $this->option('force')) {
            $this->info('Pagewire @source directives already present.');
            $this->line('If Tailwind still doesn\'t pick up vendor changes, restart `npm run dev` or run `npm run build`.');

            return self::SUCCESS;
        }

        // Ensure directives appear before @import "tailwindcss" so Tailwind v4 picks them up.
        $directives = "@source \"../../vendor/hemant/pagewire/resources/views/**/*.blade.php\";\n".
            "@source \"../../vendor/hemant/pagewire/src/**/*.php\";\n\n";

        if ($this->option('force')) {
            $css = preg_replace(
                '/^@source\s+["\'][^\n]*vendor\/hemant\/pagewire\/[^\n]*;\s*\n?/m',
                '',
                $css
            ) ?? $css;
        }

        $pattern = '/^@import\s+["\']tailwindcss["\'];\s*$/m';
        if (preg_match($pattern, $css, $match, PREG_OFFSET_CAPTURE)) {
            $pos = $match[0][1];
            $css = substr($css, 0, $pos).$directives.substr($css, $pos);
        } else {
            $css = $directives.$css;
        }

        if (file_put_contents($cssPath, $css) === false) {
            $this->error("Could not write {$cssPath}.");

            return self::FAILURE;
        }

        $this->info('Added Pagewire @source directives to resources/css/app.css.');
        $this->line('Next: rebuild your CSS with `npm run dev` (restart) or `npm run build`.');

        return self::SUCCESS;
    }
}

