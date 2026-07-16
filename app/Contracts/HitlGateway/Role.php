<?php

namespace App\Contracts\HitlGateway;

enum Role: string
{
    case ASSIGNEE = 'assignee';
    case ADVISOR = 'advisor';
    case OWNER = 'owner';
    case FOLLOWER = 'follower';
    case UNKNOWN = 'unknown';
}