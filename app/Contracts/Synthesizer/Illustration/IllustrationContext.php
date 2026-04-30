<?php

namespace App\Contracts\Synthesizer\Illustration;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\CommonData\SemanticContextConcerns\HasMeta;
use App\Enums\AspectRatio;

/**
 * @method null|array getSubject()
 * @method null|string getSubjectValue()
 * @method null|string getSubjectDescription()
 * @method null|array getGoal()
 * @method null|string getGoalValue()
 * @method null|string getGoalDescription()
 * @method null|array getStyle()
 * @method null|string getStyleValue()
 * @method null|string getStyleDescription()
 * @method null|array getAspectRatio()
 * @method null|string getAspectRatioValue()
 * @method null|string getAspectRatioDescription()
 * @method null|array getReferenceFileIds()
 * @method null|array getReferenceFileIdsValue()
 * @method null|string getReferenceFileIdsDescription()
 * @method null|array getMacroContext()
 * @method null|string getMacroContextValue()
 * @method null|string getMacroContextDescription()
 * @method null|array getMicroContext()
 * @method null|string getMicroContextValue()
 * @method null|string getMicroContextDescription()
 * @method null|array getConstraints()
 * @method null|array getConstraintsValue()
 * @method null|string getConstraintsDescription()
 * @method null|array getMeta()
 * @method null|array getMetaValue()
 * @method null|string getMetaDescription()
 */
class IllustrationContext extends SemanticContext
{
    use HasMeta;

    public function setSubject(string $subject): static
    {
        return $this->set(
            'subject',
            'The primary subject or concept that needs to be illustrated.',
            $subject
        );
    }

    public function setGoal(string $goal): static
    {
        return $this->set(
            'goal',
            'The communication goal of the illustration.',
            $goal
        );
    }

    public function setStyle(string $style): static
    {
        return $this->set(
            'style',
            'Preferred visual style for this illustration.',
            $style
        );
    }

    public function setAspectRatio(AspectRatio $aspectRatio): static
    {
        return $this->set(
            'aspect_ratio',
            'Preferred aspect ratio for generated illustrations.',
            $aspectRatio->value
        );
    }

    public function setReferenceFileIds(array $referenceFileIds): static
    {
        return $this->set(
            'reference_file_ids',
            'File IDs used as visual references for generating this illustration.',
            array_values(array_filter($referenceFileIds, fn (mixed $fileId): bool => is_string($fileId) && $fileId !== ''))
        );
    }

    public function setMacroContext(string $macroContext): static
    {
        return $this->set(
            'macro_context',
            'High-level context of what we are working on.',
            $macroContext
        );
    }

    public function setMicroContext(string $microContext): static
    {
        return $this->set(
            'micro_context',
            'Fine-grained, local, detailed context that we needs to illustrate..',
            $microContext
        );
    }

    public function setConstraints(array $constraints): static
    {
        return $this->set(
            'constraints',
            'Hard requirements or limitations the illustration must satisfy.',
            array_values(array_filter($constraints, fn (mixed $constraint): bool => is_string($constraint)))
        );
    }
}