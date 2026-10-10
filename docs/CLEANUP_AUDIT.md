# Cleanup audit — 20261009.19

## Done

### 1. Bootstrap unified
- All code loads `app/bootstrap.php` only.
- Root `bootstrap.php` **deleted**.
- `api_value.php` no longer falls back to root bootstrap.

### 2. Deny non-public paths
| Path | IIS (`web.config` hiddenSegments) | Apache (`.htaccess`) |
|------|-----------------------------------|----------------------|
| `/sql/` | yes | yes |
| `/tests/` | yes | yes |
| `/docs/` | yes | yes |
| `/data/`, `/app/`, `/tenants/`, `/storage/` | already | already |

### 3. BUG FIX comments
- Multi-line `// BUG FIX` narratives collapsed to the first line.
- Multi-line HTML `<!-- BUG FIX -->` replaced with short markers.

## Still intentional
- Root tool aliases (`print_directory.php`, etc.) for IIS.
- `tests/svg_sanitizer_test.php` kept but not web-reachable.
