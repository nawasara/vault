<?php

$prefix = 'nawasara-vault';

return [
    [
        'label' => 'Vault',
        'icon' => 'lucide-lock',
        'group' => 'Keamanan',
        'url' => '',
        'permission' => 'vault.credential.view',
        'submenu' => [
            [
                'label' => 'Credentials',
                'icon' => 'lucide-key-round',
                'url' => url($prefix.'/credentials'),
                'permission' => 'vault.credential.view',
                'navigate' => true,
            ],
            [
                'label' => 'Access Log',
                'icon' => 'lucide-file-text',
                'url' => url($prefix.'/access-log'),
                'permission' => 'vault.log.view',
                'navigate' => true,
            ],
        ],
    ],
];
