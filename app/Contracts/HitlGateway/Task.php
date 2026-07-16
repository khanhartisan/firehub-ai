<?php

namespace App\Contracts\HitlGateway;

use App\Contracts\CommonData\SemanticContext;
use App\Models\File;

class Task
{
    protected ?SemanticContext $context;

    protected ?string $reference;

    protected ?string $title;

    protected ?string $description;

    protected TaskStatus $status;

    protected ?Human $assignee;

    protected ?Human $advisor;

    protected ?Human $owner;

    /** @var Human[] */
    protected array $followers = [];

    /** @var File[] */
    protected array $files = [];

    /** @var Message[] */
    protected array $messages = [];

    protected ?TaskOutput $output;
}