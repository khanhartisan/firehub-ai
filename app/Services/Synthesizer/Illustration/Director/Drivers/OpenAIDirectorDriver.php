<?php

namespace App\Services\Synthesizer\Illustration\Director\Drivers;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\Response;
use App\Contracts\OpenAI\ResponseInput;
use App\Contracts\OpenAI\ResponseOptions;
use App\Contracts\Synthesizer\Illustration\DirectionContexts\ArtStyleContext;
use App\Contracts\Synthesizer\Illustration\DirectionContexts\CameraAndLightingContext;
use App\Contracts\Synthesizer\Illustration\DirectionContexts\ConceptContext;
use App\Contracts\Synthesizer\Illustration\DirectionContexts\ConceptContexts\AbstractionContext;
use App\Contracts\Synthesizer\Illustration\DirectionContexts\ConceptContexts\CharacterContext;
use App\Contracts\Synthesizer\Illustration\DirectionContexts\ConceptContexts\LandscapeContext;
use App\Contracts\Synthesizer\Illustration\DirectionContexts\ConceptContexts\ObjectContext;
use App\Contracts\Synthesizer\Illustration\Director;
use App\Contracts\Synthesizer\Illustration\IllustrationContext;
use App\Contracts\Synthesizer\Illustration\IllustrationDirection;
use App\Contracts\Synthesizer\Illustration\Illustratable;
use App\Contracts\Synthesizer\Illustration\Illustrator;
use App\Enums\AspectRatio;
use App\Services\Synthesizer\Illustration\Director\DirectorService;
use RuntimeException;

class OpenAIDirectorDriver extends DirectorService implements Director
{
    protected ?OpenAIClient $openAIClient;

    /** @var array<string, mixed> */
    protected array $config;

    /** @param array<string, mixed> $config */
    public function __construct(?OpenAIClient $openAIClient = null, array $config = [])
    {
        $this->openAIClient = $openAIClient;
        $this->config = array_merge(config('synthesizer.openai_illustration_director', []), $config);
    }

    public function resolveIllustrationContexts(
        Illustratable $illustratable,
        ?int $minContexts = null,
        ?int $maxContexts = null
    ): array {
        $content = trim($illustratable->getIllustrationContent());
        if ($content === '') {
            return [];
        }

        $min = max(1, (int) ($minContexts ?? 1));
        $max = max($min, (int) ($maxContexts ?? 3));
        $max = min($max, (int) ($this->config['max_contexts'] ?? 8));

        $payload = $this->requestStructuredJson(
            $this->buildResolveContextsPrompt($content, $min, $max),
            'illustration_contexts',
            $this->buildResolveContextsSchema($min, $max),
            'Failed to resolve illustration contexts with OpenAI'
        );

        $contexts = [];
        foreach (($payload['contexts'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $subject = trim((string) ($row['subject'] ?? ''));
            if ($subject === '') {
                continue;
            }

            $context = (new IllustrationContext())->setSubject($subject);
            $language = trim((string) ($row['language'] ?? ''));
            if ($language !== '') {
                $context->setLanguage($language);
            }

            $goal = trim((string) ($row['goal'] ?? ''));
            if ($goal !== '') {
                $context->setGoal($goal);
            }

            $style = trim((string) ($row['style'] ?? ''));
            if ($style !== '') {
                $context->setStyle($style);
            }

            $macroContext = trim((string) ($row['macro_context'] ?? ''));
            if ($macroContext !== '') {
                $context->setMacroContext($macroContext);
            }

            $microContext = trim((string) ($row['micro_context'] ?? ''));
            if ($microContext !== '') {
                $context->setMicroContext($microContext);
            }

            if (is_string($row['aspect_ratio'] ?? null)) {
                $aspectRatio = AspectRatio::tryFrom($row['aspect_ratio']);
                if ($aspectRatio instanceof AspectRatio) {
                    $context->setAspectRatio($aspectRatio);
                }
            }

            if (is_array($row['reference_file_ids'] ?? null)) {
                $context->setReferenceFileIds($row['reference_file_ids']);
            }

            if (is_array($row['constraints'] ?? null)) {
                $context->setConstraints($row['constraints']);
            }
            if (is_array($row['knowledge_guidelines'] ?? null)) {
                $context->setKnowledgeGuidelines($row['knowledge_guidelines']);
            }

            $contexts[] = $context;
        }

        return array_slice($contexts, 0, $max);
    }

    public function direct(IllustrationContext $context): IllustrationDirection
    {
        $payload = $this->requestStructuredJson(
            $this->buildDirectionPrompt($context),
            'illustration_direction',
            $this->buildDirectionSchema(),
            'Failed to create illustration direction with OpenAI'
        );

        return (new IllustrationDirection())
            ->setConceptContext($this->hydrateConceptContext($payload['concept_context'] ?? null))
            ->setArtStyleContext($this->hydrateArtStyleContext($payload['art_style_context'] ?? null))
            ->setCameraAndLightingContext(
                $this->hydrateCameraAndLightingContext($payload['camera_and_lighting_context'] ?? null)
            );
    }

    public function determineIllustrator(
        IllustrationContext $context,
        IllustrationDirection $direction,
        array $illustrators
    ): ?Illustrator {
        $available = array_values(array_filter(
            $illustrators,
            static fn (mixed $illustrator): bool => $illustrator instanceof Illustrator
        ));

        if ($available === []) {
            return null;
        }

        $payload = $this->requestStructuredJson(
            $this->buildDetermineIllustratorPrompt($context, $direction, $available),
            'illustrator_selection',
            $this->buildDetermineIllustratorSchema(),
            'Failed to determine illustrator with OpenAI'
        );

        $selectedIdentifier = trim((string) ($payload['identifier'] ?? ''));
        if ($selectedIdentifier === '') {
            return $available[0];
        }

        foreach ($available as $illustrator) {
            if ((string) $illustrator->getIdentifier() === $selectedIdentifier) {
                return $illustrator;
            }
        }

        return $available[0];
    }

    protected function getModel(): string
    {
        return (string) ($this->config['model'] ?? 'gpt-4o-mini');
    }

    protected function getTemperature(): float
    {
        return (float) ($this->config['temperature'] ?? 0.2);
    }

    protected function buildResolveContextsPrompt(string $content, int $min, int $max): string
    {
        return <<<PROMPT
You are an illustration planning director.

Given source content, split it into concrete illustration contexts suitable for downstream image generation.
Generate between {$min} and {$max} contexts.
Extract factual details and domain knowledge carefully from the source content.
Capture concrete facts in constraints and knowledge_guidelines (names, numbers, quantities, dates, measurements, percentages, amounts, classifications, and rules).
Preserve extracted facts exactly; do not paraphrase away critical figures or qualifiers.
Do not invent facts or knowledge that are not present in the source content.
If a detail is uncertain or missing, do not guess; only include verifiable information from the input.

Input content:
{$content}
PROMPT;
    }

    protected function buildDirectionPrompt(IllustrationContext $context): string
    {
        $json = json_encode($context->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return <<<PROMPT
You are an illustration art director.

Given the illustration context JSON, produce structured direction contexts for:
- concept_context
- art_style_context
- camera_and_lighting_context

Return practical, implementation-ready fields only:
- Use concise, explicit strings
- Keep list fields short and concrete
- Do not include markdown formatting
- Do not invent unknown schema keys

Illustration context:
{$json}
PROMPT;
    }

    /** @param Illustrator[] $illustrators */
    protected function buildDetermineIllustratorPrompt(
        IllustrationContext $context,
        IllustrationDirection $direction,
        array $illustrators
    ): string {
        $candidateData = array_map(static function (Illustrator $illustrator): array {
            return [
                'identifier' => $illustrator->getIdentifier(),
                'description' => $illustrator->getDescription(),
            ];
        }, $illustrators);

        $payload = [
            'context' => $context->toArray(),
            'direction' => $direction->toArray(),
            'candidates' => $candidateData,
        ];
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return <<<PROMPT
Choose the best illustrator identifier from the given candidates.
If uncertain, choose the most generally capable one.

Input:
{$json}
PROMPT;
    }

    /** @return array<string, mixed> */
    protected function buildResolveContextsSchema(int $min, int $max): array
    {
        $descriptions = $this->buildResolveContextDescriptionMap();

        return [
            'type' => 'object',
            'properties' => [
                'contexts' => [
                    'type' => 'array',
                    'description' => 'Resolved illustration contexts extracted from the source content.',
                    'minItems' => $min,
                    'maxItems' => $max,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'language' => [
                                'type' => 'string',
                                'description' => $descriptions['language'],
                            ],
                            'subject' => [
                                'type' => 'string',
                                'description' => $descriptions['subject'],
                            ],
                            'goal' => [
                                'type' => 'string',
                                'description' => $descriptions['goal'],
                            ],
                            'style' => [
                                'type' => 'string',
                                'description' => $descriptions['style'],
                            ],
                            'macro_context' => [
                                'type' => 'string',
                                'description' => $descriptions['macro_context'],
                            ],
                            'micro_context' => [
                                'type' => 'string',
                                'description' => $descriptions['micro_context'],
                            ],
                            'aspect_ratio' => [
                                'type' => 'string',
                                'description' => $descriptions['aspect_ratio'],
                                'enum' => array_map(static fn (AspectRatio $ratio): string => $ratio->value, AspectRatio::cases()),
                            ],
//                            'reference_file_ids' => [
//                                'type' => 'array',
//                                'description' => $descriptions['reference_file_ids'],
//                                'items' => ['type' => 'string'],
//                            ],
                            'constraints' => [
                                'type' => 'array',
                                'description' => $descriptions['constraints'],
                                'items' => ['type' => 'string'],
                            ],
                            'knowledge_guidelines' => [
                                'type' => 'array',
                                'description' => $descriptions['knowledge_guidelines'],
                                'items' => ['type' => 'string'],
                            ],
                        ],
                        'required' => ['language', 'subject', 'goal', 'style', 'macro_context', 'micro_context', 'aspect_ratio', /*'reference_file_ids',*/ 'constraints', 'knowledge_guidelines'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['contexts'],
            'additionalProperties' => false,
        ];
    }

    /** @return array<string, string> */
    protected function buildResolveContextDescriptionMap(): array
    {
        $context = (new IllustrationContext())
            ->setLanguage('placeholder')
            ->setSubject('placeholder')
            ->setGoal('placeholder')
            ->setStyle('placeholder')
            ->setMacroContext('placeholder')
            ->setMicroContext('placeholder')
            ->setAspectRatio(AspectRatio::SQUARE)
            ->setReferenceFileIds(['placeholder'])
            ->setConstraints(['placeholder'])
            ->setKnowledgeGuidelines(['placeholder']);

        return $this->extractDescriptions(
            $context,
            ['language', 'subject', 'goal', 'style', 'macro_context', 'micro_context', 'aspect_ratio', 'reference_file_ids', 'constraints', 'knowledge_guidelines']
        );
    }

    /** @return array<string, mixed> */
    protected function buildDirectionSchema(): array
    {
        $descriptions = $this->buildDirectionDescriptionMap();

        return [
            'type' => 'object',
            'properties' => [
                'concept_context' => [
                    'type' => 'object',
                    'description' => $descriptions['illustration_direction']['concept_context'],
                    'properties' => [
                        'logline' => [
                            'type' => 'string',
                            'description' => $descriptions['concept_context']['logline'],
                        ],
                        'primary_subject' => [
                            'type' => 'string',
                            'description' => $descriptions['concept_context']['primary_subject'],
                        ],
                        'narrative_intent' => [
                            'type' => 'string',
                            'description' => $descriptions['concept_context']['narrative_intent'],
                        ],
                        'scene_context' => [
                            'type' => 'string',
                            'description' => $descriptions['concept_context']['scene_context'],
                        ],
                        'mood' => [
                            'type' => 'string',
                            'description' => $descriptions['concept_context']['mood'],
                        ],
                        'symbolic_notes' => [
                            'type' => 'array',
                            'description' => $descriptions['concept_context']['symbolic_notes'],
                            'items' => ['type' => 'string'],
                        ],
                        'constraints' => [
                            'type' => 'array',
                            'description' => $descriptions['concept_context']['constraints'],
                            'items' => ['type' => 'string'],
                        ],
                        'character_contexts' => [
                            'type' => 'array',
                            'description' => $descriptions['concept_context']['character_contexts'],
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'role' => [
                                        'type' => 'string',
                                        'description' => $descriptions['character_context']['role'],
                                    ],
                                    'identity' => [
                                        'type' => 'string',
                                        'description' => $descriptions['character_context']['identity'],
                                    ],
                                    'appearance' => [
                                        'type' => 'string',
                                        'description' => $descriptions['character_context']['appearance'],
                                    ],
                                    'wardrobe' => [
                                        'type' => 'string',
                                        'description' => $descriptions['character_context']['wardrobe'],
                                    ],
                                    'pose' => [
                                        'type' => 'string',
                                        'description' => $descriptions['character_context']['pose'],
                                    ],
                                    'position' => [
                                        'type' => 'string',
                                        'description' => $descriptions['character_context']['position'],
                                    ],
                                    'expression' => [
                                        'type' => 'string',
                                        'description' => $descriptions['character_context']['expression'],
                                    ],
                                    'action' => [
                                        'type' => 'string',
                                        'description' => $descriptions['character_context']['action'],
                                    ],
                                    'props' => [
                                        'type' => 'array',
                                        'description' => $descriptions['character_context']['props'],
                                        'items' => ['type' => 'string'],
                                    ],
                                    'constraints' => [
                                        'type' => 'array',
                                        'description' => $descriptions['character_context']['constraints'],
                                        'items' => ['type' => 'string'],
                                    ],
                                ],
                                'required' => ['role', 'identity', 'appearance', 'wardrobe', 'pose', 'position', 'expression', 'action', 'props', 'constraints'],
                                'additionalProperties' => false,
                            ],
                        ],
                        'object_contexts' => [
                            'type' => 'array',
                            'description' => $descriptions['concept_context']['object_contexts'],
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => [
                                        'type' => 'string',
                                        'description' => $descriptions['object_context']['name'],
                                    ],
                                    'type' => [
                                        'type' => 'string',
                                        'description' => $descriptions['object_context']['type'],
                                    ],
                                    'appearance' => [
                                        'type' => 'string',
                                        'description' => $descriptions['object_context']['appearance'],
                                    ],
                                    'material' => [
                                        'type' => 'string',
                                        'description' => $descriptions['object_context']['material'],
                                    ],
                                    'condition' => [
                                        'type' => 'string',
                                        'description' => $descriptions['object_context']['condition'],
                                    ],
                                    'position' => [
                                        'type' => 'string',
                                        'description' => $descriptions['object_context']['position'],
                                    ],
                                    'scale' => [
                                        'type' => 'string',
                                        'description' => $descriptions['object_context']['scale'],
                                    ],
                                    'interaction' => [
                                        'type' => 'string',
                                        'description' => $descriptions['object_context']['interaction'],
                                    ],
                                    'constraints' => [
                                        'type' => 'array',
                                        'description' => $descriptions['object_context']['constraints'],
                                        'items' => ['type' => 'string'],
                                    ],
                                ],
                                'required' => ['name', 'type', 'appearance', 'material', 'condition', 'position', 'scale', 'interaction', 'constraints'],
                                'additionalProperties' => false,
                            ],
                        ],
                        'abstraction_contexts' => [
                            'type' => 'array',
                            'description' => $descriptions['concept_context']['abstraction_contexts'],
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'theme' => [
                                        'type' => 'string',
                                        'description' => $descriptions['abstraction_context']['theme'],
                                    ],
                                    'metaphor' => [
                                        'type' => 'string',
                                        'description' => $descriptions['abstraction_context']['metaphor'],
                                    ],
                                    'narrative_arc' => [
                                        'type' => 'string',
                                        'description' => $descriptions['abstraction_context']['narrative_arc'],
                                    ],
                                    'dominant_shapes' => [
                                        'type' => 'array',
                                        'description' => $descriptions['abstraction_context']['dominant_shapes'],
                                        'items' => ['type' => 'string'],
                                    ],
                                    'symbolic_elements' => [
                                        'type' => 'array',
                                        'description' => $descriptions['abstraction_context']['symbolic_elements'],
                                        'items' => ['type' => 'string'],
                                    ],
                                    'mood' => [
                                        'type' => 'string',
                                        'description' => $descriptions['abstraction_context']['mood'],
                                    ],
                                    'visual_tension' => [
                                        'type' => 'string',
                                        'description' => $descriptions['abstraction_context']['visual_tension'],
                                    ],
                                    'constraints' => [
                                        'type' => 'array',
                                        'description' => $descriptions['abstraction_context']['constraints'],
                                        'items' => ['type' => 'string'],
                                    ],
                                ],
                                'required' => ['theme', 'metaphor', 'narrative_arc', 'dominant_shapes', 'symbolic_elements', 'mood', 'visual_tension', 'constraints'],
                                'additionalProperties' => false,
                            ],
                        ],
                        'landscape_context' => [
                            'type' => 'object',
                            'description' => $descriptions['concept_context']['landscape_context'],
                            'properties' => [
                                'setting' => [
                                    'type' => 'string',
                                    'description' => $descriptions['landscape_context']['setting'],
                                ],
                                'location' => [
                                    'type' => 'string',
                                    'description' => $descriptions['landscape_context']['location'],
                                ],
                                'terrain' => [
                                    'type' => 'string',
                                    'description' => $descriptions['landscape_context']['terrain'],
                                ],
                                'vegetation' => [
                                    'type' => 'string',
                                    'description' => $descriptions['landscape_context']['vegetation'],
                                ],
                                'structures' => [
                                    'type' => 'array',
                                    'description' => $descriptions['landscape_context']['structures'],
                                    'items' => ['type' => 'string'],
                                ],
                                'weather' => [
                                    'type' => 'string',
                                    'description' => $descriptions['landscape_context']['weather'],
                                ],
                                'time_of_day' => [
                                    'type' => 'string',
                                    'description' => $descriptions['landscape_context']['time_of_day'],
                                ],
                                'season' => [
                                    'type' => 'string',
                                    'description' => $descriptions['landscape_context']['season'],
                                ],
                                'mood' => [
                                    'type' => 'string',
                                    'description' => $descriptions['landscape_context']['mood'],
                                ],
                                'constraints' => [
                                    'type' => 'array',
                                    'description' => $descriptions['landscape_context']['constraints'],
                                    'items' => ['type' => 'string'],
                                ],
                            ],
                            'required' => ['setting', 'location', 'terrain', 'vegetation', 'structures', 'weather', 'time_of_day', 'season', 'mood', 'constraints'],
                            'additionalProperties' => false,
                        ],
                    ],
                    'required' => ['logline', 'primary_subject', 'narrative_intent', 'scene_context', 'mood', 'symbolic_notes', 'constraints', 'character_contexts', 'object_contexts', 'abstraction_contexts', 'landscape_context'],
                    'additionalProperties' => false,
                ],
                'art_style_context' => [
                    'type' => 'object',
                    'description' => $descriptions['illustration_direction']['art_style_context'],
                    'properties' => [
                        'art_medium' => [
                            'type' => 'string',
                            'description' => $descriptions['art_style_context']['art_medium'],
                        ],
                        'style' => [
                            'type' => 'string',
                            'description' => $descriptions['art_style_context']['style'],
                        ],
                        'creator_references' => [
                            'type' => 'array',
                            'description' => $descriptions['art_style_context']['creator_references'],
                            'items' => ['type' => 'string'],
                        ],
                        'color_palette' => [
                            'type' => 'string',
                            'description' => $descriptions['art_style_context']['color_palette'],
                        ],
                        'overall_vibe' => [
                            'type' => 'string',
                            'description' => $descriptions['art_style_context']['overall_vibe'],
                        ],
                        'rendering_details' => [
                            'type' => 'string',
                            'description' => $descriptions['art_style_context']['rendering_details'],
                        ],
                        'negative_style_constraints' => [
                            'type' => 'array',
                            'description' => $descriptions['art_style_context']['negative_style_constraints'],
                            'items' => ['type' => 'string'],
                        ],
                    ],
                    'required' => ['art_medium', 'style', 'creator_references', 'color_palette', 'overall_vibe', 'rendering_details', 'negative_style_constraints'],
                    'additionalProperties' => false,
                ],
                'camera_and_lighting_context' => [
                    'type' => 'object',
                    'description' => $descriptions['illustration_direction']['camera_and_lighting_context'],
                    'properties' => [
                        'shot_size' => [
                            'type' => 'string',
                            'description' => $descriptions['camera_and_lighting_context']['shot_size'],
                        ],
                        'camera_angle' => [
                            'type' => 'string',
                            'description' => $descriptions['camera_and_lighting_context']['camera_angle'],
                        ],
                        'lenses' => [
                            'type' => 'array',
                            'description' => $descriptions['camera_and_lighting_context']['lenses'],
                            'items' => ['type' => 'string'],
                        ],
                        'lighting' => [
                            'type' => 'string',
                            'description' => $descriptions['camera_and_lighting_context']['lighting'],
                        ],
                        'filter' => [
                            'type' => 'string',
                            'description' => $descriptions['camera_and_lighting_context']['filter'],
                        ],
                        'optical' => [
                            'type' => 'string',
                            'description' => $descriptions['camera_and_lighting_context']['optical'],
                        ],
                        'color_palette' => [
                            'type' => 'string',
                            'description' => $descriptions['camera_and_lighting_context']['color_palette'],
                        ],
                        'compositional_rules' => [
                            'type' => 'array',
                            'description' => $descriptions['camera_and_lighting_context']['compositional_rules'],
                            'items' => ['type' => 'string'],
                        ],
                        'depth_plan' => [
                            'type' => 'string',
                            'description' => $descriptions['camera_and_lighting_context']['depth_plan'],
                        ],
                        'negative_constraints' => [
                            'type' => 'array',
                            'description' => $descriptions['camera_and_lighting_context']['negative_constraints'],
                            'items' => ['type' => 'string'],
                        ],
                    ],
                    'required' => ['shot_size', 'camera_angle', 'lenses', 'lighting', 'filter', 'optical', 'color_palette', 'compositional_rules', 'depth_plan', 'negative_constraints'],
                    'additionalProperties' => false,
                ],
            ],
            'required' => ['concept_context', 'art_style_context', 'camera_and_lighting_context'],
            'additionalProperties' => false,
        ];
    }

    /** @return array<string, array<string, string>> */
    protected function buildDirectionDescriptionMap(): array
    {
        $direction = (new IllustrationDirection())
            ->setConceptContext(new ConceptContext())
            ->setArtStyleContext(new ArtStyleContext())
            ->setCameraAndLightingContext(new CameraAndLightingContext());

        $concept = (new ConceptContext())
            ->setLogline('placeholder')
            ->setPrimarySubject('placeholder')
            ->setNarrativeIntent('placeholder')
            ->setSceneContext('placeholder')
            ->setMood('placeholder')
            ->setSymbolicNotes(['placeholder'])
            ->setConstraints(['placeholder'])
            ->set('character_contexts', 'Character-level concept contexts.', [new CharacterContext()])
            ->set('object_contexts', 'Object-level concept contexts.', [new ObjectContext()])
            ->set('abstraction_contexts', 'Abstraction-level concept contexts.', [new AbstractionContext()])
            ->set('landscape_context', 'Landscape-level concept context.', new LandscapeContext());

        $character = (new CharacterContext())
            ->setRole('placeholder')
            ->setIdentity('placeholder')
            ->setAppearance('placeholder')
            ->setWardrobe('placeholder')
            ->setPose('placeholder')
            ->setPosition('placeholder')
            ->setExpression('placeholder')
            ->setAction('placeholder')
            ->setProps(['placeholder'])
            ->setConstraints(['placeholder']);

        $object = (new ObjectContext())
            ->setName('placeholder')
            ->setType('placeholder')
            ->setAppearance('placeholder')
            ->setMaterial('placeholder')
            ->setCondition('placeholder')
            ->setPosition('placeholder')
            ->setScale('placeholder')
            ->setInteraction('placeholder')
            ->setConstraints(['placeholder']);

        $abstraction = (new AbstractionContext())
            ->setTheme('placeholder')
            ->setMetaphor('placeholder')
            ->setNarrativeArc('placeholder')
            ->setDominantShapes(['placeholder'])
            ->setSymbolicElements(['placeholder'])
            ->setMood('placeholder')
            ->setVisualTension('placeholder')
            ->setConstraints(['placeholder']);

        $landscape = (new LandscapeContext())
            ->setSetting('placeholder')
            ->setLocation('placeholder')
            ->setTerrain('placeholder')
            ->setVegetation('placeholder')
            ->setStructures(['placeholder'])
            ->setWeather('placeholder')
            ->setTimeOfDay('placeholder')
            ->setSeason('placeholder')
            ->setMood('placeholder')
            ->setConstraints(['placeholder']);

        $artStyle = (new ArtStyleContext())
            ->set('art_medium', 'Primary image medium choice (photography, 2D illustration, or 3D illustration).', 'placeholder')
            ->setStyle('placeholder')
            ->setCreatorReferences(['placeholder'])
            ->setColorPalette('placeholder')
            ->setOverallVibe('placeholder')
            ->setRenderingDetails('placeholder')
            ->setNegativeStyleConstraints(['placeholder']);

        $cameraAndLighting = (new CameraAndLightingContext())
            ->setShotSize('placeholder')
            ->setCameraAngle('placeholder')
            ->setLenses(['placeholder'])
            ->setLighting('placeholder')
            ->setFilter('placeholder')
            ->setOptical('placeholder')
            ->setColorPalette('placeholder')
            ->setCompositionalRules(['placeholder'])
            ->setDepthPlan('placeholder')
            ->setNegativeConstraints(['placeholder']);

        return [
            'illustration_direction' => $this->extractDescriptions(
                $direction,
                ['concept_context', 'art_style_context', 'camera_and_lighting_context']
            ),
            'concept_context' => $this->extractDescriptions(
                $concept,
                ['logline', 'primary_subject', 'narrative_intent', 'scene_context', 'mood', 'symbolic_notes', 'constraints', 'character_contexts', 'object_contexts', 'abstraction_contexts', 'landscape_context']
            ),
            'character_context' => $this->extractDescriptions(
                $character,
                ['role', 'identity', 'appearance', 'wardrobe', 'pose', 'position', 'expression', 'action', 'props', 'constraints']
            ),
            'object_context' => $this->extractDescriptions(
                $object,
                ['name', 'type', 'appearance', 'material', 'condition', 'position', 'scale', 'interaction', 'constraints']
            ),
            'abstraction_context' => $this->extractDescriptions(
                $abstraction,
                ['theme', 'metaphor', 'narrative_arc', 'dominant_shapes', 'symbolic_elements', 'mood', 'visual_tension', 'constraints']
            ),
            'landscape_context' => $this->extractDescriptions(
                $landscape,
                ['setting', 'location', 'terrain', 'vegetation', 'structures', 'weather', 'time_of_day', 'season', 'mood', 'constraints']
            ),
            'art_style_context' => $this->extractDescriptions(
                $artStyle,
                ['art_medium', 'style', 'creator_references', 'color_palette', 'overall_vibe', 'rendering_details', 'negative_style_constraints']
            ),
            'camera_and_lighting_context' => $this->extractDescriptions(
                $cameraAndLighting,
                ['shot_size', 'camera_angle', 'lenses', 'lighting', 'filter', 'optical', 'color_palette', 'compositional_rules', 'depth_plan', 'negative_constraints']
            ),
        ];
    }

    /**
     * @param string[] $keys
     * @return array<string, string>
     */
    protected function extractDescriptions(SemanticContext $context, array $keys): array
    {
        $descriptions = [];
        foreach ($keys as $key) {
            $descriptions[$key] = (string) ($context->getDescription($key) ?? '');
        }

        return $descriptions;
    }

    protected function hydrateConceptContext(mixed $raw): ?ConceptContext
    {
        if (! is_array($raw) || $raw === []) {
            return null;
        }

        $context = new ConceptContext();

        $this->setIfNonEmptyString($raw['logline'] ?? null, static fn (string $value) => $context->setLogline($value));
        $this->setIfNonEmptyString($raw['primary_subject'] ?? null, static fn (string $value) => $context->setPrimarySubject($value));
        $this->setIfNonEmptyString($raw['narrative_intent'] ?? null, static fn (string $value) => $context->setNarrativeIntent($value));
        $this->setIfNonEmptyString($raw['scene_context'] ?? null, static fn (string $value) => $context->setSceneContext($value));
        $this->setIfNonEmptyString($raw['mood'] ?? null, static fn (string $value) => $context->setMood($value));
        if (is_array($raw['symbolic_notes'] ?? null)) {
            $context->setSymbolicNotes($raw['symbolic_notes']);
        }
        if (is_array($raw['constraints'] ?? null)) {
            $context->setConstraints($raw['constraints']);
        }

        $characterContexts = [];
        foreach (($raw['character_contexts'] ?? []) as $item) {
            $character = $this->hydrateCharacterContext($item);
            if ($character instanceof CharacterContext) {
                $characterContexts[] = $character;
            }
        }
        if ($characterContexts !== []) {
            $context->set(
                'character_contexts',
                'Character-level concept contexts.',
                $characterContexts
            );
        }

        $objectContexts = [];
        foreach (($raw['object_contexts'] ?? []) as $item) {
            $object = $this->hydrateObjectContext($item);
            if ($object instanceof ObjectContext) {
                $objectContexts[] = $object;
            }
        }
        if ($objectContexts !== []) {
            $context->set(
                'object_contexts',
                'Object-level concept contexts.',
                $objectContexts
            );
        }

        $abstractionContexts = [];
        foreach (($raw['abstraction_contexts'] ?? []) as $item) {
            $abstraction = $this->hydrateAbstractionContext($item);
            if ($abstraction instanceof AbstractionContext) {
                $abstractionContexts[] = $abstraction;
            }
        }
        if ($abstractionContexts !== []) {
            $context->set(
                'abstraction_contexts',
                'Abstraction-level concept contexts.',
                $abstractionContexts
            );
        }

        $landscape = $this->hydrateLandscapeContext($raw['landscape_context'] ?? null);
        if ($landscape instanceof LandscapeContext) {
            $context->set(
                'landscape_context',
                'Landscape-level concept context.',
                $landscape
            );
        }

        return $context;
    }

    protected function hydrateArtStyleContext(mixed $raw): ?ArtStyleContext
    {
        if (! is_array($raw) || $raw === []) {
            return null;
        }

        $context = new ArtStyleContext();
        $this->setIfNonEmptyString($raw['art_medium'] ?? null, static function (string $value) use ($context): void {
            $context->set('art_medium', 'Primary image medium choice.', $value);
        });
        $this->setIfNonEmptyString($raw['style'] ?? null, static fn (string $value) => $context->setStyle($value));
        if (is_array($raw['creator_references'] ?? null)) {
            $context->setCreatorReferences($raw['creator_references']);
        }
        $this->setIfNonEmptyString($raw['color_palette'] ?? null, static fn (string $value) => $context->setColorPalette($value));
        $this->setIfNonEmptyString($raw['overall_vibe'] ?? null, static fn (string $value) => $context->setOverallVibe($value));
        $this->setIfNonEmptyString($raw['rendering_details'] ?? null, static fn (string $value) => $context->setRenderingDetails($value));
        if (is_array($raw['negative_style_constraints'] ?? null)) {
            $context->setNegativeStyleConstraints($raw['negative_style_constraints']);
        }

        return $context;
    }

    protected function hydrateCameraAndLightingContext(mixed $raw): ?CameraAndLightingContext
    {
        if (! is_array($raw) || $raw === []) {
            return null;
        }

        $context = new CameraAndLightingContext();
        $this->setIfNonEmptyString($raw['shot_size'] ?? null, static fn (string $value) => $context->setShotSize($value));
        $this->setIfNonEmptyString($raw['camera_angle'] ?? null, static fn (string $value) => $context->setCameraAngle($value));
        if (is_array($raw['lenses'] ?? null)) {
            $context->setLenses($raw['lenses']);
        }
        $this->setIfNonEmptyString($raw['lighting'] ?? null, static fn (string $value) => $context->setLighting($value));
        $this->setIfNonEmptyString($raw['filter'] ?? null, static fn (string $value) => $context->setFilter($value));
        $this->setIfNonEmptyString($raw['optical'] ?? null, static fn (string $value) => $context->setOptical($value));
        $this->setIfNonEmptyString($raw['color_palette'] ?? null, static fn (string $value) => $context->setColorPalette($value));
        if (is_array($raw['compositional_rules'] ?? null)) {
            $context->setCompositionalRules($raw['compositional_rules']);
        }
        $this->setIfNonEmptyString($raw['depth_plan'] ?? null, static fn (string $value) => $context->setDepthPlan($value));
        if (is_array($raw['negative_constraints'] ?? null)) {
            $context->setNegativeConstraints($raw['negative_constraints']);
        }

        return $context;
    }

    protected function hydrateCharacterContext(mixed $raw): ?CharacterContext
    {
        if (! is_array($raw) || $raw === []) {
            return null;
        }

        $context = new CharacterContext();
        $this->setIfNonEmptyString($raw['role'] ?? null, static fn (string $value) => $context->setRole($value));
        $this->setIfNonEmptyString($raw['identity'] ?? null, static fn (string $value) => $context->setIdentity($value));
        $this->setIfNonEmptyString($raw['appearance'] ?? null, static fn (string $value) => $context->setAppearance($value));
        $this->setIfNonEmptyString($raw['wardrobe'] ?? null, static fn (string $value) => $context->setWardrobe($value));
        $this->setIfNonEmptyString($raw['pose'] ?? null, static fn (string $value) => $context->setPose($value));
        $this->setIfNonEmptyString($raw['position'] ?? null, static fn (string $value) => $context->setPosition($value));
        $this->setIfNonEmptyString($raw['expression'] ?? null, static fn (string $value) => $context->setExpression($value));
        $this->setIfNonEmptyString($raw['action'] ?? null, static fn (string $value) => $context->setAction($value));
        if (is_array($raw['props'] ?? null)) {
            $context->setProps($raw['props']);
        }
        if (is_array($raw['constraints'] ?? null)) {
            $context->setConstraints($raw['constraints']);
        }

        return $context;
    }

    protected function hydrateObjectContext(mixed $raw): ?ObjectContext
    {
        if (! is_array($raw) || $raw === []) {
            return null;
        }

        $context = new ObjectContext();
        $this->setIfNonEmptyString($raw['name'] ?? null, static fn (string $value) => $context->setName($value));
        $this->setIfNonEmptyString($raw['type'] ?? null, static fn (string $value) => $context->setType($value));
        $this->setIfNonEmptyString($raw['appearance'] ?? null, static fn (string $value) => $context->setAppearance($value));
        $this->setIfNonEmptyString($raw['material'] ?? null, static fn (string $value) => $context->setMaterial($value));
        $this->setIfNonEmptyString($raw['condition'] ?? null, static fn (string $value) => $context->setCondition($value));
        $this->setIfNonEmptyString($raw['position'] ?? null, static fn (string $value) => $context->setPosition($value));
        $this->setIfNonEmptyString($raw['scale'] ?? null, static fn (string $value) => $context->setScale($value));
        $this->setIfNonEmptyString($raw['interaction'] ?? null, static fn (string $value) => $context->setInteraction($value));
        if (is_array($raw['constraints'] ?? null)) {
            $context->setConstraints($raw['constraints']);
        }

        return $context;
    }

    protected function hydrateAbstractionContext(mixed $raw): ?AbstractionContext
    {
        if (! is_array($raw) || $raw === []) {
            return null;
        }

        $context = new AbstractionContext();
        $this->setIfNonEmptyString($raw['theme'] ?? null, static fn (string $value) => $context->setTheme($value));
        $this->setIfNonEmptyString($raw['metaphor'] ?? null, static fn (string $value) => $context->setMetaphor($value));
        $this->setIfNonEmptyString($raw['narrative_arc'] ?? null, static fn (string $value) => $context->setNarrativeArc($value));
        if (is_array($raw['dominant_shapes'] ?? null)) {
            $context->setDominantShapes($raw['dominant_shapes']);
        }
        if (is_array($raw['symbolic_elements'] ?? null)) {
            $context->setSymbolicElements($raw['symbolic_elements']);
        }
        $this->setIfNonEmptyString($raw['mood'] ?? null, static fn (string $value) => $context->setMood($value));
        $this->setIfNonEmptyString($raw['visual_tension'] ?? null, static fn (string $value) => $context->setVisualTension($value));
        if (is_array($raw['constraints'] ?? null)) {
            $context->setConstraints($raw['constraints']);
        }

        return $context;
    }

    protected function hydrateLandscapeContext(mixed $raw): ?LandscapeContext
    {
        if (! is_array($raw) || $raw === []) {
            return null;
        }

        $context = new LandscapeContext();
        $this->setIfNonEmptyString($raw['setting'] ?? null, static fn (string $value) => $context->setSetting($value));
        $this->setIfNonEmptyString($raw['location'] ?? null, static fn (string $value) => $context->setLocation($value));
        $this->setIfNonEmptyString($raw['terrain'] ?? null, static fn (string $value) => $context->setTerrain($value));
        $this->setIfNonEmptyString($raw['vegetation'] ?? null, static fn (string $value) => $context->setVegetation($value));
        if (is_array($raw['structures'] ?? null)) {
            $context->setStructures($raw['structures']);
        }
        $this->setIfNonEmptyString($raw['weather'] ?? null, static fn (string $value) => $context->setWeather($value));
        $this->setIfNonEmptyString($raw['time_of_day'] ?? null, static fn (string $value) => $context->setTimeOfDay($value));
        $this->setIfNonEmptyString($raw['season'] ?? null, static fn (string $value) => $context->setSeason($value));
        $this->setIfNonEmptyString($raw['mood'] ?? null, static fn (string $value) => $context->setMood($value));
        if (is_array($raw['constraints'] ?? null)) {
            $context->setConstraints($raw['constraints']);
        }

        return $context;
    }

    protected function setIfNonEmptyString(mixed $value, callable $setter): void
    {
        if (! is_string($value)) {
            return;
        }

        $value = trim($value);
        if ($value === '') {
            return;
        }

        $setter($value);
    }

    /** @return array<string, mixed> */
    protected function buildDetermineIllustratorSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'identifier' => ['type' => 'string'],
            ],
            'required' => ['identifier'],
            'additionalProperties' => false,
        ];
    }

    /** @return array<string, mixed> */
    protected function requestStructuredJson(
        string $prompt,
        string $schemaName,
        array $jsonSchema,
        string $failureMessage,
    ): array {
        if (! $this->openAIClient instanceof OpenAIClient) {
            throw new RuntimeException("{$failureMessage}: OpenAI client is not configured.");
        }

        $input = ResponseInput::text($prompt);
        $options = ResponseOptions::create()
            ->model($this->getModel())
            ->temperature($this->getTemperature())
            ->responseFormat([
                'type' => 'json_schema',
                'name' => $schemaName,
                'schema' => $jsonSchema,
                'strict' => true,
            ]);

        try {
            $response = $this->openAIClient->createResponse($input, $options);
        } catch (\Exception $e) {
            throw new RuntimeException("{$failureMessage}: {$e->getMessage()}", 0, $e);
        }

        $this->checkForRefusal($response);

        $text = $response->getFirstOutputText();
        if ($text === null || $text === '') {
            throw new RuntimeException("{$failureMessage}: empty model output.");
        }

        $data = json_decode($text, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($data)) {
            throw new RuntimeException("{$failureMessage}: invalid JSON (".json_last_error_msg().').');
        }

        return $data;
    }

    protected function checkForRefusal(Response $response): void
    {
        foreach ($response->getOutput() as $item) {
            if (($item['type'] ?? null) !== 'message' || ! isset($item['content']) || ! is_array($item['content'])) {
                continue;
            }

            foreach ($item['content'] as $content) {
                if (($content['type'] ?? null) === 'refusal') {
                    $message = $content['refusal'] ?? 'The model refused to complete this request.';
                    throw new RuntimeException("OpenAI refused the request: {$message}");
                }
            }
        }
    }
}

