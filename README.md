# Nawasara Vault

Encrypted credential management for the Nawasara superapp framework. Every service package (Cloudflare, WHM, Keycloak, SMTP, etc.) reads its credentials from this single place at runtime, rotates without redeploy, and gets a complete access log for audit.

## Features

- **Service groups** — declarative group-of-fields registration via `config/nawasara-vault.php`. Each service contributes its own group with the fields it needs (host, token, password, etc.)
- **Multi-instance** — flag `multi_instance => true` on a group to manage many credential sets per service (e.g. multiple WHM servers, multiple Cloudflare accounts)
- **Optional fields** — `optional => true` on a field exempts it from the "complete" check so the group can still be marked configured without it
- **Field types** — `text`, `password`, `select`, `textarea` (multi-line for PEM keys, etc.)
- **Encryption at rest** — values stored encrypted via Laravel's built-in encrypter, decrypted on read
- **Access log** — every read / write / delete of a credential is recorded with user, action, and timestamp
- **One-click test connection** — declare `'test' => Service@method` on a group; the credential dropdown shows a Test Connection button that calls your handler and surfaces the result as a toast

## Installation

```bash
composer require nawasara/vault
php artisan migrate
php artisan db:seed --class="Nawasara\Vault\Database\Seeders\PermissionSeeder" --force
```

Auto-discovered. The `Vault` facade is registered as an alias.

## Declaring a service group

```php
// config/nawasara-vault.php
'groups' => [
    'cloudflare' => [
        'label' => 'Cloudflare',
        'icon' => 'lucide-cloud',
        'test' => \Nawasara\Cloudflare\Services\CloudflareClient::class.'@testConnection',
        'fields' => [
            'api_token'  => ['label' => 'API Token', 'type' => 'password'],
            'account_id' => ['label' => 'Account ID', 'type' => 'text'],
        ],
    ],

    'whm' => [
        'label' => 'WHM / cPanel',
        'icon' => 'lucide-hard-drive',
        'multi_instance' => true,
        'test' => \Nawasara\Whm\Services\WhmClient::class.'@testConnection',
        'fields' => [
            'host'      => ['label' => 'Host', 'type' => 'text'],
            'username'  => ['label' => 'Username', 'type' => 'text'],
            'api_token' => ['label' => 'API Token', 'type' => 'password'],
            'role'      => ['label' => 'Role', 'type' => 'select', 'options' => [
                'hosting' => 'Hosting', 'mail' => 'Mail', 'both' => 'Both',
            ]],
            'ssh_key'   => ['label' => 'SSH Key (PEM)', 'type' => 'textarea', 'optional' => true],
        ],
    ],
],
```

## Reading credentials at runtime

```php
use Nawasara\Vault\Facades\Vault;

// Single instance
$token = Vault::get('cloudflare', 'api_token');

// Multi-instance
$host = Vault::get('whm', 'host', 'WHM-Ryder');

// Boolean checks
Vault::has('cloudflare', 'api_token');
Vault::isConfigured('cloudflare');                  // every required field has a value
Vault::isConfigured('whm', 'WHM-Ryder');

// List instances of a multi-instance group
Vault::instances('whm');                            // ['WHM-Ryder', 'WHM-30', …]

// Programmatic write (rare — usually done through the UI)
Vault::set('cloudflare', 'api_token', $secret);
Vault::delete('cloudflare', 'api_token');
```

## Test connection contract

A group's `test` handler receives `?string $instance = null` and should return:

```php
public function testConnection(?string $instance = null): array
{
    return [
        'success' => true,
        'message' => 'Connected. Listed 12 items.',
    ];
}
```

The credential UI calls the handler and shows the message as a green/red toast.

## Pages

| Route | Permission |
|-------|-----------|
| `/nawasara-vault/credentials` | `vault.credential.view` |
| `/nawasara-vault/access-logs` | `vault.access-log.view` |

## Permissions

| Permission | Description |
|---|---|
| `vault.credential.view` | View credential list and individual fields |
| `vault.credential.manage` | Create / edit / delete credentials and instances |
| `vault.credential.reveal` | Reveal a masked credential value |
| `vault.access-log.view` | View the access log |

## Author

**Pringgo J. Saputro** &lt;odyinggo@gmail.com&gt;

## License

MIT
