#!/bin/bash
set -euo pipefail
test "$(id -u)" = 0
SOURCE="$(cd "$(dirname "$0")/.." && pwd)"
test -f "$SOURCE/vendor/autoload.php"
test -x /root/btcpayserver-docker/btcpay-restart.sh
STAMP="$(date -u +%Y%m%d-%H%M%S)"
BACKUP="/root/btcpay-control-backups/$STAMP"
mkdir -p "$BACKUP"
chmod 700 /root/btcpay-control-backups "$BACKUP"
cp -a /etc/apache2/conf/httpd.conf "$BACKUP/httpd.conf"
if test -d /opt/btcpay-control; then cp -a /opt/btcpay-control "$BACKUP/app"; fi
id btcpay-control >/dev/null 2>&1 || useradd --system --home-dir /var/lib/btcpay-control --shell /sbin/nologin btcpay-control
install -d -o root -g root -m 0755 /opt/btcpay-control /etc/btcpay-control
install -d -o btcpay-control -g btcpay-control -m 0700 /var/lib/btcpay-control /var/lib/btcpay-control/sessions
install -d -o root -g root -m 0700 /var/lib/btcpay-control-root /run/btcpay-control-ops
cp -a "$SOURCE/public" "$SOURCE/src" "$SOURCE/vendor" "$SOURCE/composer.json" "$SOURCE/composer.lock" /opt/btcpay-control/
chown -R root:root /opt/btcpay-control
find /opt/btcpay-control -type d -exec chmod 0755 {} +
find /opt/btcpay-control -type f -exec chmod 0644 {} +
install -m 0755 "$SOURCE/deploy/btcpay-control" /usr/local/sbin/btcpay-control
install -m 0600 "$SOURCE/deploy/php-fpm.conf" /etc/btcpay-control/php-fpm.conf
install -m 0440 "$SOURCE/deploy/sudoers" /etc/sudoers.d/btcpay-control
visudo -cf /etc/sudoers.d/btcpay-control
install -m 0644 "$SOURCE/deploy/btcpay-control-php.service" /etc/systemd/system/btcpay-control-php.service
install -m 0644 "$SOURCE/deploy/btcpay-control-restart.service" /etc/systemd/system/btcpay-control-restart.service
install -m 0644 "$SOURCE/deploy/tmpfiles.conf" /etc/tmpfiles.d/btcpay-control.conf
if ! test -f /var/lib/btcpay-control/bootstrap.sha256; then
    TOKEN="$(openssl rand -hex 32)"
    printf '%s\n' "$TOKEN" > /etc/btcpay-control/bootstrap-token
    printf '%s' "$TOKEN" | sha256sum | cut -d' ' -f1 > /var/lib/btcpay-control/bootstrap.sha256
    chmod 0600 /etc/btcpay-control/bootstrap-token
    chown root:btcpay-control /var/lib/btcpay-control/bootstrap.sha256
    chmod 0640 /var/lib/btcpay-control/bootstrap.sha256
    unset TOKEN
fi
SSL=/etc/apache2/conf.d/userdata/ssl/2_4/sellerafrica/sellerafrica.com
HTTP=/etc/apache2/conf.d/userdata/std/2_4/sellerafrica/sellerafrica.com
mkdir -p "$SSL" "$HTTP"
if test -f "$SSL/btcpay-control.conf"; then cp -a "$SSL/btcpay-control.conf" "$BACKUP/apache-ssl.conf"; fi
if test -f "$HTTP/btcpay-control.conf"; then cp -a "$HTTP/btcpay-control.conf" "$BACKUP/apache-http.conf"; fi
install -m 0644 "$SOURCE/deploy/apache-ssl.conf" "$SSL/btcpay-control.conf"
install -m 0644 "$SOURCE/deploy/apache-http.conf" "$HTTP/btcpay-control.conf"
/opt/cpanel/ea-php82/root/usr/sbin/php-fpm --test --fpm-config /etc/btcpay-control/php-fpm.conf
systemctl daemon-reload
systemctl enable --now btcpay-control-php.service
/usr/local/cpanel/scripts/rebuildhttpdconf
if ! /usr/sbin/httpd -t; then
    rm -f "$SSL/btcpay-control.conf" "$HTTP/btcpay-control.conf"
    test ! -f "$BACKUP/apache-ssl.conf" || cp -a "$BACKUP/apache-ssl.conf" "$SSL/btcpay-control.conf"
    test ! -f "$BACKUP/apache-http.conf" || cp -a "$BACKUP/apache-http.conf" "$HTTP/btcpay-control.conf"
    /usr/local/cpanel/scripts/rebuildhttpdconf
    exit 1
fi
/usr/local/cpanel/scripts/restartsrv_httpd --graceful
echo "Installed. Backup: $BACKUP"
echo "Read /etc/btcpay-control/bootstrap-token through root SSH to set up the independent owner account."
