<?php

namespace App\Contracts\HitlGateway;

enum TaskStatus: string
{
    case PENDING = 'pending';
    case DOING = 'doing';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}