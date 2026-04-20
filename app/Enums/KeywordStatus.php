<?php

namespace App\Enums;

enum KeywordStatus: int
{
    case PENDING = 1;
    case RESEARCHING = 2;
    case RESEARCHED = 3;
    case ERROR = 4;
}