<?php

namespace App\ModelListeners\Channel;

use App\Models\Channel;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListener;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListenerInterface;

class UpdatePlatformCounter extends ModelListener implements ModelListenerInterface
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
        return Channel::class;
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
     * @param Channel $channel
     * @param string $event
     * @return void
     */
    protected function _handle(Channel $channel, string $event): void
    {
        $channel->platform()->update([
            'channels_count' => Channel::query()->where('platform_id', $channel->platform_id)->count()
        ]);
    }
}
