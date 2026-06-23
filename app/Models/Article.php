<?php

namespace App\Models;

use App\Casts\ArticleArticleCast;
use App\Casts\ArticleContextCast;
use App\Casts\ArticleIllustratedArticleCast;
use App\Casts\ArticleIllustrationCast;
use App\Casts\ArticleStageDataCast;
use App\Contracts\Mcp\StructuredMcpResource;
use App\Contracts\Model\Article\Context as ArticleContext;
use App\Enums\ArticleStage;
use App\Enums\ArticleStageStatus;
use App\Enums\ArticleStatus;
use App\Enums\Language;
use App\Enums\Temporal;
use App\Models\Concerns\Publishable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use KhanhArtisan\LaravelBackbone\RelationCascade\CascadeDetails;
use KhanhArtisan\LaravelBackbone\RelationCascade\Cascades;
use KhanhArtisan\LaravelBackbone\RelationCascade\ShouldCascade;

class Article extends EmbeddableModel implements ShouldCascade, StructuredMcpResource
{
    use Cascades;
    use Publishable;

    protected $casts = [
        'article' => ArticleArticleCast::class,
        'context' => ArticleContextCast::class,
        'status' => ArticleStatus::class,
        'language' => Language::class,
        'temporal' => Temporal::class,
        'stage' => ArticleStage::class,
        'stage_status' => ArticleStageStatus::class,
        'stage_data' => ArticleStageDataCast::class,
        'illustration' => ArticleIllustrationCast::class,
        'illustrated_article' => ArticleIllustratedArticleCast::class,
        'vector' => 'array',
        'is_embeddable' => 'boolean',
        'is_embedded' => 'boolean',
        'attempts' => 'integer',
        'intents_count' => 'integer',
        'intent_resolved_at' => 'datetime',
    ];

    public function getCascadeDetails(): CascadeDetails|array
    {
        return [
            new CascadeDetails($this->hasMany(ArticleIntent::class)),
            new CascadeDetails($this->hasMany(ArticleTag::class)),
            new CascadeDetails($this->publications()),
        ];
    }

    public function autoForceDeleteWhenAllRelationsAreDeleted(): bool
    {
        return true;
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function thumbnailFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'thumbnail_file_id');
    }

    public static function getMcpOutputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('The unique identifier'),
            'client_id' => $schema->string()->description('The client this article belongs to'),
            'status' => $schema
                ->integer()
                ->description(
                    'Article status. '
                    .collect(ArticleStatus::cases())
                        ->map(fn (ArticleStatus $articleStatus) => $articleStatus->value.': '.$articleStatus->name)
                        ->join(', ')
                ),
            'stage' => $schema
                ->integer()
                ->description(
                    'Article pipeline stage. '
                    .collect(ArticleStage::cases())
                        ->map(fn (ArticleStage $articleStage) => $articleStage->value.': '.$articleStage->name)
                        ->join(', ')
                ),
            'stage_status' => $schema
                ->integer()
                ->description(
                    'Status within the current stage. '
                    .collect(ArticleStageStatus::cases())
                        ->map(fn (ArticleStageStatus $stageStatus) => $stageStatus->value.': '.$stageStatus->name)
                        ->join(', ')
                ),
            'language' => $schema
                ->string()
                ->enum(Language::class)
                ->nullable()
                ->description('Article language (BCP 47 tag)'),
            'temporal' => $schema
                ->string()
                ->enum(Temporal::class)
                ->nullable()
                ->description('Article temporal classification'),
            'attempts' => $schema
                ->integer()
                ->description('Number of build attempts'),
            'intents_count' => $schema
                ->integer()
                ->description('Number of resolved intents'),
            'created_at' => $schema->string()->description('Article created at'),
            'updated_at' => $schema->string()->description('Article updated at'),
        ];
    }

    public static function getMcpDetailOutputSchema(JsonSchema $schema): array
    {
        return [
            ...self::getMcpOutputSchema($schema),
            'context' => $schema
                ->object(new ArticleContext()->toJsonSchema($schema))
                ->description('Article semantic context')
                ->nullable(),

            'publications' => $schema
                ->array()
                ->items(
                    $schema
                        ->object(Publication::getMcpOutputSchema($schema))
                ),
        ];
    }

    public function toMcpStructuredData(): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'status' => $this->status?->value,
            'stage' => $this->stage?->value,
            'stage_status' => $this->stage_status?->value,
            'language' => $this->language?->value,
            'temporal' => $this->temporal?->value,
            'attempts' => $this->attempts,
            'intents_count' => $this->intents_count,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    public function toMcpDetailStructuredData(): array
    {
        return [
            ...$this->toMcpStructuredData(),
            'context' => $this->context?->toArray() ?? [],
        ];
    }

    public function intents(): BelongsToMany
    {
        return $this->belongsToMany(Intent::class)
            ->using(ArticleIntent::class)
            ->as('article_intent')
            ->withPivot(['relevance']);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)
            ->using(ArticleTag::class)
            ->as('article_tag');
    }

    public function isEmbeddable(): bool
    {
        return $this->stage === ArticleStage::FINAL
            and $this->stage_status === ArticleStageStatus::APPROVED
            and ($this->title
                or $this->excerpt
                or $this->article?->toHtml()
            );
    }

    public function isEmbedded(): bool
    {
        if (! $this->is_embedded) {
            return false;
        }

        if ($this->isDirty('title')
            or $this->isDirty('excerpt')
            or $this->isDirty('article')
        ) {
            return false;
        }

        return true;
    }

    public function getTextForEmbedding(): ?string
    {
        return '# '.$this->title."\n\n".$this->excerpt;
    }
}
