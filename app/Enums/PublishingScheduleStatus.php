<?php

namespace App\Enums;

enum PublishingScheduleStatus: int
{
    case INACTIVE = 0;
    case ACTIVE = 1;
}