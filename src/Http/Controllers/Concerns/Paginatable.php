<?php

namespace Easybdit\LaravelEasyAttendance\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * Shared `?per_page=` handling for every list endpoint — clamped so a
 * client can't request an unbounded page and defeat the point of paginating.
 */
trait Paginatable
{
    protected function perPage(Request $request, int $default = 25, int $max = 100): int
    {
        return min($max, max(1, (int) $request->input('per_page', $default)));
    }
}
