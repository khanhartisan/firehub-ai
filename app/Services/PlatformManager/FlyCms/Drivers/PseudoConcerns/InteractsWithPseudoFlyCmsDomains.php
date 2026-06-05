<?php

namespace App\Services\PlatformManager\FlyCms\Drivers\PseudoConcerns;

use App\Contracts\PlatformManager\FlyCms\Filters\DomainFilter;
use App\Contracts\PlatformManager\FlyCms\Resources\DomainResource;

trait InteractsWithPseudoFlyCmsDomains
{
    public function showDomain(string $domainId): ?DomainResource
    {
        $domain = self::$domains[$domainId] ?? null;

        if ($domain === null) {
            return null;
        }

        return new DomainResource($domain);
    }

    /**
     * @return DomainResource[]
     */
    public function listDomains(int $page = 1, int $limit = 100, ?DomainFilter $domainFilter = null): array
    {
        $domains = array_values(self::$domains);

        if ($domainFilter !== null) {
            $domains = $this->applyDomainFilter($domains, $domainFilter);
        }

        $offset = max(0, ($page - 1) * $limit);
        $domains = array_slice($domains, $offset, $limit);

        return array_map(
            static fn (array $domain): DomainResource => new DomainResource($domain),
            $domains
        );
    }
    protected function seedSampleDomains(): void
    {
        self::$domains = [
            '01J00000000000000000000031' => array_merge($this->defaultDomainAttributes(), [
                'id' => '01J00000000000000000000031',
                'website_id' => '01J00000000000000000000001',
                'is_primary' => true,
                'is_alias' => false,
                'status' => 'active',
                'domain' => 'blog.example.com',
                'nameservers' => ['ns1.example.com', 'ns2.example.com'],
                'is_connected_to_server' => true,
            ]),
            '01J00000000000000000000032' => array_merge($this->defaultDomainAttributes(), [
                'id' => '01J00000000000000000000032',
                'website_id' => '01J00000000000000000000001',
                'is_primary' => false,
                'is_alias' => true,
                'status' => 'active',
                'domain' => 'www.blog.example.com',
                'nameservers' => ['ns1.example.com', 'ns2.example.com'],
                'is_connected_to_server' => true,
            ]),
            '01J00000000000000000000033' => array_merge($this->defaultDomainAttributes(), [
                'id' => '01J00000000000000000000033',
                'website_id' => '01J00000000000000000000002',
                'is_primary' => true,
                'is_alias' => false,
                'status' => 'inactive',
                'domain' => 'shop.demo.test',
                'nameservers' => ['ns1.demo.test'],
                'is_connected_to_server' => false,
            ]),
        ];
    }
    protected function defaultDomainAttributes(): array
    {
        return [
            'website_id' => null,
            'is_primary' => false,
            'is_alias' => false,
            'status' => 'inactive',
            'domain' => 'example.com',
            'nameservers' => [],
            'is_connected_to_server' => false,
        ];
    }
    protected function applyDomainFilter(array $domains, DomainFilter $domainFilter): array
    {
        $filterData = $domainFilter->getFilterData();

        if (isset($filterData['website_id']) && is_string($filterData['website_id']) && $filterData['website_id'] !== '') {
            $domains = array_values(array_filter(
                $domains,
                static fn (array $domain): bool => ($domain['website_id'] ?? null) === $filterData['website_id']
            ));
        }

        if (isset($filterData['domain']) && is_string($filterData['domain']) && $filterData['domain'] !== '') {
            $domains = array_values(array_filter(
                $domains,
                static fn (array $domain): bool => ($domain['domain'] ?? null) === $filterData['domain']
            ));
        }

        return $domains;
    }
}
