<?php

namespace App\Console\Commands\Assistance;

use App\Contracts\CommonData\AudienceContext;
use App\Contracts\Model\Client\Context as ClientContext;
use App\Facades\SemanticContextBuilder;
use App\Enums\Language;
use App\Models\Client;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('assistance:make:client')]
#[Description('Make a client')]
class MakeClient extends Command
{
    protected const int MAX_ROUNDS = 10;
    protected const int MAX_AUDIENCE_ROUNDS = 6;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $builderDriver = $this->chooseBuilderDriver();
        if ($builderDriver === null) {
            return self::FAILURE;
        }

        $seed = (string) $this->ask(
            'Describe the publishing channel brand you want to create',
            'A personal blog of a 25 yo dude named John traveling around Japan'
        );

        $baseContext = (new ClientContext())->withEmptyFields();
        $builder = SemanticContextBuilder::driver($builderDriver)
            ->setContext($baseContext)
            ->start($seed);

        for ($round = 1; $round <= self::MAX_ROUNDS; $round++) {
            $this->newLine();
            $this->info("Round {$round}/".self::MAX_ROUNDS);

            if (! $builder->getContext() instanceof ClientContext) {
                throw new RuntimeException('Builder context must remain a ClientContext during client phase.');
            }

            $assistantMessage = $this->latestAssistantMessage($builder->getConversation());
            if ($assistantMessage !== null) {
                $this->line('');
                $this->comment('Assistant');
                $this->line($assistantMessage);
            }

            $this->displayContextSummary($builder->getContext());

            if ($builder->isFulfilled()) {
                try {
                    $audienceContexts = $this->runAudienceBuildPhase(
                        $builderDriver,
                        $builder->getConversation(),
                        $builder->getContext(),
                        is_array($builder->getContext()->getAudienceContextsValue() ?? null)
                            ? $builder->getContext()->getAudienceContextsValue()
                            : []
                    );
                    $referenceId = $this->normalizeOptionalString((string) $this->ask('Reference ID (optional)', ''));
                    $languageInput = $this->normalizeOptionalString((string) $this->ask('Language code (optional, e.g. en)', ''));
                    $client = $this->createClientFromContext($builder->getContext(), $audienceContexts, $referenceId, $languageInput);
                    $this->newLine();
                    $this->info("Client created successfully: {$client->id}");
                    $this->table(
                        ['field', 'value'],
                        [
                            ['id', (string) $client->id],
                            ['name', (string) ($client->name ?? '')],
                            ['reference_id', (string) ($client->reference_id ?? '')],
                            ['language', (string) ($client->language?->value ?? '')],
                        ]
                    );

                    return self::SUCCESS;
                } catch (\Throwable $e) {
                    $this->error('Creation failed: '.$e->getMessage());
                    $builder->continueWith(
                        'Creation failed: '.$e->getMessage().'. Please refine data and ask concise follow-up questions.'
                    );
                    continue;
                }
            }

            $questions = $builder->getPendingQuestions();
            if ($questions !== []) {
                $this->line('');
                $this->comment('Questions');
                foreach ($questions as $idx => $question) {
                    $this->line(($idx + 1).'. '.(string) $question);
                }
            }

            $reply = (string) $this->ask('Your reply (/quit to stop)');
            if (trim($reply) === '/quit') {
                $this->warn('Cancelled by user.');

                return self::FAILURE;
            }

            $builder->continueWith($reply);
        }

        $this->error('Stopped: maximum rounds reached before successful client creation.');

        return self::FAILURE;
    }

    private function chooseBuilderDriver(): ?string
    {
        $drivers = array_keys((array) config('semantic_context_builder.drivers', []));
        if ($drivers === []) {
            $this->error('No semantic context builder drivers configured.');

            return null;
        }

        $defaultDriver = (string) config('semantic_context_builder.default', $drivers[0]);
        $defaultIndex = array_search($defaultDriver, $drivers, true);
        if (! is_int($defaultIndex)) {
            $defaultIndex = 0;
        }

        return $this->choice('Choose semantic context builder driver', $drivers, $defaultIndex);
    }

    private function latestAssistantMessage(array $conversation): ?string
    {
        for ($i = count($conversation) - 1; $i >= 0; $i--) {
            $turn = $conversation[$i] ?? null;
            if (! is_array($turn) || ($turn['role'] ?? null) !== 'assistant') {
                continue;
            }

            $text = trim((string) ($turn['text'] ?? ''));
            if ($text !== '') {
                return $text;
            }
        }

        return null;
    }

    private function normalizeOptionalString(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function displayContextSummary(ClientContext $context): void
    {
        $this->table(
            ['field', 'value'],
            [
                ['name', (string) ($context->getNameValue() ?? '')],
                ['description', (string) ($context->getDescriptionValue() ?? '')],
                ['tone_of_voice', (string) ($context->getToneOfVoiceValue() ?? '')],
                ['industry', (string) ($context->getIndustryValue() ?? '')],
                ['core_mission', (string) ($context->getCoreMissionValue() ?? '')],
                ['niches_count', (string) count((array) ($context->getNichesValue() ?? []))],
                ['guidelines_count', (string) count((array) ($context->getGuidelinesValue() ?? []))],
                ['audience_contexts_count', (string) count((array) ($context->getAudienceContextsValue() ?? []))],
                ['meta_keys', implode(', ', array_keys((array) ($context->getMetaValue() ?? [])))],
            ]
        );
    }

    private function createClientFromContext(
        ClientContext $context,
        array $audienceContexts,
        ?string $referenceId = null,
        ?string $languageCode = null
    ): Client
    {
        $name = trim((string) ($context->getNameValue() ?? ''));
        if ($name === '') {
            throw new RuntimeException('name is required.');
        }

        $description = trim((string) ($context->getDescriptionValue() ?? ''));
        if ($description === '') {
            throw new RuntimeException('description is required.');
        }

        $clientContext = (new ClientContext)
            ->setName($name)
            ->setDescription($description);

        if (($context->getToneOfVoiceValue() ?? null) !== null) {
            $clientContext->setToneOfVoice((string) $context->getToneOfVoiceValue());
        }
        if (($context->getIndustryValue() ?? null) !== null) {
            $clientContext->setIndustry((string) $context->getIndustryValue());
        }
        if (($context->getCoreMissionValue() ?? null) !== null) {
            $clientContext->setCoreMission((string) $context->getCoreMissionValue());
        }
        if (is_array($context->getNichesValue() ?? null)) {
            $clientContext->setNiches($context->getNichesValue());
        }
        if (is_array($context->getGuidelinesValue() ?? null)) {
            $clientContext->setGuidelines($context->getGuidelinesValue());
        }
        if (is_array($context->getMetaValue() ?? null)) {
            $clientContext->setMeta($context->getMetaValue());
        }
        $clientContext->setAudienceContexts($audienceContexts);

        $client = new Client;
        $client->reference_id = $referenceId;
        $client->name = $name;
        $client->context = $clientContext;

        $language = $languageCode !== null ? Language::tryFrom($languageCode) : null;
        if ($languageCode !== null && ! $language instanceof Language) {
            throw new RuntimeException('language must be a valid Language enum value.');
        }
        $client->language = $language;
        $client->save();

        return $client;
    }

    /**
     * @param  array<int, array{role: string, text: string}>  $clientConversation
     * @param  ClientContext  $clientContext
     * @param  array<int, mixed>  $seedAudiences
     * @return array<int, AudienceContext>
     */
    private function runAudienceBuildPhase(
        string $builderDriver,
        array $clientConversation,
        ClientContext $clientContext,
        array $seedAudiences
    ): array
    {
        $this->newLine();
        $this->info('Audience phase');

        $requestedCount = (int) $this->ask(
            'How many audience profiles should we build?',
            (string) max(1, count($seedAudiences))
        );
        $requestedCount = max(0, min(10, $requestedCount));

        $audienceContexts = [];
        for ($index = 0; $index < $requestedCount; $index++) {
            $seed = $seedAudiences[$index] ?? [];
            $seedText = is_array($seed) && $seed !== []
                ? json_encode($seed, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                : null;
            $clientContextText = json_encode($clientContext->toArray(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}';
            $conversationText = json_encode($clientConversation, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '[]';

            $initialPrompt = "Build audience #".($index + 1)." for this client.\n\n"
                ."Client current context:\n{$clientContextText}\n\n"
                ."Previous client-building conversation:\n{$conversationText}";
            if ($seedText) {
                $initialPrompt .= "\n\nSeed audience draft:\n{$seedText}";
            }

            $builder = clone SemanticContextBuilder::driver($builderDriver);
            $builder
                ->setContext((new AudienceContext())->withEmptyFields(true))
                ->start($initialPrompt);

            for ($round = 1; $round <= self::MAX_AUDIENCE_ROUNDS; $round++) {
                $this->newLine();
                $this->info('Audience #'.($index + 1).' - Round '.$round.'/'.self::MAX_AUDIENCE_ROUNDS);

                $assistantMessage = $this->latestAssistantMessage($builder->getConversation());
                if ($assistantMessage !== null) {
                    $this->comment('Assistant');
                    $this->line($assistantMessage);
                }

                $questions = $builder->getPendingQuestions();
                if ($questions !== []) {
                    $this->comment('Questions');
                    foreach ($questions as $qIndex => $question) {
                        $this->line(($qIndex + 1).'. '.(string) $question);
                    }
                }

                if ($builder->isFulfilled()) {
                    break;
                }

                $reply = (string) $this->ask('Your audience reply (/skip to skip this audience)');
                if (trim($reply) === '/skip') {
                    continue 2;
                }

                $builder->continueWith($reply);
            }

            if (! $builder->getContext() instanceof AudienceContext) {
                throw new RuntimeException('Audience builder must return an AudienceContext.');
            }
            $audienceContexts[] = clone $builder->getContext();
        }

        return $audienceContexts;
    }

}
