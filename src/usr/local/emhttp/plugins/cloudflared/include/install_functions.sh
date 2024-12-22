#!/bin/bash

cloudflared_log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
    logger -t cloudflared-install "$1"
}

cloudflared_create_default_config() {
    local config_file="/boot/config/plugins/cloudflared/config/cloudflared.cfg"

    if [ ! -f "$config_file" ]; then
        cloudflared_log "Creating default configuration..."
        mkdir -p "$(dirname "$config_file")"
        cat > "$config_file" <<EOL
# Cloudflared configuration
SERVICE="disabled"
TUNNEL_TOKEN=""
TUNNEL_RETRIES="5"
TUNNEL_REGION=""
TUNNEL_TRANSPORT_PROTOCOL="auto"
TUNNEL_EDGE_BIND_ADDRESS=""
TUNNEL_EDGE_IP_VERSION="auto"
TUNNEL_GRACE_PERIOD="30s"
TUNNEL_ORIGIN_CERT=""
TUNNEL_METRICS="0.0.0.0:46495"
TUNNEL_LOGLEVEL="info"
EOL
    fi
}

cloudflared_setup_logging() {
    local log_dir="/var/log/cloudflared"
    mkdir -p "$log_dir"
    chmod 755 "$log_dir"

    cat > "/etc/logrotate.d/cloudflared" <<EOL
/var/log/cloudflared/*.log {
    daily
    rotate 7
    compress
    delaycompress
    missingok
    notifempty
    postrotate
        /usr/local/emhttp/plugins/cloudflared/scripts/restart.sh restart >/dev/null 2>&1 || true
    create 644 nobody users
}
EOL
}

cloudflared_install_completion() {
    if command -v cloudflared >/dev/null 2>&1; then
        cloudflared_log "Installing shell completion..."
        cloudflared completion bash > /etc/bash_completion.d/cloudflared
        chmod 644 /etc/bash_completion.d/cloudflared
    fi
}

cloudflared_migrate_config() {
    if [ -f "/boot/config/plugins/cloudflared/config/token" ]; then
        cloudflared_log "Migrating existing token to new configuration..."
        token=$(cat "/boot/config/plugins/cloudflared/config/token")
        sed -i "s/TUNNEL_TOKEN=\"\"/TUNNEL_TOKEN=\"$token\"/" "/boot/config/plugins/cloudflared/config/cloudflared.cfg"
        rm "/boot/config/plugins/cloudflared/config/token"
    fi
}
