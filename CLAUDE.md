# contact-monitor-for-whmcs

WHMCS addon module that exposes a read-only REST API for Contact Monitor synchronization.

---

## Rules

- One small change per iteration.
- Before writing code, show a short plan in max 3 bullets.
- Ask before creating new files outside: `whmcs/modules/addons/contact_monitor_for_whmcs/` and `ops/`
- No refactors unless requested.
- No formatting-only changes.
- Simple PHP, no extra deps.

---

## Naming

| Context | Value |
|---------|-------|
| WHMCS module key | `contact_monitor_for_whmcs` |
| Module directory | `whmcs/modules/addons/contact_monitor_for_whmcs/` |
| Main file | `contact_monitor_for_whmcs.php` |
| WHMCS function prefix | `contact_monitor_for_whmcs_` |
| Display name | `Contact Monitor for WHMCS` |
| API service name (JSON) | `contact-monitor-for-whmcs` |
| Release ZIP prefix | `contact-monitor-for-whmcs` |

---

## File Structure

```
whmcs/modules/addons/contact_monitor_for_whmcs/
├── contact_monitor_for_whmcs.php   WHMCS addon hooks (config/activate/deactivate/output)
├── api.php                         REST API entry point
├── README.txt                      short install note
└── queries/
    ├── ClientsQuery.php            tblclients — id, name, email, company, revenue
    ├── ContactsQuery.php           tblcontacts — sub-contacts linked to clients
    ├── ServicesQuery.php           tblhosting + tblhostingaddons — services + addons
    └── TicketsQuery.php            tbltickets + tblticketreplies — messages (3yr history)

ops/
└── build-release.sh <version>      packages module folder into releases/contact-monitor-for-whmcs-{version}.zip

spec/whmcs_sql/
├── clients.sql                     reference WHMCS schema for clients
├── contacts.sql                    reference schema for contacts
├── services.sql                    reference schema for services
└── tickets.sql                     reference schema for tickets

releases/                           output of build-release.sh (git-ignored)
```

---

## WHMCS Module Hooks

`contact_monitor_for_whmcs.php` implements the four required WHMCS addon functions:

| Function | Purpose |
|----------|---------|
| `contact_monitor_for_whmcs_config()` | Returns module metadata + two settings: `enabled` (yesno), `bearer_token` (text 64) |
| `contact_monitor_for_whmcs_activate()` | Called on module activation — no DB changes needed |
| `contact_monitor_for_whmcs_deactivate()` | Called on deactivation |
| `contact_monitor_for_whmcs_output($vars)` | Admin UI — shows enabled state + API URL |

Settings are stored in `tbladdonmodules` with `module = 'contact_monitor_for_whmcs'`.

---

## API (`api.php`)

### Auth

Every request must include:
```
Authorization: Bearer <bearer_token>
```

Token is read from `tbladdonmodules` at runtime. Uses `hash_equals()` — constant-time compare.
If `enabled != 'on'` → 404. Wrong/missing token → 401.

### Endpoints

| Request | Response |
|---------|----------|
| `GET api.php` | Health check `{ok, service, time_utc, status}` |
| `GET api.php?resource=clients` | Paginated clients |
| `GET api.php?resource=contacts` | Paginated contacts |
| `GET api.php?resource=services` | Paginated services + addons |
| `GET api.php?resource=tickets` | Paginated ticket messages |

### Pagination

**clients / contacts / services** — cursor via `after_id`:
```
GET api.php?resource=clients&limit=500&after_id=1234
```
Response includes `next_cursor: {after_id: N}` or `null`.

**tickets** — dual cursor (sent_at + ticket_id to handle ties):
```
GET api.php?resource=tickets&limit=200&after_sent_at=2024-01-01T00:00:00Z&after_ticket_id=0
```
Response includes `next_cursor: {after_sent_at, after_ticket_id}` or `null`.

Limits: clients/contacts/services max 500, tickets max 200. Default 100.

### Tickets: message truncation

Optional param `max_message_chars` (default 4000, max 12000).
Response adds `message_truncated: bool` and `message_length_original: int` per row.

### Response envelope

```json
{
  "ok": true,
  "resource": "clients",
  "count": 100,
  "next_cursor": {"after_id": 1234},
  "data": [...]
}
```

---

## Query Classes

All query classes are static, receive `$params` array, return `array of arrays`.
Loaded on demand via `require_once` in `api.php`.

| Class | Table(s) | Key fields |
|-------|----------|-----------|
| `ClientsQuery` | `tblclients`, `tblaccounts`, `tblcurrencies` | clientid, firstname, lastname, companyname, email, total_revenue (USD), yr3_revenue (USD) |
| `ContactsQuery` | `tblcontacts` | contactid, clientid, firstname, lastname, companyname, email |
| `ServicesQuery` | `tblhosting`, `tblhostingaddons`, `tblinvoiceitems`, `tblcancelrequests` | clientid, serviceid, productid, product_name, type (product/addon), service_status, start_date, termination_date, total_revenue, renewal_count, cancel info |
| `TicketsQuery` | `tbltickets`, `tblticketreplies`, `tblticketdepartments`, `tblclients`, `tbladmins` | ticket_id, msg_id, sent_at, department_name, status, title, sender_type (admin/client), direction (to_client/from_client), sender_name, sender_email, message |

Tickets query covers **last 3 years** only (`DATE_SUB(CURDATE(), INTERVAL 3 YEAR)`).
Revenue in `ClientsQuery` is normalized to **USD** via exchange rate from `tblcurrencies`.

---

## Build Release

```bash
./ops/build-release.sh 1.0.0
# → releases/contact-monitor-for-whmcs-1.0.0.zip
```

ZIP contains only the `contact_monitor_for_whmcs/` folder, ready to drop into WHMCS.
