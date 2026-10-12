DATA RECOVERY — Resource Centre

PROBLEM: Full FTP of a COMPLETE zip that contained empty team.json wiped live data.
Media Kit still shows 76 because that file was not overwritten; team.json was.

FUSIONLIMITED (rc.fusionlimited.in)
-----------------------------------
No full Fusion team snapshot is in this package.
Restore from Plesk / hosting backup:

  tenants/fusionlimited/data/team.json
  (+ company.json, locations.json, departments.json, designations.json, …)

Construction status:
  If status/data/projects.json still exists on the server, copy to:
  tenants/fusionlimited/data/status/

Or while logged in as Super Admin open:
  https://rc.fusionlimited.in/tools/recover_empty_data.php

ARTHSATHI (rc.arthsathi.com)
----------------------------
Copy restore/arthsathi/*.json → tenants/arthsathi/data/

THEN hard-refresh the browser.
NEVER again upload tenants/*/data/ from a code zip.
