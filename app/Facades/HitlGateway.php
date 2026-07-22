<?php

namespace App\Facades;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\HitlGateway\TaskAgent;
use App\Contracts\HitlGateway\TaskConclusion;
use App\Contracts\HitlGateway\TaskStatus;
use App\Models\File;
use App\Models\HitlPlatform;
use App\Models\HitlTask;

class HitlGateway
{
    /**
     * @param string $internalReference
     * @param HitlPlatform $hitlPlatform
     * @param TaskAgent $taskAgent
     * @param SemanticContext $context
     * @param File[] $files
     * @return TaskConclusion|null
     * @throws \Exception
     */
    public static function askHuman(string $internalReference,
                                    HitlPlatform $hitlPlatform,
                                    TaskAgent $taskAgent,
                                    SemanticContext $context,
                                    array $files = []): ?TaskConclusion
    {
        $files = array_filter($files, fn ($file) => $file instanceof File);

        $hitlPlatformManager = $hitlPlatform->getHitlPlatformManager();

        $hitlTask = HitlTask::query()->firstOrNew([
            'hitl_platform_id' => $hitlPlatform->id,
            'internal_reference' => $internalReference,
        ]);

        // Create a task on the platform if not yet created
        if (!$hitlTask->hitl_platform_reference
            or !$task = $hitlPlatformManager->fetchTask($hitlTask->hitl_platform_reference)
        ) {
            $task = $taskAgent->planTask(
                $context
                    ->clone()
                    ->set(
                        'platform_context',
                        '"Human in the loop" platform context',
                        $hitlPlatformManager->getContext()
                    ),
                $files
            );

            $hitlTask->title = $task->getTitle();
            $hitlTask->description = $task->getDescription();

            if (! $hitlPlatformManager->createTask($task) || ! $task->getReference()) {
                throw new \Exception('Failed to create HITL task on platform');
            }

            $hitlTask->hitl_platform_reference = $task->getReference();
        }

        $hitlTask->status = $task->getStatus();
        $hitlTask->data = $task->toArray();
        $hitlTask->save();

        // If it's doing, we don't give a conclusion
        if (in_array($task->getStatus(), [
            TaskStatus::PENDING,
            TaskStatus::DOING,
        ])) {
            return null;
        }

        // Rejected means the human was unable to answer — treat as resolved.
        if ($task->getStatus() === TaskStatus::REJECTED) {
            return (new TaskConclusion)
                ->setResolved(true)
                ->setConclusion('Human was unable to answer');
        }

        // Approved, make conclusion
        if ($task->getStatus() === TaskStatus::COMPLETED) {
            $taskConclusion = $taskAgent->conclude($task);

            $hitlTask->conclusion = $taskConclusion->toArray();
            $hitlTask->save();

            return $taskConclusion;
        }

        throw new \Exception('Unknown status: ' . $task->getStatus()?->value);
    }
}