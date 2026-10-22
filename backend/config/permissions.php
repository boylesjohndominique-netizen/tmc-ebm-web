<?php

/**
 * Permission catalog — the single source of truth for modules and actions
 * in the TMC equipment system. Both the API (enforcement) and the web UI
 * (permission editor, menu/button gating) read from this structure.
 *
 * "view" is the read/entry action of each module; without it the module's
 * pages and API endpoints are inaccessible to the user.
 */

return [

    'modules' => [

        'dashboard' => [
            'label' => 'Dashboard',
            'description' => 'System overview, statistics and analytics.',
            'actions' => ['view'],
        ],

        'users' => [
            'label' => 'User Management',
            'description' => 'Accounts, verification and banning.',
            'actions' => ['view', 'create', 'edit', 'delete', 'verify', 'ban'],
        ],

        'equipment' => [
            'label' => 'Equipment Management',
            'description' => 'Equipment catalog and stock levels.',
            'actions' => ['view', 'create', 'edit', 'delete'],
        ],

        'borrowings' => [
            'label' => 'Borrowing Requests',
            'description' => 'Review borrowing requests and confirm or reject equipment pickup.',
            'actions' => ['view', 'process', 'handover'],
        ],

        'returns' => [
            'label' => 'Return Requests',
            'description' => 'Approve return receipts and inspect equipment before confirming returns.',
            'actions' => ['view', 'process', 'handover'],
        ],

        'transactions' => [
            'label' => 'Transaction History',
            'description' => 'Audit trail of processed requests.',
            'actions' => ['view'],
        ],

        'notifications' => [
            'label' => 'Notifications',
            'description' => 'Sending system notifications to users.',
            'actions' => ['view', 'send'],
        ],

        'reports' => [
            'label' => 'Reports',
            'description' => 'Printable summary and audit reports.',
            'actions' => ['view'],
        ],

        'settings' => [
            'label' => 'System Settings',
            'description' => 'Configure overdue fees and other system-wide options.',
            'actions' => ['view', 'update'],
        ],
    ],

    /**
     * Defaults when a user has no stored overrides:
     *  - super admin: everything, always (handled separately in code)
     *  - Administrator: every module/action except the permission editor
     *  - Staff/Faculty: read-only basics so accounts are usable out of the box
     */
    'role_defaults' => [
        'Administrator' => '*',
        'Staff' => [
            'dashboard' => ['view'],
            'equipment' => ['view'],
            'borrowings' => ['view', 'handover'],
            'returns' => ['view', 'handover'],
            'transactions' => ['view'],
        ],
        'Faculty' => [
            'dashboard' => ['view'],
            'equipment' => ['view'],
        ],
        'Student' => [],
    ],
];
