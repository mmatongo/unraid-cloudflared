# unraid-cloudflared

![Current Release](https://img.shields.io/github/v/tag/mmatongo/unraid-cloudflared)
![Last Commit](https://img.shields.io/github/last-commit/mmatongo/unraid-cloudflared/master)
![License](https://img.shields.io/github/license/mmatongo/unraid-cloudflared)
![Issues](https://img.shields.io/github/issues-raw/mmatongo/unraid-cloudflared)
![Sponsors](https://img.shields.io/github/sponsors/mmatongo)

## Overview

This installs Cloudflared on Unraid as a plugin.

## Installation
1. Go to the Unraid web UI.
2. Navigate to the "Plugins" tab.
3. Click on "Install Plugin".
4. Enter the URL for the latest release of the plugin.

```
https://github.com/mmatongo/unraid-cloudflared/releases
```

5. Click "Install".
6. After installation, you can configure Cloudflared through the Unraid web UI.

## Usage
To use Cloudflared, you need to configure it with your Cloudflare account. This typically involves authenticating with your Cloudflare credentials and setting up any necessary tunnels or configurations.
You can find detailed instructions on how to set up and use Cloudflared in the [Cloudflare documentation](https://developers.cloudflare.com/cloudflare-one/connections/connect-apps/install-and-setup/installation/cloudflared/).

## Updating cloudflared

The plugin no longer ships a pinned cloudflared binary. In **Settings > Cloudflared** you can see the installed version, check for a newer release, install it with one click, and choose when to check (never / at startup / daily / weekly) and whether to install automatically (otherwise a notification tells you a verified release is ready).

Every download is verified: the binary's SHA256 must match both the checksum in the Cloudflare release notes and the digest GitHub computes for the asset, and the binary must run. The same logic is available from a shell:

```
/usr/local/emhttp/plugins/cloudflared/scripts/cf-update.sh check
/usr/local/emhttp/plugins/cloudflared/scripts/cf-update.sh stage [version]
/usr/local/emhttp/plugins/cloudflared/scripts/cf-update.sh apply <version>
```

The two newest binaries are kept in `/boot/config/plugins/cloudflared/` so you can roll back with `apply <older version>`.

### Note for existing installs

The plugin version is now the plugin's own (date based) instead of the cloudflared version, so Unraid may not offer it as an update to an older install. Reinstall once (Plugins > Install Plugin with the plugin URL, or `plugin install <url> forced`). Your settings and tunnel token in `/boot/config/plugins/cloudflared/config` are kept.
