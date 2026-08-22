#!/bin/bash

# ============================================================================
# RemoteLabz Complete Installation Script
# Automated installation of system requirements, SSL, and RemoteLabz application
# ============================================================================

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Default configuration values
REMOTELABZ_PATH="/opt/remotelabz"
REMOTELABZ_ENV="prod"
REMOTELABZ_PORT=80
REMOTELABZ_MAX_FILESIZE="3000M"
INSTALL_LOG_PATH="/var/log/remotelabz"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/.."
DEFAULT_CA_PASS="R3mot3!abz-0penVPN-CA2020"
# Global variable to track which .env.local file was used
ENV_FILE="${REMOTELABZ_PATH}/.env.local"

# Functions for colored output
print_info() {
    echo -e "${GREEN}🔥 $1${NC}"
}

print_error() {
    echo -e "${RED}❌ Error: $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_step() {
    echo -e "${CYAN}===================================================${NC}"
    echo -e "${CYAN}$1${NC}"
    echo -e "${CYAN}===================================================${NC}"
}

# ============================================================================
# Check if running as root
# ============================================================================
check_root() {
    if [ "$EUID" -ne 0 ]; then 
        print_error "Please run as root (use sudo)"
        exit 1
    fi
}

# ============================================================================
# Load or create environment file
# This function REQUIRES a base .env file to exist
# ============================================================================
setup_env_file() {
    local BASE_ENV_FILE="${SCRIPT_DIR}/.env"
    #echo "$BASE_ENV_FILE"
    
    # Also check for .env.local in the script directory / source tree
    if [ ! -f "$ENV_FILE" ] && [ -f "${SCRIPT_DIR}/.env.local" ]; then
        ENV_FILE="${SCRIPT_DIR}/.env.local"
    fi
    
    print_info "Your .env.local used is ${ENV_FILE}"

    # Create directory if it doesn't exist
    mkdir -p /opt/remotelabz
    
    # Check if .env.local already exists
    if [ -f "$ENV_FILE" ]; then
        print_info "Environment file .env.local already exists at $ENV_FILE"
        
        # Load the existing file
        source "$ENV_FILE"
        print_info "Existing environment file loaded successfully"
        
        # Ask if user wants to review/edit it
        read -p "Do you want to review/edit the existing configuration? (y/N): " edit_existing
        if [[ "$edit_existing" =~ ^[Yy]$ ]]; then
            ${EDITOR:-nano} "$ENV_FILE"
            source "$ENV_FILE"
        fi
        
        return 0
    fi
    
    # .env.local doesn't exist, we need to create it from a base .env file
    print_info "Environment file .env.local not found, looking for base .env file..."
    
    if [ -f "$BASE_ENV_FILE" ]; then
        print_info "✅ Found base .env file: $BASE_ENV_FILE"
        print_info "Creating .env.local from base .env file..."
        cp "$BASE_ENV_FILE" "$ENV_FILE"
        print_info "✅ Configuration copied to $ENV_FILE"

    # No .env file found - this is a critical error
    else
        echo ""
        print_error "❌ CRITICAL: No base .env file found!"
        echo ""
        print_error "The .env file is required for RemoteLabz to work properly."
        print_error "Searched in:"
        print_error "  - $BASE_ENV_FILE"
        print_error "  - /opt/remotelabz/.env"
        echo ""
        print_warning "Please ensure you have the .env file in your RemoteLabz source directory"
        print_warning "or provide the path to the .env file."
        echo ""
        
        print_error "Installation cannot continue without a .env file"
        print_warning "Please place your .env file in the RemoteLabz source directory and run this script again"
        exit 1
    fi
    
    # At this point, .env.local exists (copied from .env)
    print_info "Configuring .env.local with installation-specific values..."
    
    # Get public IP
    print_info "Detecting public IP address..."
    PUBLIC_IP=$(curl -s ifconfig.me 2>/dev/null)
    
    if [ -z "$PUBLIC_IP" ] || [ "$PUBLIC_IP" == "" ]; then
        print_warning "Could not detect public IP address automatically"
        read -p "Enter your server's public IP or FQDN: " PUBLIC_IP
    else
        print_info "Detected public IP: $PUBLIC_IP"
        read -p "Use this IP address? (Y/n): " use_detected
        use_detected=${use_detected:-Y}
        
        if [[ ! "$use_detected" =~ ^[Yy]$ ]]; then
            read -p "Enter your server's public IP or FQDN: " PUBLIC_IP
        fi
    fi
    
    # Update PUBLIC_ADDRESS
    if grep -q "^PUBLIC_ADDRESS=" "$ENV_FILE"; then
        sed -i "s|^PUBLIC_ADDRESS=.*|PUBLIC_ADDRESS=\"${PUBLIC_IP}\"|g" "$ENV_FILE"
        print_info "✅ Updated PUBLIC_ADDRESS to ${PUBLIC_IP}"
    else
        echo "PUBLIC_ADDRESS=\"${PUBLIC_IP}\"" >> "$ENV_FILE"
        print_info "✅ Added PUBLIC_ADDRESS=${PUBLIC_IP}"
    fi
    
    # Update IP_ADDRESS (for single server setups)
    if grep -q "^#IP_ADDRESS=" "$ENV_FILE" || grep -q "^IP_ADDRESS=" "$ENV_FILE"; then
        # Ask if single server deployment
        read -p "Is this a single server deployment (front + worker on same machine)? (Y/n): " single_server
        single_server=${single_server:-Y}
        
        if [[ "$single_server" =~ ^[Yy]$ ]]; then
            sed -i "s|^IP_ADDRESS=.*|IP_ADDRESS=\"127.0.0.1\"|g" "$ENV_FILE"
            sed -i "s|^DEPLOY_SINGLE_SERVER=.*|DEPLOY_SINGLE_SERVER=1|g" "$ENV_FILE"
            print_info "✅ Configured for single server deployment"
        else
            sed -i "s|^IP_ADDRESS=.*|IP_ADDRESS=\"${PUBLIC_IP}\"|g" "$ENV_FILE"
            sed -i "s|^DEPLOY_SINGLE_SERVER=.*|DEPLOY_SINGLE_SERVER=0|g" "$ENV_FILE"
            print_info "✅ Configured for multi-server deployment"
        fi
    fi
    
    # Update VPN_ADDRESS to use PUBLIC_ADDRESS
    if grep -q "^VPN_ADDRESS=" "$ENV_FILE"; then
        sed -i "s|^VPN_ADDRESS=.*|VPN_ADDRESS=\$PUBLIC_ADDRESS|g" "$ENV_FILE"
        print_info "✅ Updated VPN_ADDRESS"
    fi
    
    # Update REMOTELABZ_PROXY_SERVER
    if grep -q "^REMOTELABZ_PROXY_SERVER=" "$ENV_FILE"; then
        sed -i "s|^REMOTELABZ_PROXY_SERVER=.*|REMOTELABZ_PROXY_SERVER=\$PUBLIC_ADDRESS|g" "$ENV_FILE"
        print_info "✅ Updated REMOTELABZ_PROXY_SERVER"
    fi
    
    # Ensure APP_MAINTENANCE is set to 1 initially
    if grep -q "^APP_MAINTENANCE=" "$ENV_FILE"; then
        sed -i "s|^APP_MAINTENANCE=.*|APP_MAINTENANCE=1|g" "$ENV_FILE"
        print_info "✅ Set APP_MAINTENANCE=1 (will need to be changed to 0 after installation)"
    fi
    
    echo ""
    print_info "=================================="
    print_info "Configuration file ready!"
    print_info "=================================="
    print_warning "IMPORTANT: Please review the configuration before continuing"
    print_warning "Pay special attention to:"
    echo "  • PUBLIC_ADDRESS"
    echo "  • IP_ADDRESS"
    echo "  • MYSQL credentials"
    echo "  • SSL_CA_KEY_PASSPHRASE"
    echo "  • CONTACT_MAIL"
    echo ""
    
    read -p "Do you want to review/edit the configuration now? (Y/n): " edit_config
    edit_config=${edit_config:-Y}
    
    if [[ "$edit_config" =~ ^[Yy]$ ]]; then
        ${EDITOR:-nano} "$ENV_FILE"
        print_info "Configuration saved"
    fi
    
    # Load the environment file
    source "$ENV_FILE"
    print_info "✅ Environment file loaded successfully"
    
    # Display key configuration values
    echo ""
    print_info "Current configuration summary:"
    echo "  • PUBLIC_ADDRESS: ${PUBLIC_ADDRESS:-not set}"
    echo "  • IP_ADDRESS: ${IP_ADDRESS:-not set}"
    echo "  • WORKER_SERVER: ${WORKER_SERVER:-not set}"
    echo "  • MYSQL_DATABASE: ${MYSQL_DATABASE:-not set}"
    echo "  • APP_ENV: ${APP_ENV:-not set}"
    echo "  • APP_MAINTENANCE: ${APP_MAINTENANCE:-not set}"
    echo "  • DEPLOY_SINGLE_SERVER: ${DEPLOY_SINGLE_SERVER:-not set}"
    echo ""
    
    read -p "Configuration looks correct? Continue with installation? (Y/n): " continue_install
    continue_install=${continue_install:-Y}
    
    if [[ ! "$continue_install" =~ ^[Yy]$ ]]; then
        print_warning "Installation cancelled by user"
        print_info "You can edit $ENV_FILE and run the installation again"
        exit 0
    fi
}

# ============================================================================
# Set sysctl parameter helper function
# ============================================================================
set_sysctl_param() {
    local param="$1"
    local value="$2"
    local file="/etc/sysctl.d/99-remotelabz.conf"

    if grep -q "^${param}=" "$file" 2>/dev/null; then
        sed -i "s|^${param}=.*|${param}=${value}|" "$file"
    else
        echo "${param}=${value}" >> "$file"
    fi
}

# ============================================================================
# STEP 1: Install System Requirements
# ============================================================================
install_requirements() {
    print_step "STEP 1: Installing System Requirements"
    
    # Update system
    print_info "Updating system packages..."
    apt-get update
    apt-get -y upgrade

    # Install base packages
    print_info "Installing base packages..."
    apt install -y fail2ban exim4 apache2 curl gnupg zip unzip ntpsec openvpn qemu-utils openssl git expect
    
    # Install PHP 8.5
    print_info "Installing PHP 8.5..."
    #add-apt-repository ppa:ondrej/php -y
    #apt update
    apt install -y php8.5 php8.5-common php8.5-gd php8.5-amqp php8.5-cli \
        php8.5-mysql php8.5-xml php8.5-curl php8.5-zip php8.5-mbstring php8.5-intl \
        php8.5-bcmath php8.5-ssh2
    
    # Install HAProxy
    print_info "Installing HAProxy..."
    apt install -y haproxy
    
    # Install Apache modules
    print_info "Configuring Apache..."
    apt install -y libapache2-mod-shib libapache2-mod-php8.5
    apt autoremove -y
    a2dismod php7.4 php8.1 php8.2 php8.3 php8.4 2>/dev/null || true
    a2enmod php8.5
    a2enmod headers 
    a2enmod remoteip
    
    # Install Composer
    print_info "Installing Composer..."
    if [ ! -f /usr/local/bin/composer ]; then
        php -r "copy('https://getcomposer.org/download/2.10.2/composer.phar', 'composer.phar');"
        cp composer.phar /usr/local/bin/composer
        chmod a+x /usr/local/bin/composer
        rm composer.phar
    fi
  
    # Install Node.js and packages
    print_info "Installing Node.js and npm packages..."
    if ! command -v node &> /dev/null; then
        curl -sL https://deb.nodesource.com/setup_20.x | sudo -E bash - 
        apt-get install -y nodejs
    fi
    
    if ! command -v yarn &> /dev/null; then
        npm install -g yarn
    fi
    
    if ! command -v configurable-http-proxy &> /dev/null; then
        npm install -g configurable-http-proxy@5.0.1
    fi
    
    # Install and configure MySQL
    print_info "Installing and configuring MySQL..."
    apt-get install -y mysql-server
    systemctl start mysql
    systemctl enable mysql
    
    # Load environment values for MySQL from the env file
    local MYSQL_ROOT_PASS="R3mot3Lab$-2026\$"
    local MYSQL_DB_USER="user"
    local MYSQL_DB_PASS="Mysql-Pa33wrd\$"
    local MYSQL_DB_NAME="remotelabz"

    if [ -f "$ENV_FILE" ]; then
        local tmp_env
        tmp_env=$(mktemp)
        cp "$ENV_FILE" "$tmp_env"
        
        # Read values from the env file, respecting quoted values
        local val
        val=$(grep -E '^MYSQL_ROOT_PASSWORD=' "$tmp_env" 2>/dev/null | head -1 | cut -d'=' -f2-)
        [ -n "$val" ] && val=$(echo "$val" | sed 's/^"//;s/"$//;s/^'\''//;s/'\''$//') && [ -n "$val" ] && MYSQL_ROOT_PASS="$val"
        
        val=$(grep -E '^MYSQL_USER=' "$tmp_env" 2>/dev/null | head -1 | cut -d'=' -f2-)
        [ -n "$val" ] && val=$(echo "$val" | sed 's/^"//;s/"$//;s/^'\''//;s/'\''$//') && [ -n "$val" ] && MYSQL_DB_USER="$val"
        
        val=$(grep -E '^MYSQL_PASSWORD=' "$tmp_env" 2>/dev/null | head -1 | cut -d'=' -f2-)
        [ -n "$val" ] && val=$(echo "$val" | sed 's/^"//;s/"$//;s/^'\''//;s/'\''$//') && [ -n "$val" ] && MYSQL_DB_PASS="$val"
        
        val=$(grep -E '^MYSQL_DATABASE=' "$tmp_env" 2>/dev/null | head -1 | cut -d'=' -f2-)
        [ -n "$val" ] && val=$(echo "$val" | sed 's/^"//;s/"$//;s/^'\''//;s/'\''$//') && [ -n "$val" ] && MYSQL_DB_NAME="$val"
        
        rm -f "$tmp_env"
    fi

    cat > /tmp/mysql_secure_sql.sql << EOF
ALTER USER IF EXISTS 'root'@'localhost' IDENTIFIED BY '${MYSQL_ROOT_PASS}';
DELETE FROM mysql.user WHERE User='';
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';
CREATE USER IF NOT EXISTS '${MYSQL_DB_USER}'@'localhost' IDENTIFIED WITH caching_sha2_password BY '${MYSQL_DB_PASS}';
CREATE DATABASE IF NOT EXISTS ${MYSQL_DB_NAME};
GRANT ALL ON ${MYSQL_DB_NAME}.* TO '${MYSQL_DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOF

    mysql -sfu root < /tmp/mysql_secure_sql.sql 2>/dev/null || mysql < /tmp/mysql_secure_sql.sql
    rm /tmp/mysql_secure_sql.sql
    
    print_info "MySQL configured with user '${MYSQL_DB_USER}' and password '${MYSQL_DB_PASS}'"
    
    # Auto-detect MySQL server version and update .env.local
    local MYSQL_INSTALLED_VERSION
    MYSQL_INSTALLED_VERSION=$(mysql --version 2>/dev/null | grep -oP '[\d]+\.[\d]+\.[\d]+' | head -1)
    if [ -n "$MYSQL_INSTALLED_VERSION" ] && [ -f "$ENV_FILE" ]; then
        if grep -q '^MYSQL_VERSION=' "$ENV_FILE"; then
            sed -i "s|^MYSQL_VERSION=.*|MYSQL_VERSION='${MYSQL_INSTALLED_VERSION}'|g" "$ENV_FILE"
        else
            echo "MYSQL_VERSION='${MYSQL_INSTALLED_VERSION}'" >> "$ENV_FILE"
        fi
        print_info "✅ MYSQL_VERSION updated to '${MYSQL_INSTALLED_VERSION}' in $ENV_FILE"
    fi
    
    # Install and configure RabbitMQ
    print_info "Installing and configuring RabbitMQ..."
    apt-get install -y rabbitmq-server php8.5-amqp
    systemctl start rabbitmq-server
    systemctl enable rabbitmq-server
    
    local RABBITMQ_USER='remotelabz-amqp'
    local RABBITMQ_PASS='password-amqp'

    if [ -f "$ENV_FILE" ]; then
        local dsn
        dsn=$(grep -E '^MESSENGER_TRANSPORT_DSN=' "$ENV_FILE" 2>/dev/null | head -1 | cut -d'=' -f2- | sed 's/^"//;s/"$//;s/^'\''//;s/'\''$//')
        if [ -n "$dsn" ]; then
            local amqp_match
            amqp_match=$(echo "$dsn" | grep -oP 'amqp://\K[^:@]+:[^:@]+(?=@)' 2>/dev/null)
            if [ -n "$amqp_match" ]; then
                RABBITMQ_USER=$(echo "$amqp_match" | cut -d':' -f1)
                RABBITMQ_PASS=$(echo "$amqp_match" | cut -d':' -f2)
            fi
        fi
    fi

    if ! rabbitmqctl list_users | grep -q "$RABBITMQ_USER"; then
        rabbitmqctl add_user "$RABBITMQ_USER" "$RABBITMQ_PASS"
    fi
    
    rabbitmqctl set_permissions -p '/' "$RABBITMQ_USER" '.*' '.*' '.*'
    rabbitmqctl set_user_tags "$RABBITMQ_USER" administrator
    rabbitmq-plugins enable rabbitmq_management
    systemctl restart rabbitmq-server
    
    print_info "System requirements installation completed! ✅"
}

# ============================================================================
# STEP 2: Setup OpenVPN with EasyRSA
# ============================================================================
setup_openvpn() {
    print_step "STEP 2: Setting up OpenVPN"
    
    cd ~
    
    if [ ! -f EasyRSA-3.0.8.tgz ]; then
        print_info "Downloading EasyRSA..."
        wget -q https://github.com/OpenVPN/easy-rsa/releases/download/v3.0.8/EasyRSA-3.0.8.tgz
    fi
    
    if [ ! -d EasyRSA-3.0.8 ]; then
        print_info "Extracting EasyRSA..."
        tar -xzf EasyRSA-3.0.8.tgz
    fi
    
    if [ ! -L EasyRSA ]; then
        ln -s EasyRSA-3.0.8 EasyRSA
    fi
    
    cd EasyRSA
    
    cat > vars << EOF
set_var EASYRSA_BATCH           "yes"
set_var EASYRSA_REQ_CN         "RemoteLabz-VPNServer-CA"
set_var EASYRSA_REQ_COUNTRY    "FR"
set_var EASYRSA_REQ_PROVINCE   "Grand-Est"
set_var EASYRSA_REQ_CITY       "Reims"
set_var EASYRSA_REQ_ORG        "RemoteLabz"
set_var EASYRSA_REQ_EMAIL      "contact@remotelabz.com"
set_var EASYRSA_REQ_OU         "RemoteLabz-VPNServer"
set_var EASYRSA_ALGO           "ec"
set_var EASYRSA_DIGEST         "sha512"
set_var EASYRSA_CURVE          secp384r1
set_var EASYRSA_CA_EXPIRE      1825
set_var EASYRSA_CERT_EXPIRE    1825
EOF

    sed -i "s/RANDFILE/#RANDFILE/g" openssl-easyrsa.cnf
    
    if [ ! -d pki ]; then
        print_info "Initializing PKI..."
        ./easyrsa init-pki
    fi

    local DEFAULT_CA_PASSPHRASE="R3mot3!abz-0penVPN-CA2020"
    local SSL_CA_KEY_PASSPHRASE=""
    local CA_PASS="" CA_PASS_1="" PEM_PASS="" PEM_PASS_1=""

    if [ -f "$ENV_FILE" ]; then
        SSL_CA_KEY_PASSPHRASE=$(grep -E '^SSL_CA_KEY_PASSPHRASE=' "$ENV_FILE" 2>/dev/null | head -1 | cut -d'=' -f2- | sed 's/^"//;s/"$//;s/^'\''//;s/'\''$//')
    fi

    if [ -f pki/ca.crt ]; then
        : # CA already exists, skip
    else
        if [ -n "$SSL_CA_KEY_PASSPHRASE" ]; then
            print_info "Using SSL_CA_KEY_PASSPHRASE from .env.local for CA certificate..."
            CA_PASS="$SSL_CA_KEY_PASSPHRASE"
            CA_PASS_1="$SSL_CA_KEY_PASSPHRASE"
            PEM_PASS="$SSL_CA_KEY_PASSPHRASE"
            PEM_PASS_1="$SSL_CA_KEY_PASSPHRASE"

            if [ "${#CA_PASS}" -lt 4 ]; then
                print_warning "⚠️ Passphrase from .env.local is too short (< 4 chars), prompting user..."
                CA_PASS=""
            fi

            if [ -z "$CA_PASS" ]; then
                print_warning "===================================================="
                print_warning "CA Certificate Password Setup"
                print_warning "===================================================="
                print_info "The default password in the documentation is: ${DEFAULT_CA_PASS}"
                print_info "This password will be needed to sign VPN certificates"
                print_warning "===================================================="

                while true; do
                    read -p "Enter New CA passphrase [${DEFAULT_CA_PASS}]: " CA_PASS
                    CA_PASS="${CA_PASS:-$DEFAULT_CA_PASS}"

                    if [ "${#CA_PASS}" -lt 4 ]; then
                        echo "⚠️ Passphrase is too short, must be at least 4"
                        continue
                    fi

                    read -p "Re-Enter CA passphrase [${DEFAULT_CA_PASS}]: " CA_PASS_1
                    CA_PASS_1="${CA_PASS_1:-$DEFAULT_CA_PASS}"

                    if [ "${#CA_PASS_1}" != "${#CA_PASS}" ]; then
                        echo "⚠️ Passphrase don't match..."
                        continue
                    fi
                    break
                done

                while true; do
                    read -p "Enter PEM passphrase: " PEM_PASS

                    if [ "${#PEM_PASS}" -lt 4 ]; then
                        echo "⚠️ Passphrase is too short, must be at least 4"
                        continue
                    fi

                    read -p "Verifying - Enter PEM passphrase: " PEM_PASS_1

                    if [ "${#PEM_PASS_1}" != "${#PEM_PASS}" ]; then
                        echo "⚠️ Passphrase don't match..."
                        continue
                    fi
                    break
                done
            fi
        else
            print_warning "===================================================="
            print_warning "CA Certificate Password Setup"
            print_warning "===================================================="
            print_info "The default password in the documentation is: ${DEFAULT_CA_PASS}"
            print_info "This password will be needed to sign VPN certificates"
            print_warning "===================================================="

            while true; do
                read -p "Enter New CA passphrase [${DEFAULT_CA_PASS}]: " CA_PASS
                CA_PASS="${CA_PASS:-$DEFAULT_CA_PASS}"

                if [ "${#CA_PASS}" -lt 4 ]; then
                    echo "⚠️ Passphrase is too short, must be at least 4"
                    continue
                fi

                read -p "Re-Enter CA passphrase [${DEFAULT_CA_PASS}]: " CA_PASS_1
                CA_PASS_1="${CA_PASS_1:-$DEFAULT_CA_PASS}"

                if [ "${#CA_PASS_1}" != "${#CA_PASS}" ]; then
                    echo "⚠️ Passphrase don't match..."
                    continue
                fi
                break
            done

            while true; do
                read -p "Enter PEM passphrase: " PEM_PASS

                if [ "${#PEM_PASS}" -lt 4 ]; then
                    echo "⚠️ Passphrase is too short, must be at least 4"
                    continue
                fi

                read -p "Verifying - Enter PEM passphrase: " PEM_PASS_1

                if [ "${#PEM_PASS_1}" != "${#PEM_PASS}" ]; then
                    echo "⚠️ Passphrase don't match..."
                    continue
                fi
                break
            done
        fi

        cp ./vars ./vars-ca
        sed -i "s/RemoteLabz-VPNServer-CA/RemoteLabz-VPNServer/g" vars

        expect << EOF
spawn ./easyrsa build-ca
expect "Enter New CA Key Passphrase:"
send "${CA_PASS}\r"
expect "Re-Enter New CA Key Passphrase:"
send "${CA_PASS_1}\r"
expect "Enter PEM pass phrase:"
send "${PEM_PASS}\r"
expect "Verifying - Enter PEM pass phrase:"
send "${PEM_PASS_1}\r"
expect eof
EOF
    fi

    if [ ! -f pki/issued/RemoteLabz-VPNServer.crt ]; then
        print_info "Generating server certificate..."
        ./easyrsa gen-req RemoteLabz-VPNServer nopass
        
        # Ensure we have the CA passphrase available for signing
        if [ -z "$PEM_PASS" ] && [ -n "$SSL_CA_KEY_PASSPHRASE" ]; then
            PEM_PASS="$SSL_CA_KEY_PASSPHRASE"
            PEM_PASS_1="$SSL_CA_KEY_PASSPHRASE"
            CA_PASS="$SSL_CA_KEY_PASSPHRASE"
            CA_PASS_1="$SSL_CA_KEY_PASSPHRASE"
        fi
        
        print_info "Signing server certificate (enter CA password)..."
        expect << EOF
spawn ./easyrsa sign-req server RemoteLabz-VPNServer
expect "Enter pass phrase for /root/EasyRSA/pki/private/ca.key:"
send "${PEM_PASS}\r"
expect eof
EOF
    fi
    
    print_info "Installing OpenVPN certificates..."
    mkdir -p /etc/openvpn/server
    cp pki/issued/RemoteLabz-VPNServer.crt /etc/openvpn/server/
    cp pki/private/RemoteLabz-VPNServer.key /etc/openvpn/server/
    cp pki/ca.crt /etc/openvpn/server/
    cp pki/private/ca.key /etc/openvpn/server/
    
    if [ ! -f ta.key ]; then
        openvpn --genkey secret ta.key
    fi
    cp ta.key /etc/openvpn/server/
    
    if [ ! -f dh2048.pem ]; then
        print_info "Generating Diffie-Hellman parameters (this may take a while)..."
        openssl dhparam -out dh2048.pem 2048
    fi
    cp dh2048.pem /etc/openvpn/server/
    
    chown www-data: /etc/openvpn/server -R
    
    cat > /etc/openvpn/server/server.conf << EOF
port 1194
proto udp
dev tun
tun-mtu 1400
mssfix 1360
ca ca.crt
cert RemoteLabz-VPNServer.crt
key RemoteLabz-VPNServer.key
dh dh2048.pem
cipher AES-256-GCM
tls-auth ta.key 0
server 10.8.0.0 255.255.255.0
keepalive 5 30
explicit-exit-notify 1
persist-key
persist-tun
status /var/log/openvpn/openvpn-status.log
log /var/log/openvpn/openvpn.log
verb 1
mute 20
explicit-exit-notify 1
duplicate-cn
push "route 10.11.0.0 255.255.0.0"
EOF

    mkdir -p /var/log/openvpn
    mkdir -p /etc/openvpn/client
    chown :www-data /etc/openvpn/client
    chmod g+w /etc/openvpn/client
    
    systemctl enable openvpn-server@server
    systemctl start openvpn-server@server
    
    print_info "OpenVPN setup completed! ✅"
}

# ============================================================================
# STEP 3: Configure System Parameters
# ============================================================================
configure_system() {
    print_step "STEP 3: Configuring System Parameters"

    # Ensure our custom file has the IP forwarding setting
    print_info "Enabling IP forwarding..."
    set_sysctl_param "net.ipv4.ip_forward" "1"
    
    print_info "Setting system limits..."
    set_sysctl_param "fs.inotify.max_user_watches" "800000"
    set_sysctl_param "fs.inotify.max_user_instances" "500000"
    set_sysctl_param "fs.file-max" "15793398"
    set_sysctl_param "kernel.pty.max" "10000"
    set_sysctl_param "net.ipv6.route.max_size" "20000"
    set_sysctl_param "net.ipv6.conf.all.disable_ipv6" "1"
    set_sysctl_param "net.ipv6.conf.lo.disable_ipv6" "1"
    set_sysctl_param "net.ipv6.conf.default.disable_ipv6" "1"
    
    # Apply all sysctl settings
    sysctl --system
    
    print_info "System configuration completed! ✅"
}

# ============================================================================
# STEP 4: Install and Configure SSL
# ============================================================================
install_ssl() {
    print_step "STEP 4: Configuring SSL Certificates"
    
    # Check if PUBLIC_ADDRESS is set
    if [ -z "$PUBLIC_ADDRESS" ]; then
        PUBLIC_ADDRESS=$(curl -s ifconfig.me 2>/dev/null || echo "localhost")
        print_warning "PUBLIC_ADDRESS not set, using: $PUBLIC_ADDRESS"
    fi
    
    cd /home/${SUDO_USER:-root}
    
    # Download EasyRSA if not present (might be different instance than OpenVPN)
    if [ ! -d /home/${SUDO_USER:-root}/EasyRSA-3.0.8 ]; then
        print_info "Downloading EasyRSA for SSL certificates..."
        wget -q https://github.com/OpenVPN/easy-rsa/releases/download/v3.2.6/EasyRSA-3.2.6.tgz 
        tar -xzf EasyRSA-3.2.6.tgz
    fi
    
    if [ ! -L /home/${SUDO_USER:-root}/EasyRSA ]; then 
        ln -s EasyRSA-3.2.6 EasyRSA
    fi
    
    cd /home/${SUDO_USER:-root}/EasyRSA
    
    # Create a basic cert.cnf if it doesn't exist
    if [ ! -f cert.cnf ]; then
        cat > cert.cnf << EOF
[req]
default_bits = 2048
prompt = no
default_md = sha512
distinguished_name = dn
req_extensions = v3_req

[dn]
C=FR
ST=Grand-Est
L=Reims
O=RemoteLabz
OU=IT
emailAddress=contact@remotelabz.com
CN = 127.0.0.1

[v3_req]
keyUsage = keyEncipherment, dataEncipherment
extendedKeyUsage = serverAuth
subjectAltName = @alt_names

[alt_names]
IP.1 = 127.0.0.1
EOF
    fi
    
    # If cert.cnf exists in the RemoteLabz config, use it
    if [ -f /home/${SUDO_USER:-root}/remotelabz/config/apache/cert.cnf ]; then
        print_info "Using RemoteLabz certificate configuration..."
        cp /home/${SUDO_USER:-root}/remotelabz/config/apache/cert.cnf .
    elif [ -f $REMOTELABZ_PATH/config/apache/cert.cnf ]; then
        cp $REMOTELABZ_PATH/config/apache/cert.cnf .
    fi
    
    # Configure certificate with public address
    sed -i "s/commonName = 127.0.0.1/commonName = ${PUBLIC_ADDRESS}/g" cert.cnf
    sed -i "s/CN = 127.0.0.1/CN = ${PUBLIC_ADDRESS}/g" cert.cnf
    sed -i "s/IP.1 = 127.0.0.1/IP.1 = ${PUBLIC_ADDRESS}/g" cert.cnf
    
    # Generate SSL certificate if it doesn't exist
    if [ ! -f RemoteLabz-WebServer.crt ]; then
        print_info "Generating SSL certificate for ${PUBLIC_ADDRESS}..."
        openssl req -x509 -nodes -days 365 -sha512 -newkey rsa:2048 \
            -keyout RemoteLabz-WebServer.key \
            -out RemoteLabz-WebServer.crt \
            -config /home/${SUDO_USER:-root}/remotelabz/config/apache/cert.cnf
    fi
    
    # Install certificates
    print_info "Installing SSL certificates..."
    mkdir -p /etc/apache2
    cp RemoteLabz-WebServer.crt /etc/apache2/
    cp RemoteLabz-WebServer.key /etc/apache2/
    cat /etc/apache2/RemoteLabz-WebServer.crt /etc/apache2/RemoteLabz-WebServer.key > /etc/apache2/RemoteLabz-WebServer.pem
	chown www-data:www-data /etc/apache2/RemoteLabz-WebServer.key
    
    # Enable SSL module
    print_info "Enabling Apache SSL module..."
    a2enmod ssl
    a2enmod rewrite
    
    # Update RemoteLabz configuration for WSS
    if [ -f /opt/remotelabz/.env.local ]; then
        print_info "Enabling WSS in RemoteLabz configuration..."
        sed -i "s/REMOTELABZ_PROXY_USE_WSS=0/REMOTELABZ_PROXY_USE_WSS=1/g" /opt/remotelabz/.env.local
        
        # Ask about self-signed certificate
        echo ""
        read -p "Is your certificate self-signed? (Y/n): " yn
        yn=${yn:-Y}
        if [[ "$yn" =~ ^[Yy]$ ]]; then
            sed -i "s/REMOTELABZ_PROXY_SSL_CERT_SELFSIGNED=0/REMOTELABZ_PROXY_SSL_CERT_SELFSIGNED=1/g" /opt/remotelabz/.env.local
            print_info "Self-signed certificate configured ✅"
        fi
    fi
    
    # Secure Apache configuration
    print_info "Securing Apache configuration..."
    sed -i "s/ServerTokens OS/ServerTokens Prod/g" /etc/apache2/conf-enabled/security.conf
    sed -i "s/ServerSignature On/ServerSignature Off/g" /etc/apache2/conf-enabled/security.conf
    
    print_info "SSL configuration completed! ✅"
}

# ============================================================================
# STEP 5: Install RemoteLabz Application
# ============================================================================
install_remotelabz_app() {

    print_step "STEP 5: Installing RemoteLabz Application"
    
    # Check if we're in a RemoteLabz source directory
    if [ ! -f "$SCRIPT_DIR/bin/install" ] || [ ! -f "$SCRIPT_DIR/lib/autoload.php" ]; then
	echo "$SCRIPT_DIR/bin/install"
	echo "$SCRIPT_DIR/lib/remotelabz/autoload.php"
        print_error "RemoteLabz installation files not found!"
        print_error "This script must be run from the RemoteLabz source directory"
        print_error "Expected files: install, lib/autoload.php"
        return 1
    fi
    
    # Create log directory
    mkdir -p "$INSTALL_LOG_PATH"
    
    print_info "Running RemoteLabz installer..."
    print_warning "This will install RemoteLabz to $REMOTELABZ_PATH"

    print_info "Installation des bundles..."

    if [ ! -d "$SCRIPT_DIR/lib/network-bundle" ]; then
        git clone https://github.com/remotelabz/network-bundle.git "$SCRIPT_DIR/lib/network-bundle"
        git -C "$SCRIPT_DIR/lib/network-bundle" fetch --tags
        git -C "$SCRIPT_DIR/lib/network-bundle" checkout 1.0.4
    fi

    if [ ! -d "$SCRIPT_DIR/lib/remotelabz-message-bundle" ]; then
        git clone https://github.com/remotelabz/remotelabz-message-bundle.git "$SCRIPT_DIR/lib/remotelabz-message-bundle"
        git -C "$SCRIPT_DIR/lib/remotelabz-message-bundle" fetch --tags
        git -C "$SCRIPT_DIR/lib/remotelabz-message-bundle" checkout 1.0.6
    fi

    # Build install command
    INSTALL_CMD="$SCRIPT_DIR/bin/install"
    INSTALL_CMD="$INSTALL_CMD -e $REMOTELABZ_ENV"
    INSTALL_CMD="$INSTALL_CMD -p $REMOTELABZ_PORT"
    INSTALL_CMD="$INSTALL_CMD -s $REMOTELABZ_MAX_FILESIZE"
    INSTALL_CMD="$INSTALL_CMD --server-name ${PUBLIC_ADDRESS}"
    
    # Execute PHP installer
    php $INSTALL_CMD
    
    if [ $? -eq 0 ]; then
        print_info "RemoteLabz application installed successfully! ✅"
        return 0
    else
        print_error "RemoteLabz application installation failed!"
        print_error "Check logs at $INSTALL_LOG_PATH/install.log"
        return 1
    fi
}

# ============================================================================
# STEP 6: Final Configuration
# ============================================================================
final_configuration() {
    print_step "STEP 6: Final Configuration"
    
    # Configure HAProxy and Apache symlinks if not already done
    if [ -f $REMOTELABZ_PATH/config/haproxy/haproxy.cfg ]; then
        print_info "Configuring HAProxy..."
        rm -f /etc/haproxy/haproxy.cfg
        ln -sf $REMOTELABZ_PATH/config/haproxy/haproxy.cfg /etc/haproxy/haproxy.cfg
    fi
    
    if [ -f $REMOTELABZ_PATH/config/apache/ports.conf ]; then
        print_info "Configuring Apache ports..."
        rm -f /etc/apache2/ports.conf
        ln -sf $REMOTELABZ_PATH/config/apache/ports.conf /etc/apache2/ports.conf
    fi
    
    # Update Apache configuration with server name
    if [ -f /etc/apache2/sites-available/100-remotelabz.conf ]; then
        sed -i "s/ServerName localhost/ServerName ${PUBLIC_ADDRESS}/g" /etc/apache2/sites-available/100-remotelabz.conf
    fi
    
    # Enable SSL site if exists
    if [ -f /etc/apache2/sites-available/200-remotelabz-ssl.conf ]; then
        a2ensite 200-remotelabz-ssl.conf 2>/dev/null || true
    fi

	# Configure SSH for the front
	if [ ! -d /home/remotelabz/.ssh ]; then
        print_info "Configuring SSH directory..."
		mkdir -p /home/remotelabz/.ssh
		chown remotelabz:remotelabz /home/remotelabz/.ssh

        chmod 755 /home/remotelabz/
		chmod 711 /home/remotelabz/.ssh

		runuser -u remotelabz -- ssh-keygen -m PEM -t rsa -f /home/remotelabz/.ssh/myremotelabzfront -N ""
        chmod 644 /home/remotelabz/.ssh/myremotelabzfront
	fi

    # Restart all services
    print_info "Restarting services..."
    systemctl restart apache2
    systemctl restart haproxy
    
    if systemctl is-active --quiet remotelabz-proxy; then
        systemctl restart remotelabz-proxy
    fi
    
    if systemctl is-active --quiet remotelabz; then
        systemctl restart remotelabz
    fi
    
    print_info "Final configuration completed! ✅"
}

# ============================================================================
# Display final instructions
# ============================================================================
show_completion_message() {
    clear
    print_step "Installation Complete!"
    
    echo ""
    print_info "RemoteLabz has been successfully installed!"
    echo ""
    
    echo -e "${YELLOW}📋 Important Information:${NC}"
    echo ""
    echo -e "   ${GREEN}✓${NC} Installation directory: ${BLUE}$REMOTELABZ_PATH${NC}"
    echo -e "   ${GREEN}✓${NC} MySQL user: ${BLUE}${MYSQL_DB_USER}${NC}"
    echo -e "   ${GREEN}✓${NC} MySQL password: ${BLUE}${MYSQL_DB_PASS}${NC}"
    echo -e "   ${GREEN}✓${NC} MySQL database: ${BLUE}${MYSQL_DB_NAME}${NC}"
    echo -e "   ${GREEN}✓${NC} RabbitMQ user: ${BLUE}${RABBITMQ_USER}${NC}"
    echo -e "   ${GREEN}✓${NC} RabbitMQ password: ${BLUE}${RABBITMQ_PASS}${NC}"
    echo -e "   ${GREEN}✓${NC} OpenVPN CA password: ${BLUE}${SSL_CA_KEY_PASSPHRASE}${NC}"
    echo ""
    
    echo -e "${YELLOW}📝 Next Steps:${NC}"
    echo ""
    echo -e "   ${CYAN}2.${NC} Access RemoteLabz:"
    echo -e "      ${BLUE}https://${PUBLIC_ADDRESS}${NC}"
    echo ""
    
    if [ -n "$WORKER_SERVER" ] && [ "$WORKER_SERVER" != "localhost" ]; then
        echo -e "${YELLOW}⚠️  Worker Configuration Required:${NC}"
        echo ""
        echo -e "   Copy SSL certificates to worker server:"
        echo -e "   ${BLUE}cd ~/EasyRSA${NC}"
        echo -e "   ${BLUE}scp RemoteLabz-WebServer.crt user@${WORKER_SERVER}:~${NC}"
        echo -e "   ${BLUE}scp RemoteLabz-WebServer.key user@${WORKER_SERVER}:~${NC}"
        echo ""
        echo -e "   On the worker server:"
        echo -e "   ${BLUE}sudo mv RemoteLabz-WebServer.* /opt/remotelabz-worker/config/certs/${NC}"
        echo -e "   ${BLUE}sed -i 's/REMOTELABZ_PROXY_USE_WSS=0/REMOTELABZ_PROXY_USE_WSS=1/g' /opt/remotelabz-worker/.env.local${NC}"
        echo -e "   ${BLUE}sudo systemctl restart remotelabz-worker${NC}"
        echo ""
    fi
    
    echo -e "${YELLOW}📚 Documentation:${NC}"
    echo -e "   ${BLUE}https://docs.remotelabz.com${NC}"
    echo ""
    
    echo -e "${YELLOW}📋 Configuration files:${NC}"
    echo -e "   ${BLUE}$REMOTELABZ_PATH/.env.local${NC}"
    echo -e "   ${BLUE}/etc/apache2/sites-available/100-remotelabz.conf${NC}"
    echo -e "   ${BLUE}/etc/haproxy/haproxy.cfg${NC}"
    echo ""
    
    echo -e "${GREEN}Thank you for installing RemoteLabz! ❤️${NC}"
    echo ""
}

# ============================================================================
# Main Menu
# ============================================================================
show_menu() {
    clear
    echo "============================================================"
    echo "       RemoteLabz Complete Installation Script"
    echo "============================================================"
    echo ""
    echo "This script will install:"
    echo "  • System requirements (Apache, PHP, MySQL, RabbitMQ, etc.)"
    echo "  • OpenVPN with EasyRSA"
    echo "  • SSL certificates"
    echo "  • RemoteLabz application"
    echo ""
    echo "Installation options:"
    echo ""
    echo "1) Full automatic installation (recommended)"
    echo "2) Install system requirements only"
    echo "3) Setup OpenVPN only"
    echo "4) Configure SSL only"
    echo "5) Install RemoteLabz application only"
    echo "6) Custom installation (select steps)"
    echo "7) Exit"
    echo ""
    echo "============================================================"
}

# ============================================================================
# Custom installation menu
# ============================================================================
custom_installation() {
    clear
    echo "============================================================"
    echo "       Custom Installation"
    echo "============================================================"
    echo ""
    echo "Select components to install:"
    echo ""
    
    read -p "Install system requirements? (Y/n): " do_req
    do_req=${do_req:-Y}
    
    read -p "Setup OpenVPN? (Y/n): " do_vpn
    do_vpn=${do_vpn:-Y}
    
    read -p "Configure system parameters? (Y/n): " do_sys
    do_sys=${do_sys:-Y}
    
    read -p "Configure SSL? (Y/n): " do_ssl
    do_ssl=${do_ssl:-Y}
    
    read -p "Install RemoteLabz application? (Y/n): " do_app
    do_app=${do_app:-Y}
    
    echo ""
    print_info "Starting custom installation..."
    sleep 2
    
    [[ "$do_req" =~ ^[Yy]$ ]] && install_requirements
    [[ "$do_vpn" =~ ^[Yy]$ ]] && setup_openvpn
    [[ "$do_sys" =~ ^[Yy]$ ]] && configure_system
    [[ "$do_ssl" =~ ^[Yy]$ ]] && install_ssl
    [[ "$do_app" =~ ^[Yy]$ ]] && install_remotelabz_app && final_configuration
    
    show_completion_message
}

# ============================================================================
# Full installation
# ============================================================================
full_installation() {
    print_info "Starting full RemoteLabz installation..."
    sleep 2
    
    setup_env_file
    install_requirements
    setup_openvpn
    configure_system
    install_ssl
    
    if install_remotelabz_app; then
        final_configuration
        show_completion_message
    else
        print_error "Installation failed during application setup"
        exit 1
    fi
}

# ============================================================================
# Main function
# ============================================================================
main() {
    check_root
    
    # Handle command line arguments
    case "${1:-}" in
        --full|-f)
            full_installation
            exit 0
            ;;
        --requirements|-r)
            install_requirements
            exit 0
            ;;
        --openvpn|-v)
            setup_openvpn
            exit 0
            ;;
        --ssl|-s)
            setup_env_file
            install_ssl
            exit 0
            ;;
        --app|-a)
            setup_env_file
            install_remotelabz_app
            final_configuration
            exit 0
            ;;
        --help|-h)
            echo "Usage: $0 [OPTION]"
            echo ""
            echo "Options:"
            echo "  -f, --full           Full automatic installation (recommended)"
            echo "  -r, --requirements   Install system requirements only"
            echo "  -v, --openvpn        Setup OpenVPN only"
            echo "  -s, --ssl            Configure SSL only"
            echo "  -a, --app            Install RemoteLabz application only"
            echo "  -h, --help           Display this help message"
            echo ""
            echo "If no option is provided, an interactive menu will be shown."
            exit 0
            ;;
    esac
    
    # Interactive menu
    while true; do
        show_menu
        read -p "Enter your choice [1-7]: " choice
        
        case $choice in
            1)
                full_installation
                exit 0
                ;;
            2)
                install_requirements
                read -p "Press Enter to continue..."
                ;;
            3)
                setup_openvpn
                read -p "Press Enter to continue..."
                ;;
            4)
                setup_env_file
                install_ssl
                read -p "Press Enter to continue..."
                ;;
            5)
                setup_env_file
                if install_remotelabz_app; then
                    final_configuration
                fi
                read -p "Press Enter to continue..."
                ;;
            6)
                custom_installation
                exit 0
                ;;
            7)
                print_info "Exiting..."
                exit 0
                ;;
            *)
                print_error "Invalid option. Please choose 1-7."
                sleep 2
                ;;
        esac
    done
}

# ============================================================================
# Script Entry Point
# ============================================================================
main "$@"
