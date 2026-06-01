<?php

namespace App\Enums;

enum ChannelStatus: int
{
    case PENDING = 0;
    case CONNECTING = 1;
    case CONNECTED = 2;
    case ERROR = 3;
}