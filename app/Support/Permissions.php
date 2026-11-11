<?php

namespace App\Support;

/**
 * The catalog of what admin users and stylists can be allowed to do.
 * Routes enforce these; the screens only hide what a user can't reach.
 */
final class Permissions
{
    /** group => [permission => [label, what it allows]] */
    public const GROUPS = [
        'Bookings' => [
            'appointments.view'   => ['See bookings', 'Appointments, calendar and receipts.'],
            'appointments.manage' => ['Manage bookings', 'Create, confirm, change and cancel bookings, and add extras.'],
            'appointments.delete' => ['Delete bookings', 'Permanently remove a booking.'],
            'appointments.export' => ['Export bookings', 'Download the booking list as a CSV file, with customer details.'],
        ],
        'Payments' => [
            'payments.take' => ['Take payments', 'Use the cashier station and record advances.'],
            'payments.void' => ['Remove payments', 'Take a recorded payment off a booking, for mistakes or refunds.'],
        ],
        'Customers' => [
            'customers.view'  => ['See customers', 'Customer list, contact details and visit history.'],
            'customers.manage' => ['Manage customers', 'Edit and delete customer records.'],
            'feedback.manage' => ['Manage reviews', 'Read reviews, publish them on the website or delete them.'],
        ],
        'Catalog' => [
            'services.manage' => ['Manage services', 'Services, packages, prices and categories.'],
            'offers.manage'   => ['Manage offers', 'Discounts for a period of time, shown on the website and applied to bookings.'],
            'stock.view'      => ['See stock', 'Stock levels, low-stock alerts and history.'],
            'stock.manage'    => ['Manage stock', 'Add products, restock and adjust quantities.'],
        ],
        'Team' => [
            'staff.manage' => ['Manage stylists', 'Stylist profiles, hours, portal logins and portal permissions.'],
            'users.manage' => ['Manage admin users', 'Add admin users and choose what they can do.'],
        ],
        'Reports' => [
            'reports.view' => ['See reports & revenue', 'Sales, staff performance and revenue figures.'],
            'logs.view'    => ['See the system log', 'Everything that has happened in the system, and who did it.'],
        ],
        'Business' => [
            'hours.manage'    => ['Manage opening hours', 'Opening hours, breaks and closed days.'],
            'website.manage'  => ['Manage website content', 'Homepage hero and gallery.'],
            'settings.manage' => ['Manage settings', 'Salon details, booking rules, branding and the cashier PIN.'],
        ],
    ];

    /** Holding the left one needs the right ones: you can't manage what you can't see. */
    public const IMPLIES = [
        'appointments.manage' => ['appointments.view'],
        'appointments.delete' => ['appointments.view'],
        'appointments.export' => ['appointments.view'],
        'payments.void'       => ['appointments.view'],
        'customers.manage'    => ['customers.view'],
        'stock.manage'        => ['stock.view'],
    ];

    /** Quick starting points in the form. Nothing depends on these once saved. */
    public const ROLES = [
        'manager' => ['Manager', [
            'appointments.view', 'appointments.manage', 'appointments.delete', 'appointments.export', 'payments.take', 'payments.void',
            'customers.view', 'customers.manage', 'feedback.manage', 'services.manage', 'offers.manage', 'stock.view', 'stock.manage',
            'staff.manage', 'reports.view', 'hours.manage', 'website.manage',
        ]],
        'receptionist' => ['Receptionist', ['appointments.view', 'appointments.manage', 'payments.take', 'customers.view', 'customers.manage']],
        'cashier'      => ['Cashier', ['appointments.view', 'payments.take', 'customers.view']],
        'stock'        => ['Stock keeper', ['stock.view', 'stock.manage']],
    ];

    /** What each stylist can be allowed to do in the portal. Everything is on by default. */
    public const PORTAL = [
        'portal.walkin'   => ['Start walk-ins', 'Add a customer who is here now.'],
        'portal.bookings' => ['Take phone bookings', 'Book customers in for later, for themselves or a colleague.'],
        'portal.extras'   => ['Add extras to bills', 'Add or remove extra charges on their appointments.'],
    ];

    public static function all(): array
    {
        return array_merge(...array_map('array_keys', array_values(self::GROUPS)));
    }

    public static function label(string $permission): string
    {
        foreach (self::GROUPS as $items) {
            if (isset($items[$permission])) {
                return $items[$permission][0];
            }
        }

        return $permission;
    }

    /** Known permissions only, with what they imply, in catalog order. Unknown names never get stored. */
    public static function normalise(array $permissions): array
    {
        foreach ($permissions as $permission) {
            if (is_string($permission)) {
                $permissions = array_merge($permissions, self::IMPLIES[$permission] ?? []);
            }
        }

        return array_values(array_intersect(self::all(), $permissions));
    }

    /** The preset a set of permissions matches exactly, if any. */
    public static function roleFor(array $permissions): ?string
    {
        $permissions = self::normalise($permissions);

        foreach (self::ROLES as [$label, $preset]) {
            if (self::normalise($preset) === $permissions) {
                return $label;
            }
        }

        return null;
    }
}
