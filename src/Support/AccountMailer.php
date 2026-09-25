<?php

namespace Goldnead\Accounts\Support;

use Goldnead\Accounts\Mail\AccountMail;
use Illuminate\Support\Facades\Mail;

class AccountMailer
{
    public function __construct(protected MailTemplates $templates) {}

    /**
     * @param  array<string, mixed>  $variables
     */
    public function send(string $key, string $to, array $variables): void
    {
        $rendered = $this->templates->render($key, $variables);

        Mail::to($to)->send(new AccountMail($key, $rendered['subject'], $rendered['html']));
    }
}
