#!/bin/bash

LOG_DIR="/var/log/cloudflared"
LOGFILE="$LOG_DIR/cloudflared.log"

mkdir -p "$LOG_DIR"

if [ ! -f "$LOGFILE" ]; then
    touch "$LOGFILE"
    echo "$(date '+%Y-%m-%d %H:%M:%S') Cloudflared log initialized." > "$LOGFILE"
fi

chmod 644 "$LOGFILE"
chown nobody:users "$LOGFILE"
