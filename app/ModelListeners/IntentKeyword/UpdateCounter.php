<?php

namespace App\ModelListeners\IntentKeyword;

use App\Models\IntentKeyword;
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
        return IntentKeyword::class;
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
     * @param IntentKeyword $intentKeyword
     * @param string $event
     * @return void
     */
    protected function _handle(IntentKeyword $intentKeyword, string $event): void
    {
        if ($event === 'created') {
            $intentKeyword->intent()->increment('keywords_count');
            $intentKeyword->keyword()->increment('intents_count');
        }

        if ($event === 'deleted') {
            $intentKeyword->intent()->decrement('keywords_count');
            $intentKeyword->keyword()->decrement('intents_count');
        }
    }
}
