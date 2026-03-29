<?php

namespace App\ModelListeners\Page\Saving;

use App\Models\Page;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListener;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListenerInterface;

class SetUrlHashListener extends ModelListener implements ModelListenerInterface
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
        if ($page->url === '' || $page->url === null) {
            return;
        }

        $hash = sha1($page->url);

        if ($page->url_hash !== $hash) {
            $page->url_hash = $hash;
        }
    }
}
