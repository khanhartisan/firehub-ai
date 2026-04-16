<?php

namespace App\Enums;

enum ArticleStage: int
{
    case IDEA = 1;
    case BRIEF = 2;
    case OUTLINE = 3;
    case DRAFT = 4;
    case FINAL = 5;
}