#!/bin/bash
set -euo pipefail
cd "$(dirname "$0")"
bash scripts/start-local.sh
if /usr/bin/dscacheutil -q host -a name mis.badmaal.test | /usr/bin/grep -q '127.0.0.1'; then
    open http://mis.badmaal.test/login.php
else
    open http://localhost:8093/login.php
fi
