# Services systemd RemoteLabz

Ce répertoire contient les fichiers de configuration systemd pour tous les services
et timers de RemoteLabz.

## Récapitulatif des services

| Type | Nom | Description | Fréquence / Déclencheur |
|------|-----|-------------|------------------------|
| **Timer** | `remotelabz-clean-notification.timer` | Nettoyage des notifications lues | Quotidien à 2h00 UTC |
| **Service** | `remotelabz-clean-notification.service` | Exécute `app:notifications:clean` | Déclenché par le timer |
| **Timer** | `remotelabz-git-version-update.timer` | Mise à jour de la version Git | Toutes les heures (dès 30s après boot) |
| **Service** | `remotelabz-git-version-update.service` | Met à jour le cache de version | Déclenché par le timer |
| **Timer** | `remotelabz-route-monitor.timer` | Monitoring des routes des labs | Toutes les 5 minutes |
| **Service** | `remotelabz-route-monitor.service` | Vérifie la disponibilité des endpoints | Déclenché par le timer |
| **Timer** | `remotelabz-login-logs.timer` | Nettoyage des logs de connexion | Quotidien à 2h00 UTC |
| **Service** | `remotelabz-login-logs.service` | Exécute `app:login-logs:clean` | Déclenché par le timer |
| **Daemon** | `remotelabz.service` | Consommateur de messages (messenger) | En continu (démarrage) |
| **Daemon** | `remotelabz-proxy.service` | Proxy de redirection | En continu (démarrage) |

---

## Installation

### 1. Copier les fichiers dans /etc/systemd/system

```bash
sudo cp remotelabz-clean-notification.service /etc/systemd/system/
sudo cp remotelabz-clean-notification.timer /etc/systemd/system/
sudo cp remotelabz-git-version-update.service /etc/systemd/system/
sudo cp remotelabz-git-version-update.timer /etc/systemd/system/
sudo cp remotelabz-route-monitor.service /etc/systemd/system/
sudo cp remotelabz-route-monitor.timer /etc/systemd/system/
sudo cp remotelabz-login-logs.service /etc/systemd/system/
sudo cp remotelabz-login-logs.timer /etc/systemd/system/
sudo cp remotelabz.service /etc/systemd/system/
sudo cp remotelabz-proxy.service /etc/systemd/system/
```

### 2. Recharger la configuration systemd

```bash
sudo systemctl daemon-reload
```

### 3. Activer et démarrer les services

#### Services en continu (démarrage automatique)

```bash
sudo systemctl enable --now remotelabz.service
sudo systemctl enable --now remotelabz-proxy.service
```

#### Timers de maintenance

```bash
sudo systemctl enable --now remotelabz-clean-notification.timer
sudo systemctl enable --now remotelabz-git-version-update.timer
sudo systemctl enable --now remotelabz-route-monitor.timer
sudo systemctl enable --now remotelabz-login-logs.timer
```

#### Service de monitoring des routes (avec timer)

```bash
sudo systemctl enable --now remotelabz-route-monitor.service
```

---

## Vérification

### Voir tous les timers activés

```bash
sudo systemctl list-timers --all | grep remotelabz
```

### Vérifier l'état des services

```bash
# Service en continu
sudo systemctl status remotelabz.service
sudo systemctl status remotelabz-proxy.service

# Service de monitoring
sudo systemctl status remotelabz-route-monitor.service

# Timers
sudo systemctl status remotelabz-clean-notification.timer
sudo systemctl status remotelabz-git-version-update.timer
sudo systemctl status remotelabz-route-monitor.timer
sudo systemctl status remotelabz-login-logs.timer
```

### Voir les logs journalisés

```bash
# Logs du consommateur de messages
sudo journalctl -u remotelabz.service -f

# Logs du proxy
sudo systemctl status remotelabz-proxy.service

# Logs du monitoring des routes
sudo journalctl -u remotelabz-route-monitor.service -f

# Logs du nettoyage des notifications
sudo journalctl -u remotelabz-clean-notification.service --since yesterday

# Logs du nettoyage des logs de connexion
sudo journalctl -u remotelabz-login-logs.service --since yesterday

# Logs de la mise à jour de version
sudo journalctl -u remotelabz-git-version-update.service --since yesterday
```

### Exécuter manuellement un service déclenché par timer

```bash
# Nettoyage des notifications
sudo systemctl start remotelabz-clean-notification.service

# Nettoyage des logs de connexion
sudo systemctl start remotelabz-login-logs.service

# Mise à jour de version (immédiat)
sudo systemctl start remotelabz-git-version-update.service
```

---

## Arrêter / Désactiver un service

### Arrêter temporairement (redémarrage le remettra en marche)

```bash
sudo systemctl stop remotelabz.service
sudo systemctl stop remotelabz-proxy.service
```

### Désactiver définitivement

```bash
# Pour un service en continu
sudo systemctl disable --now remotelabz.service

# Pour un timer
sudo systemctl disable --now remotelabz-clean-notification.timer
sudo systemctl disable --now remotelabz-git-version-update.timer
sudo systemctl disable --now remotelabz-route-monitor.timer
sudo systemctl disable --now remotelabz-login-logs.timer

# Pour un service avec timer
sudo systemctl disable --now remotelabz-route-monitor.service
```

### Supprimer les fichiers de configuration

```bash
sudo rm /etc/systemd/system/remotelabz*.service
sudo rm /etc/systemd/system/remotelabz*.timer
sudo systemctl daemon-reload
```

---

## Détails de chaque service

### 1. remotelabz.service — Consommateur de messages

```ini
Type=simple
Restart=always
RestartSec=5
ExecStart=/usr/bin/env php /opt/remotelabz/bin/console messenger:consume front --memory-limit=128M --time-limit=3600
```

Consomme les messages de la queue `front` via Symfony Messenger.
- Redémarre automatiquement en cas de crash (5s de délai)
- Limite mémoire : 128M (soft), 256M (hard)
- Consomme pendant 1h max avant de se recharger

### 2. remotelabz-proxy.service — Proxy de redirection

```ini
Type=simple
Restart=always
RestartSec=1
ExecStart=/usr/bin/env php /opt/remotelabz/bin/remotelabz-proxy
```

Gère la redirection des requêtes vers les labs.
- Redémarre instantanément en cas de crash (1s de délai)

### 3. remotelabz-route-monitor.service — Monitoring des routes

```ini
Type=simple
Restart=always
RestartSec=300
ExecStart=/usr/bin/php /opt/remotelabz/bin/console app:route:monitor --no-interaction
```

Vérifie la disponibilité des endpoints des labs.
- Redémarre toutes les 5 minutes en cas de crash
- Exécuté toutes les 5 minutes via le timer

### 4. remotelabz-clean-notification.timer/service — Nettoyage des notifications

```ini
OnCalendar=*-*-* 02:00:00
Persistent=true
RandomizedDelaySec=10min
```

Supprime les notifications lues de plus de 30 jours.
- Exécute la commande : `php bin/console app:notifications:clean`
- Déclenché quotidiennement à 2h00 UTC ± 10 min
- Si le serveur était éteint, s'exécute au prochain démarrage

### 5. remotelabz-git-version-update.timer/service — Mise à jour de version

```ini
OnBootSec=30s
OnUnitActiveSec=1h
Persistent=true
```

Met à jour le cache de version Git.
- Exécute : `/opt/remotelabz/bin/remotelabz-git-version-update.sh`
- Exécuté 30s après le boot, puis toutes les heures

### 6. remotelabz-route-monitor.timer — Monitoring des routes

```ini
OnBootSec=1min
OnUnitActiveSec=5min
Unit=remotelabz-route-monitor.service
```

Déclenche le monitoring des routes toutes les 5 minutes.

### 7. remotelabz-login-logs.timer/service — Nettoyage des logs de connexion

```ini
OnCalendar=*-*-* 02:00:00
Persistent=true
RandomizedDelaySec=10min
```

Supprime les logs de connexion de plus d'1 an.
- Exécute la commande : `php bin/console app:login-logs:clean`
- Déclenché quotidiennement à 2h00 UTC ± 10 min
- Si le serveur était éteint, s'exécute au prochain démarrage

---

## Commandes utilitaires

```bash
# Lister tous les timers systemd
sudo systemctl list-timers --all

# Redémarrer un service
sudo systemctl restart remotelabz.service

# Relire la configuration après modification
sudo systemctl daemon-reload

# Voir les erreurs récentes d'un service
sudo journalctl -u remotelabz.service -p err --no-pager

# Suivre les logs en temps réel
sudo journalctl -u remotelabz.service -f

# Redémarrer tous les services RemoteLabz
sudo systemctl restart remotelabz.service remotelabz-proxy.service
```
