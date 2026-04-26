<?php

namespace App\Contracts\DOM;

use Exception;

class Article extends Element
{
    protected ?ElementType $type = ElementType::ARTICLE;

    /**
     * @throws Exception
     */
    public function setType(?ElementType $type): static
    {
        throw new Exception('Prohibited');
    }
}