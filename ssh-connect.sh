#!/bin/bash
# SSH Connection Helper for Trentino Immobiliare Server
# This script sets up SSH agent and connects to the server without asking for password each time

# Path to project directory
PROJECT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
SSH_KEY="${PROJECT_DIR}/.ssh-config/id_rsa"
SSH_CONFIG="${PROJECT_DIR}/.ssh-config/config"

# Start ssh-agent if not running
if [ -z "$SSH_AGENT_PID" ]; then
    eval "$(ssh-agent -s)"
fi

# Add key to agent if not already added
if ! ssh-add -l | grep -q "${SSH_KEY}"; then
    echo "Adding SSH key to agent (you'll need to enter the password once)..."
    ssh-add "${SSH_KEY}"
fi

# Connect to server using config
echo "Connecting to trentinoimmobiliare server..."
ssh -F "${SSH_CONFIG}" trentinoimmobiliare "$@"
