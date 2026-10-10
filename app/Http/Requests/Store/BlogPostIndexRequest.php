<?php

namespace App\Http\Requests\Store;

use App\Http\Requests\Shared\ListQueryRequest;

class BlogPostIndexRequest extends ListQueryRequest
{
    public function defaultLimit(): int
    {
        return 10;
    }
}
