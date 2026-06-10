<!-- file generated with AI assistance: Claude Code - 2026-05-07 -->

# php-bc4-client

Modern PHP 8.4 client for the Basecamp 4 API (which is hosted at `https://3.basecampapi.com/{account_id}/...` and follows the BC3 API specification).

Built on **Symfony HttpClient**, with a small surface area focused on what real applications need: Projects, Todolists, Todos, People, plus a complete OAuth 2.0 flow including transparent refresh-token rotation.

## Features

- **OAuth 2.0** web-server flow with Launchpad (`launchpad.37signals.com`)
- **Refresh-token rotation**: tokens are rotated on every refresh and persisted via a pluggable `TokenStorageInterface` — your application picks the storage backend (Doctrine, Redis, file, in-memory)
- **Transparent token lifecycle**: proactive refresh when `expires_at` is near, reactive refresh on `401`, automatic single retry of the original request
- **Rate-limit aware**: exponential backoff on `429`, configurable retry count
- **`invalid_grant` detection**: throws `InvalidGrantException` when the refresh token is rejected, signalling that a fresh user-authorization is required
- **Pagination** via `Link: <…>; rel="next"` headers, transparent for callers
- **Resource-oriented API**: `$client->projects()->all()`, `$client->todos()->getInProject($id, $todolistId)` etc.

## Installation

```sh
composer require dmstr/php-bc4-client
```

## Minimum Usage

```php
use Dmstr\Bc4Client\Authentication\OAuth2Authentication;
use Dmstr\Bc4Client\Client\Bc4Client;

$auth = new OAuth2Authentication(
    clientId: 'YOUR_CLIENT_ID',
    clientSecret: 'YOUR_CLIENT_SECRET',
    appName: 'My App',
    appContact: 'ops@example.com',
    storage: $myTokenStorage,    // implements TokenStorageInterface
);

$client = new Bc4Client(accountId: '6164391', authentication: $auth);

foreach ($client->projects()->all() as $project) {
    echo $project['name'], "\n";
}
```

## Architecture

```
Authentication/
  AuthenticationInterface         — minimal request-decoration contract
  OAuth2Authentication            — Bearer token, refresh lifecycle
  TokenStorageInterface           — your app implements this
  AuthorizationFlow               — initial code → tokens, account discovery
Client/
  Bc4Client                       — entrypoint, hands out resources
Exception/
  Bc4ApiException                 — base exception
  InvalidGrantException           — refresh token rejected
  RequestException                — wrapped HTTP error
Resource/
  AbstractResource                — pagination + request helper
  ProjectsResource
  TodoSetsResource
  TodolistsResource
  TodosResource
  PeopleResource
```

## Inspired by

- [`schmunk42/php-bcx-client`](https://github.com/schmunk42/php-bcx-client) — the BC2 (BCX-API) sibling

## License

MIT
