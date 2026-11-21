<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

/** A Power BI report shown inside the portal (secure embed: viewers sign in to Power BI with Microsoft). */
class Dashboard extends Model
{
    use BelongsToAccount;

    protected $fillable = ['account_id', 'title', 'description', 'embed_url', 'roles', 'sort'];

    protected $casts = ['roles' => 'array'];

    public function visibleTo(User $user): bool
    {
        return $user->isCompass() && $user->isActive() && in_array($user->role, $this->roles ?? [], true);
    }

    /**
     * Only Power BI report links are accepted. A "Website or portal" embed link, or the report's own
     * address from the browser, both work; the latter is turned into an embed link.
     */
    public static function normaliseUrl(string $url): ?string
    {
        $url = trim($url);
        if (preg_match('/<iframe[^>]+src="([^"]+)"/i', $url, $m)) {
            $url = html_entity_decode($m[1]);
        }
        $p = parse_url($url);
        if (($p['scheme'] ?? '') !== 'https' || ! in_array(strtolower($p['host'] ?? ''), ['app.powerbi.com', 'app.powerbi.de', 'app.powerbigov.us'], true)) {
            return null;
        }
        parse_str($p['query'] ?? '', $q);
        if (str_starts_with($p['path'] ?? '', '/reportEmbed') || str_starts_with($p['path'] ?? '', '/view')) {
            return $url;
        }
        // https://app.powerbi.com/groups/{workspace}/reports/{report}/{page}?ctid={tenant}
        if (preg_match('#^/groups/([\w-]+)/reports/([\w-]+)#', $p['path'] ?? '', $m)) {
            $embed = ['reportId' => $m[2], 'autoAuth' => 'true'];
            if ($m[1] !== 'me') {
                $embed['groupId'] = $m[1];
            }
            if (! empty($q['ctid'])) {
                $embed['ctid'] = $q['ctid'];
            }

            return 'https://'.$p['host'].'/reportEmbed?'.http_build_query($embed);
        }

        return null;
    }
}
