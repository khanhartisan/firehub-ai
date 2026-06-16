<?php

namespace App\Services\PlatformManager\FlyCms\Drivers\FlyCmsConcerns;

use App\Contracts\PlatformManager\FlyCms\Config;
use App\Contracts\PlatformManager\FlyCms\Exceptions\FlyCmsException;
use App\Contracts\PlatformManager\FlyCms\Filters\SubjectFilter;
use App\Contracts\PlatformManager\FlyCms\MutationData\SubjectMutationData\CreateSubjectData;
use App\Contracts\PlatformManager\FlyCms\Resources\SubjectResource;
use App\Contracts\PlatformManager\PublishingResult;
use App\Models\Article;
use App\Models\Publication;
use Exception;

trait InteractsWithArticles
{
    /**
     * @throws Exception
     */
    public function publishArticle(Publication $publication): PublishingResult
    {
        $article = $publication->publishable;
        if (!$article instanceof Article) {
            throw new \InvalidArgumentException('The publishable resource is not an instance of Article.');
        }

        $subject = $this->ensureSubject($publication);

        // TODO: Continue to implement

        throw new \BadMethodCallException('FlyCmsDriver::publishArticle is not implemented yet.');
    }

    /**
     * @throws FlyCmsException
     */
    protected function ensureSubject(Publication $publication): SubjectResource
    {
        $article = $publication->publishable;

        /** @var Config $config */
        $config = $this->getConfig();

        // Check if the subject exists
        $existingSubject = $this
            ->listResources(
                SubjectResource::class,
                1,
                1,
                null,
                new SubjectFilter([
                    'code' => $article->id
                ])
            );

        // Return if already exists
        if ($existingSubject) {
            /** @var SubjectResource */
            return $existingSubject[0];
        }

        // Create new if not
        /** @var SubjectResource */
        return $this->createResource(
            SubjectResource::class,
            new CreateSubjectData()
                ->setData([
                    'branch_id' => $config->getBranchId(),
                    'code' => $article->id,
                    'title' => $article->title,
                ]),
        );
    }
}
