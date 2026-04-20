<?php

namespace App\Jobs\Concerns;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

trait HasManualLock
{
    protected Lock $manualLock;

    protected function getManualLock(): Lock
    {
        if (!method_exists($this, 'uniqueId')) {
            throw new \Exception('uniqueId() is not defined');
        }

        if (!isset($this->uniqueFor)) {
            throw new \Exception('Job property uniqueFor is not defined');
        }

        return $this->manualLock ??= Cache::lock(sha1('manual-lock@'.$this->uniqueId()), $this->uniqueFor);
    }
}