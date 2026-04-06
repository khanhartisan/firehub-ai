<?php

namespace App\Enums;

enum ArticleStage: int
{
    case BRIEF = 1;
    case OUTLINE = 2;
    case DRAFT = 3;
    case FINAL = 4;
}