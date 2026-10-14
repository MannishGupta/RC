# Core engine (`/core`)

Canonical location for compliance, i18n, and product subsystems.

| Path | Role |
|------|------|
| `Compliance/` | DPDP notices + consent log |
| `I18n/` | Eighth Schedule language registry |
| `WhatsNew/` | Uncapped changelog store |

`app/Compliance` and `app/WhatsNew` are **thin shims** so existing `require` paths keep working. Prefer requiring `core/...` in new code.

Tenant runtime data remains only under `tenants/{id}/data/` — never inside `core/`.
