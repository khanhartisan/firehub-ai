<?php

namespace App\Enums;

enum ScrapingStage: string
{
    case FETCHING = 'fetching';
    case DATA_PREPARING = 'data_preparing';
    case DATA_PARSING = 'data_parsing';
    case ENRICHMENT = 'enrichment';
    case VERTICAL_RESOLUTION = 'vertical_resolution';
    case POLICY_EVALUATION = 'policy_evaluation';
    case FINISHING = 'finishing';
    case EXPANDING = 'expanding';
}