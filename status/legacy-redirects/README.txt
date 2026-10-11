Legacy host redirects (fusionlimited.in → rc.fusionlimited.in)

On the OLD site document root that still serves fusionlimited.in/status/:

1. Replace (or add) status/index.php with legacy-redirects/index.php
2. Replace (or add) status/admin.php with legacy-redirects/admin.php

Optional IIS web.config for the old /status folder:

  <?xml version="1.0" encoding="UTF-8"?>
  <configuration>
    <system.webServer>
      <rewrite>
        <rules>
          <rule name="StatusToRc" stopProcessing="true">
            <match url="(.*)" />
            <action type="Redirect" url="https://rc.fusionlimited.in/status/{R:1}" redirectType="Permanent" />
          </rule>
        </rules>
      </rewrite>
    </system.webServer>
  </configuration>

Do NOT put these files on rc.fusionlimited.in or rc.arthsathi.com — only on the legacy apex host.
