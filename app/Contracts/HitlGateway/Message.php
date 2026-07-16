<?php

namespace App\Contracts\HitlGateway;

use Carbon\CarbonInterface;

class Message
{
    protected ?Human $human;

    protected ?string $message;

    protected ?CarbonInterface $datetime;
}