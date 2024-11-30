#!/bin/bash

DAEMON="cloudflared"
DAEMON_BIN="/usr/local/sbin/$DAEMON"
CONFIG="/boot/config/plugins/cloudflared/config/cloudflared.cfg"
PIDFILE="/var/run/cloudflared.pid"
LOGFILE="/var/log/cloudflared/cloudflared.log"

mkdir -p "$(dirname $LOGFILE)"

if [ -f "$CONFIG" ]; then
    source "$CONFIG"
else
    echo "Configuration file not found: $CONFIG"
    exit 1
fi

log() {
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo "$timestamp $1" | tee -a "$LOGFILE"
    logger -t $DAEMON "$1"
}

get_pid() {
    pgrep cloudflared
}

is_running() {
    [ -n "$(get_pid)" ]
}

build_args() {
    if [ -z "$TUNNEL_TOKEN" ]; then
        log "ERROR: TUNNEL_TOKEN is required but not set"
        return 1
    fi

    echo "tunnel run --token $TUNNEL_TOKEN"
}


start_daemon() {
    if [ "$SERVICE" != "enabled" ]; then
        log "Service is disabled, not starting"
        return 0
    fi

    if is_running; then
        log "Service already running"
        return 0
    fi

    log "Starting $DAEMON..."

    local args=$(build_args)
    if [ $? -ne 0 ]; then
        log "Failed to build arguments"
        return 1
    fi

    env \
        TUNNEL_EDGE_IP_VERSION="$TUNNEL_EDGE_IP_VERSION" \
        TUNNEL_GRACE_PERIOD="$TUNNEL_GRACE_PERIOD" \
        TUNNEL_METRICS="$TUNNEL_METRICS" \
        TUNNEL_LOGLEVEL="$TUNNEL_LOGLEVEL" \
        TUNNEL_REGION="$TUNNEL_REGION" \
        TUNNEL_TRANSPORT_PROTOCOL="$TUNNEL_TRANSPORT_PROTOCOL" \
        NO_AUTOUPDATE='true' \
        nohup $DAEMON_BIN $args >> "$LOGFILE" 2>&1 &

    local pid=$!

    sleep 2
    if kill -0 $pid 2>/dev/null; then
        log "Service started successfully (PID: $pid)"
        echo $pid > "$PIDFILE"
        return 0
    else
        log "Service failed to start"
        log "Last few lines of log:"
        tail -n 5 "$LOGFILE" | while read -r line; do
            log "  $line"
        done
        rm -f "$PIDFILE"
        return 1
    fi
}

stop_daemon() {
    local pids=$(get_pid)

    if [ -n "$pids" ]; then
        log "Stopping $DAEMON..."

        for pid in $pids; do
            kill $pid 2>/dev/null

            local timeout=10
            while [ $timeout -gt 0 ] && kill -0 $pid 2>/dev/null; do
                sleep 1
                let timeout=timeout-1
            done

            if kill -0 $pid 2>/dev/null; then
                kill -9 $pid 2>/dev/null
                log "Forced termination of PID $pid"
            fi
        done

        log "Service stopped"
    else
        log "Service not running"
    fi

    rm -f "$PIDFILE"
}

case "$1" in
    'start')
        start_daemon
        ;;
    'stop')
        stop_daemon
        ;;
    'restart')
        if [ "$SERVICE" = "enabled" ]; then
            stop_daemon
            sleep 1
            start_daemon
        else
            log "Service is disabled, stopping only"
            stop_daemon
        fi
        ;;
    'status')
        if is_running; then
            echo "running"
            exit 0
        else
            echo "stopped"
            exit 1
        fi
        ;;
    *)
        echo "Usage: $0 {start|stop|restart|status}"
        exit 1
        ;;
esac

exit $?
