<?php

namespace App\Services\Microsoft;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The Dataverse (Dynamics / Power Apps CRM) Web API, as the portal's application user.
 * Table names are the plural "entity set" names, e.g. accounts, contacts, opportunities.
 */
class Dataverse
{
    public static function isConfigured(): bool
    {
        return MicrosoftApp::isConfigured() && filled(MicrosoftApp::config('dataverse_url'));
    }

    private static function base(): string
    {
        return rtrim((string) MicrosoftApp::config('dataverse_url'), '/');
    }

    private static function http(): PendingRequest
    {
        return Http::withToken(MicrosoftApp::token(self::base().'/.default'))
            ->withHeaders(['OData-Version' => '4.0', 'OData-MaxVersion' => '4.0', 'Prefer' => 'odata.include-annotations="OData.Community.Display.V1.FormattedValue"'])
            ->acceptJson()->timeout(30)->baseUrl(self::base().'/api/data/v9.2/');
    }

    private static function check($res): array
    {
        if (! $res->successful()) {
            throw new RuntimeException('Dataverse: '.MicrosoftApp::error($res->json())." ({$res->status()})");
        }

        return $res->json() ?? [];
    }

    /** Who the portal is signed in as: UserId, BusinessUnitId, OrganizationId. */
    public static function whoAmI(): array
    {
        return self::check(self::http()->get('WhoAmI'));
    }

    /**
     * Read rows, e.g. Dataverse::rows('accounts', ['$select' => 'name,telephone1', '$filter' => "name eq 'Client Co'", '$top' => 10]).
     *
     * @return array<int, array>
     */
    public static function rows(string $table, array $query = []): array
    {
        return self::check(self::http()->get($table, $query))['value'] ?? [];
    }

    /** Every row, following Dataverse's paging. */
    public static function all(string $table, array $query = []): array
    {
        $rows = [];
        $res = self::http()->withHeaders(['Prefer' => 'odata.maxpagesize=500'])->get($table, $query);
        while (true) {
            $page = self::check($res);
            $rows = array_merge($rows, $page['value'] ?? []);
            if (empty($page['@odata.nextLink'])) {
                return $rows;
            }
            $res = self::http()->withHeaders(['Prefer' => 'odata.maxpagesize=500'])->baseUrl('')->get($page['@odata.nextLink']);
        }
    }

    public static function find(string $table, string $id, array $query = []): array
    {
        return self::check(self::http()->get("{$table}({$id})", $query));
    }

    /** Create a row; returns its id. */
    public static function create(string $table, array $data): string
    {
        $res = self::http()->post($table, $data);
        self::check($res);
        // Dataverse answers 204 with the new row's address in OData-EntityId: .../accounts(<guid>)
        $where = $res->header('OData-EntityId') ?: $res->header('Location');

        return preg_match('/\(([0-9a-f-]{36})\)\s*$/i', (string) $where, $m) ? $m[1] : '';
    }

    public static function update(string $table, string $id, array $data): void
    {
        self::check(self::http()->patch("{$table}({$id})", $data));
    }
}
