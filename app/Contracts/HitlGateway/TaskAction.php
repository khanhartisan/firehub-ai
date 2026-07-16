<?php

namespace App\Contracts\HitlGateway;

use App\Contracts\Serializable;

class TaskAction implements Serializable
{
    use \App\Concerns\Serializable;

    protected ?TaskStatus $status = null;

    protected ?Message $message = null;

    protected ?TaskOutput $output = null;

    public function getStatus(): ?TaskStatus
    {
        return $this->status;
    }

    public function setStatus(?TaskStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getMessage(): ?Message
    {
        return $this->message;
    }

    public function setMessage(?Message $message): static
    {
        $this->message = $message;

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
            'status' => $this->getStatus()?->value,
            'message' => $this->getMessage()?->toArray(),
            'output' => $this->getOutput()?->toArray(),
        ];
    }

    public static function fromArray(array $data): static
    {
        $action = new static;

        if (array_key_exists('status', $data)) {
            $rawStatus = $data['status'];
            $status = $rawStatus instanceof TaskStatus
                ? $rawStatus
                : (is_string($rawStatus) ? TaskStatus::tryFrom($rawStatus) : null);

            if ($rawStatus !== null && ! $status instanceof TaskStatus) {
                throw new \InvalidArgumentException('TaskAction "status" is invalid.');
            }

            $action->setStatus($status);
        }

        if (array_key_exists('message', $data)) {
            $rawMessage = $data['message'];
            if ($rawMessage instanceof Message) {
                $action->setMessage($rawMessage);
            } elseif (is_array($rawMessage)) {
                $action->setMessage(Message::fromArray($rawMessage));
            } elseif ($rawMessage === null) {
                $action->setMessage(null);
            } else {
                throw new \InvalidArgumentException('TaskAction "message" is invalid.');
            }
        }

        if (array_key_exists('output', $data)) {
            $rawOutput = $data['output'];
            if ($rawOutput instanceof TaskOutput) {
                $action->setOutput($rawOutput);
            } elseif (is_array($rawOutput)) {
                $action->setOutput(TaskOutput::fromArray($rawOutput));
            } elseif ($rawOutput === null) {
                $action->setOutput(null);
            } else {
                throw new \InvalidArgumentException('TaskAction "output" is invalid.');
            }
        }

        return $action;
    }
}