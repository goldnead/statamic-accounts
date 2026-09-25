<?php

/*
 * The two declarations of goldnead/statamic-email-templates the default
 * template source uses, under their real names, for PHPStan and for the test
 * that runs the source. Same signatures as the sibling.
 */

namespace Goldnead\EmailTemplates\Contracts {
    use Goldnead\EmailTemplates\Support\EmailTemplateData;

    if (! interface_exists(EmailTemplateSource::class)) {
        interface EmailTemplateSource
        {
            public function label(): string;

            /** @return array<int, EmailTemplateData> */
            public function all(): array;
        }
    }
}

namespace Goldnead\EmailTemplates\Support {
    if (! class_exists(EmailTemplateData::class)) {
        class EmailTemplateData
        {
            public function __construct(
                public string $slug,
                public string $title,
                public string $subject = '',
                public string $preview = '',
                public string $body = '',
                public ?string $plainText = null,
                public ?string $description = null,
                public ?string $layout = null,
                public string $source = 'entry',
            ) {}

            /** @param  array<string, mixed>  $data */
            public static function fromArray(array $data): self
            {
                return new self(
                    slug: (string) ($data['slug'] ?? ''),
                    title: (string) ($data['title'] ?? ''),
                    subject: (string) ($data['subject'] ?? ''),
                    preview: (string) ($data['preview'] ?? ''),
                    body: (string) ($data['body'] ?? ''),
                    description: isset($data['description']) ? (string) $data['description'] : null,
                    source: (string) ($data['source'] ?? 'entry'),
                );
            }
        }
    }
}
