<?php

namespace App\Contracts\CommonData\SemanticContextConcerns;

use App\Contracts\CommonData\Audience;

/**
 * @method null|array getAudiences()
 * @method null|array getAudiencesValue()
 * @method null|string getAudiencesDescription()
 */
trait HasAudiences
{
    protected string $_audiencesKey = 'audiences';

    protected string $_audiencesDescription = 'Detailed information of the audiences';

    protected function bootHasAudiences(): void
    {
        if ($this->isKeyAllowed($this->_audiencesKey)) {
            return;
        }

        $this->keys[] = $this->_audiencesKey;
    }

    public function setAudiences(array $audiences): static
    {
        return $this->set(
            $this->_audiencesKey,
            $this->_audiencesDescription,
            array_filter($audiences, fn ($audience) => $audience instanceof Audience)
        );
    }

    public function addAudience(Audience $audience): static
    {
        $audiences = $this->getAudiencesValue() ?: [];
        $audiences[] = $audience;
        $this->setAudiences($audiences);
        return $this;
    }
}