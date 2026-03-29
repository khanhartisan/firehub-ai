<?php

namespace App\ModelListeners\Page\Saving;

use App\Models\Page;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListener;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListenerInterface;

class TrimDescription extends ModelListener implements ModelListenerInterface
{
    /**
     * Listeners with higher priority will run first.
     *
     * @return int
     */
    public function priority(): int
    {
        return 0;
    }

    /**
     * Listen to the events of the given model.
     *
     * @return string
     */
    public function modelClass(): string
    {
        return Page::class;
    }

    /**
     * The list of all the events to listen to.
     *
     * @return array<string>
     */
    public function events(): array
    {
        return ["saving"];
    }

    /**
     * Handle the event.
     *
     * @param Page $page
     * @param string $event
     * @return void
     */
    protected function _handle(Page $page, string $event): void
    {
        if (strlen((string) $page->description) > 1024) {
            $page->description = substr((string) $page->description, 0, 1021) . '...';
        }
    }
}
