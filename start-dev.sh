#!/bin/bash

echo "Starting local development environment..."
docker compose -f compose.yml -f docker-compose.override.yml up -d
echo "Local development environment started."