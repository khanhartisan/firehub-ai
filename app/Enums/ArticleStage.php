<?php

namespace App\Enums;

enum ArticleStage: int
{
    case IDEA = 1;
    case RESEARCH = 2;
    case BRIEF = 3;
    case OUTLINE = 4;
    case DRAFT = 5;
    case RECTIFICATION = 6;
    case ILLUSTRATION = 7;
    case FINAL = 8;
}
