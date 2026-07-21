<?php

namespace App\Services;

use Illuminate\Support\Facades\Blade;

/**
 * The safety net in front of every template save.
 *
 * ShopMGR lets merchants edit real Blade, which means a merchant can type a
 * syntax error into the checkout template. On a shop, a 500 is not an
 * inconvenience, it is lost revenue for as long as nobody notices. So nothing
 * is ever persisted until it has been proven to compile to PARSEABLE PHP.
 *
 * How it works, and why it works this way:
 *
 *   1. Blade::compileString() turns the submitted template into PHP. This
 *      catches directive-level breakage (a malformed @foreach expression, a
 *      component tag the compiler cannot parse).
 *
 *   2. token_get_all($compiled, TOKEN_PARSE) then proves the RESULTING PHP is
 *      syntactically valid. TOKEN_PARSE makes the tokenizer run the real
 *      parser, so it throws \ParseError on invalid PHP - without executing a
 *      single line of it. That is the whole point: we need to know the file
 *      would run, and we must not run it.
 *
 *      We do NOT eval() the compiled output: eval() runs merchant input, and a
 *      template that merely renders is not the same thing as a template that is
 *      safe to execute on a validation request.
 *
 *      We also do NOT lean on `php artisan view:cache` succeeding. In this app
 *      view:cache has been observed to exit 0 on templates that compile to
 *      invalid PHP, and that false all-clear has already put this storefront on
 *      the floor twice. A green view:cache is not evidence.
 *
 *   3. Two house-rule traps are checked explicitly, because both compile
 *      cleanly and then misbehave at runtime, which is worse than a hard error:
 *
 *      - @php(...) on line 1. Blade's raw-block regex swallows it against a
 *        later @endphp and 500s the page. This already caused a live /cart 500.
 *
 *      - A directive glued to a preceding word character ("Received@if (...)").
 *        Blade will not compile the @if but WILL compile the matching @endif,
 *        producing silent mis-scoping rather than an error.
 */
class TemplateValidator
{
    /**
     * Validate a template's source.
     *
     * @return array{ok: bool, error: ?string, line: ?int, excerpt: ?array, exact: bool}
     */
    public function validate(string $source): array
    {
        if (trim($source) === '') {
            return $this->fail('The template is empty. An empty override would render a blank page.');
        }

        if ($problem = $this->houseRuleProblem($source)) {
            return $problem;
        }

        // Step 1: Blade -> PHP.
        try {
            $compiled = Blade::compileString($source);
        } catch (\Throwable $e) {
            return $this->fail('Blade could not compile this template: '.$e->getMessage());
        }

        // Step 2: does the generated PHP actually parse? Parses only, never runs.
        try {
            token_get_all($compiled, TOKEN_PARSE);
        } catch (\ParseError $e) {
            return $this->parseFailure($source, $compiled, $e);
        } catch (\Throwable $e) {
            return $this->fail('The generated PHP could not be parsed: '.$e->getMessage());
        }

        return ['ok' => true, 'error' => null, 'line' => null, 'excerpt' => null, 'exact' => true];
    }

    /** Convenience for callers that only need a yes/no. */
    public function passes(string $source): bool
    {
        return $this->validate($source)['ok'];
    }

    /**
     * Turn a \ParseError on the compiled output into something a merchant can
     * act on: a line number, and the surrounding template source.
     *
     * Blade replaces most directives in place, so compiled and source line
     * numbers usually line up exactly. When the line counts match we say so;
     * when they do not (a multi-line @php block, an expanded component tag) we
     * mark the location approximate rather than pointing confidently at the
     * wrong line.
     */
    private function parseFailure(string $source, string $compiled, \ParseError $e): array
    {
        $line = $e->getLine();
        $exact = substr_count($source, "\n") === substr_count($compiled, "\n");

        $message = rtrim($e->getMessage(), '.');

        // Blade can expand a component tag into several lines of PHP, so the
        // compiled line number can land past the end of the merchant's file.
        // Clamp it for the code excerpt: showing the tail of their template is
        // far more use than showing nothing, which is what an out-of-range
        // line number produces.
        $sourceLines = max(1, substr_count($source, "\n") + 1);
        $excerptLine = min($line, $sourceLines);

        return [
            'ok' => false,
            'error' => $message.' (line '.$line.($exact ? '' : ' of the generated PHP, approximate').')',
            'line' => $line,
            'excerpt' => $this->excerpt($source, $excerptLine),
            'exact' => $exact,
        ];
    }

    /**
     * House-rule traps that compile without complaint and then break at
     * runtime. Both have cost this codebase a live 500, so they are rejections,
     * not warnings.
     */
    private function houseRuleProblem(string $source): ?array
    {
        $lines = preg_split('/\R/', $source) ?: [];

        // @php(...) must never be the first thing in a view.
        foreach ($lines as $i => $text) {
            if (trim($text) === '') {
                continue;
            }
            if (preg_match('/^\s*@php\s*\(/', $text)) {
                if ($i === 0) {
                    return $this->fail(
                        'Line 1 is an @php(...) statement. Blade\'s raw-block matching swallows a '
                        .'first-line @php(...) against any later @endphp and 500s the page. Move it '
                        .'below the first line, or use a full @php ... @endphp block.',
                        1,
                        $this->excerpt($source, 1)
                    );
                }
            }
            break;
        }

        // A directive glued to a word character: "Received@if (...)".
        $directives = 'if|elseif|else|endif|unless|endunless|foreach|endforeach|forelse|endforelse|for|endfor|while|endwhile|isset|endisset|empty|endempty|switch|endswitch|php|endphp|auth|endauth|guest|endguest';

        foreach ($lines as $i => $text) {
            if (preg_match('/\w@('.$directives.')\b/', $text, $m)) {
                return $this->fail(
                    'Line '.($i + 1).' has @'.$m[1].' glued to the character before it ("'.trim($m[0]).'"). '
                    .'Blade will not compile a directive preceded by a word character, but it WILL compile '
                    .'the matching closing directive, which silently mis-scopes the rest of the template. '
                    .'Put the directive on its own line, or leave a space before the @.',
                    $i + 1,
                    $this->excerpt($source, $i + 1)
                );
            }
        }

        return null;
    }

    /** Three lines of context around the offending line, for the error panel. */
    private function excerpt(string $source, int $line): array
    {
        $lines = preg_split('/\R/', $source) ?: [];
        $out = [];

        for ($n = max(1, $line - 2); $n <= min(count($lines), $line + 2); $n++) {
            $out[] = ['number' => $n, 'text' => $lines[$n - 1] ?? '', 'is_error' => $n === $line];
        }

        return $out;
    }

    private function fail(string $message, ?int $line = null, ?array $excerpt = null): array
    {
        return ['ok' => false, 'error' => $message, 'line' => $line, 'excerpt' => $excerpt, 'exact' => true];
    }
}
