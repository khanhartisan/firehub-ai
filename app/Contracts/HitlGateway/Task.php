<?php

namespace App\Contracts\HitlGateway;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\HitlGateway\Concerns\ResolvesFilesFromIds;
use App\Contracts\Serializable;
use App\Models\File;

class Task implements Serializable
{
    use \App\Concerns\Serializable;
    use ResolvesFilesFromIds;

    protected ?SemanticContext $context = null;

    protected ?string $reference = null;

    protected ?string $title = null;

    protected ?string $description = null;

    protected TaskStatus $status = TaskStatus::PENDING;

    protected ?Human $assignee = null;

    protected ?Human $advisor = null;

    protected ?Human $owner = null;

    /** @var Human[] */
    protected array $followers = [];

    /** @var File[] */
    protected array $files = [];

    /** @var Message[] */
    protected array $messages = [];

    protected ?TaskOutput $output = null;

    public function getContext(): ?SemanticContext
    {
        return $this->context;
    }

    public function setContext(?SemanticContext $context): static
    {
        $this->context = $context;

        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getStatus(): TaskStatus
    {
        return $this->status;
    }

    public function setStatus(TaskStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getAssignee(): ?Human
    {
        return $this->assignee;
    }

    public function setAssignee(?Human $assignee): static
    {
        $this->assignee = $assignee;

        return $this;
    }

    public function getAdvisor(): ?Human
    {
        return $this->advisor;
    }

    public function setAdvisor(?Human $advisor): static
    {
        $this->advisor = $advisor;

        return $this;
    }

    public function getOwner(): ?Human
    {
        return $this->owner;
    }

    public function setOwner(?Human $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    /**
     * @return Human[]
     */
    public function getFollowers(): array
    {
        return $this->followers;
    }

    /**
     * @param  Human[]  $followers
     */
    public function setFollowers(array $followers): static
    {
        $this->followers = [];

        foreach ($followers as $follower) {
            $this->addFollower($follower);
        }

        return $this;
    }

    public function addFollower(Human $follower): static
    {
        $this->followers[] = $follower;

        return $this;
    }

    /**
     * @return File[]
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * @param  File[]  $files
     */
    public function setFiles(array $files): static
    {
        $this->files = [];

        foreach ($files as $file) {
            $this->addFile($file);
        }

        return $this;
    }

    public function addFile(File $file): static
    {
        $this->files[] = $file;

        return $this;
    }

    /**
     * @return Message[]
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * @param  Message[]  $messages
     */
    public function setMessages(array $messages): static
    {
        $this->messages = [];

        foreach ($messages as $message) {
            $this->addMessage($message);
        }

        return $this;
    }

    public function addMessage(Message $message): static
    {
        $this->messages[] = $message;

        return $this;
    }

    public function getOutput(): ?TaskOutput
    {
        return $this->output;
    }

    public function setOutput(?TaskOutput $output): static
    {
        $this->output = $output;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'context' => $this->getContext()?->toArray(),
            'reference' => $this->getReference(),
            'title' => $this->getTitle(),
            'description' => $this->getDescription(),
            'status' => $this->getStatus()->value,
            'assignee' => $this->getAssignee()?->toArray(),
            'advisor' => $this->getAdvisor()?->toArray(),
            'owner' => $this->getOwner()?->toArray(),
            'followers' => array_map(
                static fn (Human $human): array => $human->toArray(),
                $this->getFollowers()
            ),
            'files' => array_values(array_filter(array_map(
                static fn (File $file) => $file->getKey(),
                $this->getFiles()
            ))),
            'messages' => array_map(
                static fn (Message $message): array => $message->toArray(),
                $this->getMessages()
            ),
            'output' => $this->getOutput()?->toArray(),
        ];
    }

    public static function fromArray(array $data): static
    {
        $task = new static;

        if (array_key_exists('context', $data)) {
            $rawContext = $data['context'];
            if ($rawContext instanceof SemanticContext) {
                $task->setContext($rawContext);
            } elseif (is_array($rawContext)) {
                $task->setContext(SemanticContext::fromArray($rawContext));
            } elseif ($rawContext === null) {
                $task->setContext(null);
            } else {
                throw new \InvalidArgumentException('Task "context" is invalid.');
            }
        }

        if (array_key_exists('reference', $data)) {
            $task->setReference($data['reference'] !== null ? (string) $data['reference'] : null);
        }

        if (array_key_exists('title', $data)) {
            $task->setTitle($data['title'] !== null ? (string) $data['title'] : null);
        }

        if (array_key_exists('description', $data)) {
            $task->setDescription($data['description'] !== null ? (string) $data['description'] : null);
        }

        if (array_key_exists('status', $data)) {
            $rawStatus = $data['status'];
            $status = $rawStatus instanceof TaskStatus
                ? $rawStatus
                : (is_string($rawStatus) ? TaskStatus::tryFrom($rawStatus) : null);

            if (! $status instanceof TaskStatus) {
                throw new \InvalidArgumentException('Task "status" is invalid.');
            }

            $task->setStatus($status);
        }

        if (array_key_exists('assignee', $data)) {
            $task->setAssignee(self::humanFromMixed($data['assignee'], 'assignee'));
        }

        if (array_key_exists('advisor', $data)) {
            $task->setAdvisor(self::humanFromMixed($data['advisor'], 'advisor'));
        }

        if (array_key_exists('owner', $data)) {
            $task->setOwner(self::humanFromMixed($data['owner'], 'owner'));
        }

        if (isset($data['followers']) && is_array($data['followers'])) {
            $task->setFollowers(array_values(array_filter(
                array_map(static fn ($follower): ?Human => self::humanFromMixed($follower, 'followers'), $data['followers'])
            )));
        }

        if (isset($data['files']) && is_array($data['files'])) {
            $task->setFiles(self::filesFromMixedList($data['files']));
        }

        if (isset($data['messages']) && is_array($data['messages'])) {
            $task->setMessages(array_values(array_filter(
                array_map(static function ($message): ?Message {
                    if ($message instanceof Message) {
                        return $message;
                    }

                    if (is_array($message)) {
                        return Message::fromArray($message);
                    }

                    return null;
                }, $data['messages'])
            )));
        }

        if (array_key_exists('output', $data)) {
            $rawOutput = $data['output'];
            if ($rawOutput instanceof TaskOutput) {
                $task->setOutput($rawOutput);
            } elseif (is_array($rawOutput)) {
                $task->setOutput(TaskOutput::fromArray($rawOutput));
            } elseif ($rawOutput === null) {
                $task->setOutput(null);
            } else {
                throw new \InvalidArgumentException('Task "output" is invalid.');
            }
        }

        return $task;
    }

    protected static function humanFromMixed(mixed $value, string $field): ?Human
    {
        if ($value instanceof Human) {
            return $value;
        }

        if (is_array($value)) {
            return Human::fromArray($value);
        }

        if ($value === null) {
            return null;
        }

        throw new \InvalidArgumentException('Task "'.$field.'" is invalid.');
    }
}
