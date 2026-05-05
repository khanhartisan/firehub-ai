<?php

namespace App\Enums;

enum ArticleStage: int
{
    case IDEA = 1;
    case RESEARCH = 2;
    case BRIEF = 3;
    case OUTLINE = 4;
    case DRAFT = 5;
    case ILLUSTRATION = 6;
    case FINAL = 7;
}