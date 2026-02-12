#!/bin/bash

echo "Starting local development environment..."
USER_ID=$(id -u) GROUP_ID=$(id -g) docker compose -f compose.yml -f docker-compose.override.yml up -d --build
echo "Local development environment started."