<?php

namespace App\Services\Synthesizer\Writer\Drivers;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\DOM\Article;
use App\Contracts\DOM\Element;
use App\Contracts\DOM\ElementType;
use App\Contracts\Synthesizer\Critic\Criticism;
use App\Contracts\Synthesizer\Critic\Rectification;
use App\Contracts\Synthesizer\Writer\Draft;
use App\Contracts\Synthesizer\Writer\IllustrationAnchor;
use App\Contracts\Synthesizer\Writer\RectifiedArticle;
use App\Contracts\Synthesizer\BriefBuilder\Brief;
use App\Contracts\Synthesizer\Illustration\IllustrationResult;
use App\Contracts\Synthesizer\OutlineBuilder\Outline;
use App\Services\Synthesizer\Writer\WriterService;

class BasicWriterDriver extends WriterService
{
    public function draft(
        Brief $brief,
        Outline $outline,
        ?SemanticContext $authorContext = null,
        ?SemanticContext $generalContext = null,
    ): Draft
    {
        $article = new Article;
        foreach ($outline->getItems() as $item) {
            $point = $item->getPoint();
            $section = (new Element)->setType(ElementType::DIV);
            $heading = trim((string) ($point->getHeadline() ?? ''));
            if ($heading !== '') {
                $section->addChild(
                    (new Element)
                        ->setType(ElementType::H2)
                        ->addChild($heading)
                );
            }

            $body = trim((string) $point->getDescription());
            if ($body !== '') {
                $section->addChild(
                    (new Element)
                        ->setType(ElementType::P)
                        ->addChild($body)
                );
            }

            $instructions = $item->getGuidelines();
            if ($instructions === []) {
                $instructions = $point->getEvidences();
            }
            if ($instructions !== []) {
                $instructionList = (new Element)->setType(ElementType::UL);
                foreach ($instructions as $instruction) {
                    $line = trim((string) $instruction);
                    if ($line === '') {
                        continue;
                    }

                    $instructionList->addChild(
                        (new Element)
                            ->setType(ElementType::LI)
                            ->addChild($line)
                    );
                }

                if ($instructionList->getChildren() !== []) {
                    $section->addChild($instructionList);
                }
            }

            if ($section->getChildren() !== []) {
                $article->addChild($section);
            }
        }

        $contextLines = array_merge(
            $this->contextToLines($authorContext),
            $this->contextToLines($generalContext),
        );
        if ($contextLines !== []) {
            $contextSection = (new Element)
                ->setType(ElementType::DIV)
                ->addChild(
                    (new Element)
                        ->setType(ElementType::H2)
                        ->addChild('Additional context')
                );

            $contextList = (new Element)->setType(ElementType::UL);
            foreach ($contextLines as $line) {
                $contextList->addChild(
                    (new Element)
                        ->setType(ElementType::LI)
                        ->addChild($line)
                );
            }

            $contextSection->addChild($contextList);
            $article->addChild($contextSection);
        }

        return (new Draft)
            ->setTitle($brief->getTitle())
            ->setExcerpt($brief->getDescription())
            ->setArticle($article);
    }

    /**
     * @param  Criticism[]  $criticisms
     */
    public function rectifyArticle(
        Article $article,
        array $criticisms,
        ?SemanticContext $authorContext = null,
        ?SemanticContext $generalContext = null,
    ): RectifiedArticle {
        $normalized = $this->normalizeCriticisms($criticisms);
        if ($normalized === []) {
            return (new RectifiedArticle)
                ->setArticle($article)
                ->setRectifications([]);
        }

        $rectified = Article::fromArray($article->toArray());
        $rectifications = [];

        foreach ($this->groupCriticismsByReference($normalized) as $reference => $group) {
            $adjustments = $this->remarksFromCriticisms($group);
            if ($adjustments === []) {
                continue;
            }

            if ($this->criticismsRequestRemoval($group)) {
                $this->assertRemovableReference($rectified, $reference);

                if (! $this->removeElementByReference($rectified, $reference)) {
                    continue;
                }

                $rectifications[] = (new Rectification)
                    ->setReference($reference)
                    ->setConfidence($this->confidenceFromCriticisms($group))
                    ->setAdjustments(['Removed the referenced node from the article.']);

                continue;
            }

            $target = $this->findElementByReference($rectified, $reference);
            if ($target === null || $target instanceof Article) {
                continue;
            }

            foreach ($adjustments as $adjustment) {
                $target->addChild(
                    (new Element)
                        ->setType(ElementType::P)
                        ->addChild($adjustment)
                );
            }

            $rectifications[] = (new Rectification)
                ->setReference($reference)
                ->setConfidence($this->confidenceFromCriticisms($group))
                ->setAdjustments($adjustments);
        }

        return (new RectifiedArticle)
            ->setArticle($rectified)
            ->setRectifications($rectifications);
    }

    /**
     * @param  IllustrationResult[]  $illustrationResults
     * @return IllustrationAnchor[]
     */
    public function getIllustrationAnchors(Article $article, array $illustrationResults): array
    {
        $candidates = $this->collectIllustrationAnchorCandidates($article);
        $lastIndex = max(0, count($candidates) - 1);

        $anchors = [];

        foreach ($illustrationResults as $result) {
            if (! $result instanceof IllustrationResult) {
                continue;
            }

            if ($candidates !== []) {
                $slot = count($anchors);
                $element = $candidates[$slot > $lastIndex ? $lastIndex : $slot];
                $elementIdentifier = $element->getIdentifier();
            } else {
                $elementIdentifier = $article->getIdentifier();
            }

            $anchors[] = new IllustrationAnchor(
                $result->getIdentifier(),
                $elementIdentifier,
                true,
            );
        }

        return $anchors;
    }

    /**
     * Block-level elements in document order suitable for placing illustrations nearby.
     *
     * @return Element[]
     */
    protected function collectIllustrationAnchorCandidates(Element $root): array
    {
        $candidates = [];

        foreach ($root->getChildren() as $child) {
            if (! $child instanceof Element) {
                continue;
            }

            if ($this->isIllustrationAnchorCandidate($child)) {
                $candidates[] = $child;
            }

            $candidates = array_merge($candidates, $this->collectIllustrationAnchorCandidates($child));
        }

        return $candidates;
    }

    protected function isIllustrationAnchorCandidate(Element $element): bool
    {
        return match ($element->getType()) {
            ElementType::H2, ElementType::H3, ElementType::P => true,
            default => false,
        };
    }

    /**
     * @return list<string>
     */
    protected function contextToLines(?SemanticContext $context): array
    {
        if (! $context instanceof SemanticContext) {
            return [];
        }

        $lines = [];
        foreach ($context->toArray() as $key => $entry) {
            if (! is_array($entry) || ! isset($entry['value'])) {
                continue;
            }

            $value = trim((string) json_encode($entry['value'], JSON_UNESCAPED_UNICODE));
            if ($value === '' || $value === 'null') {
                continue;
            }

            $lines[] = sprintf('Use context "%s": %s', (string) $key, $value);
        }

        return $lines;
    }

    /**
     * @param  list<Criticism>  $criticisms
     */
    protected function confidenceFromCriticisms(array $criticisms): ?float
    {
        $values = [];

        foreach ($criticisms as $criticism) {
            $confidence = $criticism->getConfidence();
            if ($confidence !== null) {
                $values[] = $confidence;
            }
        }

        if ($values === []) {
            return null;
        }

        return round(max($values), 2);
    }
}
