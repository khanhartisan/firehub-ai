<?php

namespace App\Contracts\CommonData\SemanticContextConcerns;

use App\Contracts\CommonData\AudienceContext;

/**
 * @method null|array getAudienceContexts()
 * @method null|array getAudienceContextsValue()
 * @method null|string getAudienceContextsDescription()
 */
trait HasAudienceContexts
{
    protected string $_audienceContextsKey = 'audience_contexts';

    protected string $_audienceContextsDescription = 'Detailed audience context profiles.';

    protected function bootHasAudienceContexts(): void
    {
        if ($this->isKeyAllowed($this->_audienceContextsKey)) {
            return;
        }

        $this->keys[] = $this->_audienceContextsKey;
    }

    public function setAudienceContexts(array $audienceContexts): static
    {
        return $this->set(
            $this->_audienceContextsKey,
            $this->_audienceContextsDescription,
            array_filter($audienceContexts, fn ($audienceContext) => $audienceContext instanceof AudienceContext)
        );
    }

    public function addAudienceContext(AudienceContext $audienceContext): static
    {
        $audienceContexts = $this->getAudienceContextsValue() ?: [];
        $audienceContexts[] = $audienceContext;
        $this->setAudienceContexts($audienceContexts);
        return $this;
    }
}
