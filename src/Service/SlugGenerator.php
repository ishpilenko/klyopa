<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\String\Slugger\AsciiSlugger;

class SlugGenerator
{
    private readonly AsciiSlugger $slugger;

    public function __construct()
    {
        $this->slugger = new AsciiSlugger();
    }

    public function generate(string $text, string $locale = 'en'): string
    {
        return strtolower($this->slugger->slug($text, '-', $locale)->toString());
    }

    public function generateUnique(string $text, callable $exists, string $locale = 'en'): string
    {
        $base = $this->generate($text, $locale);
        $slug = $base;
        $i = 2;

        while ($exists($slug)) {
            $slug = $base . '-' . $i;
            ++$i;
        }

        return $slug;
    }
}
