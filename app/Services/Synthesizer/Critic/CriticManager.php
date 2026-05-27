<?php

namespace App\Services\Synthesizer\Critic;

use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\Synthesizer\Critic\Critic;
use App\Services\Synthesizer\Critic\ArticleCritics\ArticleCritic;
use App\Services\Synthesizer\Critic\ArticleCritics\ClarityArticleCritic;
use App\Services\Synthesizer\Critic\ArticleCritics\ConcisionArticleCritic;
use App\Services\Synthesizer\Critic\ArticleCritics\EvidenceArticleCritic;
use App\Services\Synthesizer\Critic\ArticleCritics\FingerprintArticleCritic;
use App\Services\Synthesizer\Critic\ArticleCritics\GeneralArticleCritic;
use App\Services\Synthesizer\Critic\ArticleCritics\StructureArticleCritic;
use App\Services\Synthesizer\Critic\ArticleCritics\VoiceArticleCritic;
use App\Services\Synthesizer\Critic\Drivers\BasicCriticDriver;
use App\Services\Synthesizer\Critic\Drivers\OpenAICompatibleCriticDriver;
use App\Services\Synthesizer\Critic\Drivers\OpenAICriticDriver;
use App\Services\Synthesizer\Support\SubserviceManager;
use InvalidArgumentException;
use LogicException;

class CriticManager extends SubserviceManager
{
    /** @var array<string, class-string<ArticleCritic>> */
    private const ARTICLE_CRITIC_CLASSES = [
        'voice' => VoiceArticleCritic::class,
        'structure' => StructureArticleCritic::class,
        'clarity' => ClarityArticleCritic::class,
        'concision' => ConcisionArticleCritic::class,
        'fingerprint' => FingerprintArticleCritic::class,
        'evidence' => EvidenceArticleCritic::class,
        'general' => GeneralArticleCritic::class,
    ];

    protected function configKey(): string
    {
        return 'critic';
    }

    /**
     * @return list<string>
     */
    public function purposes(): array
    {
        return array_keys(self::ARTICLE_CRITIC_CLASSES);
    }

    public function resolvePurpose(string $purpose): string
    {
        if (! isset(self::ARTICLE_CRITIC_CLASSES[$purpose])) {
            throw new InvalidArgumentException(sprintf(
                'Unknown article critic purpose "%s". Known purposes: %s',
                $purpose,
                implode(', ', $this->purposes())
            ));
        }

        return $purpose;
    }

    public function makeArticleCritic(string $purpose): ArticleCritic
    {
        $this->resolvePurpose($purpose);

        return new (self::ARTICLE_CRITIC_CLASSES[$purpose]);
    }

    public function makeCritic(string $purpose, ?string $driver = null): Critic
    {
        $driver = $driver ?? $this->getDefaultDriver();
        $purpose = $this->resolvePurpose($purpose);
        $key = "{$driver}:{$purpose}";

        if (isset($this->drivers[$key])) {
            return $this->drivers[$key];
        }

        return $this->drivers[$key] = match ($driver) {
            'basic' => new BasicCriticDriver(
                $this,
                $purpose,
                $this->driverConfiguration('basic'),
            ),
            'openai' => new OpenAICriticDriver(
                $this,
                $purpose,
                $this->container->make(OpenAIClient::class),
                $this->driverConfiguration('openai'),
            ),
            'openai_compatible' => new OpenAICompatibleCriticDriver(
                $this,
                $purpose,
                $this->driverConfiguration('openai_compatible'),
            ),
            default => throw new InvalidArgumentException("Critic driver [{$driver}] is not supported."),
        };
    }

    /**
     * @return list<Critic>
     */
    public function getCritics(?string $driver = null): array
    {
        $driver = $driver ?? $this->getDefaultDriver();
        $purposes = $this->purposes();
        $critics = [];

        foreach ($purposes as $index => $purpose) {
            $critics[] = $this->makeCritic($purpose, $driver)->setOrder($index);
        }

        return $critics;
    }

    /**
     * @param  mixed  $driver
     */
    public function driver($driver = null)
    {
        throw new LogicException('Use makeCritic($purpose) or getCritics() — each critic driver instance handles one purpose.');
    }
}
