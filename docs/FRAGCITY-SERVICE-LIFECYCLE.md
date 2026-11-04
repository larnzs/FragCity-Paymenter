> **ARCHIVED — NOT ACTIVE IN THE CURRENT API RELEASE**
>
> This document describes the durable-identity and service-lifecycle bridge that was removed from the active FragCityAPI/FragCityCS2 code during the rollback to `63c742cbeb7fa343df5ddc210abe086421f8e258`. Keep it for historical reference only. Do not execute its lifecycle/admin API procedures or assume those endpoints are registered in the current release.

---

# FragCity Customer Identity & Service Lifecycle

This document describes the FragCity-owned lifecycle layer for Paymenter 1.5.9 and Pterodactyl. It is implemented only in the custom `FragCityAPI` and `FragCityCS2` extensions. Paymenter Core, the official Pterodactyl extension, and Pterodactyl Core remain unmodified.

## Ownership model

- FragCity website authentication is the customer-facing identity source.
- `fragcity_customer_identities` stores one durable UUID per SteamID64/customer.
- Paymenter `users.id` is a mutable platform-local reference, not a durable identity.
- Pterodactyl Client `users.external_id` stores the durable FragCity customer UUID.
- Every Pterodactyl Server `servers.external_id` remains the corresponding Paymenter `services.id`.
- `fragcity_service_servers` stores one mapping per Paymenter service. A customer with multiple services therefore has one Client account and multiple Server mappings.

No foreign keys connect the registry to Paymenter or Pterodactyl tables. This is deliberate: lifecycle tombstones and identity history must survive deletion of a platform-local row.

## Database tables

### `fragcity_customer_identities`

One row per customer/SteamID64. It stores `customer_uuid`, SteamID64, the current nullable Paymenter user ID, the verified Pterodactyl Client user ID, identity status, and verification timestamps.

When a Paymenter user is deleted through Eloquent, the `User::deleted` listener detaches the Paymenter ID and marks the identity `detached`. The UUID, SteamID64 and Pterodactyl Client mapping remain. A later authenticated sync for the same SteamID64 can attach the new Paymenter user ID and reuse the same UUID.

### `fragcity_service_servers`

One row per Paymenter service. It stores Paymenter service/customer references, Pterodactyl Client/Server IDs, server identifier, desired/observed state, lifecycle state, last-seen/reconcile timestamps, deletion timestamp and last error.

### `fragcity_service_lifecycle_events`

Append-only state-transition and reconciliation history. It deliberately has no cascading foreign key to the service row.

## Lifecycle behavior

| Action or observation | FragCity behavior |
| --- | --- |
| Create service | Create/reuse the customer's Pterodactyl Client by UUID; create one Server with the Paymenter service ID as Server external ID; verify owner and external ID; persist mapping. |
| Retry CreateJob | Look up the exact Server external ID first. If it exists and belongs to the expected Client, recover the existing server instead of creating a duplicate. |
| Suspend | Suspend only the server whose external ID matches this Paymenter service. Repeated calls are safe. |
| Unsuspend | Unsuspend the same server. Never create a replacement when the server is missing. |
| Delete one Paymenter service through Eloquent | Terminate only that service's Pterodactyl Server before the service row is deleted. A confirmed Pterodactyl 404 is an idempotent success. Other API failures abort the Eloquent deletion. |
| Delete a Paymenter customer through Eloquent | Terminate each FragCityCS2 service first. If a cleanup fails, the user deletion is aborted. The Pterodactyl Client account and durable FragCity UUID are retained. |
| Manually delete a Server in Pterodactyl | A confirmed 404 becomes `missing_external`. Website management controls are disabled and no automatic replacement is created. |
| Manually delete a Paymenter service using raw SQL/out-of-band DB changes | Eloquent hooks are bypassed. Reconciliation marks the mapping `paymenter_missing` and records an audit event. It does not guess whether the external Server should be deleted; admin review is required. |
| Temporary Pterodactyl API/network failure | Keep the mapping, store the error and retry later. A timeout, 401/403 or 5xx is never interpreted as deletion. |
| Server external ID matches but Client owner is different | Refuse suspend, unsuspend and delete. The operation proceeds only when the server owner equals the Pterodactyl Client ID verified for the durable FragCity customer UUID. |

### Important deletion policy

Deleting a FragCityCS2 Paymenter service always cleans up its paired Pterodactyl Server, even if the legacy admin delete action's optional `delete_server` checkbox is off. The extension-owned `Service::deleting` hook enforces a single consistent policy and prevents an accidental orphan. This does not delete the customer's Pterodactyl Client account or any of that customer's other Servers.

The user-delete listener performs the same cleanup for every FragCityCS2 service owned by the customer before the Paymenter user row can be removed.

Direct SQL deletes bypass Eloquent model events by definition. Do not use raw SQL for normal service deletion. The reconciliation process only marks these records for review; it intentionally does not delete Pterodactyl resources automatically.

## State semantics

- `provisioning`: Paymenter service exists and provisioning is not yet verified.
- `ready`: the server mapping was verified. Actual active/suspended intent and observation are stored separately in `desired_state` and `observed_state`.
- `suspending` / `unsuspending`: lifecycle operation is in progress.
- `suspended`: the suspend operation completed; subsequent verified reads may normalize lifecycle state to `ready` while retaining desired/observed suspended values.
- `missing_external`: Pterodactyl explicitly returned 404 for this service's Server.
- `deleting` / `deleted`: deletion is in progress / confirmed.
- `paymenter_missing`: the Paymenter service row is missing outside the supported Eloquent deletion path; admin review is required.
- `review_required`: the current Paymenter service is no longer associated with the FragCityCS2 extension; do not mutate the external server automatically.

Only a confirmed 404 for a previously verified Server (a stored Pterodactyl Server ID or `last_seen_at`) marks it `missing_external`. A first-time service with no evidence of prior provisioning remains `provisioning`, allowing a failed first CreateJob to be retried. For services that existed before this registry was deployed, the first successful My Servers read seeds the mapping. If a legacy Server was already manually deleted before its first mapping could be seeded, no system can prove its prior existence from the new registry alone; review those legacy services during rollout before retrying old jobs. API errors and failed authentication are operational errors, not missing resources.

## Reconciliation

The extension registers a Laravel scheduled callback named `fragcity-service-lifecycle-reconcile`. It runs every five minutes, processes at most 10 due mappings, and uses `next_reconcile_at` to spread larger installations across executions. The host must also run Laravel's standard `php artisan schedule:run` every minute; `schedule:list` proves registration only, not that the host scheduler is executing.

For each due mapping:

1. If the Paymenter service is gone, mark `paymenter_missing`, write an audit event and stop automatic action.
2. If the Paymenter service still exists, call the FragCityCS2 verified server-summary path.
3. A Pterodactyl 404 becomes `missing_external`.
4. Transient failures update `last_error` and schedule a retry in one minute.

The website's My Servers request also verifies its own services on demand, so the customer sees current state even between scheduled runs.

A service in `missing_external` is deliberately excluded from automatic retries. If an administrator decides that the same Paymenter service should receive a replacement Server, review backups/data-loss implications and use the FragCity Website **Admin → Lifecycle** dashboard:

1. Open the exact service record and confirm the customer UUID, Paymenter service ID, Pterodactyl Client ID and previous Server mapping.
2. Confirm the service is still active or suspended and the lifecycle state is `missing_external`.
3. Submit **Authorize recreation** with a meaningful reason. The Bridge creates a unique, one-shot authorization ID and records the administrator, reason and timestamp.
4. Retry only that exact Paymenter service's CreateJob. The extension atomically claims the grant with a two-hour lease, verifies the Client identity, creates the replacement Server and consumes the grant only after verifying the new Server.
5. If the retry should not proceed, revoke the unused grant from the same dashboard. An active claim cannot be revoked; wait for the lease to expire or for the running job to release it.

Never authorize recreation by changing `desired_state` or editing lifecycle tables directly. A service already marked `deleted` cannot be reused; create a new Paymenter service instead.

The standard Laravel scheduler must be active on the host. Verify with:

```bash
cd /var/www/paymenter
php artisan schedule:list | grep -i fragcity
```

The host must run Laravel's standard `php artisan schedule:run` every minute. If scheduling is not configured, on-demand My Servers reconciliation still runs, but periodic checks will not.

## Deployment and migration

1. Back up the Paymenter database and Pterodactyl database.
2. Deploy the `larnzs/FragCity-Paymenter` `main` branch, including both extensions and all pending additive FragCityAPI migrations.
3. Upgrade the FragCityAPI extension from the Paymenter extension manager. Its `upgraded()` hook runs the extension migrations and verifies that every migration file is recorded; an incomplete migration now fails the extension-upgrade command instead of silently reporting success. Keep FragCityAPI enabled so its model listeners and scheduler are registered.
4. Verify the new tables:

```bash
cd /var/www/paymenter

php artisan tinker --execute='
dump([
    "customer_identities" => \Illuminate\Support\Facades\Schema::hasTable("fragcity_customer_identities"),
    "service_servers" => \Illuminate\Support\Facades\Schema::hasTable("fragcity_service_servers"),
    "lifecycle_events" => \Illuminate\Support\Facades\Schema::hasTable("fragcity_service_lifecycle_events"),
]);
'
```

5. Deploy the website repository `main` branch. The website's account-sync client expects the bridge to return `customer_uuid`; deploy the Paymenter API first.
6. Have each customer complete the normal authenticated website account-sync flow. The bridge creates/reuses the durable UUID.
7. Review legacy Pterodactyl Client accounts through the FragCity Website **Admin → Lifecycle** dashboard. For a service that Reconciliation marks `review_required` with `missing_client`, `client_identity_conflict` or `identity_mapping_conflict`, select the existing Pterodactyl Client ID and use **Verify Client Identity** with a mandatory reason. The Bridge verifies that the Client exists, its email exactly matches the Paymenter customer, its UUID is not assigned to another customer, and no live prior Client mapping conflicts. It changes only an empty or legacy numeric Client `external_id` to the durable customer UUID, updates the identity/service mappings and writes an audit event. Then run Reconcile again to verify the exact Server owner and state.

Do not directly update `fragcity_customer_identities` or the Pterodactyl `users.external_id` column with SQL. A different UUID, non-numeric external ID, mismatched email or conflicting live Client is a hard conflict and requires a separate ownership investigation. My Servers and Console/File Manager reads remain read-only and never perform legacy identity repair.

8. Retry only the specific failed service job after its Client mapping is verified.

Do not manually change Pterodactyl Server external IDs. They remain Paymenter service IDs.

## Acceptance test matrix

Run these in staging or with disposable test services before production use:

- One customer provisions two services: one Pterodactyl Client, two distinct Pterodactyl Servers, two service mappings.
- Retry the same CreateJob: no duplicate Server is created.
- Suspend, then repeat Suspend: the same Server remains suspended.
- Unsuspend: the same Server becomes active.
- Manually delete the Pterodactyl Server: My Servers reports missing; Console/File access is disabled; no automatic recreation.
- Return a temporary 500/timeout from Pterodactyl: service is unavailable/retryable, not missing or deleted.
- Delete a Paymenter service through its normal admin action: only its corresponding Pterodactyl Server is deleted; other customer servers and Client account remain.
- Make Pterodactyl API deletion fail: Paymenter service deletion is blocked.
- Change a test Server's owner while keeping the same service external ID: Suspend, Unsuspend and Terminate must fail closed and must not mutate the other customer's Server.
- Delete a Paymenter user through Eloquent: every owned FragCityCS2 Server is cleaned up; UUID and Pterodactyl Client remain; re-sync the same SteamID64 and verify the same UUID is reused.
- Remove a Paymenter service out-of-band in a disposable database: scheduled reconciliation marks `paymenter_missing` and does not delete the Pterodactyl Server.

## Operational notes

- Cache must support atomic locks for `Cache::lock`; Redis is recommended for a multi-worker deployment.
- API access uses the configured Pterodactyl Application API credentials already owned by the extension. No Application API key is sent to the browser.
- Keep both FragCity extensions enabled and deploy their matching release versions.
- Never remove a customer identity or service tombstone as routine cleanup.
