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

# Global variables for MySQL, RabbitMQ, and SSL so they can be displayed in the completion message
MYSQL_DB_USER=""
MYSQL_DB_PASS=""
MYSQL_DB_NAME=""
RABBITMQ_USER=""
RABBITMQ_PASS=""
SSL_CA_KEY_PASSPHRASE=""

# Global variables pour les workers
WORKER_COUNT=0
declare -a WORKER_IPS=()
declare -a WORKER_TOPOLOGIES=()
declare -a WORKER_SSH_PWS=()
declare -a WORKER_SSH_USERS=()
declare -a WORKER_SSH_USERS_PW=()
declare -a WORKER_EXISTING_PWS=()
WORKER_ANSIBLE_PLAYBOOK="${SCRIPT_DIR}/bin/install_worker.yml"

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
# Set env mercure
# ============================================================================


setup_mercure_env_file() {
    local MERCURE_ENV_FILE="${SCRIPT_DIR}/.env.mercure"

    print_info "Your .env.mercure used is ${MERCURE_ENV_FILE}"

    if [ -f "$MERCURE_ENV_FILE" ]; then
        print_info "Environment file .env.mercure already exists, loading it"
        source "$MERCURE_ENV_FILE"
        return 0
    fi

    print_info "Environment file .env.mercure not found, creating it..."

    local JWT_KEY=""
    if [ -f "$ENV_FILE" ]; then
        JWT_KEY=$(grep -E '^MERCURE_JWT_SECRET=' "$ENV_FILE" | head -n1 | cut -d= -f2- | tr -d '"')
    fi
    if [ -z "$JWT_KEY" ] || [ "$JWT_KEY" == "!ChangeThisMercureHubJWTSecretKey!" ]; then
        JWT_KEY=$(openssl rand -hex 32)
        print_info "✅ New JWT key generated"
    fi

    cat > "$MERCURE_ENV_FILE" <<EOF
MERCURE_PUBLISHER_JWT_KEY="${JWT_KEY}"
MERCURE_SUBSCRIBER_JWT_KEY="${JWT_KEY}"
EOF
    chmod 600 "$MERCURE_ENV_FILE"
    print_info "✅ $MERCURE_ENV_FILE created"

    if [ -f "$ENV_FILE" ]; then
        if grep -q "^MERCURE_JWT_SECRET=" "$ENV_FILE"; then
            sed -i "s|^MERCURE_JWT_SECRET=.*|MERCURE_JWT_SECRET=\"${JWT_KEY}\"|" "$ENV_FILE"
        else
            echo "MERCURE_JWT_SECRET=\"${JWT_KEY}\"" >> "$ENV_FILE"
        fi
        print_info "✅ MERCURE_JWT_SECRET synchronized in $ENV_FILE"
    fi

    source "$MERCURE_ENV_FILE"
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
    apt purge -y unattended-upgrades
    apt -y autoremove
    apt-get -y upgrade

    # Install base packages
    print_info "Installing base packages..."
    apt install -y fail2ban sudo exim4 apache2 curl gnupg zip unzip ntpsec openvpn qemu-utils openssl git expect
    
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
    
    # Install REDIS
    print_info "Installing Redis..."
    apt install -y redis-server php8.5-redis
    systemctl start redis-server
    systemctl enable redis-server

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
        php -r "copy('https://getcomposer.org/download/2.10.3/composer.phar', 'composer.phar');"
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
    
    MERCURE_DEB_PATH="${SCRIPT_DIR}/mercure_1.0.2_linux_amd64.deb"

    # Install and configure MySQL
    print_info "Installing and configuring MySQL..."
    apt-get install -y mysql-server
    systemctl start mysql
    systemctl enable mysql
    
    # Load environment values for MySQL from the env file
    local MYSQL_ROOT_PASS="R3mot3Lab\$-2026\$"
    MYSQL_DB_USER="user"
    MYSQL_DB_PASS="Mysql-Pa33wrd\$"
    MYSQL_DB_NAME="remotelabz"

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

    # Write SQL file with proper escaping for special characters ($ signs)
    {
        echo "ALTER USER IF EXISTS 'root'@'localhost' IDENTIFIED BY '${MYSQL_ROOT_PASS}';"
        echo "DELETE FROM mysql.user WHERE User='';"
        echo "DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');"
        echo "DROP DATABASE IF EXISTS test;"
        echo "DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';"
        echo "CREATE USER IF NOT EXISTS '${MYSQL_DB_USER}'@'localhost' IDENTIFIED WITH caching_sha2_password BY '${MYSQL_DB_PASS}';"
        echo "CREATE DATABASE IF NOT EXISTS ${MYSQL_DB_NAME};"
        echo "GRANT ALL ON ${MYSQL_DB_NAME}.* TO '${MYSQL_DB_USER}'@'localhost';"
        echo "FLUSH PRIVILEGES;"
    } > /tmp/mysql_secure_sql.sql

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
    
    RABBITMQ_USER='remotelabz-amqp'
    RABBITMQ_PASS='password-amqp'

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
    
    print_info "Installation de Mercure Hub"
    wget -O "$MERCURE_DEB_PATH" https://github.com/dunglas/mercure/releases/download/v1.0.2/mercure_1.0.2_linux_amd64.deb
    wget -O "${MERCURE_DEB_PATH}.sha256" https://github.com/dunglas/mercure/releases/download/v1.0.2/checksums.txt
    sha256sum -c "${MERCURE_DEB_PATH}.sha256" --ignore-missing

    print_info "System requirements installation completed! ✅"
}

# ============================================================================
# STEP 2: Setup OpenVPN with EasyRSA
# ============================================================================
setup_openvpn() {
    print_step "STEP 2: Setting up OpenVPN"
    
    cd ~
    
    if [ ! -f EasyRSA-3.2.6.tgz ]; then
        print_info "Downloading EasyRSA..."
        wget -q https://github.com/OpenVPN/easy-rsa/releases/download/v3.2.6/EasyRSA-3.2.6.tgz
    fi
    
    if [ ! -d EasyRSA-3.2.6 ]; then
        print_info "Extracting EasyRSA..."
        tar --warning=no-unknown-keyword -xzf EasyRSA-3.2.6.tgz
    fi
    
    if [ ! -L EasyRSA ]; then
        ln -s EasyRSA-3.2.6 EasyRSA
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
    SSL_CA_KEY_PASSPHRASE=""
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
set timeout 30
spawn ./easyrsa build-ca
expect {
    -re "Enter New CA Key Passphrase:" {
        send "${CA_PASS}\r"
        exp_continue
    }
    -re "(Re-Enter|Confirm) New CA Key Passphrase:" {
        send "${CA_PASS_1}\r"
        exp_continue
    }
    -re "Enter PEM pass phrase:" {
        send "${PEM_PASS}\r"
        exp_continue
    }
    -re "Verifying - Enter PEM pass phrase:" {
        send "${PEM_PASS_1}\r"
        exp_continue
    }
    eof {
        # process finished on its own, nothing more to do
    }
    timeout {
        puts "\n\[EasyRSA build-ca\] Timed out waiting for a prompt - aborting."
        exit 1
    }
}
EOF

        # expect always exits 0 even if the spawned process failed, so
        # explicitly verify the CA was actually created before moving on.
        if [ ! -f pki/private/ca.key ] || [ ! -f pki/ca.crt ]; then
            print_error "CA key/cert was not created (pki/private/ca.key or pki/ca.crt missing)."
            print_error "build-ca likely failed or an expect prompt pattern didn't match your EasyRSA version's output."
            exit 1
        fi
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
set timeout 30
spawn ./easyrsa sign-req server RemoteLabz-VPNServer
expect {
    -re "Enter pass phrase for .*ca.key:" {
        send "${PEM_PASS}\r"
        exp_continue
    }
    eof {
        # process finished on its own, nothing more to do
    }
    timeout {
        puts "\n\[EasyRSA sign-req\] Timed out waiting for CA passphrase prompt - aborting."
        exit 1
    }
}
EOF

        if [ ! -f pki/issued/RemoteLabz-VPNServer.crt ]; then
            print_error "Server certificate was not signed (pki/issued/RemoteLabz-VPNServer.crt missing)."
            print_error "Check that the CA passphrase used matches the one entered during build-ca."
            exit 1
        fi
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
    if [ ! -d /home/${SUDO_USER:-root}/EasyRSA-3.2.6 ]; then
        print_info "Downloading EasyRSA for SSL certificates..."
        wget -q https://github.com/OpenVPN/easy-rsa/releases/download/v3.2.6/EasyRSA-3.2.6.tgz 
        tar --warning=no-unknown-keyword -xzf EasyRSA-3.2.6.tgz
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
    if [ -f "$SCRIPT_DIR/config/apache/cert.cnf" ]; then
        print_info "Using RemoteLabz certificate configuration..."
        cp "$SCRIPT_DIR/config/apache/cert.cnf" .
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
            -config "$SCRIPT_DIR/config/apache/cert.cnf"
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
        git -C "$SCRIPT_DIR/lib/remotelabz-message-bundle" checkout 1.0.10
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
    
	#Configure Mercure Hub
    print_info "Configuration de Mercure Hub"
    cp -f "${SCRIPT_DIR}/mercure_1.0.2_linux_amd64.deb" /tmp/
    apt install -y /tmp/mercure_1.0.2_linux_amd64.deb
    cp -f "${SCRIPT_DIR}/.env.mercure" /opt/remotelabz/.env.mercure.local 2>/dev/null || true
    cp /opt/remotelabz/bin/systemd/remotelabz-mercure.service /etc/systemd/system
    systemctl daemon-reload
    systemctl enable --now remotelabz-mercure
    rm -f /tmp/mercure_1.0.2_linux_amd64.deb

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

	# Configure SSH for the front (generate keys as remotelabz user)
	if [ ! -d /home/remotelabz/.ssh ]; then
        print_info "Configuring SSH directory and generating keys as remotelabz user..."
		mkdir -p /home/remotelabz/.ssh
		chown remotelabz:remotelabz /home/remotelabz/.ssh
        chmod 700 /home/remotelabz/
		chmod 700 /home/remotelabz/.ssh
		ssh-keygen -m PEM -t rsa -f /home/remotelabz/.ssh/myremotelabzfront -N ""
		chown remotelabz:remotelabz /home/remotelabz/.ssh/myremotelabzfront
		chown remotelabz:remotelabz /home/remotelabz/.ssh/myremotelabzfront.pub
		chmod 600 /home/remotelabz/.ssh/myremotelabzfront
	fi

	# Copy public key to system keys directory for PHP app (SshService)
	mkdir -p /opt/remotelabz/config/system/keys
	if [ ! -f /opt/remotelabz/config/system/keys/myremotelabzfront.pub ]; then
		cp /home/remotelabz/.ssh/myremotelabzfront.pub /opt/remotelabz/config/system/keys/myremotelabzfront.pub
	fi
	cp /home/remotelabz/.ssh/myremotelabzfront /opt/remotelabz/config/system/keys/myremotelabzfront 2>/dev/null || true
	chmod 600 /opt/remotelabz/config/system/keys/myremotelabzfront 2>/dev/null || true
	chmod 644 /opt/remotelabz/config/system/keys/myremotelabzfront.pub 2>/dev/null || true

    # Restart all services
    print_info "Restarting services..."
    systemctl restart apache2
    systemctl restart haproxy
    
    if systemctl is-active --quiet remotelabz-proxy; then
        systemctl restart remotelabz-proxy.service
    fi
    
    if systemctl is-active --quiet remotelabz; then
        systemctl restart remotelabz.service
    fi
    
    print_info "Final configuration completed! ✅"
}

# ============================================================================
# STEP 6b: Deploy Workers with Ansible
# ============================================================================
configure_workers() {
    echo ""
    print_step "Worker Deployment Configuration"
    echo ""
    
    # Check if ansible is installed
    if ! command -v ansible-playbook &> /dev/null; then
        print_warning "Ansible is not installed. Installing..."
        apt-get update
        apt-get install -y ansible
        print_info "Ansible installed ✅"
    fi
    
    # Check if sshpass is installed (required for SSH key deployment)
    if ! command -v sshpass &> /dev/null; then
        print_warning "sshpass is not installed. Installing..."
        apt-get update
        apt-get install -y sshpass
        print_info "sshpass installed ✅"
    fi
    
    # Disable host key checking for Ansible
    export ANSIBLE_HOST_KEY_CHECKING=False
    
    # Ensure SUDO_USER is set (fallback to current user if not running with sudo)
    export SUDO_USER="${SUDO_USER:-$USER}"
    
    # Generate current user SSH key if it doesn't exist
    local current_user="$SUDO_USER"
    local current_key="${HOME}/.ssh/id_ed25519"
    if [ ! -f "$current_key" ] || [ ! -f "${current_key}.pub" ]; then
        print_info "No SSH key found, generating ed25519 keypair for $current_user..."
        mkdir -p "${HOME}/.ssh"
        chmod 700 "${HOME}/.ssh"
        ssh-keygen -t ed25519 -f "$current_key" -N "" -C "$current_user@remotelabz" -q
        # Fix ownership if running as root
        chown "$current_user:$current_user" "${HOME}/.ssh/id_ed25519" "${HOME}/.ssh/id_ed25519.pub" 2>/dev/null || true
        chmod 600 "${HOME}/.ssh/id_ed25519" 2>/dev/null || true
        chmod 644 "${HOME}/.ssh/id_ed25519.pub" 2>/dev/null || true
        print_info "SSH key generated at ${current_key} ✅"
    fi
    
    # Generate remotelabz SSH key if it doesn't exist
    if ! sudo -u remotelabz test -f /home/remotelabz/.ssh/myremotelabzfront 2>/dev/null; then
        print_info "No SSH key found for remotelabz user, generating keypair..."
        sudo -u remotelabz mkdir -p /home/remotelabz/.ssh
        sudo -u remotelabz chmod 700 /home/remotelabz/.ssh
        sudo -u remotelabz ssh-keygen -m PEM -t rsa -f /home/remotelabz/.ssh/myremotelabzfront -N "" -q
        sudo -u remotelabz chmod 600 /home/remotelabz/.ssh/myremotelabzfront
        print_info "SSH key generated for remotelabz user ✅"
    fi
    
    # Copy public key to system keys directory for PHP app (SshService)
    mkdir -p /opt/remotelabz/config/system/keys
    if [ ! -f /opt/remotelabz/config/system/keys/myremotelabzfront.pub ]; then
        cp /home/remotelabz/.ssh/myremotelabzfront.pub /opt/remotelabz/config/system/keys/myremotelabzfront.pub
    fi
    cp /home/remotelabz/.ssh/myremotelabzfront /opt/remotelabz/config/system/keys/myremotelabzfront 2>/dev/null || true
    chmod 600 /opt/remotelabz/config/system/keys/myremotelabzfront 2>/dev/null || true
    chmod 644 /opt/remotelabz/config/system/keys/myremotelabzfront.pub 2>/dev/null || true
    
    # Check if playbook exists
    if [ ! -f "$WORKER_ANSIBLE_PLAYBOOK" ]; then
        print_error "Ansible playbook not found at $WORKER_ANSIBLE_PLAYBOOK"
        print_error "Please ensure install_worker.yml is in the bin/ directory"
        return 1
    fi
    
    # Ask if user wants to deploy workers
    read -p "Do you want to deploy RemoteLabz workers? (y/n) [n]: " deploy_workers
    deploy_workers=${deploy_workers:-n}
    
    if [[ ! "$deploy_workers" =~ ^[Yy]$ ]]; then
        print_info "Skipping worker deployment"
        return 0
    fi
    
    # Ask how many workers
    read -p "How many workers? [0]: " worker_count
    worker_count=${worker_count:-0}
    
    if [ "$worker_count" -eq 0 ]; then
        print_info "No workers to deploy"
        return 0
    fi
    
    # Create temporary inventory and vars directory
    local tmp_inventory="/tmp/worker_inventory_$$.yml"
    local tmp_vars_dir="/tmp/worker_vars_$$.d"
    mkdir -p "$tmp_vars_dir"
    
    cat > "$tmp_inventory" <<'EOF'
all:
  children:
    workers:
      hosts:
EOF
    
    # Collect config for each worker
    for (( i=1; i<=worker_count; i++ )); do
        print_info "─────────────────────────────────────────"
        print_info "Worker $i of $worker_count"
        print_info "─────────────────────────────────────────"
        echo ""
        
        local worker_name="worker-$i"
        local worker_ip=""
        local vars_file="${tmp_vars_dir}/worker_${i}.yml"
        local worker_topology=1
        local adm_ip=""
        local worker_user="$SUDO_USER"
        
        read -p "Worker user account [$worker_user]: " worker_user
        worker_user=${worker_user:-$SUDO_USER}
        
        read -p "Deployment topology (1=single-server, 2=multi-server) [1]: " topology
        topology=${topology:-1}
        
        if [ "$topology" -eq 2 ]; then
            read -p "Worker IP address: " worker_ip
            if [ -z "$worker_ip" ]; then
                print_error "Worker IP is required for multi-server deployment"
                return 1
            fi
            
            echo ""
            print_info "Front server interfaces (for reference only):"
            ip -br addr show 2>/dev/null | grep -E 'UP' | awk '{print "  - " $1 ": " $3}'
            echo ""
            read -p "Worker administration interface [ensX]: " adm_iface
            adm_iface=${adm_iface:-"ensX"}
            
            adm_ip=$(ip -4 addr show "$adm_iface" 2>/dev/null | grep -oP '(?<=inet\s)\d+(\.\d+){3}' | head -1)
            
            read -p "Worker data interface (or Enter to use $adm_iface): " data_iface
            data_iface=${data_iface:-$adm_iface}
            
            read -p "Worker internet access interface (or Enter to use $data_iface): " int_iface
            int_iface=${int_iface:-$data_iface}
        else
            
            echo ""
            print_info "Available network interfaces:"
            ip -br addr show 2>/dev/null | grep -E 'UP' | awk '{print "  - " $1 ": " $3}'
            echo ""
            
            local default_adm_iface=$(ip route show default 2>/dev/null | awk '{print $5}' | head -1)
            default_adm_iface=${default_adm_iface:-"ensX"}
            if [ -n "$default_adm_iface" ]; then
                local nat_ip=$(ip -4 addr show "$default_adm_iface" 2>/dev/null | grep -oP '(?<=inet\s)\d+(\.\d+){3}' | head -1)
                case "$nat_ip" in
                    10.0.2.*) # VirtualBox NAT - skip it
                        default_adm_iface=$(ip -br addr show 2>/dev/null | grep -E 'UP' | grep -v 'lo' | grep -v '10\.0\.2\.' | head -1 | awk '{print $1}')
                        ;;
                esac
            fi
            default_adm_iface=${default_adm_iface:-"ensX"}
            read -p "Administration interface [$default_adm_iface]: " adm_iface
            adm_iface=${adm_iface:-$default_adm_iface}
            
            adm_ip=$(ip -4 addr show "$adm_iface" 2>/dev/null | grep -oP '(?<=inet\s)\d+(\.\d+){3}' | head -1)
            
            worker_ip="$adm_ip"
            print_info "Worker IP: $worker_ip (same as front on this interface)"
            
            read -p "Data interface (or Enter to use $adm_iface): " data_iface
            data_iface=${data_iface:-$adm_iface}
            
            read -p "Internet access interface (or Enter to use $data_iface): " int_iface
            int_iface=${int_iface:-$data_iface}
        fi
        
        if [ "$topology" -eq 1 ] && [ -n "$adm_ip" ]; then
            front_ip="$adm_ip"
        fi
        read -p "Front server IP [${front_ip:-$adm_ip}]: " front_ip
        front_ip=${front_ip:-${adm_ip:-127.0.0.1}}
        
        # 6. Data network
        read -p "Data network [10.22.128.0/24]: " data_network
        data_network=${data_network:-"10.22.128.0/24"}
        
        # 8. Internal data IP
        read -p "Internal data IP [10.22.128.2/24]: " data_ip
        data_ip=${data_ip:-"10.22.128.2"}
        
        # 9. Data network gateway
        read -p "Data network gateway [10.22.128.254]: " data_gw
        data_gw=${data_gw:-"10.22.128.254"}
        
        # 10. VPN
        read -p "Is the VPN hosted on this worker? (y/n) [y]: " vpn_local
        vpn_local=${vpn_local:-y}
        
        # 11. SSH passwords
        echo ""
        print_warning "IMPORTANT: Change default passwords for security!"
        
        # Password for the user that already exists on the worker (e.g., giz)
        local existing_user_pw=""
        if [ "$topology" -eq 2 ]; then
            read -s -r -p "Password for user '${worker_user}' on worker [leave empty if none]: " existing_user_pw
            echo ""
        else
            existing_user_pw=""
        fi
        
        # Password for the remotelabz-worker user that will be created by Ansible
        read -p "Password for remotelabz-worker user [leave empty for default]: " ssh_pw
        read -p "Password for remotelabz (front) user [leave empty for default]: " front_pw
        
        # 12. WSS
        read -p "Enable WSS (Secure WebSocket)? (y/n) [y]: " wss
        wss=${wss:-y}
        
        # 13. Contact email
        local contact_email=""
        if [ -f "$ENV_FILE" ]; then
            contact_email=$(grep -E '^CONTACT_MAIL=' "$ENV_FILE" | head -n1 | cut -d= -f2- | tr -d '"')
        fi
        read -p "Contact email [$contact_email]: " contact_mail
        contact_mail=${contact_mail:-$contact_email}
        
        # Generate Ansible vars file for this worker
        cat > "$vars_file" <<EOF
    worker_topology: "$topology"
    worker_user: "$worker_user"
    worker_adm_interface: "$adm_iface"
    worker_data_interface: "$data_iface"
    worker_ip: "$worker_ip"
    worker_internet_interface: "$int_iface"
    worker_front_ip: "$front_ip"
    worker_data_network: "$data_network"
    worker_data_ip: "$data_ip"
    worker_data_gateway: "$data_gw"
    worker_vpn_local: "$vpn_local"
    worker_ssh_password: "$ssh_pw"
    worker_front_password: "$front_pw"
    worker_wss: "$wss"
    worker_contact_mail: "$contact_mail"
EOF
        
        cat >> "$tmp_inventory" <<EOF
        $worker_name:
          ansible_host: $worker_ip
EOF
        
        WORKER_IPS+=("$worker_ip")
        WORKER_TOPOLOGIES+=("$topology")
        WORKER_VARS_FILES+=("$vars_file")
        WORKER_NAMES+=("$worker_name")
        WORKER_SSH_PWS+=("$ssh_pw")
        WORKER_SSH_USERS+=("$worker_user")
        WORKER_SSH_USERS_PW+=("$ssh_pw")
        WORKER_EXISTING_PWS+=("$existing_user_pw")
        
        print_info "Worker $i configuration saved ✅"
        echo ""
    done
    
    local has_single=0
    local has_multi=0
    for t in "${WORKER_TOPOLOGIES[@]}"; do
        if [ "$t" -eq 1 ]; then has_single=1; fi
        if [ "$t" -eq 2 ]; then has_multi=1; fi
    done
    
    if [ "$has_single" -eq 0 ] && [ "$has_multi" -eq 0 ]; then
        return 0
    fi
    
    # Ask for worker password FIRST (needed for inventory and SSH setup)
    if [ "$has_multi" -eq 1 ]; then
        print_info "─────────────────────────────────────────"
        print_info "SSH Access Configuration"
        print_info "─────────────────────────────────────────"
        echo ""
        
        # Auto-detect worker password from .env.local or .env
        local default_worker_pass=""
        local env_candidate=""
        if [ -f "$ENV_FILE" ]; then
            env_candidate="$ENV_FILE"
        elif [ -f "${SCRIPT_DIR}/.env" ]; then
            env_candidate="${SCRIPT_DIR}/.env"
        fi
        
        if [ -n "$env_candidate" ]; then
            default_worker_pass=$(grep -E '^SSH_USER_PASSWD=' "$env_candidate" | head -n1 | cut -d= -f2- | tr -d '"')
        fi
        
        if [ -n "$default_worker_pass" ]; then
            read -s -p "Password for user remotelabz-worker [${default_worker_pass}]: " worker_pass
            worker_pass=${worker_pass:-$default_worker_pass}
        else
            read -s -p "Password for user remotelabz-worker: " worker_pass
        fi
        echo ""
        
        if [ -z "$worker_pass" ]; then
            print_error "Password is required to access workers"
            return 1
        fi
    fi
    
    # ========================================================================
    # Deploy workers with Ansible (single = localhost, multi = SSH)
    # ========================================================================
    print_step "Deploying Workers with Ansible"
    echo ""
    
    # Build a unified inventory for all workers
    local final_inventory="/tmp/worker_inventory_final_$$.yml"
    cat > "$final_inventory" <<'EOF'
all:
  children:
    workers:
      hosts:
EOF
    
    # Add localhost for single-server workers, remote IPs for multi-server workers
    for (( i=0; i<worker_count; i++ )); do
        local wname="${WORKER_NAMES[$i]}"
        local wip="${WORKER_IPS[$i]}"
        local wtopo="${WORKER_TOPOLOGIES[$i]}"
        
        if [ "$wtopo" -eq 1 ]; then
            # Single-server: add as localhost with local connection
            cat >> "$final_inventory" <<EOF
        $wname:
          ansible_connection: local
          ansible_user: $SUDO_USER
EOF
        else
            # Multi-server: connect as existing user (giz) who will create remotelabz-worker
            local wuser="${WORKER_SSH_USERS[$i]}"
            local wpw="${WORKER_EXISTING_PWS[$i]}"
            cat >> "$final_inventory" <<EOF
        $wname:
          ansible_host: $wip
          ansible_user: $wuser
          ansible_password: "${wpw}"
          ansible_become_pass: "${wpw}"
          ansible_become_method: sudo
          ansible_become_flags: "-S"
EOF
        fi
    done
    
    echo "Inventory:"
    cat "$final_inventory"
    echo ""
    
    # Deploy each worker with Ansible
    local ansible_ssh_key="${HOME}/.ssh/id_ed25519"
    
    for (( i=0; i<worker_count; i++ )); do
        if [ "${WORKER_TOPOLOGIES[$i]}" -eq 0 ]; then continue; fi
        
        local worker_name="${WORKER_NAMES[$i]}"
        local wtopo="${WORKER_TOPOLOGIES[$i]}"
        local vars_file="${WORKER_VARS_FILES[$i]}"
        
        print_info "─────────────────────────────────────────"
        print_info "Deploying $worker_name (topology: $wtopo)..."
        print_info "─────────────────────────────────────────"
        echo ""
        
        # Multi-server: set become pass for sudo
        if [ "$wtopo" -eq 2 ]; then
            local wexistingpw="${WORKER_EXISTING_PWS[$i]}"
            echo "  Worker existing user: ${WORKER_SSH_USERS[$i]}"
            echo "  Worker existing password length: ${#wexistingpw}"
            export ANSIBLE_BECOME_PASS="$wexistingpw"
        fi

        # Read the SSH public key from remotelabz user
        local ssh_key_content=""
        if [ -f "/home/remotelabz/.ssh/myremotelabzfront.pub" ]; then
            ssh_key_content=$(cat /home/remotelabz/.ssh/myremotelabzfront.pub)
        fi

        ansible-playbook "$WORKER_ANSIBLE_PLAYBOOK" \
            -i "$final_inventory" \
            --private-key "$ansible_ssh_key" \
            -e "target_hosts=${worker_name}" \
            -e "@${vars_file}" \
            -e ansible_become_method=sudo \
            2>&1 | tee /tmp/worker_install_${worker_name}.log

        unset ANSIBLE_BECOME_PASS
        
        if [ ${PIPESTATUS[0]} -ne 0 ]; then
            print_error "Failed to deploy $worker_name (check /tmp/worker_install_${worker_name}.log)"
        else
            print_info "$worker_name deployed successfully ✅"
        fi

        # Single-server: send SSH key to localhost
        if [ "$wtopo" -eq 1 ] && [ -f "/home/remotelabz/.ssh/myremotelabzfront.pub" ]; then
            local worker_user="${WORKER_SSH_USERS[$i]}"
            sudo -u remotelabz ssh-copy-id -i /home/remotelabz/.ssh/myremotelabzfront.pub "${worker_user}@127.0.0.1" 2>&1 || true
            print_info "SSH key sent to $worker_name (single-server) ✅"
        fi
        echo ""
    done
    
    rm -f "$final_inventory"
    
    # Update .env.local with worker IPs
    if [ ${#WORKER_IPS[@]} -gt 0 ]; then
        print_info "Updating .env.local with worker IPs..."
        local workers_str=$(IFS=,; echo "${WORKER_IPS[*]}")
        
        if grep -q "^WORKER_SERVER=" "$ENV_FILE"; then
            sed -i "s|^WORKER_SERVER=.*|WORKER_SERVER=\"${workers_str}\"|g" "$ENV_FILE"
        else
            echo "WORKER_SERVER=\"${workers_str}\"" >> "$ENV_FILE"
        fi
        
        # Disable single-server mode if workers are configured
        if grep -q "^DEPLOY_SINGLE_SERVER=1" "$ENV_FILE"; then
            sed -i "s|^DEPLOY_SINGLE_SERVER=.*|DEPLOY_SINGLE_SERVER=0|g" "$ENV_FILE"
        fi
        
        print_info "Workers configured: $workers_str ✅"
    fi
    
    # Cleanup temporary files
    rm -rf "$tmp_inventory" "$tmp_vars_dir"
    
    print_info "Worker deployment completed ✅"
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
    echo "1) Install system requirements only"
    echo "2) Setup OpenVPN only"
    echo "3) Configure SSL only"
    echo "4) Install RemoteLabz application only"
    echo "5) Configure workers deployment"
    echo "6) Custom installation (select steps)"
    echo "7) Full automatic installation (recommended)"
    echo "8) Exit"
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
	setup_mercure_env_file
    install_requirements
    setup_openvpn
    configure_system
    install_ssl
    
    if install_remotelabz_app; then
        configure_workers
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
        read -p "Enter your choice [1-9]: " choice
        
        case $choice in
            1)
                install_requirements
                read -p "Press Enter to continue..."
                ;;
            2)
                setup_openvpn
                read -p "Press Enter to continue..."
                ;;
            3)
                setup_env_file
                install_ssl
                read -p "Press Enter to continue..."
                ;;
            4)
                setup_env_file
                if install_remotelabz_app; then
                    final_configuration
                fi
                read -p "Press Enter to continue..."
                ;;
            5)
                configure_workers
                read -p "Press Enter to continue..."
                ;;
            6)
                custom_installation
                exit 0
                ;;
            7)
                full_installation
                exit 0
                ;;
            8)
                print_info "Exiting..."
                exit 0
                ;;
            *)
                print_error "Invalid option. Please choose 1-8."
                sleep 2
                ;;
        esac
    done
}

# ============================================================================
# Script Entry Point
# ============================================================================
main "$@"
