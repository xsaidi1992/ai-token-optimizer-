#!/usr/bin/env bash
# Source this file to activate the proxy for the current session:
#   source /home/thinkpad/pojects/ia_token_opt/proxy/activate_proxy.sh
export ANTHROPIC_BASE_URL=http://localhost:3100
export ANTHROPIC_API_BASE=http://localhost:3100
export GOOGLE_GENERATIVE_AI_API_BASE=http://localhost:3100
echo "✅ Proxy activé — toutes les requêtes passent par localhost:3100"
