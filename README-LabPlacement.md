# Placement des labs sur les workers — Répartition en cas de lancement simultané

## Fichiers concernés

| Rôle | Fichier |
|------|---------|
| Réservations mémoire des instances en attente | `src/Service/Worker/LabPlacementCache.php` |
| Choix du worker (score + réservations) | `src/Service/Worker/WorkerManager.php` |
| Création de l'instance + réservation | `src/Service/Instance/InstanceManager.php` |
| Placement + lancement asynchrones | `src/MessageHandler/LabLaunchRequestMessageHandler.php` |
| Libération de la réservation (état final) | `src/MessageHandler/InstanceStateMessageHandler.php` |
| Pool Redis du cache | `config/packages/cache.yaml` (`lab_placement`) |
| Injection du service | `config/services.yaml` (`App\Service\Worker\LabPlacementCache`) |
| File de placement | `config/packages/messenger.yaml` (`LabLaunchRequestMessage` → `front`) |
| Consommateur unique de la file `front` | `bin/systemd/remotelabz.service` |
| Test de répartition | `tests/Service/Worker/WorkerManagerPlacementTest.php` |

Voir aussi `README-ScheduleAction.md` : un démarrage groupé planifié
(`ScheduledActionService`) est le cas d'usage qui déclenche le problème.

---

## 1. Le problème : la course sur `getFreeWorker()`

`WorkerManager::getFreeWorker($lab)` :

1. interroge chaque worker disponible (`GET /stats/hardwarelight`) pour obtenir
   `memory`, `memory_total`, `cpu`, `disk['rlz-vg']`, `lxcfs` ;
2. calcule un score pour chacun (`loadBalancing()`) ;
3. renvoie le worker au score le plus élevé.

Or, lors d'un démarrage groupé (lab lancé pour tout un groupe, action
planifiée, etc.), N
instances sont créées quasi simultanément et N appels à `getFreeWorker()`
s'enchaînent. Deux effets se combinent :

- **les stats sont décalées** : elles ne reflètent que les labs déjà en cours
  d'exécution. Une instance créée mais pas encore lancée est invisible pour les
  stats ;
- **les stats sont identiques d'un appel à l'autre** (les workers ne changent
  pas entre deux appels espacés de quelques millisecondes) : tous les scores
  sont égaux, donc le même worker gagne N fois.

Résultat : les N instances partent sur le même worker (souvent le premier de
la liste), qui se retrouve surchargé pendant que les autres restent inutilisés.

---

## 2. Le mécanisme

Le placement est sorti du chemin de création et les mémoires déjà attribuées
sont gardées dans un cache partagé, de sorte que chaque décision voit le
résultat des décisions précédentes.

```
Requête HTTP / runner planifié          File "front" (1 seul consommateur)      Worker
--------------------------------        ---------------------------------      ------
InstanceManager::create()
  ├─ INSERT LabInstance (worker = NULL)
  ├─ LabPlacementCache::add(uuid, RAM)      ← réservation mémorisée
  └─ dispatch LabLaunchRequestMessage ────► LabLaunchRequestMessageHandler
                                              ├─ getFreeWorker(lab)
                                              │    ├─ stats HTTP de chaque worker
                                              │    └─ + réservations déjà affectées
                                              │       (LabPlacementCache::memoryAssignedTo)
                                              ├─ LabInstance.workerIp = W
                                              ├─ dispatch ACTION_CREATE ─────────────► création
                                              └─ LabPlacementCache::assign(uuid, W)
                                                                        (réservation
                                                                         affectée à W)

                                            ◄─ InstanceStateMessage (STATE_CREATED)
                                              LabPlacementCache::remove(uuid)
                                                   ← réservation libérée, les stats
                                                     du worker la prennent désormais
                                                     en compte directement
```

Trois points font toute la démonstration :

1. **Le placement est asynchrone et sérialisé.** `LabLaunchRequestMessage` est
   routé sur la file `front`, consommée par **un seul** processus
   (`bin/systemd/remotelabz.service`). Chaque placement est ainsi traité après le
   précédent : au moment du `getFreeWorker()` suivant, la réservation de
   l'instance précédente est déjà en base.
2. **La réservation est partagée entre tous les process PHP.** Le cache
   `lab_placement` est un pool Redis (`config/packages/cache.yaml`), donc la
   requête qui crée l'instance (PHP-FPM) et le consommateur Messenger (CLI) se
   voient mutuellement.
3. **La réservation compte comme de la mémoire réellement utilisée** dans le
   score et dans le garde-fou de `getFreeWorker()` (cf. §3).

---

## 3. Ce que `getFreeWorker()` fait des réservations

```php
// WorkerManager::getFreeWorker()
$reservedMemory     = $this->placementCache->memoryAssignedTo($workerIp); // Mo
$reservedMemoryPct  = ($reservedMemory / $usage['memory_total']) * 100;    // %
$adjustedMemory     = $usage['memory'] + $reservedMemoryPct;               // % utilisé réel + réservé

$availableMemory = (100 - $adjustedMemory) * $usage['memory_total'];
if ($availableMemory < $memory) { /* worker écarté */ }

$val = $this->loadBalancing($adjustedMemory, ...); // score du worker
```

- `memoryAssignedTo($ip)` additionne uniquement les entrées du cache dont
  `workerIp === $ip` : une réservation « non affectée » (déjà `add()`, pas
  encore `assign()`) n'est imputée à aucun worker — c'est pourquoi `assign()`
  a lieu avant le `getFreeWorker()` suivant.
- `adjustedMemory` remplace `memory` dans `loadBalancing()` : un worker qui
  vient de se voir attribuer des labs voit son `memoryScore` baisser
  (poids 0.3), il perd donc le duel suivant au profit d'un worker vierge.
- `$availableMemory` vaut en réalité **100 × la mémoire restante en Mo**
  (`(100 - %) × total` sans division par 100). La comparaison
  `$availableMemory < $memory` écarte donc un worker quand sa mémoire restante
  (réelle − réservée) passe sous `$memory / 100`, c'est-à-dire quand elle
  devient **négative** : c'est un garde-fou de saturation, pas un contrôle de
  capacité exact (cf. §7).

Conséquence pratique : les stats figées d'un appel à l'autre sont corrigées par
les réservations, et le score décroît à chaque lab placé → les labs défilent
worker après worker.

---

## 4. Cycle de vie d'une réservation

| Événement | Appel | Fichier |
|-----------|-------|---------|
| Instance créée (worker encore inconnu) | `add(uuid, RAM)` | `InstanceManager::create()` (`src/Service/Instance/InstanceManager.php:244`) |
| Worker retenu | `assign(uuid, ip)` | `LabLaunchRequestMessageHandler` (`src/MessageHandler/LabLaunchRequestMessageHandler.php:139`) |
| Instance déjà placée (message rejoué) | `remove(uuid)` | `LabLaunchRequestMessageHandler.php:92` |
| Instance introuvable (supprimée entre-temps) | `remove(uuid)` | `LabLaunchRequestMessageHandler.php:85` |
| Aucun worker disponible | `remove(uuid)` | `LabLaunchRequestMessageHandler.php:104` |
| Suppression d'une instance jamais placée | `remove(uuid)` | `InstanceManager::delete()` (`src/Service/Instance/InstanceManager.php:275`) |
| État final `created` / `deleted` / `error` | `remove(uuid)` | `InstanceStateMessageHandler` (`src/MessageHandler/InstanceStateMessageHandler.php:212`) |
| Filet de sécurité | expiration au bout de **3600 s** | `LabPlacementCache::TTL` |

La libération la plus importante est celle de l'état final : à partir de là, le
worker consomme réellement la mémoire et ses stats la reflètent, il n'y a plus
de doublon à tenir en cache.

---

## 5. Garanties et prérequis

| Prérequis | Détail |
|-----------|--------|
| **Un seul consommateur `front`** | `bin/systemd/remotelabz.service`. Le cache est en lecture-modification-écriture sur une clé Redis unique : deux consommateurs concurrents peuvent écraser les réservations de l'autre (perdues silencieusement) et retrouver le bug d'origine. Ne pas lancer un second `messenger:consume front` en parallèle (par exemple à la main en dev pendant que la systemd tourne). |
| **Redis disponible** | Pool `lab_placement` (`cache.adapter.redis`). Sans Redis, `fetchPending()`/`save()` attrapent l'exception, journalisent un `warning` et le placement continue **sans réservation** : c'est du best-effort, un lancement n'est jamais bloqué par le cache. En cas de Redis arrêté, il n'y a **pas** de message de permission : le seul symptôme est un `warning` `[LabPlacementCache]::` dans les logs, et les labs recommencent à s'empiler sur le même worker. |
| **Idempotence** | `add()` ignore un uuid déjà suivi ; le handler saute si `workerIp` est déjà renseigné (message rejoué) et libère alors la réservation. |
| **Écriture/lecture par des process différents** | Le pool `lab_placement` est en **Redis** (depuis le commit « Add Redis server ») : PHP-FPM, le consommateur Messenger et le runner planifié parlent tous au même serveur Redis, il n'y a plus de fichier `var/cache/**/pools/app/…` à renommer → plus de message `Failed to save key "lab_placement.pending" … rename(…): Permission denied`, qui venait de l'ancien backend fichiers du pool. |
| **Aucun verrou applicatif** | La sérialisation vient de la file Messenger, pas d'un lock. C'est aussi ce qui rend le scénario reproductible dans le test (§6). |

---

## 6. Vérification — le test de répartition

```bash
php vendor/bin/phpunit tests/Service/Worker/WorkerManagerPlacementTest.php
```

Le test rejoue **exactement** la séquence de production (`add` →
`getFreeWorker` → `assign`, puis `remove` à l'état final) sur 3 workers aux
stats strictement identiques et figées (le cas réel : 3 workers de
3955.328 Mo, labs de 1000 Mo).

| Test | Ce qu'il prouve |
|------|-----------------|
| `testWithoutReservationsEveryLabGoesToTheSameWorker` | **Le bug d'origine** : sans réservation, les 6 appels renvoient `10.0.0.1` — c'est exactement ce qui se passait avant. |
| `testBatchOfLabsIsSpreadOverAllWorkers` | **La répartition** : les 6 labs sont répartis `10.0.0.1, .2, .3, .1, .2, .3` (2 par worker) et aucun worker ne dépasse sa RAM. |
| `testReservationExcludesAWorkerThatStillLooksIdle` | Un worker qui affiche `0 %` de mémoire mais qui a déjà 2 × 2000 Mo réservés est écarté au profit du worker chargé à 60 % : les stats seules mentent, le cache a raison. |
| `testNoWorkerIsSelectedWhenEveryWorkerIsSaturated` | `getFreeWorker()` renvoie `''` : l'instance passe en erreur au lieu d'être empilée. |
| `testBatchStopsOnceEveryWorkerIsFull` | Sur 13 labs, 12 sont placés (4 + 4 + 4) et le 13e obtient `''` : la saturation coupe le placement. |
| `testReservationIsReleasedWhenTheInstanceReachesItsFinalState` | Après `remove()`, la mémoire réservée retombe à 0 et le premier worker redevient le meilleur candidat. |
| `testOnlyAssignedReservationsAreCountedByGetFreeWorker` | Sémantique : `add()` seul n'impute rien, `assign()` impute à un worker. |

Résultat obtenu :

```
PHPUnit 9.6.37

.......                                        7 / 7 (100%)

OK (7 tests, 25 assertions)
```

> Le test surcharge `checkWorkersLightAction()` (les stats HTTP sont fournies
> directement) : c'est la seule partie non couverte, elle se résume à un appel
> `GET /stats/hardwarelight` par worker. Le score, l'exclusion et la prise en
> compte des réservations sont, eux, le code réel de `getFreeWorker()`.

#### Vérifier que le pool est bien Redis

```bash
# Le conteneur compilé doit instancier RedisAdapter avec REDIS_DSN
grep -R "lab_placement" var/cache/prod/Container*/getLabPlacementService.php

# Redis joignable + clé réellement stockée en mémoire
redis-cli ping
redis-cli --scan --pattern '*lab*'

# 0 occurrence du message de permission
grep -a 'lab_placement.pending' var/log/*.log | wc -l

# Aucun avertissement de repli (Redis éteint => réservations désactivées)
grep -a '\[LabPlacementCache\]' var/log/*.log | wc -l
```

Démonstration multi-process (3 processus PHP distincts, même pool que celui du
conteneur) :

```
PID 22757 add   -> {"cross-process-check-uuid":{"memory":1234,"workerIp":null,…}}
redis-cli --scan                -> JIuT6jtS6E:lab_placement.pending
PID 22760 read  -> {"cross-process-check-uuid":{…}}   (vu depuis un 2e processus)
PID 22760 assign ok
PID 22762 memoryAssignedTo(10.0.0.9) = 1234
PID 22762 after remove -> []
grep -rl "cross-process-check-uuid" var/cache   -> AUCUN fichier créé sur disque
```

### Sur une installation réelle

```bash
# Placement effectif des dernières instances
grep -E "launched on worker|excluded: insufficient free memory" var/log/dev.log | tail -30

# Score par worker (niveau debug, activé en dev)
grep "getFreeWorker\]::Score" var/log/dev.log | tail -20

# Consommateur Messenger
journalctl -u remotelabz.service --since "1 hour ago" | grep -E "Placement request|launched on worker"
```

On doit voir les `launched on worker` alterner entre les IP des workers quand
plusieurs labs partent ensemble.

---

## 7. Limites et points de vigilance

| Point | Détail |
|-------|--------|
| **Réservation non affectée invisible** | Entre `add()` et `assign()`, la réservation n'est imputée à aucun worker. Sans impact tant qu'un seul consommateur traite la file séquentiellement, mais deux consommateurs `front` verraient les `getFreeWorker()` concurrents partir avec la même vision. |
| **Garde-fou de capacité approximatif** | `$availableMemory = (100 - $adjustedMemory) * $memory_total` vaut 100 × la mémoire restante (Mo). La condition `$availableMemory < $memory` écarte donc un worker seulement quand la mémoire restante passe sous `$memory / 100`. Un lab de 1000 Mo est donc accepté tant qu'il reste ≥ 10 Mo : un worker peut être sur-subcrit jusqu'à ≈ la taille d'un lab avant d'être écarté (mesuré dans `testBatchStopsOnceEveryWorkerIsFull` : 4 labs × 1000 Mo + 10 % de mémoire système réservés sur 3955 Mo). Correction possible : `(100 - $adjustedMemory) * $memory_total < 100 * $memory`. |
| **Une réservation légère peut être noyée** | Le poids mémoire du score est de 0.3 ; un tout petit lab réservé (quelques Mo) peut être surpassé par un écart de CPU/disco/lxcfs sur un autre worker. La répartition est fiable pour des labs significatifs, pas garantie pour des labs minuscules face à des workers très déséquilibrés. |
| **`InstanceManager::exportlab()` contourne le cache** | `src/Service/Instance/InstanceManager.php:662` appelle `getFreeWorker()` en dehors du handler, sans `add()`/`assign()` : son choix n'est pas réservé et n'est pas pris en compte par les placements suivants. |
| **Fenêtre de transition** | La réservation est libérée à l'état `created` alors que les stats HTTP du worker peuvent avoir quelques secondes de retard (mémoire comptée zéro fois pendant ce laps) — ou, à l'inverse, être déjà à jour alors que la réservation existe encore (comptée deux fois, worker brièvement évité). Écart transitoire, sans effet durable. |
| **Les autres pools restent sur disque** | Seul `lab_placement` passe par Redis. `cache.app`, le cache Doctrine (`pools/system`), `validation.php` ou `webpack_encore.cache.php` restent en fichiers : si un fichier de `var/cache/prod` est créé par un autre utilisateur que `www-data` (console lancée en `sudo`/autre compte), le même genre de `Permission denied` réapparaît — mais jamais pour `lab_placement.pending`. Correction : `sudo chown -R www-data:www-data var/cache/prod` et lancer les commandes prod sous `www-data`. |
| **TTL de 1 h** | Une instance bloquée en `creating` plus d'une heure perd sa réservation (les stats finiront par la refléter ou l'instance sera en erreur). |
