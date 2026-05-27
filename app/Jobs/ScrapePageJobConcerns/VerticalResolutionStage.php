<?php

namespace App\Jobs\ScrapePageJobConcerns;

use App\Contracts\PageParser\PageData;
use App\Contracts\VerticalResolver\Vertical as ContractVertical;
use App\Facades\VerticalResolver as VerticalResolverFacade;
use App\Models\Page;
use App\Models\Vertical as VerticalModel;
use App\Utils\Debugger;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

trait VerticalResolutionStage
{
    protected function handleVerticalResolutionStage(Page $page): bool
    {
        Debugger::devConsoleDump('Resolving vertical data, entity '.$page->id);

        if (!$snapshot = $page->currentSnapshot) {
            return false;
        }

        // Nothing to resolve?
        // Consider as done
        if (!$pageDataFilePath = $snapshot->getFilePathForPageData()
            or !$pageDataJson = Storage::get($pageDataFilePath)
            or !$pageData = PageData::fromJson($pageDataJson)
            or !$markdownContent = $pageData->getMarkdownContent()
        ) {
            return true;
        }

        // Resolve and propose verticals based on
        // content and existing / proposed Vertical models.
        $verticalMatches = [];
        $didResolveVerticals = false;
        $proposalVerticalIds = [];

        // Always call propose() first (even when there are no verticals yet),
        // create any proposed Vertical models, and associate them to the source.
        try {
            $initialVerticalModels = VerticalModel::all();

            $initialContractVerticals = $initialVerticalModels
                ->map(function (VerticalModel $model): ContractVertical {
                    $vertical = new ContractVertical($model->name, $model->description);
                    $vertical->setIdentifier((string) $model->id);

                    return $vertical;
                })
                ->all();

            $verticalProposals = []; /*VerticalResolverFacade::propose(
                $markdownContent,
                $initialContractVerticals
            );*/ // Skip proposing verticals

            if (!empty($verticalProposals)) {
                foreach ($verticalProposals as $proposal) {
                    $this->persistProposedVerticalTree($proposal, null, $page->source_id, $proposalVerticalIds);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('ScrapePageJob: Failed to propose verticals for entity', [
                'page_id' => $page->id,
                'exception' => $e,
            ]);
            return false;
        }

        // Re-load all verticals (including newly proposed ones) and call resolve()
        // to get matches that will be attached to the entity. If there are no verticals
        // at all, there is nothing to match, so resolve() is skipped.
        $allVerticalModels = VerticalModel::all();
        if ($allVerticalModels->isNotEmpty()) {
            try {
                $contractVerticals = $allVerticalModels
                    ->map(function (VerticalModel $model): ContractVertical {
                        $vertical = new ContractVertical($model->name, $model->description);
                        $vertical->setIdentifier((string) $model->id);

                        return $vertical;
                    })
                    ->all();

                $verticalMatches = VerticalResolverFacade::resolve($markdownContent, $contractVerticals);
                $didResolveVerticals = true;
            } catch (\Throwable $e) {
                Log::warning('ScrapePageJob: Failed to resolve verticals for entity', [
                    'page_id' => $page->id,
                    'exception' => $e,
                ]);
                return false;
            }
        }

        // Map resolved verticals to database Vertical models and attach to the entity.
        // Proposed verticals are created and attached to the source above; whether they
        // are attached to the entity is decided solely by resolve(). This is best-effort
        // and should not cause the scrape to fail.
        try {
            if ($didResolveVerticals) {
                $verticalIds = [];

                $modelsByIdentifier = $allVerticalModels->keyBy(fn (VerticalModel $model): string => (string) $model->id);
                $parentByIdentifier = $allVerticalModels->mapWithKeys(function (VerticalModel $model): array {
                    return [(string) $model->id => $model->parent_id ? (string) $model->parent_id : null];
                })->all();

                foreach ($verticalMatches as $match) {
                    $identifier = $match->getVerticalIdentifier();
                    if (! $modelsByIdentifier->has($identifier)) {
                        continue;
                    }

                    // Attach the matched vertical and all ancestors so nesting queries work.
                    $current = (string) $modelsByIdentifier->get($identifier)->id;
                    $visited = [];
                    while ($current !== '' && ! isset($visited[$current])) {
                        $visited[$current] = true;
                        $verticalIds[] = $current;
                        $current = (string) ($parentByIdentifier[$current] ?? '');
                    }
                }

                $verticalIds = array_values(array_unique($verticalIds));

                // Sync to reflect latest resolution. If no matches, this clears previous verticals.
                $page->verticals()->sync($verticalIds);
            }
        } catch (\Throwable $e) {
            Log::warning('ScrapePageJob: Failed to sync verticals for page', [
                'page_id' => $page->id,
                'exception' => $e,
            ]);
            return false;
        }

        return true;
    }

    /**
     * Persist a proposed vertical tree (name, description, children) into VerticalModel with correct parent_id.
     *
     * @param  array<string, string>  $proposalVerticalIds  IDs of all created/updated verticals (passed by reference)
     */
    protected function persistProposedVerticalTree(ContractVertical $node, ?VerticalModel $parentModel, ?string $sourceId, array &$proposalVerticalIds): void
    {
        $parentId = $parentModel?->id;

        $model = VerticalModel::query()->firstOrCreate(
            ['name' => $node->getName()],
            [
                'description' => $node->getDescription(),
                'parent_id' => $parentId,
            ]
        );

        if ($model->parent_id !== $parentId) {
            $model->update(['parent_id' => $parentId, 'description' => $node->getDescription()]);
        }

        $proposalVerticalIds[] = $model->id;

        if ($sourceId !== null) {
            $model->sources()->syncWithoutDetaching([$sourceId]);
        }

        foreach ($node->getChildren() as $child) {
            $this->persistProposedVerticalTree($child, $model, $sourceId, $proposalVerticalIds);
        }
    }
}