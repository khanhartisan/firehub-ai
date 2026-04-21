<?php

namespace App\ModelListeners\Page\Saving;

use App\Models\Page;
use App\Models\Source;
use App\Utils\UrlNormalizer;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListener;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListenerInterface;

class CreateSource extends ModelListener implements ModelListenerInterface
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
        if ($page->source_id) {
            return;
        }

        $url = trim((string) $page->url);
        if ($url === '' || !str_starts_with($url, 'http')) {
            return;
        }

        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return;
        }

        $authority = $parts['host'];
        if (isset($parts['port'])) {
            $authority .= ':'.$parts['port'];
        }

        $baseUrl = UrlNormalizer::normalize($parts['scheme'].'://'.$authority);
        if ($baseUrl === '' || !str_starts_with($baseUrl, 'http')) {
            return;
        }

        $source = Source::query()->firstOrCreate([
            'base_url' => $baseUrl,
        ]);

        $page->source_id = $source->id;
    }
}
