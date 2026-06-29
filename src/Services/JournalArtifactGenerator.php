<?php

namespace LBHurtado\XJournal\Services;

use Illuminate\Support\Collection;
use LBHurtado\XJournal\Contracts\JournalArtifactRendererContract;
use LBHurtado\XJournal\Data\JournalArtifactData;
use LBHurtado\XJournal\Data\JournalArtifactProfileData;
use LBHurtado\XJournal\Exceptions\JournalArtifactRendererNotFoundException;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

class JournalArtifactGenerator
{
    /**
     * @var array<int, JournalArtifactRendererContract>
     */
    protected array $renderers = [];

    public function register(JournalArtifactRendererContract $renderer): self
    {
        $this->renderers[] = $renderer;

        return $this;
    }

    /**
     * @param  iterable<int, ExecutionJournalEntry>  $entries
     */
    public function generate(iterable $entries, JournalArtifactProfileData $profile): JournalArtifactData
    {
        $collection = $entries instanceof Collection ? $entries : collect($entries);

        foreach ($this->renderers as $renderer) {
            if ($renderer->supports($profile)) {
                return $renderer->render($collection, $profile);
            }
        }

        throw JournalArtifactRendererNotFoundException::forProfile($profile->type, $profile->format);
    }
}
