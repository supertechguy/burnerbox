#!/bin/bash

# --------------------
# BurnerBox Installer
# --------------------

set -e

# Prompt user for configuration
read -p "Enter custom domain name (e.g., example.com): " DOMAIN
read -p "Enter install path [/var/www/$DOMAIN]: " INSTALL_PATH
INSTALL_PATH=${INSTALL_PATH:-/var/www/$DOMAIN}
read -p "Enter default delivery mailbox email (e.g., web@mydomain.com): " MAILBOX
MAILUSER=$(echo "$MAILBOX" | cut -d'@' -f1)
read -p "Enter ServerAdmin email: " ADMIN_EMAIL
read -p "Enter IP addresses to allow access (space-separated, default: 127.0.0.1): " ALLOWED_IPS
ALLOWED_IPS=${ALLOWED_IPS:-127.0.0.1}
read -p "Enter mbox file location [/var/mail/$MAILUSER]: " MBOX_PATH
MBOX_PATH=${MBOX_PATH:-/var/mail/$MAILUSER}

# Check if mailbox file exists
if [ ! -f "$MBOX_PATH" ]; then
  echo "Mailbox file $MBOX_PATH not found. Exiting."
  exit 1
fi

# Create install directory
mkdir -p "$INSTALL_PATH"
cp index.php "$INSTALL_PATH"

# Generate config.php
cat <<EOF > "$INSTALL_PATH/config.php"
<?php
\$domain = '$DOMAIN';
\$mboxFile = '$MBOX_PATH';
?>
EOF

# Configure Postfix
POSTFIX_VIRTUAL="/etc/postfix/virtual"
if ! grep -q "@$DOMAIN" "$POSTFIX_VIRTUAL"; then
  echo "@$DOMAIN $MAILBOX" >> "$POSTFIX_VIRTUAL"
  postmap "$POSTFIX_VIRTUAL"
fi

if ! grep -q "$DOMAIN" /etc/postfix/main.cf; then
  echo "Configuring Postfix for $DOMAIN"
  postconf -e "virtual_alias_domains=\$($(postconf -h virtual_alias_domains) $DOMAIN)"
  postconf -e "virtual_alias_maps=hash:$POSTFIX_VIRTUAL"
  systemctl reload postfix
fi

# Configure Apache
CONF_DIR="/etc/apache2/sites-available"
NUM_SITES=$(ls $CONF_DIR | grep -E '^[0-9]+-' | wc -l)
SITE_CONF="$CONF_DIR/$(printf "%03d" $((NUM_SITES + 1)))-$DOMAIN.conf"

cat <<EOF > "$SITE_CONF"
<VirtualHost *:80>
    ServerName $DOMAIN
    ServerAdmin $ADMIN_EMAIL
    DocumentRoot $INSTALL_PATH

    <Directory $INSTALL_PATH>
        Options Indexes FollowSymLinks
        AllowOverride None
        Require all denied
EOF

for IP in $ALLOWED_IPS; do
  echo "        Require ip $IP" >> "$SITE_CONF"
done

cat <<EOF >> "$SITE_CONF"
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/error.log
    CustomLog \${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF

a2ensite $(basename "$SITE_CONF")
systemctl reload apache2

# Prompt for SSL via Certbot
read -p "Would you like to configure SSL with Let's Encrypt? (y/n): " SSL
if [[ "$SSL" == "y" || "$SSL" == "Y" ]]; then
  apt-get install -y certbot python3-certbot-apache
  certbot --apache -d "$DOMAIN"
fi

echo "BurnerBox installation complete at: http://$DOMAIN"
echo "Install path: $INSTALL_PATH"
echo "Mailbox file: $MBOX_PATH"
