<?php

namespace App\Enums;

enum PublicationStatus: int {
    case PENDING = 0;
    case PUBLISHING = 1;
    case PUBLISHED = 2;
    case TIMEOUT = 3;
    case FAILED = 4;
    case ERROR = 5;
}