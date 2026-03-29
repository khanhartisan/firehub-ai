<?php

namespace App\Models;

use App\Enums\ScrapableType;
use App\Enums\ScrapingStatus;
use Illuminate\Database\Eloquent\Model;

class PageCount extends Model
{
    protected $casts = [
        'scrapable_type' => ScrapableType::class,
        'scraping_status' => ScrapingStatus::class,
    ];
}
