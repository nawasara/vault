<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Service Groups
    |--------------------------------------------------------------------------
    |
    | Definisi service yang credential-nya dikelola oleh Vault.
    | Setiap group punya label, icon, dan daftar field.
    | Set 'multi_instance' => true jika service punya banyak instance
    | (misal: 5 router MikroTik = 5 set credential berbeda).
    |
    */

    'groups' => [
        'keycloak' => [
            'label' => 'Keycloak SSO',
            'icon' => 'lucide-key-round',
            'test' => \Nawasara\Keycloak\Services\KeycloakClient::class.'@testConnection',
            'fields' => [
                'base_url' => ['label' => 'Base URL', 'type' => 'text', 'placeholder' => 'https://sso.kominfo.go.id'],
                'realm' => ['label' => 'Realm', 'type' => 'text', 'placeholder' => 'master'],
                'client_id' => ['label' => 'Client ID', 'type' => 'text'],
                'client_secret' => ['label' => 'Client Secret', 'type' => 'password'],
            ],
        ],

        'cloudflare' => [
            'label' => 'Cloudflare',
            'icon' => 'lucide-cloud',
            'test' => \Nawasara\Cloudflare\Services\CloudflareClient::class.'@testConnection',
            'fields' => [
                'api_token' => ['label' => 'API Token', 'type' => 'password'],
                'account_id' => ['label' => 'Account ID', 'type' => 'text'],
            ],
        ],

        'proxmox' => [
            'label' => 'Proxmox VE',
            'icon' => 'lucide-server',
            'test' => \Nawasara\Proxmox\Services\ProxmoxClient::class.'@testConnection',
            'fields' => [
                'host' => ['label' => 'Host', 'type' => 'text', 'placeholder' => 'https://pve.kominfo.go.id:8006'],
                'token_id' => ['label' => 'Token ID', 'type' => 'text', 'placeholder' => 'user@pve!token-name'],
                'token_secret' => ['label' => 'Token Secret', 'type' => 'password'],
                'verify_ssl' => ['label' => 'Verify SSL', 'type' => 'select', 'options' => ['true' => 'Ya', 'false' => 'Tidak']],
            ],
        ],

        'whm' => [
            'label' => 'WHM / cPanel',
            'icon' => 'lucide-hard-drive',
            'multi_instance' => true,
            'test' => \Nawasara\Whm\Services\WhmClient::class.'@testConnection',
            'fields' => [
                'host' => ['label' => 'Host', 'type' => 'text', 'placeholder' => 'https://whm.kominfo.go.id:2087'],
                'username' => ['label' => 'Username', 'type' => 'text'],
                'api_token' => ['label' => 'API Token', 'type' => 'password'],
                'role' => ['label' => 'Server Role', 'type' => 'select', 'options' => [
                    'hosting' => 'Hosting (cPanel websites)',
                    'mail' => 'Mail (Email server)',
                    'both' => 'Both (multi-purpose)',
                ]],
                'ssh_host' => ['label' => 'SSH Host (opsional)', 'type' => 'text', 'placeholder' => 'kosongkan untuk pakai host WHM', 'optional' => true],
                'ssh_port' => ['label' => 'SSH Port', 'type' => 'text', 'placeholder' => '22', 'optional' => true],
                'ssh_user' => ['label' => 'SSH User', 'type' => 'text', 'placeholder' => 'root', 'optional' => true],
                'ssh_key' => ['label' => 'SSH Private Key (PEM)', 'type' => 'textarea', 'rows' => 8, 'placeholder' => "-----BEGIN OPENSSH PRIVATE KEY-----\n...\n-----END OPENSSH PRIVATE KEY-----", 'optional' => true],
            ],
        ],

        'mikrotik' => [
            'label' => 'MikroTik Router',
            'icon' => 'lucide-router',
            'multi_instance' => true,
            'fields' => [
                'host' => ['label' => 'Host / IP', 'type' => 'text', 'placeholder' => '192.168.1.1'],
                'port' => ['label' => 'Port', 'type' => 'text', 'placeholder' => '8728'],
                'username' => ['label' => 'Username', 'type' => 'text'],
                'password' => ['label' => 'Password', 'type' => 'password'],
            ],
        ],

        'uptime-kuma' => [
            'label' => 'Uptime Kuma',
            'icon' => 'lucide-activity',
            'fields' => [
                'url' => ['label' => 'URL', 'type' => 'text', 'placeholder' => 'https://status.kominfo.go.id'],
                'api_key' => ['label' => 'API Key', 'type' => 'password'],
            ],
        ],

        'smtp' => [
            'label' => 'SMTP Email',
            'icon' => 'lucide-mail',
            'test' => \Nawasara\Notification\Channels\EmailChannel::class.'@testFromVault',
            'fields' => [
                'host' => ['label' => 'SMTP Host', 'type' => 'text', 'placeholder' => 'smtp.gmail.com'],
                'port' => ['label' => 'SMTP Port', 'type' => 'text', 'placeholder' => '587'],
                'encryption' => ['label' => 'Encryption', 'type' => 'select', 'options' => [
                    'tls' => 'TLS (587)',
                    'ssl' => 'SSL (465)',
                    'none' => 'None (25)',
                ]],
                'username' => ['label' => 'Username', 'type' => 'text'],
                'password' => ['label' => 'Password', 'type' => 'password'],
                'from_address' => ['label' => 'From Address', 'type' => 'text', 'placeholder' => 'noreply@kominfo.go.id'],
                'from_name' => ['label' => 'From Name', 'type' => 'text', 'placeholder' => 'Nawasara Kominfo Ponorogo', 'optional' => true],
            ],
        ],

        'wazuh' => [
            'label' => 'Wazuh SIEM',
            'icon' => 'lucide-shield',
            'fields' => [
                'api_url' => ['label' => 'API URL', 'type' => 'text', 'placeholder' => 'https://wazuh.kominfo.go.id:55000'],
                'username' => ['label' => 'Username', 'type' => 'text'],
                'password' => ['label' => 'Password', 'type' => 'password'],
            ],
        ],

        'zoom' => [
            'label' => 'Zoom',
            'icon' => 'lucide-video',
            'fields' => [
                'account_id' => ['label' => 'Account ID', 'type' => 'text'],
                'client_id' => ['label' => 'Client ID', 'type' => 'text'],
                'client_secret' => ['label' => 'Client Secret', 'type' => 'password'],
            ],
        ],

        'whatsapp' => [
            'label' => 'WhatsApp Forwarder',
            'icon' => 'lucide-message-circle',
            'fields' => [
                'api_base' => ['label' => 'API Base URL', 'type' => 'text'],
                'api_key' => ['label' => 'API Key', 'type' => 'password'],
            ],
        ],

        'mail' => [
            'label' => 'Mail Server',
            'icon' => 'lucide-mail',
            'multi_instance' => true,
            'fields' => [
                'host' => ['label' => 'Host', 'type' => 'text'],
                'port' => ['label' => 'Port', 'type' => 'text', 'placeholder' => '993'],
                'username' => ['label' => 'Username', 'type' => 'text'],
                'password' => ['label' => 'Password', 'type' => 'password'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Access Log
    |--------------------------------------------------------------------------
    |
    | log_reads: Catat setiap kali credential dibaca oleh sistem.
    |            Set false jika terlalu noisy (polling setiap 5 menit = banyak log).
    | retention_days: Hapus access log lebih dari N hari.
    |
    */

    'log_reads' => true,
    'retention_days' => 90,
];
