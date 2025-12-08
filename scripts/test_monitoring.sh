#!/bin/bash

# RemoteLabz Monitoring Test Script
# This script tests the new monitoring services

echo "========================================"
echo "RemoteLabz Monitoring Services Test"
echo "========================================"
echo ""

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Function to check if a command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Function to print status
print_status() {
    if [ $1 -eq 0 ]; then
        echo -e "${GREEN}✓${NC} $2"
    else
        echo -e "${RED}✗${NC} $2"
    fi
}

# Function to print warning
print_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

# Check PHP
echo "1. Checking PHP installation..."
if command_exists php; then
    PHP_VERSION=$(php -v | head -n 1 | cut -d " " -f 2 | cut -d "." -f 1,2)
    print_status 0 "PHP $PHP_VERSION installed"
else
    print_status 1 "PHP not found"
    exit 1
fi
echo ""

# Check PHP extensions
echo "2. Checking PHP extensions..."

# Check SSH2
if php -m | grep -q ssh2; then
    print_status 0 "ssh2 extension installed"
else
    print_status 1 "ssh2 extension NOT installed"
    echo "   Install with: sudo apt-get install php-ssh2"
fi

# Check OpenSSL
if php -m | grep -q openssl; then
    print_status 0 "openssl extension installed"
else
    print_status 1 "openssl extension NOT installed"
    echo "   Install with: sudo apt-get install php-openssl"
fi
echo ""

# Check certificate files
echo "3. Checking certificate files..."

check_file() {
    local file="$1"
    local name="$2"
    
    if [ -f "$file" ]; then
        if [ -r "$file" ]; then
            print_status 0 "$name exists and is readable"
        else
            print_warning "$name exists but is NOT readable"
            echo "   Fix with: sudo chmod 644 $file"
        fi
    else
        print_status 1 "$name NOT found at: $file"
    fi
}

# Load .env.local file if exists
if [ -f ".env.local" ]; then
    source <(grep -v '^#' .env.local | sed 's/^/export /')>/dev/null
    
    check_file "$SSL_CA_CERT" "VPN CA Certificate"
    check_file "$SSL_CA_KEY" "VPN CA Key"
    check_file "$SSL_TLS_KEY" "VPN TLS Key"
    check_file "$REMOTELABZ_PROXY_SSL_CERT" "Proxy SSL Certificate"
    check_file "$REMOTELABZ_PROXY_SSL_KEY" "Proxy SSL Key"
else
    print_warning ".env.local file not found in current directory"
    echo "   Please run this script from your RemoteLabz root directory"
fi
echo ""

# Check SSH key files
echo "4. Checking SSH key files..."
if [ -f ".env.local" ]; then
    check_file "$SSH_USER_PUBLICKEY_FILE" "SSH Public Key"
    check_file "$SSH_USER_PRIVATEKEY_FILE" "SSH Private Key"
else
    print_warning "Cannot check SSH keys without .env file"
fi
echo ""

# Check if certificate is about to expire
echo "5. Checking certificate expiration dates..."
if [ -f ".env.local" ]; then
    if [ -f "$SSL_CA_CERT" ] && [ -r "$SSL_CA_CERT" ]; then
        EXPIRY_DATE=$(openssl x509 -enddate -noout -in "$SSL_CA_CERT" | cut -d= -f2)
        EXPIRY_TIMESTAMP=$(date -d "$EXPIRY_DATE" +%s 2>/dev/null)
        CURRENT_TIMESTAMP=$(date +%s)
        DAYS_REMAINING=$(( ($EXPIRY_TIMESTAMP - $CURRENT_TIMESTAMP) / 86400 ))
        
        if [ $DAYS_REMAINING -lt 0 ]; then
            print_status 1 "VPN CA Certificate EXPIRED on $EXPIRY_DATE"
        elif [ $DAYS_REMAINING -lt 30 ]; then
            print_warning "VPN CA Certificate expires in $DAYS_REMAINING days ($EXPIRY_DATE)"
        else
            print_status 0 "VPN CA Certificate valid for $DAYS_REMAINING days"
        fi
    fi
    
    if [ -f "$REMOTELABZ_PROXY_SSL_CERT" ] && [ -r "$REMOTELABZ_PROXY_SSL_CERT" ]; then
        EXPIRY_DATE=$(openssl x509 -enddate -noout -in "$REMOTELABZ_PROXY_SSL_CERT" | cut -d= -f2)
        EXPIRY_TIMESTAMP=$(date -d "$EXPIRY_DATE" +%s 2>/dev/null)
        DAYS_REMAINING=$(( ($EXPIRY_TIMESTAMP - $CURRENT_TIMESTAMP) / 86400 ))
        
        if [ $DAYS_REMAINING -lt 0 ]; then
            print_status 1 "Proxy SSL Certificate EXPIRED on $EXPIRY_DATE"
        elif [ $DAYS_REMAINING -lt 30 ]; then
            print_warning "Proxy SSL Certificate expires in $DAYS_REMAINING days ($EXPIRY_DATE)"
        else
            print_status 0 "Proxy SSL Certificate valid for $DAYS_REMAINING days"
        fi
    fi
fi
echo ""

# Test SSH connection to workers
echo "6. Testing SSH connections to workers..."
if [ -f ".env.local" ]; then
    # You would need to customize this based on your worker IPs
    print_warning "Manual SSH test recommended:"
    echo "   ssh -i $SSH_USER_PRIVATEKEY_FILE $SSH_USER_WORKER@<WORKER_IP>"
else
    print_warning "Cannot test SSH without .env file"
fi
echo ""

# Check Symfony command
echo "7. Checking Symfony console..."
if [ -f "bin/console" ]; then
    print_status 0 "Symfony console found"
    
    echo "   You can run the monitoring check with:"
    echo "   php bin/console app:check:system"
    echo "   php bin/console app:check:system --ssh"
    echo "   php bin/console app:check:system --cert"
    echo "   php bin/console app:check:system --json"
else
    print_status 1 "Symfony console NOT found"
    echo "   Are you in the RemoteLabz root directory?"
fi
echo ""
