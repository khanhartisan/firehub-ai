<?php

namespace App\Contracts\HitlGateway;

use App\Models\File;

class TaskOutput
{
    protected ?string $content;

    /** @var File[] */
    protected array $files = [];
}