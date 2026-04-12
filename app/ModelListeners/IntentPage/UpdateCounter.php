<?php

namespace App\ModelListeners\IntentPage;

use App\Models\IntentPage;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListener;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListenerInterface;

class UpdateCounter extends ModelListener implements ModelListenerInterface
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
        return IntentPage::class;
    }

    /**
     * The list of all the events to listen to.
     *
     * @return array<string>
     */
    public function events(): array
    {
        return ["created","deleted"];
    }

    /**
     * Handle the event.
     *
     * @param IntentPage $intentPage
     * @param string $event
     * @return void
     */
    protected function _handle(IntentPage $intentPage, string $event): void
    {
        if ($event === 'created') {
            $intentPage->page()->increment('intents_count');
            $intentPage->intent()->increment('pages_count');
        }

        if ($event === 'deleted') {
            $intentPage->page()->decrement('intents_count');
            $intentPage->intent()->decrement('pages_count');
        }
    }
}
