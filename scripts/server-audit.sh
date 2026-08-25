#!/usr/bin/env bash
set -u

ok(){ printf "%-34s %s\n" "$1" "OK: $2"; }
warn(){ printf "%-34s %s\n" "$1" "CHECK: $2"; }
missing(){ printf "%-34s %s\n" "$1" "MISSING: $2"; }

printf 'Top-Halal V2 server audit (read-only)\n'
printf '=====================================\n'
printf 'Host: %s\n' "$(hostname 2>/dev/null || echo unknown)"
printf 'Date: %s\n\n' "$(date -Is 2>/dev/null || date)"

if command -v php >/dev/null 2>&1; then
  PHPV=$(php -r 'echo PHP_VERSION;')
  ok "PHP" "$PHPV"
else
  missing "PHP" "php CLI not found"
fi

required_exts=(ctype curl dom fileinfo filter hash mbstring openssl pcre PDO session tokenizer xml pdo_mysql)
recommended_exts=(opcache intl exif zip redis imagick gd pcntl)

if command -v php >/dev/null 2>&1; then
  for ext in "${required_exts[@]}"; do
    if php -m | grep -qi "^${ext}$"; then ok "PHP ext: $ext" "enabled"; else missing "PHP ext: $ext" "required/expected"; fi
  done
  for ext in "${recommended_exts[@]}"; do
    if php -m | grep -qi "^${ext}$"; then ok "PHP ext: $ext" "enabled"; else warn "PHP ext: $ext" "not enabled (recommended/conditional)"; fi
  done
  php -r 'echo "memory_limit=".ini_get("memory_limit").PHP_EOL; echo "upload_max_filesize=".ini_get("upload_max_filesize").PHP_EOL; echo "post_max_size=".ini_get("post_max_size").PHP_EOL; echo "max_execution_time=".ini_get("max_execution_time").PHP_EOL;' 2>/dev/null || true
fi

for cmd in composer git node npm curl; do
  if command -v "$cmd" >/dev/null 2>&1; then
    ver=$($cmd --version 2>&1 | head -n 1)
    ok "$cmd" "$ver"
  else
    warn "$cmd" "not found"
  fi
done

if command -v mariadb >/dev/null 2>&1; then
  ok "MariaDB client" "$(mariadb --version 2>&1 | head -n 1)"
elif command -v mysql >/dev/null 2>&1; then
  ok "MySQL/MariaDB client" "$(mysql --version 2>&1 | head -n 1)"
else
  warn "MariaDB client" "not found in PATH"
fi

APACHECTL=""
for candidate in apache2ctl apachectl httpd; do
  if command -v "$candidate" >/dev/null 2>&1; then APACHECTL="$candidate"; break; fi
done
if [ -n "$APACHECTL" ]; then
  ok "Apache command" "$APACHECTL"
  mods=$($APACHECTL -M 2>/dev/null || true)
  for mod in rewrite headers expires brotli deflate http2 ssl; do
    if printf '%s\n' "$mods" | grep -qi "${mod}_module"; then ok "Apache mod: $mod" "enabled"; else warn "Apache mod: $mod" "not detected"; fi
  done
else
  warn "Apache" "control binary not found in PATH"
fi

if command -v redis-cli >/dev/null 2>&1; then
  ok "Redis CLI" "$(redis-cli --version 2>&1)"
  if redis-cli ping >/dev/null 2>&1; then ok "Redis server" "responds to PING"; else warn "Redis server" "CLI installed but no local PING response"; fi
else
  warn "Redis" "not installed/detected (recommended for cache/queues)"
fi

if command -v systemctl >/dev/null 2>&1; then ok "systemd" "available"; else warn "systemd" "not detected"; fi
if command -v supervisorctl >/dev/null 2>&1; then ok "Supervisor" "available"; else warn "Supervisor" "not detected (systemd is a valid alternative)"; fi

if command -v crontab >/dev/null 2>&1; then ok "Cron" "crontab command available"; else warn "Cron" "crontab command not found"; fi

printf '\nFilesystem / current user\n'
printf 'User: %s\n' "$(id 2>/dev/null || true)"
printf 'PWD: %s\n' "$(pwd)"
df -h . 2>/dev/null | tail -n 1 || true

printf '\nNotes\n'
printf '%s\n' '- This script makes no configuration changes.'
printf '%s\n' '- Run as the deployment user first; use elevated privileges only for a separate admin audit if needed.'
