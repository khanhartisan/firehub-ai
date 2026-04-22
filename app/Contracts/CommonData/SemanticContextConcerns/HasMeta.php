<?php

namespace App\Contracts\CommonData\SemanticContextConcerns;

/**
 * @method null|array getMetaValue()
 */
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

    public function addMeta(string $key, mixed $value): static
    {
        if (!$this->isSerializableValue($value)) {
            throw new \InvalidArgumentException('Meta value must be serializable.');
        }

        $meta = $this->getMetaValue() ?: [];
        $meta[$key] = $this->normalizeValue($value);
        $this->setMeta($meta);
        return $this;
    }

    public function forgetMeta(string $key): static
    {
        $meta = $this->getMetaValue() ?: [];
        if (array_key_exists($key, $meta)) {
            unset($meta[$key]);
        }
        $this->setMeta($meta);
        return $this;
    }
}