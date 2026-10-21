<?php

namespace App\Support;

use Illuminate\Http\Request;

/** Sends visitors back to the form they submitted (previous URL + #form-id). */
class FormAnchor
{
    public static function back(Request $request): string
    {
        $previous = url()->previous();
        // only ever redirect to our own site
        if (! str_starts_with($previous, url('/'))) {
            $previous = url('/');
        }
        $anchor = (string) $request->input('_anchor');
        $previous = strtok($previous, '#');

        return preg_match('/^[a-z0-9-]{1,40}$/', $anchor) ? $previous.'#'.$anchor : $previous;
    }
}
