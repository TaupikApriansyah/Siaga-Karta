<?php
return [
    'roles' => [
        'kota' => [
            'dashboard.view', 'operations.view', 'operations.manage', 'operations.verify',
            'reports.input', 'reports.city',
            'ambulance.manage', 'program.manage', 'finance.view', 'finance.manage',
            'payment.manage', 'users.manage', 'regions.local.manage', 'system.health',
        ],
        'kecamatan' => [
            'dashboard.view', 'operations.view', 'reports.input', 'reports.validate',
            'regions.local.manage',
        ],
        'kelurahan' => [
            'dashboard.view', 'operations.view', 'reports.input', 'reports.forward', 'regions.local.manage',
        ],
    ],
];
