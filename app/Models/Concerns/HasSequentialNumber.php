<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Allocates the next sequential, human-readable record number (REQ-1001,
 * TKT-1001, WO-1001, PRJ-1001) inside a transaction so two simultaneous
 * creates can never mint the same one. The model declares NUMBER_PREFIX and
 * optionally NUMBER_START; a booted() hook fills `number` on create.
 */
trait HasSequentialNumber
{
    public static function bootHasSequentialNumber(): void
    {
        static::creating(function ($model) {
            if (! $model->number) {
                $model->number = static::nextNumber();
            }
        });
    }

    public static function nextNumber(): string
    {
        $prefix = static::NUMBER_PREFIX;
        $start = defined(static::class.'::NUMBER_START') ? static::NUMBER_START : 1000;

        return DB::transaction(function () use ($prefix, $start) {
            $last = static::withoutGlobalScopes()
                ->lockForUpdate()
                ->where('number', 'like', $prefix.'%')
                ->orderByRaw('LENGTH(number) DESC, number DESC')
                ->value('number');

            $next = $last
                ? ((int) preg_replace('/\D/', '', substr($last, strlen($prefix))) + 1)
                : $start;

            return $prefix.$next;
        });
    }
}
