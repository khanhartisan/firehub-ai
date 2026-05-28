<?php

namespace App\Console\Commands\Sanctum;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

use function Laravel\Prompts\search;

#[Signature('sanctum:token
    {user? : The user email or ULID (optional when interactive)}
    {--name=mcp : The token name}
    {--abilities=* : Token abilities}
    {--expires-at= : Token expiration (any strtotime-compatible value)}')]
#[Description('Create a Sanctum personal access token for a user')]
class GenerateToken extends Command
{
    private const int SUGGESTED_USER_LIMIT = 8;

    private const int SEARCH_RESULT_LIMIT = 15;

    public function handle(): int
    {
        $user = $this->resolveUserArgument();

        if ($user === null) {
            return self::FAILURE;
        }

        $abilities = array_values(array_filter(
            (array) $this->option('abilities'),
            static fn (mixed $ability): bool => is_string($ability) && $ability !== '',
        ));

        $expiresAt = $this->option('expires-at');
        $expiresAt = is_string($expiresAt) && trim($expiresAt) !== ''
            ? Carbon::parse($expiresAt)
            : null;

        $accessToken = $user->createToken(
            (string) $this->option('name'),
            $abilities,
            $expiresAt,
        );

        $this->info('Sanctum token created.');
        $this->table(
            ['field', 'value'],
            [
                ['user_id', (string) $user->id],
                ['email', (string) $user->email],
                ['token_name', $accessToken->accessToken->name],
                ['token_id', (string) $accessToken->accessToken->id],
                ['abilities', $abilities === [] ? '*' : implode(', ', $abilities)],
                ['expires_at', $accessToken->accessToken->expires_at?->toIso8601String() ?? 'never'],
            ],
        );

        $this->newLine();
        $this->line('Bearer token (shown once):');
        $this->line($accessToken->plainTextToken);

        return self::SUCCESS;
    }

    private function resolveUserArgument(): ?User
    {
        $identifier = trim((string) ($this->argument('user') ?? ''));

        if ($identifier !== '') {
            $user = $this->findUserByIdentifier($identifier);

            if ($user === null) {
                $this->error('No user found for the given email or ID.');
            }

            return $user;
        }

        if (! $this->input->isInteractive()) {
            $this->error('The user argument is required when not running interactively.');

            return null;
        }

        return $this->promptForUser();
    }

    private function promptForUser(): ?User
    {
        $suggested = $this->suggestedUsers();

        if ($suggested->isEmpty()) {
            $this->warn('No users in the database. Create a user first.');

            return null;
        }

        $this->info('Recent users (type to search by name, email, or ID):');
        $this->table(
            ['id', 'name', 'email'],
            $suggested->map(fn (User $user): array => [
                (string) $user->id,
                (string) $user->name,
                (string) $user->email,
            ])->all(),
        );

        $this->newLine();

        $userId = search(
            label: 'User',
            placeholder: 'e.g. jane@example.com',
            options: fn (string $value): array => $this->userSearchOptions($value, $suggested),
        );

        $user = User::query()->find($userId);

        if ($user === null) {
            $this->error('Selected user no longer exists.');

            return null;
        }

        return $user;
    }

    /**
     * @return Collection<int, User>
     */
    private function suggestedUsers(): Collection
    {
        return User::query()
            ->orderByDesc('created_at')
            ->limit(self::SUGGESTED_USER_LIMIT)
            ->get();
    }

    /**
     * @param  Collection<int, User>  $suggested
     * @return array<string, string>
     */
    private function userSearchOptions(string $value, Collection $suggested): array
    {
        $value = trim($value);

        if ($value === '') {
            return $this->formatUsersForPrompt($suggested);
        }

        $users = User::query()
            ->where(function ($query) use ($value): void {
                $like = '%'.$value.'%';

                $query->where('name', 'ilike', $like)
                    ->orWhere('email', 'ilike', $like)
                    ->orWhere('id', $value);
            })
            ->orderByDesc('created_at')
            ->limit(self::SEARCH_RESULT_LIMIT)
            ->get();

        if ($users->isEmpty()) {
            return [];
        }

        return $this->formatUsersForPrompt($users);
    }

    /**
     * @param  Collection<int, User>  $users
     * @return array<string, string>
     */
    private function formatUsersForPrompt(Collection $users): array
    {
        return $users
            ->mapWithKeys(fn (User $user): array => [
                (string) $user->id => $this->formatUserLabel($user),
            ])
            ->all();
    }

    private function formatUserLabel(User $user): string
    {
        return sprintf('%s <%s>', $user->name, $user->email);
    }

    private function findUserByIdentifier(string $identifier): ?User
    {
        return User::query()
            ->where('email', $identifier)
            ->orWhere('id', $identifier)
            ->first();
    }
}
