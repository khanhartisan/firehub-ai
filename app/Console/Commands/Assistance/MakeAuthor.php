<?php

namespace App\Console\Commands\Assistance;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\Model\Author\AuthorContext;
use App\Contracts\Model\Author\AuthorContexts\CognitiveContext;
use App\Contracts\Model\Author\AuthorContexts\ConstraintContext;
use App\Contracts\Model\Author\AuthorContexts\DemographicContext;
use App\Contracts\Model\Author\AuthorContexts\ExperientialContext;
use App\Contracts\Model\Author\AuthorContexts\LinguisticContext;
use App\Contracts\Model\Client\Context as ClientContext;
use App\Facades\SemanticContextBuilder;
use App\Models\Author;
use App\Models\Client;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('assistance:make:author')]
#[Description('Make an author for a client')]
class MakeAuthor extends Command
{
    protected const int MAX_SUB_CONTEXT_ROUNDS = 6;

    /**
     * @var array<int, array{key: string, label: string, class: class-string<SemanticContext>}>
     */
    private const array SUB_CONTEXT_SPECS = [
        ['key' => 'cognitive_context', 'label' => 'Cognitive', 'class' => CognitiveContext::class],
        ['key' => 'constraint_context', 'label' => 'Constraint', 'class' => ConstraintContext::class],
        ['key' => 'demographic_context', 'label' => 'Demographic', 'class' => DemographicContext::class],
        ['key' => 'experiential_context', 'label' => 'Experiential', 'class' => ExperientialContext::class],
        ['key' => 'linguistic_context', 'label' => 'Linguistic', 'class' => LinguisticContext::class],
    ];

    public function handle(): int
    {
        $builderDriver = $this->chooseBuilderDriver();
        if ($builderDriver === null) {
            return self::FAILURE;
        }

        $client = $this->chooseClient();
        if (! $client instanceof Client) {
            return self::FAILURE;
        }

        $authorName = trim((string) $this->ask('Author display name', 'Editorial Lead'));
        if ($authorName === '') {
            $this->error('Author name is required.');

            return self::FAILURE;
        }

        $seed = (string) $this->ask(
            'Describe the author persona you want to create',
            'A pragmatic SaaS founder in their late 30s who writes with blunt clarity and data-backed opinions.'
        );

        try {
            $builtContexts = $this->runSubContextBuildPhase($builderDriver, $client, $authorName, $seed);
            if ($builtContexts === []) {
                $this->error('No author sub-contexts were built. Author was not created.');

                return self::FAILURE;
            }

            $author = $this->createAuthorFromContexts($client, $authorName, $builtContexts);
            $this->newLine();
            $this->info("Author created successfully: {$author->id}");
            $this->table(
                ['field', 'value'],
                [
                    ['id', (string) $author->id],
                    ['client_id', (string) $client->id],
                    ['name', (string) ($author->name ?? '')],
                    ['context_identifier', (string) ($author->context?->getIdentifier() ?? '')],
                    ['built_sub_contexts', implode(', ', array_keys($builtContexts))],
                ]
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Creation failed: '.$e->getMessage());

            return self::FAILURE;
        }
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

    private function chooseClient(): ?Client
    {
        $clients = Client::query()->orderBy('name')->get();
        if ($clients->isEmpty()) {
            $this->error('No clients found. Run assistance:make:client first.');

            return null;
        }

        if ($clients->count() === 1) {
            $client = $clients->first();
            $this->info('Using client: '.(string) ($client->name ?? $client->id));

            return $client;
        }

        $choices = $clients
            ->map(static fn (Client $client): string => (string) $client->id.' — '.(string) ($client->name ?? '(unnamed)'))
            ->all();
        $selected = (string) $this->choice('Choose client', $choices);
        $clientId = trim(explode(' — ', $selected, 2)[0]);

        $client = $clients->firstWhere('id', $clientId);
        if (! $client instanceof Client) {
            $this->error('Selected client could not be resolved.');

            return null;
        }

        return $client;
    }

    /**
     * @return array<string, SemanticContext>
     */
    private function runSubContextBuildPhase(
        string $builderDriver,
        Client $client,
        string $authorName,
        string $seed
    ): array {
        $this->newLine();
        $this->info('Author sub-context phase');

        $requestedCount = (int) $this->ask(
            'How many author sub-contexts should we build? (max '.count(self::SUB_CONTEXT_SPECS).')',
            (string) count(self::SUB_CONTEXT_SPECS)
        );
        $requestedCount = max(0, min(count(self::SUB_CONTEXT_SPECS), $requestedCount));

        $clientContext = $client->context instanceof ClientContext
            ? $client->context
            : new ClientContext;
        $clientContextText = json_encode($clientContext->toArray(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}';

        $builtContexts = [];
        for ($index = 0; $index < $requestedCount; $index++) {
            $spec = self::SUB_CONTEXT_SPECS[$index];
            $contextClass = $spec['class'];

            $initialPrompt = 'Build the '.strtolower($spec['label']).' sub-context (#'.($index + 1).') '
                .'for author "'.$authorName.'" on client "'.(string) ($client->name ?? $client->id).'".'."\n\n"
                ."Author persona seed:\n{$seed}\n\n"
                ."Client context:\n{$clientContextText}";

            $builder = clone SemanticContextBuilder::driver($builderDriver);
            $builder
                ->setContext((new $contextClass())->withEmptyFields(true))
                ->start($initialPrompt);

            for ($round = 1; $round <= self::MAX_SUB_CONTEXT_ROUNDS; $round++) {
                $this->newLine();
                $this->info($spec['label'].' sub-context — Round '.$round.'/'.self::MAX_SUB_CONTEXT_ROUNDS);

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

                $reply = (string) $this->ask('Your reply (/skip to skip this sub-context)');
                if (trim($reply) === '/skip') {
                    continue 2;
                }

                $builder->continueWith($reply);
            }

            $context = $builder->getContext();
            if (! $context instanceof SemanticContext || ! is_a($context, $contextClass)) {
                throw new RuntimeException($spec['label'].' builder must return a '.$contextClass.' instance.');
            }

            $builtContexts[$spec['key']] = clone $context;
        }

        return $builtContexts;
    }

    /**
     * @param  array<string, SemanticContext>  $builtContexts
     */
    private function createAuthorFromContexts(Client $client, string $name, array $builtContexts): Author
    {
        $authorContext = new AuthorContext;

        if (isset($builtContexts['cognitive_context']) && $builtContexts['cognitive_context'] instanceof CognitiveContext) {
            $authorContext->setCognitiveContext($builtContexts['cognitive_context']);
        }
        if (isset($builtContexts['constraint_context']) && $builtContexts['constraint_context'] instanceof ConstraintContext) {
            $authorContext->setConstraintContext($builtContexts['constraint_context']);
        }
        if (isset($builtContexts['demographic_context']) && $builtContexts['demographic_context'] instanceof DemographicContext) {
            $authorContext->setDemographicContext($builtContexts['demographic_context']);
        }
        if (isset($builtContexts['experiential_context']) && $builtContexts['experiential_context'] instanceof ExperientialContext) {
            $authorContext->setExperientialContext($builtContexts['experiential_context']);
        }
        if (isset($builtContexts['linguistic_context']) && $builtContexts['linguistic_context'] instanceof LinguisticContext) {
            $authorContext->setLinguisticContext($builtContexts['linguistic_context']);
        }

        $author = new Author;
        $author->client()->associate($client);
        $author->name = $name;
        $author->context = $authorContext;
        $author->save();

        return $author;
    }

    /**
     * @param  array<int, array{role: string, text: string}>  $conversation
     */
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
}
