# OpenAPI

`openapi.json` describes the API the family and teacher apps use (`/api/v1`). It is generated from the
code by [Scramble](https://scramble.dedoc.co) and committed, so the apps are checked against it and
every change to the API shows up in review.

- Regenerate after changing the API: `composer openapi` in `apps/api`.
- Read it locally: `http://localhost:8000/docs/api` (only in the local environment).
- CI fails when the committed file is out of date, and on a pull request when it breaks the API for
  apps already installed (oasdiff, against the base branch). A breaking change that is meant gets the
  `api-breaking` label and a new version of the apps.
- The Flutter app (`apps/mobile`) calls the API through hand-written repositories; its contract test
  reads every call in the app and fails when this file has no such method and path. The mobile CI runs
  when this file changes too. A generated TypeScript client for the web comes from this file later.

Conventions every endpoint follows (also in the document's description): the academy comes from the
token, else `X-Academy`; times are `{utc, local, timezone, label}` in the viewer's zone (`X-Timezone`);
`Accept-Language` is `ar` or `en`; errors are RFC 7807 problem details; writes that move money or
lessons take an `Idempotency-Key`.
