<?php

namespace App\Contracts\CommonData\SemanticContextConcerns;

trait HasMeta
{
    protected string $_metaKey = 'meta';

    protected string $_metaDescription = 'Dynamic, non-standard contextual signals.';

    protected function bootHasMetaConcern(): void
    {
        if ($this->isKeyAllowed($this->_metaKey)) {
            return;
        }

        $this->keys[] = $this->_metaKey;
    }

    public function setMeta(array $meta): static
    {
        $this->set($this->_metaKey, $this->_metaDescription, $meta);
        return $this;
    }
}