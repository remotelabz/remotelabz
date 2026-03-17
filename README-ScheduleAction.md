# Actions planifiées — Guide d'intégration

## Fichiers à déployer

| Fichier généré                        | Destination dans le projet                                      |
|---------------------------------------|-----------------------------------------------------------------|
| `ScheduledAction.php`                 | `src/Entity/ScheduledAction.php`                                |
| `ScheduledActionRepository.php`       | `src/Repository/ScheduledActionRepository.php`                  |
| `ScheduledActionService.php`          | `src/Service/Instance/ScheduledActionService.php`               |
| `ScheduledActionsRunCommand.php`      | `src/Command/ScheduledActionsRunCommand.php`                    |
| `ScheduledActionController.php`       | `src/Controller/ScheduledActionController.php`                  |
| `Version20260315000000.php`           | `migrations/Version20260315000000.php`                          |

---

## 1. Prérequis : méthode `findByLabAndGroup` dans `LabInstanceRepository`

Le service `ScheduledActionService` appelle `$this->labInstanceRepository->findByLabAndGroup($lab, $group)`.
Ajoute cette méthode dans `src/Repository/LabInstanceRepository.php` :

```php
/**
 * Retourne les LabInstances d'un lab appartenant à un groupe donné.
 * Le critère de rattachement au groupe est que le owner de l'instance
 * soit membre du groupe.
 *
 * @return \App\Entity\LabInstance[]
 */
public function findByLabAndGroup(\App\Entity\Lab $lab, \App\Entity\Group $group): array
{
    return $this->createQueryBuilder('li')
        ->join('li.owner', 'u')
        ->join('u.groups', 'g')
        ->where('li.lab = :lab')
        ->andWhere('g = :group')
        ->setParameter('lab', $lab)
        ->setParameter('group', $group)
        ->getQuery()
        ->getResult();
}
```

---

## 2. Migration Doctrine

```bash
# Appliquer la migration
php bin/console doctrine:migrations:migrate

# Ou si tu n'utilises pas le bundle migrations :
php bin/console doctrine:schema:update --force
```

---

## 3. Crontab (une seule ligne, générique)

```cron
* * * * * php /var/www/html/bin/console app:scheduled-actions:run >> /var/log/remotelabz/scheduled.log 2>&1
```

Le runner s'exécute chaque minute, sélectionne les actions dont `scheduled_at <= NOW()` et `status = pending`,
et les exécute. La granularité est donc d'une minute.

---

## 4. API REST — Exemples d'utilisation

### Créer une planification (start)

```http
POST /api/scheduled-actions
Content-Type: application/json

{
  "labUuid":     "aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee",
  "groupUuid":   "ffffffff-1111-2222-3333-444444444444",
  "action":      "start",
  "scheduledAt": "2026-04-01 08:00:00"
}
```

### Créer la planification d'arrêt correspondante

```http
POST /api/scheduled-actions
Content-Type: application/json

{
  "labUuid":     "aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee",
  "groupUuid":   "ffffffff-1111-2222-3333-444444444444",
  "action":      "stop",
  "scheduledAt": "2026-04-01 18:00:00"
}
```

### Lister ses planifications

```http
GET /api/scheduled-actions
```

Réponse :
```json
[
  {
    "uuid": "...",
    "lab": { "uuid": "...", "name": "Lab TP Réseau" },
    "group": { "uuid": "...", "name": "Groupe L3 Info" },
    "action": "start",
    "scheduledAt": "2026-04-01T08:00:00+02:00",
    "executedAt": null,
    "status": "pending",
    "errorMessage": null,
    "executionReport": null,
    "createdBy": { "uuid": "...", "name": "Jean Dupont" },
    "createdAt": "2026-03-15T10:30:00+02:00"
  }
]
```

### Annuler une planification

```http
DELETE /api/scheduled-actions/{uuid}
```

---

## 5. Commande Symfony — Usages manuels

```bash
# Voir les actions dues sans les exécuter (dry-run)
php bin/console app:scheduled-actions:run --dry-run

# Exécuter une planification précise par UUID (test / debug)
php bin/console app:scheduled-actions:run --uuid=aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee

# Exécution normale (appelée par le cron)
php bin/console app:scheduled-actions:run
```

---

## 6. Règles métier importantes

| Règle | Détail |
|-------|--------|
| **Doublon refusé** | Si une planification `pending` avec la même combinaison lab+groupe+action existe déjà, la création renvoie HTTP 409 |
| **Date dans le futur** | `scheduledAt` doit être strictement postérieure à l'instant de création |
| **Idempotence des états** | Un device déjà `started` est ignoré (skipped) lors d'un `start` ; idem pour `stop` sur un device déjà arrêté |
| **Reset natif exclu** | Les devices avec hyperviseur `natif` sont ignorés lors d'un `reset`, cohérent avec le comportement existant |
| **Protection double exécution** | Le runner passe le statut à `running` avant d'agir ; un second runner concurrent ignorera l'entrée |
| **Droits enseignant** | Un enseignant ne peut planifier que sur les labs qu'il a créés ou auxquels il a accès via ses groupes |

---

## 7. Flux complet — Exemple "TP du 1er avril"

```
[Enseignant — UI ou curl]
  POST /api/scheduled-actions  { action: "start", scheduledAt: "2026-04-01 08:00" }
  POST /api/scheduled-actions  { action: "stop",  scheduledAt: "2026-04-01 18:00" }

[Cron — 2026-04-01 08:00]
  app:scheduled-actions:run
  → trouve l'action start → status=running → instanceManager->start() × N devices
  → status=done, executionReport sauvegardé

[Cron — 2026-04-01 18:00]
  app:scheduled-actions:run
  → trouve l'action stop  → status=running → instanceManager->stop() × N devices
  → status=done
```