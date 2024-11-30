#!/bin/bash

. /usr/local/emhttp/plugins/cloudflared/include/install_functions.sh

verify_requirements() {
    cloudflared_log "Verifying installation requirements..."

    local required_dirs=(
        "/boot/config/plugins/cloudflared"
        "/usr/local/emhttp/plugins/cloudflared"
    )

    for dir in "${required_dirs[@]}"; do
        if [ ! -d "$dir" ]; then
            cloudflared_log "Creating directory: $dir"
            mkdir -p "$dir"
        fi
    done
}

main() {
    cloudflared_log "Starting package installation..."
    verify_requirements
    cloudflared_create_default_config
    cloudflared_setup_logging
    cloudflared_log "Installation completed."
}

main
