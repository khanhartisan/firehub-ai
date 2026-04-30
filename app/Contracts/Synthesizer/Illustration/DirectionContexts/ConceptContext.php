<?php

namespace App\Contracts\Synthesizer\Illustration\DirectionContexts;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\Synthesizer\Illustration\DirectionContexts\ConceptConcerns\HasAbstractionContexts;
use App\Contracts\Synthesizer\Illustration\DirectionContexts\ConceptConcerns\HasCharacterContexts;
use App\Contracts\Synthesizer\Illustration\DirectionContexts\ConceptConcerns\HasLandscapeContext;
use App\Contracts\Synthesizer\Illustration\DirectionContexts\ConceptConcerns\HasObjectContexts;

/**
 * @method null|array getLogline()
 * @method null|string getLoglineValue()
 * @method null|string getLoglineDescription()
 * @method null|array getPrimarySubject()
 * @method null|string getPrimarySubjectValue()
 * @method null|string getPrimarySubjectDescription()
 * @method null|array getNarrativeIntent()
 * @method null|string getNarrativeIntentValue()
 * @method null|string getNarrativeIntentDescription()
 * @method null|array getSceneContext()
 * @method null|string getSceneContextValue()
 * @method null|string getSceneContextDescription()
 * @method null|array getMood()
 * @method null|string getMoodValue()
 * @method null|string getMoodDescription()
 * @method null|array getSymbolicNotes()
 * @method null|array getSymbolicNotesValue()
 * @method null|string getSymbolicNotesDescription()
 * @method null|array getConstraints()
 * @method null|array getConstraintsValue()
 * @method null|string getConstraintsDescription()
 * @method null|array getCharacterContexts()
 * @method null|array getCharacterContextsValue()
 * @method null|string getCharacterContextsDescription()
 * @method null|array getObjectContexts()
 * @method null|array getObjectContextsValue()
 * @method null|string getObjectContextsDescription()
 * @method null|array getAbstractionContexts()
 * @method null|array getAbstractionContextsValue()
 * @method null|string getAbstractionContextsDescription()
 * @method null|array getLandscapeContext()
 * @method null|array getLandscapeContextValue()
 * @method null|string getLandscapeContextDescription()
 */
class ConceptContext extends SemanticContext
{
    use HasAbstractionContexts;
    use HasCharacterContexts;
    use HasLandscapeContext;
    use HasObjectContexts;

    public function setLogline(string $logline): static
    {
        return $this->set(
            'logline',
            'Short, high-level concept summary for the illustration.',
            $logline
        );
    }

    public function setPrimarySubject(string $primarySubject): static
    {
        return $this->set(
            'primary_subject',
            'Main subject that should dominate the concept.',
            $primarySubject
        );
    }

    public function setNarrativeIntent(string $narrativeIntent): static
    {
        return $this->set(
            'narrative_intent',
            'What story or message this concept should communicate.',
            $narrativeIntent
        );
    }

    public function setSceneContext(string $sceneContext): static
    {
        return $this->set(
            'scene_context',
            'Contextual scene framing for where and when the concept happens.',
            $sceneContext
        );
    }

    public function setMood(string $mood): static
    {
        return $this->set(
            'mood',
            'Overall emotional tone of the concept direction.',
            $mood
        );
    }

    public function setSymbolicNotes(array $symbolicNotes): static
    {
        return $this->set(
            'symbolic_notes',
            'Optional symbolism cues that reinforce the concept.',
            array_values(array_filter($symbolicNotes, fn (mixed $note): bool => is_string($note) && $note !== ''))
        );
    }

    public function setConstraints(array $constraints): static
    {
        return $this->set(
            'constraints',
            'Top-level concept constraints and non-negotiable requirements.',
            array_values(array_filter($constraints, fn (mixed $constraint): bool => is_string($constraint) && $constraint !== ''))
        );
    }
}