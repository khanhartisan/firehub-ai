<?php

namespace App\Contracts\HitlGateway;

class TaskAction
{
    protected ?TaskStatus $status;

    protected ?Message $message;

    protected ?TaskOutput $output;
}