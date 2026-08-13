<?php
return [
    'roles' => [
        'admin' => [
            'dashboard.view', 'operations.view', 'operations.manage', 'operations.verify',
            'ambulance.manage', 'program.manage', 'finance.view', 'finance.manage',
            'payment.manage', 'users.manage', 'system.health',
        ],
        'petugas' => [
            'dashboard.view', 'operations.view', 'operations.manage',
            'finance.view', 'finance.manage', 'payment.manage',
        ],
    ],
];
