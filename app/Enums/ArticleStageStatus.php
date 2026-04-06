<?php

namespace App\Enums;

enum ArticleStageStatus: int
{
    case PENDING = 1;
    case PROCESSING = 2;
    case AWAITING_REVIEW = 3;
    case AWAITING_REVISION = 4;
    case APPROVED = 5;
    case REJECTED = 6;
}