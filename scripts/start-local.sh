#!/bin/bash
set -euo pipefail
MIS_PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
MIS_BREW_PREFIX="$(brew --prefix)"
MIS_APACHE="$MIS_BREW_PREFIX/opt/httpd/bin/httpd"
mkdir -p "$MIS_PROJECT_ROOT/storage/logs" "$MIS_PROJECT_ROOT/storage/sessions"
cat > "$MIS_PROJECT_ROOT/storage/httpd.conf" <<EOF
ServerRoot "$MIS_BREW_PREFIX/opt/httpd"
Listen 127.0.0.1:8093
ServerName localhost
PidFile "$MIS_PROJECT_ROOT/storage/httpd.pid"
ErrorLog "$MIS_PROJECT_ROOT/storage/logs/apache-error.log"
LoadModule mpm_prefork_module "$MIS_BREW_PREFIX/opt/httpd/lib/httpd/modules/mod_mpm_prefork.so"
LoadModule unixd_module "$MIS_BREW_PREFIX/opt/httpd/lib/httpd/modules/mod_unixd.so"
LoadModule authz_core_module "$MIS_BREW_PREFIX/opt/httpd/lib/httpd/modules/mod_authz_core.so"
LoadModule authz_host_module "$MIS_BREW_PREFIX/opt/httpd/lib/httpd/modules/mod_authz_host.so"
LoadModule dir_module "$MIS_BREW_PREFIX/opt/httpd/lib/httpd/modules/mod_dir.so"
LoadModule mime_module "$MIS_BREW_PREFIX/opt/httpd/lib/httpd/modules/mod_mime.so"
LoadModule headers_module "$MIS_BREW_PREFIX/opt/httpd/lib/httpd/modules/mod_headers.so"
LoadModule log_config_module "$MIS_BREW_PREFIX/opt/httpd/lib/httpd/modules/mod_log_config.so"
LoadModule php_module "$MIS_BREW_PREFIX/opt/php/lib/httpd/modules/libphp.so"
TypesConfig "$MIS_BREW_PREFIX/etc/httpd/mime.types"
LogFormat "%h %l %u %t %m %U %H %>s %b" mis
CustomLog "$MIS_PROJECT_ROOT/storage/logs/access.log" mis
DirectoryIndex index.php
<FilesMatch "\\.php$">
 SetHandler application/x-httpd-php
</FilesMatch>
DocumentRoot "$MIS_PROJECT_ROOT/public"
<Directory "$MIS_PROJECT_ROOT">
 AllowOverride All
 Require all denied
</Directory>
<Directory "$MIS_PROJECT_ROOT/public">
 Options FollowSymLinks
 AllowOverride All
 Require all granted
</Directory>
EOF
"$MIS_APACHE" -t -f "$MIS_PROJECT_ROOT/storage/httpd.conf"
"$MIS_APACHE" -f "$MIS_PROJECT_ROOT/storage/httpd.conf" -k start
printf '\nMIS: http://localhost:8093/login.php\n'
