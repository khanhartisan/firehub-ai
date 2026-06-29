<?php

namespace App\Services\PlatformManager\FlyCms\Drivers\PseudoConcerns;

use App\Contracts\PlatformManager\FlyCms\Filters\AuthorFilter;
use App\Contracts\PlatformManager\FlyCms\MutationData\AuthorMutationData\PutAuthorData;
use App\Contracts\PlatformManager\FlyCms\Resources\AuthorResource;
use Illuminate\Support\Str;
use InvalidArgumentException;

trait InteractsWithPseudoFlyCmsAuthors
{
    public function showAuthor(string $websiteId, string $email): ?AuthorResource
    {
        return $this->listAuthors(
            $websiteId,
            1,
            1,
            (new AuthorFilter)->setFilterData(['email' => $email])
        )[0] ?? null;
    }

    public function putAuthor(string $websiteId, PutAuthorData $putAuthorData): AuthorResource
    {
        $website = self::$websites[$websiteId] ?? null;

        if ($website === null) {
            throw new InvalidArgumentException("Website [{$websiteId}] not found.");
        }

        $data = $putAuthorData->getData() ?? [];
        $email = $data['email'] ?? null;

        if (! is_string($email) || $email === '') {
            throw new InvalidArgumentException('Author email is required.');
        }

        $existingAuthorId = $this->findPseudoAuthorIdByEmail($websiteId, $email);
        $now = now()->toIso8601String();

        if ($existingAuthorId !== null) {
            $author = self::$authors[$existingAuthorId];
            $author = array_merge($author, array_filter(
                $data,
                static fn (mixed $value): bool => $value !== null
            ), [
                'website_id' => $websiteId,
                'email' => $email,
                'updated_at' => $now,
            ]);
            self::$authors[$existingAuthorId] = $author;

            return $this->toAuthorResource($author);
        }

        $authorId = (string) Str::ulid();
        $author = array_merge($this->defaultAuthorAttributes(), $data, [
            'id' => $authorId,
            'website_id' => $websiteId,
            'email' => $email,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        self::$authors[$authorId] = $author;

        return $this->toAuthorResource($author);
    }

    /**
     * @return AuthorResource[]
     */
    public function listAuthors(string $websiteId,
                                int $page = 1,
                                int $perPage = 100,
                                ?AuthorFilter $authorFilter = null): array
    {
        $authors = array_values(array_filter(
            self::$authors,
            static fn (array $author): bool => ($author['website_id'] ?? null) === $websiteId
        ));

        if ($authorFilter !== null) {
            $authors = $this->applyAuthorFilter($authors, $authorFilter);
        }

        $offset = max(0, ($page - 1) * $perPage);
        $authors = array_slice($authors, $offset, $perPage);

        return array_map(
            fn (array $author): AuthorResource => $this->toAuthorResource($author),
            $authors
        );
    }

    public function deleteAuthor(string $websiteId, string $email): bool
    {
        $authorId = $this->findPseudoAuthorIdByEmail($websiteId, $email);

        if ($authorId === null) {
            return false;
        }

        unset(self::$authors[$authorId]);

        return true;
    }

    protected function seedSampleAuthors(): void
    {
        $now = now()->toIso8601String();

        self::$authors = [
            '01J00000000000000000000031' => array_merge($this->defaultAuthorAttributes(), [
                'id' => '01J00000000000000000000031',
                'website_id' => '01J00000000000000000000001',
                'email' => 'alex@example.com',
                'display_name' => 'Alex Editor',
                'short_bio' => 'Technology writer and editor.',
                'bio' => '<p>Alex covers software, AI, and developer tooling.</p>',
                'public_posts_count' => 8,
                'seo_title' => '{{ author.display_name }} | Sample Blog',
                'seo_description' => 'Articles by Alex Editor on Sample Blog.',
                'thumbnail_file_id' => '01J00000000000000000000071',
                'created_at' => $now,
                'updated_at' => $now,
            ]),
            '01J00000000000000000000032' => array_merge($this->defaultAuthorAttributes(), [
                'id' => '01J00000000000000000000032',
                'website_id' => '01J00000000000000000000001',
                'email' => 'sam@example.com',
                'display_name' => 'Sam Manager',
                'short_bio' => 'Editorial lead.',
                'bio' => '<p>Sam manages editorial strategy and publishing workflows.</p>',
                'public_posts_count' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ]),
            '01J00000000000000000000033' => array_merge($this->defaultAuthorAttributes(), [
                'id' => '01J00000000000000000000033',
                'website_id' => '01J00000000000000000000002',
                'email' => 'shop@example.com',
                'display_name' => 'Store Editor',
                'short_bio' => 'Product content specialist.',
                'bio' => null,
                'public_posts_count' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultAuthorAttributes(): array
    {
        return [
            'website_id' => null,
            'email' => 'author@example.com',
            'display_name' => 'Untitled Author',
            'short_bio' => null,
            'bio' => null,
            'public_posts_count' => 0,
            'seo_title' => null,
            'seo_description' => null,
            'thumbnail_file_id' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $author
     */
    protected function toAuthorResource(array $author): AuthorResource
    {
        $resourceData = $author;
        $thumbnailFileId = $author['thumbnail_file_id'] ?? null;

        if (is_string($thumbnailFileId) && $thumbnailFileId !== '') {
            $file = self::$files[$thumbnailFileId] ?? null;
            $resourceData['thumbnailFile'] = $file !== null
                ? $this->fileRecordForOutput($file)
                : null;
        } else {
            $resourceData['thumbnailFile'] = null;
        }

        unset($resourceData['email']);

        return new AuthorResource($resourceData);
    }

    protected function findPseudoAuthorIdByEmail(string $websiteId, string $email): ?string
    {
        foreach (self::$authors as $authorId => $author) {
            if (
                ($author['website_id'] ?? null) === $websiteId
                && ($author['email'] ?? null) === $email
            ) {
                return $authorId;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $authors
     * @return array<int, array<string, mixed>>
     */
    protected function applyAuthorFilter(array $authors, AuthorFilter $authorFilter): array
    {
        $filterData = $authorFilter->getFilterData();
        $email = $filterData['email'] ?? null;

        if (! is_string($email) || $email === '') {
            return $authors;
        }

        return array_values(array_filter(
            $authors,
            static fn (array $author): bool => ($author['email'] ?? null) === $email
        ));
    }
}
