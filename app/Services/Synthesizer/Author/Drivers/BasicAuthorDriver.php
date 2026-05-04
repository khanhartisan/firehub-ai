<?php

namespace App\Services\Synthesizer\Author\Drivers;

use App\Contracts\DOM\Article;
use App\Contracts\CommonData\SemanticContext;
use App\Contracts\DOM\Element;
use App\Contracts\DOM\ElementType;
use App\Contracts\Synthesizer\Author\Draft;
use App\Contracts\Synthesizer\Author\IllustrationAnchor;
use App\Contracts\Synthesizer\BriefBuilder\Brief;
use App\Contracts\Synthesizer\Illustration\IllustrationResult;
use App\Contracts\Synthesizer\OutlineBuilder\Outline;
use App\Services\Synthesizer\Author\AuthorService;

class BasicAuthorDriver extends AuthorService
{
    public function draft(Brief $brief, Outline $outline, ?SemanticContext $context = null): Draft
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

        $contextLines = $this->contextToLines($context);
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
     * @param  IllustrationResult[]  $illustrationResults
     * @return IllustrationAnchor[]
     */
    public function getIllustrationAnchors(Article $article, array $illustrationResults): array
    {
        $candidates = $this->collectIllustrationAnchorCandidates($article);
        if ($candidates === []) {
            return [];
        }

        $anchors = [];
        $lastIndex = count($candidates) - 1;

        foreach ($illustrationResults as $result) {
            if (! $result instanceof IllustrationResult) {
                continue;
            }

            $slot = count($anchors);
            $element = $candidates[$slot > $lastIndex ? $lastIndex : $slot];

            $anchors[] = new IllustrationAnchor(
                $result->getIdentifier(),
                $element->getIdentifier(),
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
}
