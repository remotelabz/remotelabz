#!/bin/bash
# Script: /opt/remotelabz/bin/git-version-update.sh
# Description: Génère un fichier JSON avec les informations Git

PROJECT_DIR="/opt/remotelabz"
OUTPUT_FILE="/opt/remotelabz/var/git-version.json"
ENV_FILE="$PROJECT_DIR/.env"
ENV_LOCAL_FILE="$PROJECT_DIR/.env.local"

# Charger les variables du fichier .env
if [ ! -f "$ENV_FILE" ]; then
    echo "{\"error\": \"Environment file not found: $ENV_FILE\"}" > "$OUTPUT_FILE"
    exit 1
fi

# Fonction pour extraire une variable du .env (avec priorité .env.local)
get_env_var() {
    local var_name=$1
    local default_value=$2
    local value=""
    
    # D'abord chercher dans .env.local s'il existe
    if [ -f "$ENV_LOCAL_FILE" ]; then
        value=$(grep "^${var_name}=" "$ENV_LOCAL_FILE" | cut -d '=' -f2- | sed 's/^"//;s/"$//' | head -n 1)
    fi
    
    # Si pas trouvé dans .env.local, chercher dans .env
    if [ -z "$value" ]; then
        value=$(grep "^${var_name}=" "$ENV_FILE" | cut -d '=' -f2- | sed 's/^"//;s/"$//' | head -n 1)
    fi
    
    echo "${value:-$default_value}"
}

# Récupérer GITHUB_REPOSITORY depuis .env ou utiliser une valeur par défaut
GITHUB_REPO=$(get_env_var "GITHUB_REPOSITORY" "https://github.com/remotelabz/remotelabz")

cd "$PROJECT_DIR" || exit 1

# Vérifier que nous sommes dans un dépôt Git
if [ ! -d ".git" ]; then
    echo '{"error": "Not a git repository"}' > "$OUTPUT_FILE"
    exit 1
fi

# Récupérer les informations Git
COMMIT_HASH=$(git rev-parse HEAD 2>/dev/null || echo "unknown")
COMMIT_SHORT=$(git rev-parse --short HEAD 2>/dev/null || echo "unknown")
BRANCH=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "unknown")

# Lire la version du fichier
if [ -f "$PROJECT_DIR/version" ]; then
    VERSION=$(cat "$PROJECT_DIR/version" | tr -d '\n\r')
else
    VERSION="unknown"
fi

# Générer l'URL du commit
COMMIT_URL="${GITHUB_REPO}/commit/${COMMIT_SHORT}"

# Créer le fichier JSON
cat > "$OUTPUT_FILE" << EOF
{
    "version_file": "$VERSION",
    "commit": "$COMMIT_HASH",
    "commit_short": "$COMMIT_SHORT",
    "branch": "$BRANCH",
    "commit_url": "$COMMIT_URL",
    "github_url": "$GITHUB_REPO",
    "updated_at": "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
}
EOF

chmod 644 "$OUTPUT_FILE"