<?php

namespace App\ModelListeners\Keyword\Saving;

use App\Models\Keyword;
use App\Utils\Str;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListener;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListenerInterface;

class SanitizeKeyword extends ModelListener implements ModelListenerInterface
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
        return ["saving"];
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
        $keyword->keyword = Str::sanitizeKeyword($keyword->keyword);
    }
}
