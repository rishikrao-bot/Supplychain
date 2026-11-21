<?php

namespace App\Support;

/**
 * Who can do what. Roles depend on the type of organisation a person belongs to.
 */
final class Roles
{
    public const ADMIN = 'admin';
    public const HELPDESK = 'helpdesk';
    public const FINANCE = 'finance';
    public const ENGINEER = 'engineer';
    public const CLIENT_ADMIN = 'client_admin';
    public const APPROVER = 'approver';
    public const VIEWER = 'viewer';
    public const PARTNER_MANAGER = 'partner_manager';
    public const SITE_ENGINEER = 'site_engineer';

    /** @var array<string, array<string, array{0:string,1:string}>> */
    public const BY_ORG_TYPE = [
        'operator' => [
            self::ADMIN => ['Admin', 'Everything, including users, settings, rates and finance'],
            self::HELPDESK => ['Helpdesk', 'Log orders, offer and assign jobs, check reports. No finance, settings or users'],
            self::FINANCE => ['Finance', 'Valuations and payments. Read-only on jobs'],
            self::ENGINEER => ['Engineer', 'Engineer app only: their own jobs and visit reports'],
        ],
        'client' => [
            self::CLIENT_ADMIN => ['Client admin', 'Approve estimates, decide on exceptions, add and remove their own users'],
            self::APPROVER => ['Approver', 'Approve estimates and decide on exceptions'],
            self::VIEWER => ['Viewer', 'See jobs, reports and valuations only'],
        ],
        'partner' => [
            self::PARTNER_MANAGER => ['Partner manager', 'Accept offers, manage their team and documents, see their payments'],
            self::SITE_ENGINEER => ['Site engineer', 'Engineer app only: their own jobs and visit reports'],
        ],
    ];

    public static function forOrgType(string $type): array
    {
        return self::BY_ORG_TYPE[$type] ?? [];
    }

    public static function label(string $role): string
    {
        foreach (self::BY_ORG_TYPE as $roles) {
            if (isset($roles[$role])) {
                return $roles[$role][0];
            }
        }

        return $role;
    }

    public static function validFor(string $role, string $orgType): bool
    {
        return isset(self::BY_ORG_TYPE[$orgType][$role]);
    }
}
