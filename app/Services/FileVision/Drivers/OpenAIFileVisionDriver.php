<?php

namespace App\Services\FileVision\Drivers;

use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\ResponseInput;
use App\Contracts\OpenAI\ResponseOptions;
use App\Services\FileVision\FileVisionService;
use App\Utils\FileSizeFormatter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class OpenAIFileVisionDriver extends FileVisionService
{
    protected OpenAIClient $openAIClient;

    protected string $defaultModel;

    public function __construct(OpenAIClient $openAIClient, array $config = [])
    {
        parent::__construct($config);

        $this->openAIClient = $openAIClient;
        $this->defaultModel = $config['model'] ?? 'gpt-5.4-mini';
    }

    /**
     * Generate a description for the file using AI.
     */
    protected function generateDescription(string $filePath, string $extension, string $mimeType): string
    {
        return $this->describeWithOpenAI($filePath, $extension, $mimeType);
    }

    /**
     * Get the confidence score for the file description.
     */
    protected function getConfidence(string $mimeType): float
    {
        // High confidence for AI-generated descriptions
        return 0.9;
    }

    /**
     * Describe a file using OpenAI API.
     */
    protected function describeWithOpenAI(string $filePath, string $extension, string $mimeType): string
    {
        // For images, use vision API
        if ($this->isImage($mimeType)) {
            return $this->describeImage($filePath, $mimeType);
        }

        // For text-based files, read content and analyze
        if ($this->isTextFile($mimeType)) {
            return $this->describeTextFile($filePath, $extension, $mimeType);
        }

        // For other files, describe based on metadata
        return $this->describeFileMetadata($filePath, $extension, $mimeType);
    }

    /**
     * Describe an image using OpenAI vision API.
     */
    protected function describeImage(string $filePath, string $mimeType): string
    {
        // Read file and convert to base64 using Storage facade
        $imageData = Storage::get($filePath);
        $base64Image = base64_encode($imageData);

        // Create input with image
        $input = ResponseInput::text('Describe this image in detail. Include information about what is shown, colors, composition, and any text or objects visible. DO NOT include any other proposal texts (like "Would you like to..." or "Let me know..."), or opening texts (like "Here is...", or "Result: Here is..."), or any other unrelated information.')
            ->addImageFromBase64($base64Image, $mimeType, 'high');

        // Create response options
        $options = ResponseOptions::create()->model($this->defaultModel);

        try {
            $response = $this->openAIClient->createResponse($input, $options);
        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to get AI description for image file: {$filePath}. Error: {$e->getMessage()}",
                0,
                $e
            );
        }

        $description = $response->getFirstOutputText();

        if (empty($description)) {
            throw new RuntimeException(
                "AI provider returned empty description for image file: {$filePath}"
            );
        }

        return $description;
    }

    /**
     * Describe a text file by reading its content.
     */
    protected function describeTextFile(string $filePath, string $extension, string $mimeType): string
    {
        $fileSize = Storage::size($filePath);
        $maxSize = 100000; // 100KB limit for text content

        // Read file content using Storage facade
        $content = Storage::get($filePath);
        
        // Truncate if needed
        if ($fileSize > $maxSize) {
            $content = substr($content, 0, $maxSize);
            $content .= "\n\n[File truncated - original size: ".FileSizeFormatter::format($fileSize).']';
        }

        // Create prompt
        $prompt = sprintf(
            "Analyze and describe this %s file (MIME type: %s). Provide a detailed description of its content, structure, and purpose.\n\nFile content:\n%s",
            strtoupper($extension),
            $mimeType,
            $content
        );

        $input = ResponseInput::text($prompt);
        $options = ResponseOptions::create()->model($this->defaultModel);

        try {
            $response = $this->openAIClient->createResponse($input, $options);
        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to get AI description for text file: {$filePath}. Error: {$e->getMessage()}",
                0,
                $e
            );
        }

        $description = $response->getFirstOutputText();

        if (empty($description)) {
            throw new RuntimeException(
                "AI provider returned empty description for text file: {$filePath}"
            );
        }

        return $description;
    }

    /**
     * Describe a file based on its metadata.
     */
    protected function describeFileMetadata(string $filePath, string $extension, string $mimeType): string
    {
        $fileSize = Storage::size($filePath);
        $sizeFormatted = FileSizeFormatter::format($fileSize);

        // Create prompt with file metadata
        $prompt = sprintf(
            "Describe this file based on its metadata:\n- File extension: %s\n- MIME type: %s\n- File size: %s\n\nProvide a detailed description of what this file likely contains, its purpose, and typical use cases for this file type.",
            strtoupper($extension),
            $mimeType,
            $sizeFormatted
        );

        $input = ResponseInput::text($prompt);
        $options = ResponseOptions::create()->model($this->defaultModel);

        try {
            $response = $this->openAIClient->createResponse($input, $options);
        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to get AI description for file: {$filePath}. Error: {$e->getMessage()}",
                0,
                $e
            );
        }

        $description = $response->getFirstOutputText();

        if (empty($description)) {
            throw new RuntimeException(
                "AI provider returned empty description for file: {$filePath}"
            );
        }

        return $description;
    }

    /**
     * Check if the file is a text-based file.
     */
    protected function isTextFile(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'text/') ||
               $mimeType === 'application/json' ||
               $mimeType === 'application/xml' ||
               $mimeType === 'application/javascript' ||
               str_contains($mimeType, 'javascript') ||
               str_contains($mimeType, 'json') ||
               str_contains($mimeType, 'xml');
    }
}
