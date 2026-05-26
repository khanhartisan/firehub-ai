<?php

namespace App\Services\Synthesizer\Writer;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\DOM\Article;
use App\Contracts\DOM\Element;
use App\Contracts\Synthesizer\Critic\Criticism;
use App\Contracts\Synthesizer\Writer\RectifiedArticle;
use App\Contracts\Synthesizer\Writer\Writer;

abstract class WriterService implements Writer
{
    public function rectifyArticle(
        Article $article,
        array $criticisms,
        ?SemanticContext $authorContext = null,
        ?SemanticContext $generalContext = null,
    ): RectifiedArticle {
        return (new RectifiedArticle)
            ->setArticle($article)
            ->setRectifications([]);
    }

    /**
     * @param  mixed[]  $criticisms
     * @return list<Criticism>
     */
    protected function normalizeCriticisms(array $criticisms): array
    {
        $normalized = [];

        foreach ($criticisms as $index => $criticism) {
            if (! $criticism instanceof Criticism) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'criticisms[%s] must be an instance of %s, %s given.',
                        $index,
                        Criticism::class,
                        get_debug_type($criticism)
                    )
                );
            }

            $normalized[] = $criticism;
        }

        return $normalized;
    }

    /**
     * @return list<string>
     */
    protected function collectElementReferences(Article $article): array
    {
        $references = [];
        $this->walkElementReferences($article, function (string $reference) use (&$references): void {
            $references[] = $reference;
        });

        return array_values(array_unique($references));
    }

    /**
     * @param  callable(string): void  $onReference
     */
    protected function walkElementReferences(Element $node, callable $onReference): void
    {
        $reference = trim($node->getIdentifier());
        if ($reference !== '') {
            $onReference($reference);
        }

        foreach ($node->getChildren() as $child) {
            if ($child instanceof Element) {
                $this->walkElementReferences($child, $onReference);
            }
        }
    }

    protected function findElementByReference(Element $root, string $reference): ?Element
    {
        if (trim($root->getIdentifier()) === $reference) {
            return $root;
        }

        foreach ($root->getChildren() as $child) {
            if (! $child instanceof Element) {
                continue;
            }

            $found = $this->findElementByReference($child, $reference);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    protected function replaceElementByReference(Element $root, string $reference, Element $replacement): bool
    {
        foreach ($root->getChildren() as $index => $child) {
            if (! $child instanceof Element) {
                continue;
            }

            if (trim($child->getIdentifier()) === $reference) {
                $children = $root->getChildren();
                $children[$index] = $replacement;
                $root->setChildren($children);

                return true;
            }

            if ($this->replaceElementByReference($child, $reference, $replacement)) {
                return true;
            }
        }

        return false;
    }

    protected function removeElementByReference(Element $root, string $reference): bool
    {
        foreach ($root->getChildren() as $index => $child) {
            if (! $child instanceof Element) {
                continue;
            }

            if (trim($child->getIdentifier()) === $reference) {
                $children = $root->getChildren();
                array_splice($children, $index, 1);
                $root->setChildren($children);

                return true;
            }

            if ($this->removeElementByReference($child, $reference)) {
                return true;
            }
        }

        return false;
    }

    protected function assertRemovableReference(Article $article, string $reference): void
    {
        if (trim($article->getIdentifier()) === $reference) {
            throw new \RuntimeException(
                "Cannot remove DOM reference \"{$reference}\": the article root cannot be deleted."
            );
        }
    }

    /**
     * @param  list<Criticism>  $criticisms
     */
    protected function criticismsRequestRemoval(array $criticisms): bool
    {
        foreach ($this->remarksFromCriticisms($criticisms) as $remark) {
            if (preg_match('/\b(remove|delete|drop|cut|omit)\b/i', $remark)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<Criticism>  $criticisms
     */
    protected function allCriticismsHaveReference(array $criticisms): bool
    {
        foreach ($criticisms as $criticism) {
            if (trim((string) ($criticism->getReference() ?? '')) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<Criticism>  $criticisms
     * @return array<string, list<Criticism>>
     */
    protected function groupCriticismsByReference(array $criticisms): array
    {
        $groups = [];

        foreach ($criticisms as $criticism) {
            $reference = trim((string) ($criticism->getReference() ?? ''));
            if ($reference === '') {
                continue;
            }

            $groups[$reference][] = $criticism;
        }

        return $groups;
    }

    /**
     * @param  list<Criticism>  $criticisms
     * @return list<string>
     */
    protected function remarksFromCriticisms(array $criticisms): array
    {
        $remarks = [];

        foreach ($criticisms as $criticism) {
            foreach ($criticism->getRemarks() as $remark) {
                $remark = trim($remark);
                if ($remark !== '') {
                    $remarks[] = $remark;
                }
            }
        }

        return array_values(array_unique($remarks));
    }
}
