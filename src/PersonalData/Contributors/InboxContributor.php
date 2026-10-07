<?php

namespace Goldnead\Accounts\PersonalData\Contributors;

use Goldnead\Accounts\Contracts\ErasesPersonalData;
use Goldnead\Accounts\PersonalData\ErasureResult;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Statamic\Auth\User;
use Throwable;

/**
 * goldnead/statamic-inbox: the conversations with the person (by the
 * counterpart's address), every mail in them and their attachments.
 *
 * Only the copy in Statamic. The mails stay on the mail server, which this
 * addon never touches; the inbox fetches by UID and does not bring old ones
 * back. Mails that name the person in another conversation (in Cc, say) stay:
 * they are someone else's conversation.
 */
class InboxContributor extends TableContributor implements ErasesPersonalData
{
    /**
     * Mail bodies can be long and are not what an export is for; the
     * plain text and the headers are.
     *
     * @var list<string>
     */
    protected const MESSAGE_COLUMNS = ['id', 'conversation_id', 'direction', 'from_email', 'from_name', 'to', 'cc', 'subject', 'text', 'sent_at'];

    public function key(): string
    {
        return 'inbox';
    }

    public function label(): string
    {
        return __('accounts::messages.section_inbox');
    }

    protected function marker(): string
    {
        return 'Goldnead\StatamicInbox\Models\Conversation';
    }

    protected function tables(): array
    {
        return ['inbox_conversations', 'inbox_messages'];
    }

    public function collect(User $user): array
    {
        $conversations = $this->rows('inbox_conversations', fn (Builder $q) => $this->whereEmail($q, 'counterpart_email', $user));
        $ids = array_column($conversations, 'id');

        return [
            'conversations' => array_map(fn (array $c) => array_intersect_key($c, array_flip(['id', 'subject', 'counterpart_email', 'status', 'last_message_at', 'created_at'])), $conversations),
            'messages' => $ids === [] ? [] : DB::table('inbox_messages')
                ->whereIn('conversation_id', $ids)
                ->orderBy('id')
                ->get(array_values(array_intersect(self::MESSAGE_COLUMNS, Schema::getColumnListing('inbox_messages'))))
                ->map(fn ($row) => $this->clean((array) $row))
                ->all(),
            'attachments' => $this->attachments($ids)->map(fn ($row) => array_intersect_key((array) $row, array_flip(['message_id', 'filename', 'mime', 'size'])))->all(),
        ];
    }

    public function erase(User $user): ErasureResult
    {
        $ids = DB::table('inbox_conversations')
            ->where(fn (Builder $q) => $this->whereEmail($q, 'counterpart_email', $user))
            ->pluck('id')
            ->all();

        if ($ids === []) {
            return new ErasureResult($this->key());
        }

        $messageIds = DB::table('inbox_messages')->whereIn('conversation_id', $ids)->pluck('id')->all();
        $paths = $this->attachments($ids)->pluck('path')->filter()->all();

        // Children first, see MarketingContributor.
        $attachments = $messageIds === [] ? 0 : $this->deleteWhere('inbox_attachments', fn (Builder $q) => $q->whereIn('message_id', $messageIds));
        $messages = DB::table('inbox_messages')->whereIn('id', $messageIds)->delete();
        $conversations = DB::table('inbox_conversations')->whereIn('id', $ids)->delete();

        DB::afterCommit(function () use ($paths) {
            if ($paths === []) {
                return;
            }

            try {
                Storage::disk((string) config('inbox.attachments.disk', 'local'))->delete(array_values($paths));
            } catch (Throwable $e) {
                report($e);
            }
        });

        return new ErasureResult($this->key(), deleted: array_filter([
            'conversations' => $conversations,
            'messages' => $messages,
            'attachments' => $attachments,
        ]));
    }

    /**
     * @param  list<int|string>  $conversationIds
     * @return Collection<int, \stdClass>
     */
    protected function attachments(array $conversationIds): Collection
    {
        if ($conversationIds === [] || ! Schema::hasTable('inbox_attachments')) {
            return collect();
        }

        return DB::table('inbox_attachments')
            ->join('inbox_messages', 'inbox_messages.id', '=', 'inbox_attachments.message_id')
            ->whereIn('inbox_messages.conversation_id', $conversationIds)
            ->orderBy('inbox_attachments.id')
            ->get(['inbox_attachments.message_id', 'inbox_attachments.filename', 'inbox_attachments.mime', 'inbox_attachments.size', 'inbox_attachments.path']);
    }
}
