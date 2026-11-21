<?php

namespace App\Services\Microsoft;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * The signed-in person's own Outlook emails with a company's or contact's people.
 * Emails are read live from their mailbox and only stored when they click "Save to record".
 */
class Outlook
{
    /** @param  array<int, string>  $addresses */
    public static function emailsWith(User $user, array $addresses, int $top = 15): array
    {
        $addresses = array_values(array_unique(array_filter(array_map(fn ($a) => strtolower(trim((string) $a)), $addresses),
            fn ($a) => filter_var($a, FILTER_VALIDATE_EMAIL))));
        if (! $addresses) {
            return [];
        }
        $addresses = array_slice($addresses, 0, 10);

        return Cache::remember('outlook:'.$user->id.':'.sha1(implode(',', $addresses)), 300, function () use ($user, $addresses, $top) {
            $kql = implode(' OR ', array_map(fn ($a) => 'participants:'.$a, $addresses));
            $res = MicrosoftUser::graph($user)->get('/me/messages', [
                '$search' => '"'.$kql.'"',
                '$top' => $top,
                '$select' => 'id,subject,from,toRecipients,receivedDateTime,bodyPreview,webLink',
            ]);
            $items = MicrosoftUser::check($res, 'Outlook')['value'] ?? [];

            return collect($items)->map(fn ($m) => self::shape($m))->sortByDesc('at')->values()->all();
        });
    }

    public static function message(User $user, string $id): array
    {
        $res = MicrosoftUser::graph($user)->get('/me/messages/'.rawurlencode($id), ['$select' => 'id,subject,from,toRecipients,receivedDateTime,bodyPreview,webLink']);

        return self::shape(MicrosoftUser::check($res, 'Outlook'));
    }

    private static function shape(array $m): array
    {
        return [
            'id' => $m['id'],
            'subject' => $m['subject'] ?: '(no subject)',
            'from' => $m['from']['emailAddress']['name'] ?? $m['from']['emailAddress']['address'] ?? '',
            'from_address' => strtolower($m['from']['emailAddress']['address'] ?? ''),
            'to' => collect($m['toRecipients'] ?? [])->map(fn ($r) => $r['emailAddress']['name'] ?? $r['emailAddress']['address'])->implode(', '),
            'at' => isset($m['receivedDateTime']) ? Carbon::parse($m['receivedDateTime'])->timezone('Europe/London') : null,
            'preview' => $m['bodyPreview'] ?? '',
            'link' => $m['webLink'] ?? null,
        ];
    }
}
