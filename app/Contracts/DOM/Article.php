<?php

namespace App\Contracts\DOM;

use App\Contracts\Synthesizer\Illustration\Illustratable;
use App\Utils\Markdown;
use Exception;

class Article extends Element implements Illustratable
{
    protected ?ElementType $type = ElementType::ARTICLE;

    /**
     * @throws Exception
     */
    public function setType(?ElementType $type): static
    {
        throw new Exception('Prohibited');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $article = new static;

        if (isset($data['identifier'])) {
            $article->setIdentifier($data['identifier']);
        }

        if (isset($data['props']) && is_array($data['props'])) {
            $article->setProps($data['props']);
        }

        if (isset($data['children']) && is_array($data['children'])) {
            $children = [];
            foreach ($data['children'] as $child) {
                if (is_array($child)) {
                    if (($child['type'] ?? null) === ElementType::ARTICLE->value) {
                        $children[] = static::fromArray($child);
                    } else {
                        $children[] = Element::fromArray($child);
                    }
                    continue;
                }

                if (is_string($child)) {
                    $children[] = $child;
                }
            }

            $article->setChildren($children);
        }

        return $article;
    }

    public function toMarkdown(): string
    {
        return Markdown::htmlToMarkdown($this->toHtml());
    }

    public function getIllustrationContent(): string
    {
        return $this->toMarkdown();
    }
}