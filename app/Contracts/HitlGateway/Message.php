<?php

namespace App\Contracts\HitlGateway;

use App\Contracts\Serializable;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class Message implements Serializable
{
    use \App\Concerns\Serializable;

    protected ?Human $human = null;

    protected ?string $message = null;

    protected ?CarbonInterface $datetime = null;

    public function getHuman(): ?Human
    {
        return $this->human;
    }

    public function setHuman(?Human $human): static
    {
        $this->human = $human;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getDatetime(): ?CarbonInterface
    {
        return $this->datetime;
    }

    public function setDatetime(?CarbonInterface $datetime): static
    {
        $this->datetime = $datetime;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'human' => $this->getHuman()?->toArray(),
            'message' => $this->getMessage(),
            'datetime' => $this->getDatetime()?->toIso8601String(),
        ];
    }

    public static function fromArray(array $data): static
    {
        $message = new static;

        if (array_key_exists('human', $data)) {
            $rawHuman = $data['human'];
            if ($rawHuman instanceof Human) {
                $message->setHuman($rawHuman);
            } elseif (is_array($rawHuman)) {
                $message->setHuman(Human::fromArray($rawHuman));
            } elseif ($rawHuman === null) {
                $message->setHuman(null);
            } else {
                throw new \InvalidArgumentException('Message "human" is invalid.');
            }
        }

        if (array_key_exists('message', $data)) {
            $message->setMessage($data['message'] !== null ? (string) $data['message'] : null);
        }

        if (array_key_exists('datetime', $data)) {
            $rawDatetime = $data['datetime'];
            if ($rawDatetime instanceof CarbonInterface) {
                $message->setDatetime($rawDatetime);
            } elseif ($rawDatetime === null || $rawDatetime === '') {
                $message->setDatetime(null);
            } elseif (is_string($rawDatetime) || $rawDatetime instanceof \DateTimeInterface) {
                $message->setDatetime(Carbon::parse($rawDatetime));
            } else {
                throw new \InvalidArgumentException('Message "datetime" is invalid.');
            }
        }

        return $message;
    }
}
