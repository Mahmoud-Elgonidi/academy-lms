# OpenAPI

`openapi.json` describes the API the family and teacher apps use (`/api/v1`). It is generated from the
code by [Scramble](https://scramble.dedoc.co) and committed, so the apps' clients are generated from it
and every change to the API shows up in review.

- Regenerate after changing the API: `composer openapi` in `apps/api`.
- Read it locally: `http://localhost:8000/docs/api` (only in the local environment).
- CI fails when the committed file is out of date, and on a pull request when it breaks the API for
  apps already installed (oasdiff, against the base branch). A breaking change that is meant gets the
  `api-breaking` label and a new version of the apps.
- Generated clients (Dart for the Flutter app, TypeScript for the web) come from this file; they are not
  built yet.

Conventions every endpoint follows (also in the document's description): the academy comes from the
token, else `X-Academy`; times are `{utc, local, timezone, label}` in the viewer's zone (`X-Timezone`);
`Accept-Language` is `ar` or `en`; errors are RFC 7807 problem details; writes that move money or
lessons take an `Idempotency-Key`.
