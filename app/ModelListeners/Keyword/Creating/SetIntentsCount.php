<?php

namespace App\ModelListeners\Keyword\Creating;

use App\Models\Keyword;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListener;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListenerInterface;

class SetIntentsCount extends ModelListener implements ModelListenerInterface
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
        return Keyword::class;
    }

    /**
     * The list of all the events to listen to.
     *
     * @return array<string>
     */
    public function events(): array
    {
        return ["creating"];
    }

    /**
     * Handle the event.
     *
     * @param Keyword $keyword
     * @param string $event
     * @return void
     */
    protected function _handle(Keyword $keyword, string $event): void
    {
        $keyword->intents_count = $keyword->intents_count ?? 0;
    }
}
