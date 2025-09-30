#!/bin/bash

# Simple SSH connection script for production server

SERVER_IP="165.22.112.94"
SERVER_USER="root"
SERVER_PASSWORD="2tDEoBWefYLp.PYyPF"

echo "🔐 Connecting to production server..."
echo "Server: $SERVER_IP"
echo "User: $SERVER_USER"
echo ""

# Check if sshpass is installed
if ! command -v sshpass &> /dev/null; then
    echo "❌ sshpass is not installed. Installing..."
    if [[ "$OSTYPE" == "linux-gnu"* ]]; then
        sudo apt-get update && sudo apt-get install -y sshpass
    elif [[ "$OSTYPE" == "darwin"* ]]; then
        brew install sshpass
    else
        echo "Please install sshpass manually"
        exit 1
    fi
fi

echo "🚀 Connecting via SSH..."
sshpass -p "$SERVER_PASSWORD" ssh -o StrictHostKeyChecking=no "$SERVER_USER@$SERVER_IP"