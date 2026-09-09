<?php
return [
    'roles' => [
        'kota' => [
            'dashboard.view', 'operations.view', 'operations.manage', 'operations.verify',
            'reports.input', 'reports.city',
            'ambulance.manage', 'program.manage', 'finance.view', 'finance.manage',
            'infaq.manage', 'users.manage', 'regions.local.manage', 'system.health',
        ],
        'kecamatan' => [
            'dashboard.view', 'operations.view', 'reports.input', 'reports.validate',
            'regions.local.manage', 'finance.view', 'finance.manage', 'infaq.manage',
        ],
        'kelurahan' => [
            'dashboard.view', 'operations.view', 'reports.input', 'reports.forward', 'regions.local.manage', 'finance.view', 'finance.manage', 'infaq.manage',
        ],
        'bendahara' => [
            'dashboard.view', 'finance.view', 'finance.verify', 'finance.reject', 'finance.export', 'infaq.view',
        ],
    ],
];
