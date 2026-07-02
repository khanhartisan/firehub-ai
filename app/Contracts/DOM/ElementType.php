<?php

namespace App\Contracts\DOM;

enum ElementType: string
{
    case ARTICLE = 'article';
    case DIV = 'div';
    case SPAN = 'span';
    case P = 'p';
    case A = 'a';
    case H1 = 'h1';
    case H2 = 'h2';
    case H3 = 'h3';
    case H4 = 'h4';
    case H5 = 'h5';
    case H6 = 'h6';
    case UL = 'ul';
    case OL = 'ol';
    case LI = 'li';
    case TABLE = 'table';
    case THEAD = 'thead';
    case TBODY = 'tbody';
    case TR = 'tr';
    case TH = 'th';
    case TD = 'td';
    case IMG = 'img';
    case FIGURE = 'figure';
    case FIGCAPTION = 'figcaption';
    case CODE = 'code';
    case PRE = 'pre';
    case BLOCKQUOTE = 'blockquote';
    case EM = 'em';
    case STRONG = 'strong';
    case B = 'b';
    case I = 'i';
    case BR = 'br';
    case HR = 'hr';
}