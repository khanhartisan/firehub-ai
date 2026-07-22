<?php

namespace App\Services\HitlGateway\HitlPlatformManagerDrivers\FiretasksPlatformManager;

enum TaskStatus: int
{
    case PLANNING = 0;
    case PENDING = 1;
    case DOING = 2;
    case BLOCKED = 3;
    case AWAITING_SUBTASKS = 4;
    case AWAITING_ADVICE = 5;
    case AWAITING_APPROVAL = 6;
    case AWAITING_REVISION = 7;
    case REJECTED = 8;
    case COMPLETED = 9;
}