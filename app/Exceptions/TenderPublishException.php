<?php

namespace App\Exceptions;

use App\Models\Tender;
use Exception;

/**
 * A tender could not be opened for bidding. The message is shown to the
 * officer as the reason.
 *
 * $savedDraft is set when the tender itself was saved before publishing
 * failed — the create wizard's "publish now" commits the draft first, so an
 * engine that cannot lock the profile does not throw away the officer's work.
 * The caller then sends them to that draft rather than back to an empty form.
 */
class TenderPublishException extends Exception
{
    public ?Tender $savedDraft = null;

    public function withSavedDraft(Tender $tender): self
    {
        $this->savedDraft = $tender;

        return $this;
    }
}
