<?php

namespace App\Contracts\Model\Article;

use App\Concerns\Serializable;
use App\Contracts\Synthesizer\Author\IllustrationAnchor;
use App\Contracts\Synthesizer\Illustration\IllustrationResult;

final class IllustrationData implements \App\Contracts\Serializable
{
    use Serializable;

    /** @var IllustrationResult[] */
    protected array $illustrationResults = [];

    /** @var IllustrationAnchor[] */
    protected array $illustrationAnchors = [];

    /**
     * @return IllustrationResult[]
     */
    public function getIllustrationResults(): array
    {
        return $this->illustrationResults;
    }

    /**
     * @param  IllustrationResult[]  $illustrationResults
     */
    public function setIllustrationResults(array $illustrationResults): static
    {
        $this->illustrationResults = array_values(
            array_filter($illustrationResults, static fn ($r) => $r instanceof IllustrationResult)
        );

        return $this;
    }

    /**
     * @return IllustrationAnchor[]
     */
    public function getIllustrationAnchors(): array
    {
        return $this->illustrationAnchors;
    }

    /**
     * @param  IllustrationAnchor[]  $illustrationAnchors
     */
    public function setIllustrationAnchors(array $illustrationAnchors): static
    {
        $this->illustrationAnchors = array_values(
            array_filter($illustrationAnchors, static fn ($a) => $a instanceof IllustrationAnchor)
        );

        return $this;
    }

    public function toArray(): array
    {
        return [
            'illustration_results' => array_map(
                static fn (IllustrationResult $r) => $r->toArray(),
                $this->getIllustrationResults()
            ),
            'illustration_anchors' => array_map(
                static fn (IllustrationAnchor $a) => $a->toArray(),
                $this->getIllustrationAnchors()
            ),
        ];
    }

    public static function fromArray(array $data): static
    {
        $obj = new static;

        if (isset($data['illustration_results']) && is_array($data['illustration_results'])) {
            $results = [];
            foreach ($data['illustration_results'] as $r) {
                if (is_array($r)) {
                    $results[] = IllustrationResult::fromArray($r);
                }
            }
            $obj->setIllustrationResults($results);
        }

        if (isset($data['illustration_anchors']) && is_array($data['illustration_anchors'])) {
            $anchors = [];
            foreach ($data['illustration_anchors'] as $a) {
                if (is_array($a)) {
                    $anchors[] = IllustrationAnchor::fromArray($a);
                }
            }
            $obj->setIllustrationAnchors($anchors);
        }

        return $obj;
    }
}
