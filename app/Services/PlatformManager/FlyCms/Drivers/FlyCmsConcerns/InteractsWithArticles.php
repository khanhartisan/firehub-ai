<?php

namespace App\Services\PlatformManager\FlyCms\Drivers\FlyCmsConcerns;

use App\Contracts\PlatformManager\FlyCms\Config;
use App\Contracts\PlatformManager\FlyCms\Exceptions\FlyCmsException;
use App\Contracts\PlatformManager\FlyCms\Filters\PartFilter;
use App\Contracts\PlatformManager\FlyCms\Filters\SubjectFilter;
use App\Contracts\PlatformManager\FlyCms\MutationData\PartMutationData\CreatePartData;
use App\Contracts\PlatformManager\FlyCms\MutationData\SubjectMutationData\CreateSubjectData;
use App\Contracts\PlatformManager\FlyCms\Resources\PartResource;
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

        $subjectResource = $this->ensureSubject($publication);
        $partResource = $this->ensurePart($subjectResource);

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

    /**
     * @throws FlyCmsException
     */
    protected function ensurePart(SubjectResource $subject): PartResource
    {
        $subjectId = $subject->get('id');

        if (! is_string($subjectId) || $subjectId === '') {
            throw new FlyCmsException('Subject id is required.');
        }

        $existingPart = $this->listResources(
            PartResource::class,
            1,
            1,
            null,
            (new PartFilter)->setFilterData([
                'subject_id' => $subjectId,
                'sequence' => 1,
            ]),
        );

        if ($existingPart) {
            /** @var PartResource */
            return $existingPart[0];
        }

        /** @var PartResource */
        return $this->createResource(
            PartResource::class,
            new CreatePartData()
                ->setData([
                    'subject_id' => $subjectId,
                    'sequence' => 1,
                    'title' => $subject->get('title'),
                    'description' => $subject->get('description'),
                ]),
        );
    }
}
