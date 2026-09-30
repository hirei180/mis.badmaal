#!/bin/bash
set -euo pipefail
MIS_PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
MIS_APACHE=/opt/homebrew/opt/httpd/bin/httpd
MIS_CONF=/opt/homebrew/etc/httpd/httpd.conf
MIS_VHOST=/opt/homebrew/etc/httpd/extra/mis-badmaal-local-vhost.conf
if [[ "$EUID" -ne 0 ]]; then
    echo 'Run this setup with sudo: updating /etc/hosts and reloading the system Apache requires administrator access.' >&2
    exit 1
fi
"$MIS_APACHE" -t
cp "$MIS_PROJECT_ROOT/deploy/local/mis.badmaal.test.conf" "$MIS_VHOST"
if ! /usr/bin/grep -Fq "Include \"$MIS_VHOST\"" "$MIS_CONF"; then
    printf '\nInclude "%s"\n' "$MIS_VHOST" >> "$MIS_CONF"
fi
"$MIS_APACHE" -t
if ! /usr/bin/grep -Eq '^[[:space:]]*127\.0\.0\.1[[:space:]]+mis\.badmaal\.test([[:space:]]|$)' /etc/hosts; then
    printf '\n127.0.0.1 mis.badmaal.test\n' >> /etc/hosts
fi
/usr/bin/dscacheutil -flushcache
"$MIS_APACHE" -k graceful
printf '\nMIS configured: http://mis.badmaal.test/login.php\n'
