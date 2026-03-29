<?php

namespace App\Console\Commands;

use App\Enums\ScrapableType;
use App\Enums\ScrapingStatus;
use App\Facades\PageClassifier;
use App\Facades\PageParser;
use App\Models\Page;
use App\Models\Snapshot;
use App\Utils\HtmlCleaner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class TestPage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:render-page';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     * @throws \Exception
     */
    public function handle()
    {
        $page = Page::query()
            ->where('type', ScrapableType::PAGE)
            ->findOrFail($this->ask('Page ID'));

        $action = $this->choice('Service', [
             'PageClassifier', 'PageParser', 'HtmlCleaner'
        ]);

        switch ($action) {
            case 'PageClassifier':
                $this->pageClassifier($page);
                break;

            case 'PageParser':
                $this->pageParser($page);
                break;

            case 'HtmlCleaner':
                $this->htmlCleaner($page, $this->getHtml($page));
                break;

            default:
                $this->error('Unknown service');
                break;
        }
    }

    protected function getSnapshot(Page $page): Snapshot
    {
        /** @var Snapshot */
        return $page
            ->snapshots()
            ->where('scraping_status', ScrapingStatus::SUCCESS)
            ->orderByDesc('version')
            ->firstOrFail();
    }

    protected function getHtml(Page $page): string
    {
        return Storage::get($this->getSnapshot($page)->file_path);
    }

    protected function pageClassifier(Page $page): void
    {
        $sanitizedHtml = HtmlCleaner::sanitize($this->getHtml($page));
        $classification = PageClassifier::classify($sanitizedHtml);

        $this->table(['Key', 'Value'], [
            ['Description', $classification->getDescription()],
            ['Page Type', $classification->getPageType()->name],
            ['Content Type', $classification->getContentType()->name],
            ['Temporal', $classification->getTemporal()->name],
            ['Tags', implode(', ', $classification->getTags())],
        ]);
    }

    protected function pageParser(Page $page): void
    {
        $localPath = $this->ask('Save markdown to', storage_path('app/private/'.$page->id.'.md'));

        $sanitizedHtml = HtmlCleaner::sanitize($this->getHtml($page));
        $pageData = PageParser::parse($sanitizedHtml);
        file_put_contents($localPath, $pageData->getMarkdownContent());

        $this->table(['Key', 'Value'], [
            ['Title', $pageData->getTitle()],
            ['Excerpt', $pageData->getExcerpt()],
            ['Thumbnail', $pageData->getThumbnailUrl()],
            ['Published at', $pageData->getPublishedAt()?->format('Y-m-d H:i:s')],
            ['Updated at', $pageData->getUpdatedAt()?->format('Y-m-d H:i:s')],
            ['Fetched at', $pageData->getFetchedAt()?->format('Y-m-d H:i:s')],
            ['Canonical URL', $pageData->getCanonicalUrl()],
            ['Canonical Number', $pageData->getCanonicalNumber()],
            ['Markdown', 'Saved to: '.$localPath]
        ]);
    }

    protected function htmlCleaner(Page $page, string $html): void
    {
        $function = $this->choice('Function', [
            'minify', 'sanitize'
        ]);

        $localPath = $this->ask('Save to', storage_path('app/private/'.$page->id.'.html'));

        $contents = match ($function) {
            'minify' => HtmlCleaner::minify($html),
            'sanitize' => HtmlCleaner::sanitize($html),
            default => throw new \Exception('Unknown function')
        };

        file_put_contents($localPath, $contents);
        $this->info('Saved to: '.$localPath);
    }
}
