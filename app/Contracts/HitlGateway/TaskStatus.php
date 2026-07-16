<?php

namespace App\Contracts\HitlGateway;

enum TaskStatus: string
{
    case PENDING = 'string';
    case DOING = 'doing';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}