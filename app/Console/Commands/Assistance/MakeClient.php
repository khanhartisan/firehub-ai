<?php

namespace App\Console\Commands\Assistance;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\CommonData\Audience;
use App\Contracts\Model\Client\Context as ClientContext;
use App\Contracts\OpenAI\ResponseInput;
use App\Contracts\OpenAI\ResponseOptions;
use App\Enums\Language;
use App\Facades\OpenAI;
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

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $driver = $this->chooseDriver();
        if ($driver === null) {
            return self::FAILURE;
        }

        $model = $this->ask(
            'Model',
            (string) config("openai.drivers.{$driver}.default_model", 'gpt-4o')
        );
        $model = is_string($model) && trim($model) !== '' ? trim($model) : 'gpt-4o';

        $seed = (string) $this->ask(
            'Describe the publishing channel brand you want to create',
            'A personal blog of a 25 yo dude named John traveling around Japan'
        );

        $context = new SemanticContext;
        $draft = $this->emptyDraftData();
        $conversation = [
            [
                'role' => 'user',
                'text' => $seed,
            ],
        ];

        for ($round = 1; $round <= self::MAX_ROUNDS; $round++) {
            $this->syncContext($context, $draft, $conversation);
            $this->newLine();
            $this->info("Round {$round}/".self::MAX_ROUNDS);

            $ai = $this->runAssistant($driver, $model, $context, $conversation);
            $draft = $this->mergeDraft($draft, $ai['updates'] ?? []);

            $this->line('');
            $this->comment('Assistant');
            $this->line((string) ($ai['assistant_message'] ?? ''));
            $this->displayDraftSummary($draft);

            if (($ai['is_ready'] ?? false) === true) {
                try {
                    $client = $this->createClientFromDraft($draft);
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
                    $context->set('creation_error', 'Last creation error', $e->getMessage());
                    $conversation[] = [
                        'role' => 'user',
                        'text' => 'Creation failed: '.$e->getMessage().'. Please refine the data and ask me for missing/fix inputs.',
                    ];
                    $this->error('Creation failed: '.$e->getMessage());
                    continue;
                }
            }

            $questions = is_array($ai['questions'] ?? null) ? $ai['questions'] : [];
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

            $conversation[] = [
                'role' => 'user',
                'text' => $reply,
            ];
        }

        $this->error('Stopped: maximum rounds reached before successful client creation.');

        return self::FAILURE;
    }

    private function chooseDriver(): ?string
    {
        $drivers = array_keys((array) config('openai.drivers', []));
        if ($drivers === []) {
            $this->error('No OpenAI drivers configured.');

            return null;
        }

        $defaultDriver = (string) config('openai.default', $drivers[0]);
        $defaultIndex = array_search($defaultDriver, $drivers, true);
        if (! is_int($defaultIndex)) {
            $defaultIndex = 0;
        }

        return $this->choice('Choose OpenAI driver', $drivers, $defaultIndex);
    }

    /**
     * @return array<string, mixed>
     */
    private function runAssistant(string $driver, string $model, SemanticContext $context, array $conversation): array
    {
        $payload = [
            'context' => $context->toArray(),
            'conversation' => $conversation,
        ];
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if (! is_string($json)) {
            throw new RuntimeException('Unable to encode assistant input payload.');
        }

        $prompt = <<<PROMPT
You are helping a user create a Client record.
Here "Client" means a publishing channel brand entity.

Goals:
1) Collaboratively gather and refine publishing channel brand data.
2) Propose structured updates to the draft data.
3) Ask concise follow-up questions for missing/unclear fields.
4) Set is_ready=true only when data is sufficiently complete and consistent for creation.

Draft fields:
- reference_id (optional string)
- language (optional BCP47-like code, e.g. en, vi, fr)
- name (recommended unique publishing channel brand name)
- description (editorial channel summary)
- tone_of_voice (editorial voice)
- industry (content domain)
- niches (audience/content subtopics)
- core_mission (why this channel exists)
- guidelines (editorial do/don't rules)
- meta_entries (extra channel signals as key/value pairs)
- audiences (target audience profiles)

Use the latest conversation and context. Be practical and concise.

INPUT JSON:
{$json}
PROMPT;

        $schema = $this->buildAssistantSchema();

        $response = OpenAI::driver($driver)->createResponse(
            ResponseInput::text($prompt),
            ResponseOptions::create()
                ->model($model)
                ->temperature(0.2)
                ->responseFormat([
                    'type' => 'json_schema',
                    'name' => 'make_client_assistant',
                    'schema' => $schema,
                    'strict' => true,
                ])
        );

        $text = $response->getFirstOutputText();
        if (! is_string($text) || trim($text) === '') {
            throw new RuntimeException('Assistant returned empty output.');
        }

        $decoded = json_decode($text, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Assistant returned invalid JSON: '.json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAssistantSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => $properties = [
                'assistant_message' => ['type' => 'string'],
                'is_ready' => ['type' => 'boolean'],
                'questions' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'updates' => [
                    'type' => 'object',
                    'properties' => [
                        'reference_id' => ['type' => ['string', 'null']],
                        'language' => ['type' => ['string', 'null']],
                        'name' => ['type' => ['string', 'null']],
                        'description' => ['type' => ['string', 'null']],
                        'tone_of_voice' => ['type' => ['string', 'null']],
                        'industry' => ['type' => ['string', 'null']],
                        'niches' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                        'core_mission' => ['type' => ['string', 'null']],
                        'guidelines' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                        'meta' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => $metaEntryProperties = [
                                    'key' => ['type' => 'string'],
                                    'value' => ['type' => 'string'],
                                ],
                                'required' => array_keys($metaEntryProperties),
                                'additionalProperties' => false,
                            ],
                        ],
                        'audiences' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => $audienceProperties = [
                                    'priority_weight' => ['type' => ['number', 'null']],
                                    'name' => ['type' => ['string', 'null']],
                                    'description' => ['type' => ['string', 'null']],
                                    'age_from' => ['type' => ['integer', 'null']],
                                    'age_to' => ['type' => ['integer', 'null']],
                                    'knowledge_level' => ['type' => ['string', 'null']],
                                    'language' => ['type' => ['string', 'null']],
                                    'countries' => [
                                        'type' => 'array',
                                        'items' => ['type' => 'string'],
                                    ],
                                    'pain_points' => [
                                        'type' => 'array',
                                        'items' => ['type' => 'string'],
                                    ],
                                    'concerns' => [
                                        'type' => 'array',
                                        'items' => ['type' => 'string'],
                                    ],
                                    'aspirations' => [
                                        'type' => 'array',
                                        'items' => ['type' => 'string'],
                                    ],
                                    'fears' => [
                                        'type' => 'array',
                                        'items' => ['type' => 'string'],
                                    ],
                                ],
                                'required' => array_keys($audienceProperties),
                                'additionalProperties' => false,
                            ],
                        ],
                    ],
                    'required' => ['reference_id', 'language', 'name', 'description', 'tone_of_voice', 'industry', 'niches', 'core_mission', 'guidelines', 'meta', 'audiences'],
                    'additionalProperties' => false,
                ],
            ],
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDraftData(): array
    {
        return [
            'reference_id' => null,
            'language' => null,
            'name' => null,
            'description' => null,
            'tone_of_voice' => null,
            'industry' => null,
            'niches' => [],
            'core_mission' => null,
            'guidelines' => [],
            'meta' => [],
            'audiences' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $draft
     * @param  array<string, mixed>  $updates
     * @return array<string, mixed>
     */
    private function mergeDraft(array $draft, array $updates): array
    {
        foreach (['reference_id', 'language', 'name', 'description', 'tone_of_voice', 'industry', 'core_mission'] as $key) {
            if (array_key_exists($key, $updates)) {
                $value = is_string($updates[$key]) ? trim($updates[$key]) : null;
                $draft[$key] = $value !== '' ? $value : null;
            }
        }

        if (array_key_exists('niches', $updates) && is_array($updates['niches'])) {
            $draft['niches'] = array_values(array_unique(array_filter(
                array_map(static fn ($v): string => trim((string) $v), $updates['niches']),
                static fn (string $v): bool => $v !== ''
            )));
        }

        if (array_key_exists('guidelines', $updates) && is_array($updates['guidelines'])) {
            $draft['guidelines'] = array_values(array_unique(array_filter(
                array_map(static fn ($v): string => trim((string) $v), $updates['guidelines']),
                static fn (string $v): bool => $v !== ''
            )));
        }

        if (array_key_exists('meta', $updates) && is_array($updates['meta'])) {
            $meta = [];
            foreach ($updates['meta'] as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $key = trim((string) ($entry['key'] ?? ''));
                $value = trim((string) ($entry['value'] ?? ''));
                if ($key === '' || $value === '') {
                    continue;
                }

                $meta[$key] = $value;
            }
            $draft['meta'] = $meta;
        }

        if (array_key_exists('audiences', $updates) && is_array($updates['audiences'])) {
            $audiences = [];
            foreach ($updates['audiences'] as $audience) {
                if (! is_array($audience)) {
                    continue;
                }

                $audiences[] = [
                    'priority_weight' => isset($audience['priority_weight']) ? (float) $audience['priority_weight'] : null,
                    'name' => isset($audience['name']) ? trim((string) $audience['name']) : null,
                    'description' => isset($audience['description']) ? trim((string) $audience['description']) : null,
                    'age_from' => isset($audience['age_from']) ? (int) $audience['age_from'] : null,
                    'age_to' => isset($audience['age_to']) ? (int) $audience['age_to'] : null,
                    'knowledge_level' => isset($audience['knowledge_level']) ? trim((string) $audience['knowledge_level']) : null,
                    'language' => isset($audience['language']) ? trim((string) $audience['language']) : null,
                    'countries' => is_array($audience['countries'] ?? null) ? array_values(array_filter(array_map(static fn ($v): string => trim((string) $v), $audience['countries']), static fn (string $v): bool => $v !== '')) : [],
                    'pain_points' => is_array($audience['pain_points'] ?? null) ? array_values(array_filter(array_map(static fn ($v): string => trim((string) $v), $audience['pain_points']), static fn (string $v): bool => $v !== '')) : [],
                    'concerns' => is_array($audience['concerns'] ?? null) ? array_values(array_filter(array_map(static fn ($v): string => trim((string) $v), $audience['concerns']), static fn (string $v): bool => $v !== '')) : [],
                    'aspirations' => is_array($audience['aspirations'] ?? null) ? array_values(array_filter(array_map(static fn ($v): string => trim((string) $v), $audience['aspirations']), static fn (string $v): bool => $v !== '')) : [],
                    'fears' => is_array($audience['fears'] ?? null) ? array_values(array_filter(array_map(static fn ($v): string => trim((string) $v), $audience['fears']), static fn (string $v): bool => $v !== '')) : [],
                ];
            }

            $draft['audiences'] = $audiences;
        }

        return $draft;
    }

    private function syncContext(SemanticContext $context, array $draft, array $conversation): void
    {
        $context->set('task', 'Current task', 'Create a publishing channel brand Client collaboratively with user + AI.');
        $context->set('required_fields', 'Fields typically needed for robust client creation', [
            'name',
            'description',
            'tone_of_voice',
            'industry',
            'niches',
            'core_mission',
            'guidelines',
            'audiences',
        ]);
        $context->set('entity_definition', 'What Client means in this flow', [
            'type' => 'publishing_channel_brand',
            'notes' => [
                'Client is a publishing channel brand entity',
                'Data should emphasize editorial identity, audience focus, and content direction',
            ],
        ]);
        $context->set('draft_client_data', 'Current draft data for client creation', $draft);
        $context->set('conversation', 'Conversation history for iterative enrichment', $conversation);
    }

    private function displayDraftSummary(array $draft): void
    {
        $this->table(
            ['field', 'value'],
            [
                ['reference_id', (string) ($draft['reference_id'] ?? '')],
                ['language', (string) ($draft['language'] ?? '')],
                ['name', (string) ($draft['name'] ?? '')],
                ['description', (string) ($draft['description'] ?? '')],
                ['tone_of_voice', (string) ($draft['tone_of_voice'] ?? '')],
                ['industry', (string) ($draft['industry'] ?? '')],
                ['core_mission', (string) ($draft['core_mission'] ?? '')],
                ['niches_count', (string) count((array) ($draft['niches'] ?? []))],
                ['guidelines_count', (string) count((array) ($draft['guidelines'] ?? []))],
                ['audiences_count', (string) count((array) ($draft['audiences'] ?? []))],
                ['meta_keys', implode(', ', array_keys((array) ($draft['meta'] ?? [])))],
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    private function createClientFromDraft(array $draft): Client
    {
        $name = trim((string) ($draft['name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('name is required.');
        }

        $description = trim((string) ($draft['description'] ?? ''));
        if ($description === '') {
            throw new RuntimeException('description is required.');
        }

        $context = (new ClientContext)
            ->setName($name)
            ->setDescription($description);

        if (($draft['tone_of_voice'] ?? null) !== null) {
            $context->setToneOfVoice((string) $draft['tone_of_voice']);
        }
        if (($draft['industry'] ?? null) !== null) {
            $context->setIndustry((string) $draft['industry']);
        }
        if (($draft['core_mission'] ?? null) !== null) {
            $context->setCoreMission((string) $draft['core_mission']);
        }
        if (is_array($draft['niches'] ?? null)) {
            $context->setNiches($draft['niches']);
        }
        if (is_array($draft['guidelines'] ?? null)) {
            $context->setGuidelines($draft['guidelines']);
        }
        if (is_array($draft['meta'] ?? null)) {
            $context->setMeta($draft['meta']);
        }
        if (is_array($draft['audiences'] ?? null)) {
            $audiences = [];
            foreach ($draft['audiences'] as $audienceData) {
                if (is_array($audienceData)) {
                    $audiences[] = Audience::fromArray($audienceData);
                }
            }
            $context->setAudiences($audiences);
        }

        $client = new Client;
        $client->reference_id = $draft['reference_id'] ?? null;
        $client->name = $name;
        $client->context = $context;

        $language = isset($draft['language']) && is_string($draft['language']) ? Language::tryFrom($draft['language']) : null;
        if (isset($draft['language']) && $draft['language'] !== null && ! $language instanceof Language) {
            throw new RuntimeException('language must be a valid Language enum value.');
        }
        $client->language = $language;
        $client->save();

        return $client;
    }
}
