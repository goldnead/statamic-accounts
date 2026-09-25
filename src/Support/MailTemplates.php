<?php

namespace Goldnead\Accounts\Support;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Turns a template key into subject and HTML.
 *
 * The slug under `accounts.mail.templates.<key>` is looked up in
 * goldnead/statamic-email-templates. A slug without an entry there, or a site
 * without the sibling, gets the default text from this addon's language files,
 * wrapped in a plain layout. So every mail goes out on a fresh install, and an
 * editor who wants other words writes an entry with the same slug.
 */
class MailTemplates
{
    public const FACADE = '\Goldnead\EmailTemplates\Facades\EmailTemplates';

    /**
     * Keys inserted without escaping. All are links this addon signs itself
     * and all end up in an `href`, where an escaped `&amp;` in the query
     * string would break the signature.
     *
     * @var list<string>
     */
    public const RAW_VARIABLES = ['action_url', 'reasons_list'];

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys((array) config('accounts.mail.templates', []));
    }

    public static function slug(string $key): string
    {
        return (string) config('accounts.mail.templates.'.$key, '');
    }

    /**
     * The shipped default for a key: what an import writes into the CP and
     * what is sent while no entry exists.
     *
     * @return array{slug: string, title: string, subject: string, preview: string, body: string, description: string}
     */
    public static function defaultFor(string $key): array
    {
        return [
            'slug' => static::slug($key),
            'title' => __('accounts::mail.'.$key.'.title'),
            'subject' => __('accounts::mail.'.$key.'.subject'),
            'preview' => __('accounts::mail.'.$key.'.preview'),
            'body' => __('accounts::mail.'.$key.'.body'),
            'description' => __('accounts::mail.'.$key.'.description'),
        ];
    }

    public function siblingInstalled(): bool
    {
        return class_exists(self::FACADE);
    }

    /**
     * Whether the site has its own entry for this key, as opposed to sending
     * the shipped default. For the wiring screen.
     */
    public function hasEntry(string $key): bool
    {
        return $this->hasSlug(static::slug($key));
    }

    /**
     * Whether email-templates has an entry under this slug.
     */
    public function hasSlug(string $slug): bool
    {
        if (! $this->siblingInstalled() || $slug === '') {
            return false;
        }

        try {
            $facade = self::FACADE;
            $template = $facade::resolve($slug);

            return $template !== null && ($template->source ?? 'entry') === 'entry';
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array{subject: string, html: string}
     */
    public function render(string $key, array $variables): array
    {
        $variables = array_merge(['site_name' => (string) config('app.name')], $variables);

        // "Hallo ," reads broken. An account without a name is greeted by
        // its address.
        if (is_array($variables['user'] ?? null) && blank($variables['user']['name'] ?? null)) {
            $variables['user']['name'] = $variables['user']['email'] ?? '';
        }

        $resolved = $this->fromSibling($key);

        if ($resolved !== null) {
            return [
                'subject' => $this->interpolate($resolved['subject'], $variables, escape: false),
                'html' => $this->interpolate($resolved['html'], $variables),
            ];
        }

        $default = static::defaultFor($key);
        $body = $this->interpolate($default['body'], $variables);

        return [
            'subject' => $this->interpolate($default['subject'], $variables, escape: false),
            'html' => app(ViewFactory::class)->make('accounts::mail.layout', [
                'title' => $this->interpolate($default['subject'], $variables, escape: false),
                'preview' => $this->interpolate($default['preview'], $variables, escape: false),
                'body' => $body,
            ])->render(),
        ];
    }

    /**
     * @return array{subject: string, html: string}|null
     */
    protected function fromSibling(string $key): ?array
    {
        $slug = static::slug($key);

        if ($slug === '' || ! $this->siblingInstalled()) {
            return null;
        }

        try {
            $facade = self::FACADE;
            // No fallback callable: without an entry the sibling returns null
            // and the default below is used, wrapped in this addon's layout.
            $template = $facade::resolve($slug);

            if ($template === null || ! is_string($template->body ?? null) || $template->body === '') {
                return null;
            }

            return [
                'subject' => (string) ($template->subject ?? ''),
                'html' => $template->body,
            ];
        } catch (Throwable $e) {
            Log::warning('statamic-accounts: the email template could not be rendered; the default text was sent.', [
                'template' => $slug,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * `{{ key }}` and `{{ dotted.key }}` replacement, the grammar of
     * email-templates. Values are escaped unless named in RAW_VARIABLES.
     *
     * @param  array<string, mixed>  $variables
     */
    public function interpolate(string $text, array $variables, bool $escape = true): string
    {
        return (string) preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', function (array $match) use ($variables, $escape) {
            $key = $match[1];
            $missing = new \stdClass;
            $value = data_get($variables, $key, $missing);

            // Unknown keys stay visible, like in email-templates: a typo
            // shows in the preview instead of vanishing.
            if ($value === $missing) {
                return $match[0];
            }

            if (! is_scalar($value)) {
                return '';
            }

            $value = (string) $value;

            return $escape && ! in_array($key, self::RAW_VARIABLES, true) ? e($value) : $value;
        }, $text);
    }
}
