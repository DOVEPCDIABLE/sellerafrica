#!/bin/bash
set -euo pipefail
cd "$(dirname "$0")/.."
backup="/root/btcpay-control-backups/terminal-$(date +%Y%m%d-%H%M%S)"
install -d -m 700 "$backup"
cp -a /opt/btcpay-control/public/index.php /opt/btcpay-control/src/Console.php "$backup/"
cp -a /usr/local/sbin/btcpay-control "$backup/root-bridge"
cp -a /etc/sudoers.d/btcpay-control "$backup/sudoers"
cp -a /etc/apache2/conf.d/includes/post_virtualhost_global.conf "$backup/post_virtualhost_global.conf"
/opt/cpanel/ea-php82/root/usr/bin/php -n -l public/index.php
/opt/cpanel/ea-php82/root/usr/bin/php -n -l src/Console.php
/usr/sbin/visudo -cf deploy/sudoers
install -o root -g btcpay-control -m 640 public/index.php /opt/btcpay-control/public/index.php
install -o root -g btcpay-control -m 640 src/Console.php /opt/btcpay-control/src/Console.php
install -o root -g root -m 755 deploy/btcpay-control /usr/local/sbin/btcpay-control
install -o root -g root -m 440 deploy/sudoers /etc/sudoers.d/btcpay-control
install -o root -g root -m 600 deploy/terminal-gateway.py /opt/btcpay-control/terminal-gateway.py
install -o root -g root -m 644 deploy/btcpay-control-terminal.service deploy/btcpay-control-terminal-gateway.service /etc/systemd/system/
install -o root -g root -m 600 deploy/apache-terminal.conf /etc/btcpay-control/apache-terminal.conf
include='Include "/etc/btcpay-control/apache-terminal.conf"'
if ! grep -qxF "$include" /etc/apache2/conf.d/includes/post_virtualhost_global.conf; then
    printf '\n%s\n' "$include" >> /etc/apache2/conf.d/includes/post_virtualhost_global.conf
fi
if ! /usr/sbin/httpd -t; then
    cp -a "$backup/post_virtualhost_global.conf" /etc/apache2/conf.d/includes/post_virtualhost_global.conf
    exit 1
fi
systemctl daemon-reload
systemctl enable --now btcpay-control-terminal-gateway.service
systemctl restart btcpay-control-php.service
/usr/sbin/httpd -k graceful
printf 'Installed. Backup: %s\n' "$backup"
