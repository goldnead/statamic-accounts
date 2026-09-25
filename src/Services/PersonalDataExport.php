<?php

namespace Goldnead\Accounts\Services;

use Goldnead\Accounts\Events\PersonalDataExported;
use Goldnead\Accounts\Integrations\ActivityBridge;
use Goldnead\Accounts\PersonalData\PersonalDataRegistry;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Statamic\Contracts\Auth\User;
use Throwable;
use ZipArchive;

/**
 * Collects a person's data from every contributor and packs it.
 *
 * One `<key>.json` per contributor plus `manifest.json` naming what is in the
 * file, when it was made and which contributors failed. A contributor that
 * throws is listed as failed rather than silently missing: an export that
 * looks complete but is not is worse than one that says what it lacks.
 */
class PersonalDataExport
{
    public function __construct(
        protected PersonalDataRegistry $registry,
        protected ActivityBridge $activity,
    ) {}

    /**
     * @return array{manifest: array<string, mixed>, sections: array<string, array<string, mixed>>}
     */
    public function collect(User $user): array
    {
        $sections = [];
        $failed = [];

        foreach ($this->registry->available() as $key => $contributor) {
            try {
                $sections[$key] = $contributor->collect($user);
            } catch (Throwable $e) {
                $failed[$key] = $contributor->label();

                Log::error('statamic-accounts: a personal data contributor failed.', [
                    'contributor' => $key,
                    'user_id' => (string) $user->id(),
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        return [
            'manifest' => [
                'user_id' => (string) $user->id(),
                'email' => (string) $user->email(),
                'generated_at' => now()->toIso8601String(),
                'site' => (string) config('app.name'),
                'sections' => array_keys($sections),
                'failed' => $failed,
            ],
            'sections' => $sections,
        ];
    }

    /**
     * Write the export to a temporary file and return its path and the file
     * name to offer. ZIP when `ext-zip` is there, one JSON file otherwise.
     * The caller deletes the file after sending it.
     *
     * @return array{path: string, filename: string, mime: string}
     */
    public function build(User $user, string $requestedBy = 'customer', mixed $actor = null): array
    {
        $export = $this->collect($user);
        $stamp = now()->format('Y-m-d');
        $base = 'personal-data-'.Str::slug((string) $user->email()).'-'.$stamp;
        $path = tempnam(sys_get_temp_dir(), 'accounts-export-');

        if ($path === false) {
            throw new \RuntimeException('No temporary file for the export.');
        }

        if (class_exists(ZipArchive::class)) {
            $zip = new ZipArchive;
            $zip->open($path, ZipArchive::OVERWRITE);
            $zip->addFromString('manifest.json', $this->json($export['manifest']));

            foreach ($export['sections'] as $key => $data) {
                $zip->addFromString($key.'.json', $this->json($data));
            }

            $zip->close();

            $file = ['path' => $path, 'filename' => $base.'.zip', 'mime' => 'application/zip'];
        } else {
            file_put_contents($path, $this->json($export));

            $file = ['path' => $path, 'filename' => $base.'.json', 'mime' => 'application/json'];
        }

        $sections = array_keys($export['sections']);

        PersonalDataExported::dispatch((string) $user->id(), (string) $user->email(), $user->name(), $sections, $requestedBy);

        $this->activity->record('accounts.data_exported', [
            'user_id' => (string) $user->id(),
            'sections' => $sections,
            'requested_by' => $requestedBy,
        ], $actor ?? $user);

        return $file;
    }

    protected function json(mixed $data): string
    {
        return (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }
}
