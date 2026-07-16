<?php

namespace App\Contracts\HitlGateway;

class Human
{
    protected Role $role;

    protected ?string $email = null;

    protected ?string $name = null;

    protected ?string $description = null;
}