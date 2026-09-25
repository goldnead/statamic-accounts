<?php

namespace Goldnead\Accounts\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;

/**
 * Every account mail: subject and HTML already rendered by
 * {@see \Goldnead\Accounts\Support\MailTemplates}.
 *
 * Sent, not queued. A confirmation link that sits in a queue on a host
 * without a worker never arrives, and the customer waits for it.
 */
class AccountMail extends Mailable
{
    use Queueable;

    public function __construct(public string $templateKey, string $subjectLine, public string $htmlBody)
    {
        $this->subject($subjectLine);
    }

    public function build(): static
    {
        return $this->html($this->htmlBody);
    }
}
