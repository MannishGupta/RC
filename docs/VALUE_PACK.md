# Value Pack

## 260921.41 second pass
- Role packs: `app/RolePack.php` + `data/value/roles.json` (`crm_pack`)
- Visit log links dispatches via `api_value.php` `visit_log` + `dispatch_list`
- Reminders: Field Ops "Scan now" + `tools/reminders_cron.php?token=...`

### Cron setup (Windows Task Scheduler)
1. Create `data/value/cron_token.txt` with a long random string
2. Daily action: `php.exe C:\path\to\tools\reminders_cron.php`
   or HTTP GET `https://your-host/tools/reminders_cron.php?token=SECRET`
3. Output queue: `data/value/reminder_queue.json`

### CRM packs
| Pack | Typical tabs |
|------|----------------|
| logistics | tracking, dispatch, ops, assets, locations, cartags, team, expiry |
| hr | team, docs, expiry, org, ops, terms, events, bank |
| readonly | team, locations, events, terms |
| admin | all |

Change CRM pack: **Health & Backup** tab (admin) or edit `data/value/roles.json`.
