# Contact Monitor for WHMCS

WHMCS addon module that exposes a read-only REST API used by the Contact Monitor synchronizer to pull clients, contacts, services, and ticket messages.

---

## Requirements

- WHMCS 8.x or newer
- PHP 7.4+

---

## Installation

### Option A — from release ZIP

1. Download the latest ZIP from the [Releases](../../releases) page, e.g. `contact-monitor-for-whmcs-1.0.0.zip`
2. Extract it — you will get a folder named `contact_monitor_for_whmcs/`
3. Upload the folder to your WHMCS server:
   ```
   <WHMCS_ROOT>/modules/addons/contact_monitor_for_whmcs/
   ```

### Option B — from source

1. Clone this repo
2. Copy the module folder manually:
   ```bash
   cp -r whmcs/modules/addons/contact_monitor_for_whmcs/ <WHMCS_ROOT>/modules/addons/
   ```

### Verify file structure

After installation the following files must exist under `<WHMCS_ROOT>/modules/addons/`:

```
contact_monitor_for_whmcs/
├── contact_monitor_for_whmcs.php
├── api.php
└── queries/
    ├── ClientsQuery.php
    ├── ContactsQuery.php
    ├── ServicesQuery.php
    └── TicketsQuery.php
```

---

## Configuration in WHMCS

1. Log into WHMCS Admin
2. Go to **Setup → Addon Modules**
3. Find **Contact Monitor for WHMCS** and click **Activate**
4. Click **Configure** and fill in:

| Field | Description |
|-------|-------------|
| **Enabled** | Check to enable the API endpoint. When unchecked, all requests return `404`. |
| **Bearer Token** | Secret token used to authenticate API requests. Generate a random string (min 32 chars recommended). **Keep this private.** |

5. Click **Save Changes**

---

## API Usage

Base URL:
```
https://<your-whmcs-domain>/modules/addons/contact_monitor_for_whmcs/api.php
```

All requests require the header:
```
Authorization: Bearer <your_bearer_token>
```

### Health check

```bash
curl -H "Authorization: Bearer <token>" \
  https://example.com/modules/addons/contact_monitor_for_whmcs/api.php
```

Response:
```json
{
  "ok": true,
  "service": "contact-monitor-for-whmcs",
  "time_utc": "2025-01-01T12:00:00Z",
  "status": "healthy"
}
```

### Available resources

| Resource | Description |
|----------|-------------|
| `clients` | WHMCS clients with total and 3-year revenue (normalized to USD) |
| `contacts` | Sub-contacts linked to clients |
| `services` | Hosting products and addons with status, revenue, renewal count |
| `tickets` | Ticket messages from the last 3 years (opening messages + replies) |

### Fetching data

```bash
curl -H "Authorization: Bearer <token>" \
  "https://example.com/modules/addons/contact_monitor_for_whmcs/api.php?resource=clients&limit=500"
```

Response:
```json
{
  "ok": true,
  "resource": "clients",
  "count": 500,
  "next_cursor": {"after_id": 1234},
  "data": [
    {
      "clientid": 1,
      "firstname": "John",
      "lastname": "Smith",
      "companyname": "Acme Ltd",
      "email": "john@example.com",
      "total_revenue": "4820.50",
      "yr3_revenue": "1240.00",
      "currency": "USD"
    },
    ...
  ]
}
```

### Pagination

When `next_cursor` is not `null`, pass its values as query params to fetch the next page.

**clients / contacts / services:**
```bash
# First page
?resource=clients&limit=500

# Next pages (use next_cursor from previous response)
?resource=clients&limit=500&after_id=1234
```

**tickets** (dual cursor — handles same-timestamp ties):
```bash
# First page
?resource=tickets&limit=200

# Next pages
?resource=tickets&limit=200&after_sent_at=2024-06-15T08:30:00&after_ticket_id=5678
```

Keep fetching until `next_cursor` is `null`.

### Ticket message length

By default messages are truncated at 4000 characters. Use `max_message_chars` to adjust (max 12000):

```bash
?resource=tickets&max_message_chars=8000
```

Each ticket row includes:
- `message_truncated: true/false`
- `message_length_original: <int>` — original length before truncation

### Limits

| Resource | Default | Max |
|----------|---------|-----|
| clients | 100 | 500 |
| contacts | 100 | 500 |
| services | 100 | 500 |
| tickets | 100 | 200 |

### Error responses

| HTTP | Body | Reason |
|------|------|--------|
| 404 | `{"error":"Not found"}` | Module disabled |
| 401 | `{"error":"Unauthorized"}` | Missing or wrong Bearer token |
| 400 | `{"ok":false,"error":"bad_request"}` | Unknown resource name |
| 500 | `{"ok":false,"error":"server_error"}` | Database query failed |

---

## Building a release ZIP

Requires `zip` to be installed.

```bash
./ops/build-release.sh 1.0.0
# → releases/contact-monitor-for-whmcs-1.0.0.zip
```

The ZIP contains only the `contact_monitor_for_whmcs/` folder and is ready to distribute.
