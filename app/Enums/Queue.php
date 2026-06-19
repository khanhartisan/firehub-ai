<?php

namespace App\Enums;

use Illuminate\Support\Facades\Queue as QueueFacade;

enum Queue: string
{
    case DEFAULT = 'default';
    case ARTICLE_BUILDING = 'article_building';
    case KEYWORD_RESEARCHING = 'keyword_researching';
    case PAGE_SCRAPING = 'page_scraping';
    case FILE_SCRAPING = 'file_scraping';
    case PUBLISHING = 'publishing';

    case SCHEDULER = 'scheduler';

    /**
     * Config key for this queue's max size (e.g. max_scraping_queue_size).
     */
    public function maxSizeConfigKey(): string
    {
        return 'max_'.$this->value.'_queue_size';
    }

    /**
     * Default max queue size when not set in config.
     */
    private const int DEFAULT_MAX_SIZE = 100;

    /**
     * Maximum number of jobs allowed on this queue. Defaults to DEFAULT_MAX_SIZE when not set in config.
     */
    public function maxSize(): int
    {
        $value = config('queue.'.$this->maxSizeConfigKey());

        return $value === null || $value === '' ? self::DEFAULT_MAX_SIZE : (int) $value;
    }

    /**
     * Whether a new job can be dispatched to this queue (current size below max).
     */
    public function canDispatch(): bool
    {
        return QueueFacade::size($this->value) < $this->maxSize();
    }

    /**
     * Number of job slots available (max - current).
     */
    public function slotsAvailable(): int
    {
        return max(0, $this->maxSize() - QueueFacade::size($this->value));
    }
}
