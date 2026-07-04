<?php

namespace App\Enums;

enum ArticleStage: int
{
    case IDEA = 1;
    case RESEARCH = 2;
    case BRIEF = 3;
    case TAGGING = 4;
    case OUTLINE = 5;
    case DRAFT = 6;
    case RECTIFICATION = 7;
    case ILLUSTRATION = 8;
    case FINAL = 9;
}
