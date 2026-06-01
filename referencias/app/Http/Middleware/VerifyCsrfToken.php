<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;


class VerifyCsrfToken extends Middleware
{

    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected $except = [
        'aws/hook/bounce',
        'aws/hook/complaint',
        'leads-bulk-upload',
        'leads-export-file-download',
        'leads-export-by-ids-file-download',
    ];

}
