<?php

namespace App\Support\Seo;

/**
 * Everything the <head> needs for one page, fully resolved.
 *
 * This is deliberately dumb: no lookups, no fallbacks, no formatting. By the
 * time a SeoData reaches the view component every string is final, so the Blade
 * template is a list of tags and nothing else.
 */
class SeoData
{
    /**
     * @param  array<string, string>  $og            Open Graph property => content
     * @param  array<string, string>  $twitter       Twitter name => content
     * @param  array<int, array>      $schemas       JSON-LD graph nodes
     * @param  array<string, string>  $links         extra rel => href (prev/next)
     * @param  array<string, string>  $verifications name => content
     */
    public function __construct(
        public string $title,
        public string $description = '',
        public ?string $canonical = null,
        public string $robots = 'index, follow',
        public array $og = [],
        public array $twitter = [],
        public array $schemas = [],
        public array $links = [],
        public array $verifications = [],
    ) {}

    /** The single JSON-LD blob written to the page, or null when empty. */
    public function jsonLd(): ?string
    {
        $nodes = array_values(array_filter($this->schemas));

        if (! $nodes) {
            return null;
        }

        // One @graph rather than N script tags: fewer parses for a crawler and
        // one place for the nodes to reference each other by @id later.
        $payload = count($nodes) === 1
            ? $nodes[0]
            : ['@context' => 'https://schema.org', '@graph' => array_map(
                fn (array $node) => array_diff_key($node, ['@context' => null]),
                $nodes
            )];

        return json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        ) ?: null;
    }
}
