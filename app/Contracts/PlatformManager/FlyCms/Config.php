<?php

namespace App\Contracts\PlatformManager\FlyCms;

use Illuminate\Contracts\JsonSchema\JsonSchema;

class Config extends \App\Contracts\PlatformManager\Config
{
    public function getBaseUrl(): ?string
    {
        return $this->config['base_url'] ?? null;
    }

    public function setBaseUrl(string $url): static
    {
        $this->config['base_url'] = $url;
        return $this;
    }

    public function getApiKey(): ?string
    {
        return $this->config['api_key'] ?? null;
    }

    public function setApiKey(string $apiKey): static
    {
        $this->config['api_key'] = $apiKey;
        return $this;
    }

    public function getBranchId(): ?string
    {
        return $this->config['branch_id'] ?? null;
    }

    public function setBranchId(string $branchId): static
    {
        $this->config['branch_id'] = $branchId;
        return $this;
    }

    public function getStorageId(): ?string
    {
        return $this->config['storage_id'] ?? null;
    }

    public function setStorageId(string $storageId): static
    {
        $this->config['storage_id'] = $storageId;
        return $this;
    }

    public function toJsonSchema(JsonSchema $schema): array
    {
        return [
            'base_url' => $schema
                ->string()
                ->required(),
            'api_key' => $schema
                ->string()
                ->required(),
            'branch_id' => $schema
                ->string()
                ->required()
                ->description('Branch ID (ULID) in the FlyCms'),
            'storage_id' => $schema
                ->string()
                ->required()
                ->description('Storage ID (ULID) in the FlyCms'),
        ];
    }
}
