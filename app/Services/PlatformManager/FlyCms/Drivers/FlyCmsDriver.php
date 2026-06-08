<?php

namespace App\Services\PlatformManager\FlyCms\Drivers;

use App\Contracts\PlatformManager\FlyCms\Config;
use App\Contracts\PlatformManager\FlyCms\Exceptions\FlyCmsException;
use App\Contracts\PlatformManager\FlyCms\Filter;
use App\Contracts\PlatformManager\FlyCms\MutationData;
use App\Contracts\PlatformManager\FlyCms\Resource;
use App\Services\PlatformManager\FlyCms\Drivers\FlyCmsConcerns\InteractsWithDomains;
use App\Services\PlatformManager\FlyCms\Drivers\FlyCmsConcerns\InteractsWithFiles;
use App\Services\PlatformManager\FlyCms\Drivers\FlyCmsConcerns\InteractsWithMenus;
use App\Services\PlatformManager\FlyCms\Drivers\FlyCmsConcerns\InteractsWithPages;
use App\Services\PlatformManager\FlyCms\Drivers\FlyCmsConcerns\InteractsWithPosts;
use App\Services\PlatformManager\FlyCms\Drivers\FlyCmsConcerns\InteractsWithRoles;
use App\Services\PlatformManager\FlyCms\Drivers\FlyCmsConcerns\InteractsWithTags;
use App\Services\PlatformManager\FlyCms\Drivers\FlyCmsConcerns\InteractsWithThemes;
use App\Services\PlatformManager\FlyCms\Drivers\FlyCmsConcerns\InteractsWithUsers;
use App\Services\PlatformManager\FlyCms\Drivers\FlyCmsConcerns\InteractsWithWebsites;
use App\Services\PlatformManager\FlyCms\FlyCmsService;
use App\Utils\Json;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

class FlyCmsDriver extends FlyCmsService
{
    use InteractsWithDomains;
    use InteractsWithFiles;
    use InteractsWithMenus;
    use InteractsWithPages;
    use InteractsWithPosts;
    use InteractsWithRoles;
    use InteractsWithTags;
    use InteractsWithThemes;
    use InteractsWithUsers;
    use InteractsWithWebsites;

    protected Client $apiClient;

    protected function getApiClient(): Client
    {
        $config = $this->getConfig();
        if (!$config instanceof Config) {
            throw new Exception('config is not set');
        }

        return $this->apiClient ??= new Client([
            'base_uri' => $config->getBaseUrl(),
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'x-api-key' => $config->getApiKey(),
            ]
        ]);
    }

    /**
     * @throws FlyCmsException
     */
    protected function sendApiRequest(string $method, string $apiPath, array $guzzleConfig = []): ResponseInterface
    {
        try {
            return $this
                ->getApiClient()
                ->request($method, '/api/'.$apiPath, $guzzleConfig);
        } catch (RequestException $exception) {
            if ($exception->hasResponse()) {
                $response = $exception->getResponse();
                throw new FlyCmsException(
                    Json::decode($response->getBody()->getContents())?->message ?? 'Unknown api error'
                );
            }

            throw new FlyCmsException('Unknown api request error', $exception);
        } catch (GuzzleException $e) {
            throw new FlyCmsException('Unknown api request error', $e);
        } catch (Exception) {
            throw new FlyCmsException('Unknown error');
        }
    }

    protected function parseResponseData(ResponseInterface $response): ?array
    {
        $data = (string) $response->getBody();
        return Json::decode($data, true)['data'] ?? null;
    }

    /**
     * Get a list of resources
     *
     * @param class-string<Resource> $resourceClass
     * @param int $page
     * @param int $perPage
     * @param string|null $sort
     * @param Filter|null $filter
     * @return Resource[]
     * @throws FlyCmsException
     */
    public function listResources(string $resourceClass,
                                  int $page,
                                  int $perPage,
                                  ?string $sort = null,
                                  ?Filter $filter = null): array
    {
        $response = $this->sendApiRequest('GET', $resourceClass::resourceNamespace(), [
            'query' => array_merge(
                [
                    'page' => $page,
                    'limit' => $perPage,
                    'sort' => $sort ?? '',
                ],
                isset($filter) ? $filter->toArray() : []
            ),
        ]);

        if (!$data = $this->parseResponseData($response)) {
            return [];
        }

        return array_map(function ($resourceData) use ($resourceClass) {
            return $resourceClass::fromArray($resourceData);
        }, $data);
    }

    /**
     * Show resource
     *
     * @param class-string<Resource> $resourceClass
     * @param string $resourceId
     * @return ?Resource
     * @throws FlyCmsException
     */
    public function showResource(string $resourceClass, string $resourceId): ?Resource
    {
        $response = $this->sendApiRequest('GET', $resourceClass::resourceNamespace().'/'.$resourceId);

        if (!$data = $this->parseResponseData($response)) {
            return null;
        }

        return $resourceClass::fromArray($data);
    }

    /**
     * Create resource
     *
     * @param class-string<Resource> $resourceClass
     * @param MutationData $data
     * @return Resource
     * @throws FlyCmsException
     */
    public function createResource(string $resourceClass,
                                   MutationData $data): Resource
    {
        $response = $this->sendApiRequest('POST', $resourceClass::resourceNamespace(), [
            'json' => $data->toArray()
        ]);

        if (!$data = $this->parseResponseData($response)) {
            throw new FlyCmsException('Failed to create resource (Unknown error)');
        }

        return $resourceClass::fromArray($data);
    }

    /**
     * Update resource
     *
     * @param class-string<Resource> $resourceClass
     * @param string $resourceId
     * @param MutationData $data
     * @return Resource
     * @throws FlyCmsException
     */
    public function updateResource(string $resourceClass,
                                   string $resourceId,
                                   MutationData $data): Resource
    {
        $response = $this->sendApiRequest('PATCH', $resourceClass::resourceNamespace().'/'.$resourceId, [
            'json' => $data->toArray()
        ]);

        if (!$data = $this->parseResponseData($response)) {
            throw new FlyCmsException('Failed to update resource (Unknown error)');
        }

        return $resourceClass::fromArray($data);
    }

    /**
     * Delete resource
     *
     * @param class-string<Resource> $resourceClass
     * @param string $resourceId
     * @return bool
     * @throws FlyCmsException
     */
    public function deleteResource(string $resourceClass, string $resourceId): bool
    {
        $response = $this->sendApiRequest('DELETE', $resourceClass::resourceNamespace().'/'.$resourceId);

        if (!$this->parseResponseData($response)) {
            throw new FlyCmsException('Failed to delete resource (Unknown error)');
        }

        return true;
    }
}