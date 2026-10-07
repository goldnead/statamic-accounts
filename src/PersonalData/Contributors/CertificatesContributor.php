<?php

namespace Goldnead\Accounts\PersonalData\Contributors;

use Goldnead\Accounts\Contracts\ErasesPersonalData;
use Goldnead\Accounts\PersonalData\ErasureResult;
use Goldnead\Accounts\Support\Subjects;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Statamic\Auth\User;
use Throwable;

/**
 * goldnead/statamic-certificates: the certificates issued to the user, with
 * the printed name, and the PDFs rendered for them.
 *
 * A deleted certificate's code no longer verifies on the public page. That is
 * the point: the page would otherwise go on showing the person's name.
 */
class CertificatesContributor extends TableContributor implements ErasesPersonalData
{
    public function key(): string
    {
        return 'certificates';
    }

    public function label(): string
    {
        return __('accounts::messages.section_certificates');
    }

    protected function marker(): string
    {
        return 'Goldnead\Certificates\Models\Certificate';
    }

    protected function tables(): array
    {
        return ['certificates_issued'];
    }

    public function collect(User $user): array
    {
        return [
            'certificates' => $this->rows('certificates_issued', fn (Builder $q) => $this->whereHolder($q, $user)),
        ];
    }

    /**
     * Rows first, files after: a file deleted before a rolled-back
     * transaction cannot come back, a row can.
     */
    public function erase(User $user): ErasureResult
    {
        $codes = DB::table('certificates_issued')
            ->where(fn (Builder $q) => $this->whereHolder($q, $user))
            ->pluck('code')
            ->all();

        $deleted = $this->deleteWhere('certificates_issued', fn (Builder $q) => $this->whereHolder($q, $user));

        DB::afterCommit(fn () => $this->deletePdfs($codes));

        return new ErasureResult($this->key(), deleted: array_filter(['certificates' => $deleted]));
    }

    protected function whereHolder(Builder $query, User $user): Builder
    {
        return $query->whereIn('subject_type', Subjects::userTypes())->where('subject_id', (string) $user->id());
    }

    /**
     * Where certificates' CertificatePdf keeps them: `{storage.path}/{code}.pdf`
     * on `storage.disk`.
     *
     * @param  list<string>  $codes
     */
    protected function deletePdfs(array $codes): void
    {
        if ($codes === []) {
            return;
        }

        $directory = trim((string) config('certificates.storage.path', 'certificates'), '/');

        try {
            Storage::disk((string) config('certificates.storage.disk', 'local'))
                ->delete(array_map(fn (string $code) => $directory.'/'.$code.'.pdf', $codes));
        } catch (Throwable $e) {
            // The rows are gone, nothing links to the files any more. Logged,
            // so a stray PDF on a broken disk is found.
            report($e);
        }
    }
}
