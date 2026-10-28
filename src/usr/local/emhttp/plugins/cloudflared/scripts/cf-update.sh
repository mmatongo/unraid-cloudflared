#!/bin/bash
# Local update of the cloudflared binary (no package to rebuild).
#   cf-update.sh check             show installed / latest release (nothing is changed)
#   cf-update.sh stage [version]   download, verify and store as $DEST/cloudflared-<version> (not activated)
#   cf-update.sh apply <version>   activate a staged version (copy to /usr/local/sbin, restart the service)
#   cf-update.sh auto              what the scheduled / boot check runs: stage a newer release, notify, apply it if UPDATE_AUTO=yes
#   cf-update.sh cron              (re)write the cron entry from the update settings
# Settings (UPDATE_CHECK=never|boot|daily|weekly, UPDATE_AUTO=no|yes): $DEST/config/update.cfg
# Verification: the SHA256 of the binary must match both the one in the release notes
# and the digest computed by GitHub, and the binary must run (--version).
DEST=${CF_DEST:-/boot/config/plugins/cloudflared}
REPO=cloudflare/cloudflared
ASSET=cloudflared-linux-amd64
API=https://api.github.com/repos/$REPO/releases
BIN=${CF_BIN:-/usr/local/sbin/cloudflared}
SETTINGS=$DEST/config/update.cfg
CRONFILE=$DEST/cloudflared-update.cron
SELF=$(readlink -f "$0")
UPDATE_CHECK=never; UPDATE_AUTO=no
[ -f "$SETTINGS" ] && . "$SETTINGS"
notify() { /usr/local/emhttp/webGui/scripts/notify -e "Cloudflared" -s "$1" -d "$2" -i "${3:-normal}" 2>/dev/null; }
log() { echo "$(date '+%F %T') $*"; logger -t cloudflared-update "$*" 2>/dev/null; }

release_json() { if [ -n "$1" ]; then curl -fsS -m 30 "$API/tags/$1"; else curl -fsS -m 30 "$API/latest"; fi; }

case "$1" in
check)
  cur=$("$BIN" --version 2>/dev/null | awk '{print $3}')
  last=$(release_json | python3 -c 'import sys,json;print(json.load(sys.stdin)["tag_name"])')
  echo "installed: ${cur:-unknown}  latest: $last"
  ;;
stage)
  json=$(release_json "$2") || { log "ERROR: release not found"; exit 1; }
  read -r tag digest notes < <(printf '%s' "$json" | python3 -c '
import sys,json,re
r=json.load(sys.stdin); a=[x for x in r["assets"] if x["name"]=="'$ASSET'"][0]
m=re.search(r"'$ASSET':\s*([0-9a-f]{64})", r.get("body") or "")
print(r["tag_name"], (a.get("digest") or "").replace("sha256:",""), m.group(1) if m else "")')
  [ -n "$tag" ] && [ -n "$digest" ] && [ "$digest" = "$notes" ] || { log "ERROR: checksums missing or different (GitHub digest=$digest, release notes=$notes)"; exit 1; }
  mkdir -p "$DEST"; tmp=$(mktemp)
  curl -fsSL -m 300 -o "$tmp" "https://github.com/$REPO/releases/download/$tag/$ASSET" || { rm -f "$tmp"; log "ERROR: download failed"; exit 1; }
  got=$(sha256sum "$tmp" | awk '{print $1}')
  [ "$got" = "$digest" ] || { rm -f "$tmp"; log "ERROR: file checksum ($got) differs from $digest"; exit 1; }
  chmod +x "$tmp"; "$tmp" --version >/dev/null 2>&1 || { rm -f "$tmp"; log "ERROR: the binary does not run"; exit 1; }
  mv "$tmp" "$DEST/cloudflared-$tag"
  grep -v " cloudflared-$tag\$" "$DEST/sha256sums" 2>/dev/null > "$DEST/sha256sums.tmp"; echo "$got  cloudflared-$tag" >> "$DEST/sha256sums.tmp"; mv "$DEST/sha256sums.tmp" "$DEST/sha256sums"
  log "OK: $tag staged ($got)"
  ;;
apply)
  f="$DEST/cloudflared-$2"; [ -x "$f" ] || { log "ERROR: $f not found (run stage first)"; exit 1; }
  exp=$(awk -v f="cloudflared-$2" '$2==f {print $1}' "$DEST/sha256sums" 2>/dev/null)
  [ -z "$exp" ] || [ "$exp" = "$(sha256sum "$f" | awk '{print $1}')" ] || { log "ERROR: staged file checksum mismatch"; exit 1; }
  cp "$f" "$BIN.new" && mv "$BIN.new" "$BIN" && log "binary $2 activated"
  [ "${CF_NORESTART:-0}" = 1 ] || /usr/local/emhttp/plugins/cloudflared/scripts/restart.sh restart
  ;;
auto)
  [ "$UPDATE_CHECK" = never ] && exit 0
  cur=$("$BIN" --version 2>/dev/null | awk '{print $3}')
  last=$(release_json | python3 -c 'import sys,json;print(json.load(sys.stdin)["tag_name"])') || { log "auto: release check failed"; exit 1; }
  [ -n "$last" ] && [ "$last" != "$cur" ] && [ "$(printf '%s\n%s\n' "$cur" "$last" | sort -V | tail -1)" = "$last" ] || { log "auto: up to date ($cur)"; exit 0; }
  "$SELF" stage "$last" || { notify "Cloudflared: update $last failed" "The checksum verification or the download failed, see the plugin log." alert; exit 1; }
  if [ "$UPDATE_AUTO" = yes ]; then
    "$SELF" apply "$last" && notify "Cloudflared updated to $last" "Previous version: $cur" || notify "Cloudflared: applying $last failed" "Still on $cur" alert
  else
    notify "Cloudflared $last available" "Downloaded and verified. Install it from Settings > Cloudflared."
  fi
  ;;
cron)
  case "$UPDATE_CHECK" in
    daily)  echo "17 4 * * * $SELF auto >/dev/null 2>&1" > "$CRONFILE" ;;
    weekly) echo "17 4 * * 2 $SELF auto >/dev/null 2>&1" > "$CRONFILE" ;;
    *)      rm -f "$CRONFILE" ;;
  esac
  /usr/local/sbin/update_cron
  ;;
*) sed -n 2,9p "$0"; exit 1 ;;
esac
