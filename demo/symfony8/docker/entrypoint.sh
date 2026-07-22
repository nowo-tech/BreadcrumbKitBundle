#!/bin/sh
set -e

# FRANKENPHP_MODE: classic | worker (REQ-DEMO-010). Default: worker.
# Set via .env / Compose only — not baked into the image ENV.
MODE="${FRANKENPHP_MODE:-worker}"
case "$MODE" in
	classic)
		if [ -f /etc/frankenphp/Caddyfile.dev ]; then
			cp /etc/frankenphp/Caddyfile.dev /etc/frankenphp/Caddyfile
		elif [ -f /app/docker/frankenphp/Caddyfile.dev ]; then
			cp /app/docker/frankenphp/Caddyfile.dev /etc/frankenphp/Caddyfile
		fi
		;;
	worker)
		# Restore worker Caddyfile from the mounted demo tree (needed after a prior classic run).
		if [ -f /app/docker/frankenphp/Caddyfile ]; then
			cp /app/docker/frankenphp/Caddyfile /etc/frankenphp/Caddyfile
		fi
		# else keep image default Caddyfile (worker enabled)
		;;
	*)
		echo "Unknown FRANKENPHP_MODE=$MODE (expected classic|worker)" >&2
		exit 1
		;;
esac
echo "FrankenPHP mode: $MODE"

mkdir -p /app/var/cache /app/var/log
chmod -R 777 /app/var
echo "Installing PHP dependencies..." && (cd /app && composer install --no-interaction --prefer-dist || true)
(cd /app && php bin/console assets:install public --symlink --no-interaction 2>/dev/null) || (cd /app && php bin/console assets:install public --no-interaction 2>/dev/null) || true
exec frankenphp run --config /etc/frankenphp/Caddyfile --adapter caddyfile
