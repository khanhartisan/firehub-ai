<?php

namespace App\Enums;

enum ArticleStatus: int
{
    case UNREADY = 0;
    case READY = 1;
    case PUBLISHED = 2;
    case REJECTED = 3;

    case FAILED = 4; // failed means stopped by purpose
    case ERROR = 5; // error means stopped by an unhandled error
}