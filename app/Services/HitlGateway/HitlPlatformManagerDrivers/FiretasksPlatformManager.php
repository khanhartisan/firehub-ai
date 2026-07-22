<?php

namespace App\Services\HitlGateway\HitlPlatformManagerDrivers;

use App\Contracts\Config;
use App\Contracts\HitlGateway\HitlPlatformManager;
use App\Contracts\HitlGateway\Human;
use App\Contracts\HitlGateway\Message;
use App\Contracts\HitlGateway\Role;
use App\Contracts\HitlGateway\Task;
use App\Contracts\HitlGateway\TaskAction;
use App\Contracts\HitlGateway\TaskOutput;
use App\Contracts\HitlGateway\TaskStatus;
use App\Models\File;
use App\Models\Meta;
use App\Services\HitlGateway\HitlPlatformManagerDrivers\FiretasksPlatformManager\TaskStatus as FiretasksTaskStatus;
use App\Utils\Json;
use App\Utils\Markdown;
use App\Utils\Str;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use League\CommonMark\Exception\CommonMarkException;

class FiretasksPlatformManager extends AbstractHitlPlatformManager implements HitlPlatformManager
{
    /**
     * @throws GuzzleException
     * @throws Exception
     */
    public function fetchTask(string $reference): ?Task
    {
        $apiClient = $this->getApiClient();

        try {
            $taskResponse = $apiClient->get('/api/tasks/' . $reference);
            $taskData = Json::decode($taskResponse->getBody()->getContents(), true)['data'];
            $task = $this->mapApiDataToTask($taskData);

            $messagesResponse = $apiClient->get('/api/messages', [
                'query' => [
                    'resource_type' => 'task',
                    'resource_id' => $task->getReference(),
                    'sort' => '-id'
                ]
            ]);
            $messagesData = Json::decode($messagesResponse->getBody()->getContents(), true)['data'];
            $messages = $this->mapMessages($messagesData);

            return $task->setMessages($messages);

        } catch (RequestException $e) {
            if ($e->hasResponse() and $e->getResponse()->getStatusCode() === 404) {
                return null;
            }

            if (env('APP_DEBUG')) {
                dump($e);
            }
        }

        return null;
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     * @throws CommonMarkException
     */
    public function createTask(Task $task): bool
    {
        $createResponse = $this->getApiClient()->post('/api/tasks', [
            'json' => $this->mapTaskToMutationData($task)
        ]);

        if ($createResponse->getStatusCode() !== 201) {
            return false;
        }

        $taskData = Json::decode($createResponse->getBody()->getContents(), true)['data'];

        $this->mapApiDataToTask($taskData, $task);

        return true;
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    public function updateTask(string $reference, TaskAction $action): ?Task
    {
        $mutationData = [];

        // Set a new status
        if ($action->getStatus() and $newStatus = $this->mapTaskStatusToApiStatus($action->getStatus())) {
            $mutationData['status'] = $newStatus;
        }

        // Set a new task output
        if ($output = $action->getOutput()) {
            $mutationData = array_merge($mutationData, $this->mapTaskOutputToApiOutput($output));
        }

        // Send update request
        if ($mutationData) {
            $updateResponse = $this
                ->getApiClient()
                ->patch('/api/tasks/' . $reference, [
                    'json' => $mutationData
                ]);

            if ($updateResponse->getStatusCode() !== 200) {
                return null;
            }
        }

        // Post message
        if ($message = $action->getMessage()) {
            $messageResponse = $this->getApiClient()->post('/api/messages', [
                'json' => [
                    'resource_type' => 'task',
                    'resource_id' => $reference,
                    'content' => $message->getMessage(),
                ]
            ]);

            if ($messageResponse->getStatusCode() !== 201) {
                return null;
            }
        }

        return $this->fetchTask($reference);
    }

    public function makeConfig(): ?Config
    {
        return new FiretasksPlatformManager\Config();
    }

    protected function getApiClient(): Client
    {
        if (!$config = $this->getConfig()) {
            throw new Exception('Config was not set');
        }

        return new Client([
            'base_uri' => $config->get('base_url'),
            'headers' => [
                'Authorization' => 'Bearer ' . $config->get('api_key'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * @throws GuzzleException
     */
    protected function mapApiDataToTask(array $data, ?Task $task = null): Task
    {
        return ($task ?? new Task())
            ->setReference($data['id'])
            ->setTitle($data['title'])
            ->setDescription($data['description'])
            ->setStatus($this->mapApiStatusToTaskStatus($data['status']))
            ->setAssignee(
                $this->mapUserIdToHuman($data['assignee_id'])
                    ?->setRole(Role::ASSIGNEE)
            )
            ->setAdvisor(
                $this->mapUserIdToHuman($data['advisor_id'])
                    ?->setRole(Role::ADVISOR)
            )
            ->setOwner(
                $this->mapUserIdToHuman($data['owner_id'])
                    ?->setRole(Role::OWNER)
            )
            ->setFiles($this->mapApiFilesToFiles($data['attachments'] ?? []))
            ->setOutput($this->mapApiOutputToTaskOutput($data['output'], $data['output_attachments'] ?? []));
    }

    /**
     * @throws CommonMarkException|GuzzleException
     */
    protected function mapTaskToMutationData(Task $task): array
    {
        $data = [
            'folder_id' => $this->getConfig()->get('folder_id'),
            'title' => $task->getTitle(),
            'description' => Markdown::markdownToHtml($task->getDescription()),
            'auto_start' => true,
            'pass_input_to_subtasks' => true,
            'can_see_parent_input' => true,
            'pass_output' => true,
            'can_see_previous_outputs' => true,
            'included_in_parent_output' => true,
        ];

        if ($assignee = $task->getAssignee()) {
            $data['assignee_id'] = $this->mapHumanToUserId($assignee);
        }

        if ($advisor = $task->getAdvisor()) {
            $data['advisor_id'] = $this->mapHumanToUserId($advisor);
        }

        if ($owner = $task->getOwner()) {
            $data['owner_id'] = $this->mapHumanToUserId($owner);
        }

        $data['owner_id'] = $data['owner_id'] ?? $this->getConfig()->get('default_responsible_user_id');
        $data['assignee_id'] = $data['assignee_id'] ?? $this->getConfig()->get('default_responsible_user_id');

        if ($files = $task->getFiles()) {
            $data['attachments'] = $this->mapFilesToApiFiles($files);
        }

        return $data;
    }

    protected function mapApiStatusToTaskStatus(mixed $firetasksStatus): TaskStatus
    {
        return match (FiretasksTaskStatus::tryFrom($firetasksStatus)) {
            FiretasksTaskStatus::DOING,
            FiretasksTaskStatus::AWAITING_SUBTASKS,
            FiretasksTaskStatus::AWAITING_ADVICE,
            FiretasksTaskStatus::AWAITING_APPROVAL,
            FiretasksTaskStatus::AWAITING_REVISION => TaskStatus::DOING,
            FiretasksTaskStatus::COMPLETED => TaskStatus::COMPLETED,
            FiretasksTaskStatus::REJECTED => TaskStatus::REJECTED,
            default => TaskStatus::PENDING,
        };
    }

    protected function mapTaskStatusToApiStatus(TaskStatus $taskStatus): string
    {
        return match ($taskStatus) {
            TaskStatus::DOING => FiretasksTaskStatus::DOING->value,
            TaskStatus::PENDING => FiretasksTaskStatus::PENDING->value,
            TaskStatus::REJECTED => FiretasksTaskStatus::REJECTED->value,
            TaskStatus::COMPLETED => FiretasksTaskStatus::COMPLETED->value,
        };
    }

    protected function mapHumanToUserId(?Human $human): ?int
    {
        if (!$email = $human->getEmail()) {
            return null;
        }

        $cacheKey = sha1(
            static::class
            . '@' . __FUNCTION__
            . '@' . $this->getConfig()->get('base_url')
            . '@' . $email
        );

        if ($cache = Cache::get($cacheKey)) {
            return $cache;
        }

        $resolver = function () use ($human) {
            try {
                $userResponse = $this
                    ->getApiClient()
                    ->get('/api/users?email=' . $human->getEmail());

                if (!$userData = Json::decode($userResponse->getBody()->getContents(), true)['data']) {
                    return null;
                }

                return intval($userData[0]['id']);
            } catch (RequestException $e) {
                if ($e->hasResponse() and $e->getResponse()->getStatusCode() === 404) {
                    return null;
                }
                throw $e;
            }
        };

        $userId = $resolver();
        if (is_null($userId)) {
            Cache::put($cacheKey, null, now()->addMinute());
        } else {
            Cache::put($cacheKey, $userId, now()->addHour());
        }

        return $userId;
    }

    protected function mapUserIdToHuman(null|string|int $userId): ?Human
    {
        if (!$userId) {
            return null;
        }

        $cacheKey = sha1(
            static::class
            . '@' . __FUNCTION__
            . '@' . $this->getConfig()->get('base_url')
            . '@' . $userId
        );

        if ($cache = Cache::get($cacheKey)) {
            return $cache;
        }

        $resolver = function () use ($userId) {
            try {
                $userResponse = $this->getApiClient()->get('/api/users/' . $userId);
                if (!$userData = Json::decode($userResponse->getBody()->getContents(), true)['data'] ?? null) {
                    return null;
                }
                return new Human()
                    ->setEmail($userData['email'])
                    ->setName($userData['name'])
                    ->setDescription($userData['description']);
            } catch (RequestException $e) {
                if ($e->hasResponse() and $e->getResponse()->getStatusCode() === 404) {
                    return null;
                }

                if (env('APP_DEBUG')) {
                    dump($e);
                }

                throw $e;
            }
        };

        $userId = $resolver();
        if (is_null($userId)) {
            Cache::put($cacheKey, null, now()->addMinute());
        } else {
            Cache::put($cacheKey, $userId, now()->addHour());
        }

        return $userId;
    }

    /**
     * @param string[] $apiFiles
     * @return File[]
     * @throws GuzzleException
     */
    protected function mapApiFilesToFiles(array $apiFiles): array
    {
        $fileMorphClass = new File()->getMorphClass();

        $apiFiles = array_values(array_unique(array_filter($apiFiles)));

        $metaKeyGenerator = fn (string $apiFilePath) => $this->getConfig()->get('base_url').':file:'.sha1($apiFilePath);

        // Collect existing file models
        $files = array_values(array_filter(array_map(function ($apiFilePath) use ($fileMorphClass, $metaKeyGenerator, &$apiFiles) {

            $query = Meta::query()->where([
                'metable_type' => $fileMorphClass,
                'key' => $metaKeyGenerator($apiFilePath),
            ]);
            $meta = $query->first();

            if ($meta and $file = File::query()->find($meta->metable_id)) {
                unset($apiFiles[array_search($apiFilePath, $apiFiles)]);
                return $file;
            }

            return null;

        }, $apiFiles)));

        // Download non-existing files
        $apiFiles = array_values(array_unique(array_filter($apiFiles)));
        if (count($apiFiles)) {
            $downloadResponse = $this->getApiClient()->post('/api/attachments:download', [
                'json' => [
                    'attachments' => $apiFiles,
                ]
            ]);
            $downloadData = Json::decode($downloadResponse->getBody()->getContents(), true);
            foreach ($downloadData as $data) {
                $filePath = 'hitl-platform-attachments/'.parse_url($this->getConfig()->get('base_url'), PHP_URL_HOST).'/'.$data['attachment'];
                Storage::put($filePath, file_get_contents($data['download_url']));

                $file = new File();
                $file->url = $data['download_url'];
                $file->path = $filePath;
                $file->size = Storage::size($filePath);
                $file->extension = pathinfo($filePath, PATHINFO_EXTENSION);
                $file->mime_type = Storage::mimeType($filePath);
                $file->save();

                $meta = new Meta();
                $meta->metable_type = $fileMorphClass;
                $meta->metable_id = $file->id;
                $meta->key = $metaKeyGenerator($data['attachment']);
                $meta->value = $data['attachment'];
                $meta->save();

                $files[] = $file;
            }
        }

        return $files;
    }

    /**
     * @param File[] $files
     * @return array Assoc array of fileId -> apiFilePath
     * @throws GuzzleException
     * @throws Exception
     */
    protected function mapFilesToApiFiles(array $files): array
    {
        $apiFiles = [];
        foreach ($files as $key => $file) {
            if (!$file instanceof File) {
                continue;
            }

            if (!$meta = $file
                ->meta()
                ->where('key', 'like', $this->getConfig()->get('base_url').':file:%')
                ->first()
            ) {
                continue;
            }

            unset($files[$key]);
            $apiFiles[$file->id] = $meta->value;
        }

        if (count($files)) {
            $attachmentMap = [];
            $uploadResponse = $this->getApiClient()->post('/api/attachments:upload', [
                'json' => [
                    'attachments' => array_map(function (File $file) use (&$attachmentMap) {
                        $attachment = Str::slug(env('APP_NAME')).'/'.date('Y/m').'/'.$file->id.'.'.($file->extension ?? 'unknown');
                        $attachmentMap[$attachment] = $file;
                        return $attachment;
                    }, array_values($files)),
                ]
            ]);
            $uploadData = Json::decode($uploadResponse->getBody()->getContents(), true);
            foreach ($uploadData as $data) {
                $file = $attachmentMap[$data['attachment']];
                $fileReadStream = Storage::readStream($file->path);

                // Upload attachment if not exists
                if (!$data['exists']) {
                    try {
                        $s3Response = new Client()->put($data['upload_url'], [
                            'body' => $fileReadStream,
                        ]);

                        if ($s3Response->getStatusCode() < 200 || $s3Response->getStatusCode() >= 300) {
                            throw new Exception('Failed to upload attachment to S3: ' . $data['attachment']);
                        }
                    } finally {
                        if (is_resource($fileReadStream)) {
                            fclose($fileReadStream);
                        }
                    }
                }

                $apiFiles[$file->id] = $data['attachment'];
            }
        }

        return $apiFiles;
    }

    /**
     * @throws GuzzleException
     */
    protected function mapApiOutputToTaskOutput(?string $apiOutput, array $apiOutputFiles = []): ?TaskOutput
    {
        if (!$apiOutput and !$apiOutputFiles) {
            return null;
        }

        return new TaskOutput()
            ->setContent($apiOutput)
            ->setFiles($this->mapApiFilesToFiles($apiOutputFiles));
    }

    /**
     * @throws GuzzleException
     */
    protected function mapTaskOutputToApiOutput(TaskOutput $taskOutput): array
    {
        $data = [];

        if (!is_null($taskOutput->getContent())) {
            $data['output'] = $taskOutput->getContent();
        }

        if (!is_null($taskOutput->getFiles())) {
            $data['output_attachments'] = $this->mapFilesToApiFiles($taskOutput->getFiles());
        }

        return $data;
    }

    /**
     * @param array $apiMessages
     * @return Message[]
     */
    protected function mapMessages(array $apiMessages): array
    {
        return array_map(function (array $apiMessage) {
            $message = new Message()
                ->setDatetime(Carbon::parse($apiMessage['created_at']))
                ->setMessage($apiMessage['content']);

            if ($human = $this->mapUserIdToHuman($apiMessage['user_id'])) {
                $message->setHuman($human);
            }

            return $message;
        }, $apiMessages);
    }
}
