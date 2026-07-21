<?php

namespace App\Services;

/**
 * A small line diff, hand-rolled.
 *
 * The Template Manager needs to answer one question well: "what did this
 * version change?". That is a unified line diff, which is about forty lines of
 * longest-common-subsequence, so it is written here rather than pulling a diff
 * package into a self-hosted product's dependency tree.
 *
 * The LCS table is O(n*m); templates are hundreds of lines, not millions, and
 * anything past the guard below falls back to a whole-file replacement diff
 * rather than eating the merchant's memory limit.
 */
class DiffService
{
    private const MAX_LINES = 4000;

    /**
     * @return array{rows: array<int, array{type: string, left: ?int, right: ?int, text: string}>, added: int, removed: int}
     */
    public function lines(string $before, string $after): array
    {
        $a = $before === '' ? [] : preg_split('/\R/', $before);
        $b = $after === '' ? [] : preg_split('/\R/', $after);

        if (count($a) > self::MAX_LINES || count($b) > self::MAX_LINES) {
            return $this->wholesale($a, $b);
        }

        $rows = $this->walk($a, $b, $this->lcs($a, $b));

        return [
            'rows' => $rows,
            'added' => count(array_filter($rows, fn ($r) => $r['type'] === 'add')),
            'removed' => count(array_filter($rows, fn ($r) => $r['type'] === 'del')),
        ];
    }

    /** Classic LCS length table. */
    private function lcs(array $a, array $b): array
    {
        $n = count($a);
        $m = count($b);
        $table = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));

        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $table[$i][$j] = $a[$i] === $b[$j]
                    ? $table[$i + 1][$j + 1] + 1
                    : max($table[$i + 1][$j], $table[$i][$j + 1]);
            }
        }

        return $table;
    }

    /** Walk the table into add/del/context rows. */
    private function walk(array $a, array $b, array $table): array
    {
        $rows = [];
        $i = 0;
        $j = 0;
        $n = count($a);
        $m = count($b);

        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) {
                $rows[] = ['type' => 'ctx', 'left' => $i + 1, 'right' => $j + 1, 'text' => $a[$i]];
                $i++;
                $j++;
            } elseif ($table[$i + 1][$j] >= $table[$i][$j + 1]) {
                $rows[] = ['type' => 'del', 'left' => $i + 1, 'right' => null, 'text' => $a[$i]];
                $i++;
            } else {
                $rows[] = ['type' => 'add', 'left' => null, 'right' => $j + 1, 'text' => $b[$j]];
                $j++;
            }
        }

        while ($i < $n) {
            $rows[] = ['type' => 'del', 'left' => $i + 1, 'right' => null, 'text' => $a[$i]];
            $i++;
        }

        while ($j < $m) {
            $rows[] = ['type' => 'add', 'left' => null, 'right' => $j + 1, 'text' => $b[$j]];
            $j++;
        }

        return $this->collapse($rows);
    }

    /**
     * Collapse long runs of unchanged lines into a marker. A merchant comparing
     * two versions of a 400-line template wants the changes, not the template.
     */
    private function collapse(array $rows, int $context = 3): array
    {
        $keep = [];
        $count = count($rows);

        foreach ($rows as $index => $row) {
            if ($row['type'] !== 'ctx') {
                $keep[$index] = true;

                for ($k = 1; $k <= $context; $k++) {
                    if (isset($rows[$index - $k])) {
                        $keep[$index - $k] = true;
                    }
                    if (isset($rows[$index + $k])) {
                        $keep[$index + $k] = true;
                    }
                }
            }
        }

        $out = [];
        $skipping = false;

        for ($index = 0; $index < $count; $index++) {
            if (isset($keep[$index])) {
                $out[] = $rows[$index];
                $skipping = false;
            } elseif (! $skipping) {
                $out[] = ['type' => 'gap', 'left' => null, 'right' => null, 'text' => ''];
                $skipping = true;
            }
        }

        return $out;
    }

    /** Fallback for pathologically large templates. */
    private function wholesale(array $a, array $b): array
    {
        return [
            'rows' => [[
                'type' => 'gap',
                'left' => null,
                'right' => null,
                'text' => 'Template is too large to diff line by line ('.count($a).' vs '.count($b).' lines).',
            ]],
            'added' => count($b),
            'removed' => count($a),
        ];
    }
}
