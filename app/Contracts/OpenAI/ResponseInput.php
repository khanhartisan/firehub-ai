<?php

namespace App\Contracts\OpenAI;

use App\Concerns\Serializable as SerializableTrait;
use App\Contracts\Serializable;

/**
 * Input for an OpenAI Responses API request: messages with text and/or images.
 *
 * Build via ResponseInput::text(), ResponseInput::withImage(), or addText()/addImage()
 * and newMessage() for multi-turn. toArray() returns the messages array for the API.
 */
class ResponseInput implements Serializable
{
    use SerializableTrait;

    /** @var array<int, array<string, mixed>> Completed messages (role + content) */
    protected array $messages = [];

    /** @var array<int, array<string, mixed>> Content for the message being built */
    protected array $currentContent = [];

    /**
     * Create a text-only input.
     */
    public static function text(string $text): self
    {
        return (new self)->addText($text);
    }

    /**
     * Create an input with text and image.
     */
    public static function withImage(string $text, string $imageUrl): self
    {
        return (new self)
            ->addText($text)
            ->addImage($imageUrl);
    }

    /**
     * Private constructor - use static factory methods.
     */
    private function __construct()
    {
    }

    /**
     * Add text content to the current message.
     */
    public function addText(string $text): self
    {
        $this->currentContent[] = [
            'type' => 'input_text',
            'text' => $text,
        ];

        return $this;
    }

    /**
     * Add an image URL to the current message.
     */
    public function addImage(string $imageUrl, ?string $detail = null): self
    {
        $imageItem = [
            'type' => 'input_image',
            'image_url' => $imageUrl,
        ];

        if ($detail !== null) {
            $imageItem['detail'] = $detail;
        }

        $this->currentContent[] = $imageItem;

        return $this;
    }

    /**
     * Add an image from base64 data.
     */
    public function addImageFromBase64(string $base64Data, string $mimeType = 'image/jpeg', ?string $detail = null): self
    {
        $dataUrl = "data:{$mimeType};base64,{$base64Data}";

        return $this->addImage($dataUrl, $detail);
    }

    /**
     * Add an image using a file ID (from Files API).
     */
    public function addImageFromFileId(string $fileId, ?string $detail = null): self
    {
        $imageItem = [
            'type' => 'input_image',
            'file_id' => $fileId,
        ];

        if ($detail !== null) {
            $imageItem['detail'] = $detail;
        }

        $this->currentContent[] = $imageItem;

        return $this;
    }

    /**
     * Start a new message (for multi-turn conversations).
     */
    public function newMessage(string $role = 'user'): self
    {
        // Save current message if it has content
        if (! empty($this->currentContent)) {
            $this->messages[] = [
                'role' => $role,
                'content' => $this->currentContent,
            ];
        }

        $this->currentContent = [];

        return $this;
    }

    /**
     * Convert to the format expected by the OpenAI Responses API.
     * Always returns an array structure.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        // Build the final message array
        $messages = $this->messages;

        // Add current content if it exists
        if (! empty($this->currentContent)) {
            $messages[] = [
                'role' => 'user',
                'content' => $this->currentContent,
            ];
        }

        // Always return as array of messages
        return $messages;
    }

    /**
     * Get all messages.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMessages(): array
    {
        $messages = $this->messages;

        if (! empty($this->currentContent)) {
            $messages[] = [
                'role' => 'user',
                'content' => $this->currentContent,
            ];
        }

        return $messages;
    }

    /**
     * Check if input contains images.
     */
    public function hasImages(): bool
    {
        $contentArrays = array_map(fn (array $msg) => $msg['content'] ?? [], $this->messages);
        $contentArrays[] = $this->currentContent;

        $allContent = array_merge(...$contentArrays);

        return ! empty(array_filter(
            $allContent,
            fn (array $item): bool => ($item['type'] ?? null) === 'input_image'
        ));
    }

    /**
     * Check if input is text-only.
     */
    public function isTextOnly(): bool
    {
        return ! $this->hasImages();
    }

    /**
     * Create an instance from an array representation.
     *
     * @param  array<string, mixed>  $data
     * @return static
     */
    public static function fromArray(array $data): static
    {
        $input = new static();

        // Handle array of messages format
        if (isset($data[0]) && is_array($data[0])) {
            // Array of messages format
            foreach ($data as $message) {
                if (isset($message['role']) && isset($message['content'])) {
                    $input->newMessage($message['role']);
                    foreach ($message['content'] as $content) {
                        if (($content['type'] ?? null) === 'input_text' && isset($content['text'])) {
                            $input->addText($content['text']);
                        } elseif (($content['type'] ?? null) === 'input_image') {
                            if (isset($content['image_url'])) {
                                $input->addImage($content['image_url'], $content['detail'] ?? null);
                            } elseif (isset($content['file_id'])) {
                                $input->addImageFromFileId($content['file_id'], $content['detail'] ?? null);
                            }
                        }
                    }
                }
            }
        } else {
            // Single message format - treat as current content
            foreach ($data as $content) {
                if (is_array($content)) {
                    if (($content['type'] ?? null) === 'input_text' && isset($content['text'])) {
                        $input->addText($content['text']);
                    } elseif (($content['type'] ?? null) === 'input_image') {
                        if (isset($content['image_url'])) {
                            $input->addImage($content['image_url'], $content['detail'] ?? null);
                        } elseif (isset($content['file_id'])) {
                            $input->addImageFromFileId($content['file_id'], $content['detail'] ?? null);
                        }
                    }
                }
            }
        }

        return $input;
    }
}
