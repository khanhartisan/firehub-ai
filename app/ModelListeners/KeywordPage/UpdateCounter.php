<?php

namespace App\ModelListeners\KeywordPage;

use App\Models\KeywordPage;
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
        return KeywordPage::class;
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
     * @param KeywordPage $keywordPage
     * @param string $event
     * @return void
     */
    protected function _handle(KeywordPage $keywordPage, string $event): void
    {
        if ($event == "created") {
            $keywordPage->keyword()->increment('pages_count');
            $keywordPage->page()->increment('keywords_count');
        }

        if ($event == "deleted") {
            $keywordPage->keyword()->decrement('pages_count');
            $keywordPage->page()->decrement('keywords_count');
        }
    }
}
