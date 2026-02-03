#!/bin/bash
# Webhook Worker Runner
# Usage: ./bin/run-worker.sh [options]
#
# Options are passed directly to messenger:consume
# Examples:
#   ./bin/run-worker.sh                    # Run once with default limits
#   ./bin/run-worker.sh -vv                # Verbose output
#   ./bin/run-worker.sh --time-limit=3600  # Run for 1 hour

cd "$(dirname "$0")/.." || exit 1

# Default options for cron execution
DEFAULT_OPTS="--time-limit=55 --memory-limit=128M"

if [ $# -eq 0 ]; then
    echo "Starting webhook worker with defaults: $DEFAULT_OPTS"
    php bin/console messenger:consume async $DEFAULT_OPTS
else
    echo "Starting webhook worker with options: $@"
    php bin/console messenger:consume async "$@"
fi
