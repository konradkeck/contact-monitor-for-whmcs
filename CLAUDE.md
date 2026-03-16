Repo: contact-monitor-for-whmcs (WHMCS addon module)

Rules:
- One small change per iteration.
- Before writing code, show a short plan in max 3 bullets.
- Ask before creating new files outside: whmcs/modules/addons/contact_monitor_whmcs/ and ops/
- No refactors unless requested.
- No formatting-only changes.
- Simple PHP, no extra deps.
- Target WHMCS addon module.

Module name (WHMCS): contact_monitor_whmcs
Service name (API response): contact-monitor-whmcs
Display name: Contact Monitor WHMCS

Structure:
- whmcs/modules/addons/contact_monitor_whmcs/contact_monitor_whmcs.php  — WHMCS addon hooks
- whmcs/modules/addons/contact_monitor_whmcs/api.php                    — REST API endpoint
- whmcs/modules/addons/contact_monitor_whmcs/queries/                   — query classes
- ops/build-release.sh <version>                                        — builds release ZIP
- spec/whmcs_sql/                                                        — reference SQL schemas

API:
- Auth: Authorization: Bearer <token>
- Token stored in WHMCS tbladdonmodules: module='contact_monitor_whmcs', setting='bearer_token'
- setting 'enabled' (yesno) — when off returns 404
- GET api.php              → health check { ok, service, time_utc, status }
- GET api.php?resource=clients|contacts|services|tickets → paginated JSON
- Cursor pagination: after_id (clients/contacts/services), after_sent_at+after_ticket_id (tickets)
