# MFA login challenge (phase 1)

Set `MFA_LOGIN_CHALLENGE_ENABLED=true` to require a second factor for every enrolled user. The password POST does not authenticate the user; it redirects to `GET /login/mfa`.

The phase 2 UI may submit `code` to either `POST /login/mfa/totp` or `POST /login/mfa/recovery`, or `POST /login/mfa/cancel`. Successful requests redirect to the original intended URL (and retain the login `remember` choice). Failed requests return normal Laravel validation errors (422 for JSON clients). The show endpoint returns `{expires_in}` for JSON clients and otherwise renders `Auth/MfaChallenge`.

Recovery codes are only returned by the enrollment and explicit password-confirmed regeneration endpoints. Recovery-code retrieval is not a GET operation.
