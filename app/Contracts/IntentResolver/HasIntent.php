<?php

namespace App\Contracts\IntentResolver;

trait HasIntent
{
    protected Intent $intent;

    public function getIntent(): Intent
    {
        return $this->intent;
    }

    public function setIntent(Intent $intent): static
    {
        $this->intent = $intent;

        return $this;
    }
}
