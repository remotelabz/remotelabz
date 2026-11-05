# Device caché (hidden device) — fonctionnement

> **Statut** : **implémenté**. Ce document décrit le comportement effectif du
> code, tel que livré.
>
> Un **device caché** est un device d'un lab **invisible pour tous les
> utilisateurs du lab sauf son auteur** (le `author` du lab) et les
> administrateurs de site. Il sert d'infrastructure « en arrière-plan »
> (serveur de supervision, routeur injectant du trafic, VM puits, …) sans être
> exposé aux étudiants.

## 1. Propriétés de base

| Propriété | Comportement |
|---|---|
| Visibilité | Seul l'**auteur du lab** et les **admins de site** voient le device (éditeur, liste de nœuds, topologie, liste d'instances). |
| Liens réseau | Les connexions (links) touchant un device caché sont **retirées** de la topologie — un lien en suspension révélerait le nœud caché. |
| Démarrage | Les devices cachés **démarrent toujours** au lancement du lab, quel que soit le drapeau `autoStartDevices`. |
| Bouton *Leave lab* | L'état des instances de devices cachés est **ignoré** par le calcul qui désactive le bouton *Leave lab*. |
| Suppression | Aucun teardown particulier : la suppression de l'instance de lab détruit tous les devices, cachés inclus. |
| Notifications | Les changements d'état d'un device caché ne sont notifiés **qu'à l'auteur du lab** (jamais aux admins de site). |

## 2. Modèle de données

### 2.1 Champ sur `Device`

`src/Entity/Device.php:161-169` :

```php
/**
 * Hidden devices are only visible to the lab author and system admins.
 * They are never exposed to the other users of the lab (nodes, topology,
 * instances) and only the lab author is notified of their state changes.
 */
#[Assert\Type(type: 'boolean')]
#[ORM\Column(type: 'boolean', options: ['default' => 0])]
#[Serializer\Groups(['api_get_device', 'api_get_lab', 'api_get_lab_instance', 'api_get_lab_template', 'export_lab', 'sandbox'])]
private $hidden = false;
```

- Getter `getHidden()` (`:712-715`), setter `setHidden()` (`:717-722`).
- Le groupe **`worker` n'est pas** dans la liste : le worker n'a pas besoin du
  drapeau (le descripteur worker contient la liste complète des devices de
  toute façon — les devices cachés y figurent, ils démarrent toujours).
- Le drapeau est sur l'entité `Device`, **pas** sur la relation `lab_device` :
  un device partagé par plusieurs labs serait caché dans tous. Aujourd'hui un
  device est créé dans un seul lab, donc acceptable.

### 2.2 Migration

`migrations/Version20261011120000.php` :

```php
// up
ALTER TABLE device ADD hidden TINYINT(1) DEFAULT 0 NOT NULL
// down
ALTER TABLE device DROP hidden
```

## 3. Autorisation (serveur)

Deux voters portent la règle :

### 3.1 `LabVoter::SEE_HIDDEN_DEVICE`

`src/Security/ACL/LabVoter.php` :

- constante `SEE_HIDDEN_DEVICE` (`:16`), dispatchée vers `canSeeHiddenDevice()`
  (`:95-101`) :

  ```php
  // administrateur de site OU auteur du lab
  return $user->isAdministrator() || $user == $lab->getAuthor();
  ```

C'est le predicate utilisé par tous les endpoints REST du §4.

### 3.2 `InstanceVoter::canSeeHiddenDeviceInstance()`

`src/Security/ACL/InstanceVoter.php:131-153` : appliqué aux sujets
`DeviceInstance` — une instance de device caché n'est visible que par un
administrateur ou l'auteur du lab. Il est appelé par `canView` (`:64`),
`canStopDevice` (`:72`), `canStartDevice` (`:85`), `canResetDevice` (`:105`),
`canGetLogs` (`:125`). Conséquence : un utilisateur tiers ne peut ni voir ni
démarrer/arrêter/réinitialiser une instance cachée, ni lire ses logs
(`canDelete`/`canExportInstance` ne l'appellent pas, mais l'instance étant
invisible, ces actions n'atteignent pas l'utilisateur).

## 4. Filtres de visibilité côté serveur

La règle est appliquée **côté serveur** sur chaque endpoint exposant les
devices ou la topologie d'un lab ; le frontend ne fait que cacher l'indicateur
(§5).

```
Requête sur un lab (nœuds / topologie / instances)
        │
        ▼
  Utilisateur = auteur du lab ou admin de site ?
     │ oui                          │ non
     ▼                              ▼
  devices complets            devices hidden = 0 uniquement
                              + connexions touchant un device caché supprimées
```

### 4.1 Liste de nœuds — `POST /api/labs/{id}/nodes`

`DeviceController::indexActionTest()` (`src/Controller/DeviceController.php:269`) :

- `$includeHidden = $this->isGranted(LabVoter::SEE_HIDDEN_DEVICE, $lab)`
  (`:282-283`) ;
- `DeviceRepository::findByLab($lab, $includeHidden)` (`:297`, édition) et
  `findByLabInstance($lab, $includeHidden)` (`:287`, vue live) ;
- le champ `"hidden"` est inclus dans le payload de chaque nœud (`:394`).

Le filtrage est fait **dans le repository** (`src/Repository/DeviceRepository.php`) :
`findByLab()` (`:75-91`) et `findByLabInstance()` (`:100-119`) ajoutent
`AND d.hidden = 0` sauf si le paramètre `includeHidden` est vrai.

### 4.2 Nœud unique — `POST /api/labs/{labId}/nodes/{id}`

`DeviceController::showActionTest()` (`:507`) : device caché et utilisateur
non privilégié → **404** `NotFoundHttpException` (« Device does not exist »,
`:520-523`) — un 404 plutôt qu'un 403 pour ne pas révéler l'existence du
device.

### 4.3 Interfaces d'un device — `GET /api/labs/{labId}/nodes/{deviceId}/interfaces`

`DeviceController::getNetworkInterfaces()` (`:1968`) : même règle — 404 si le
device est caché et l'utilisateur non privilégié (`:1980-1983`), sinon les
interfaces (et leurs connexions) d'un device caché seraient énumérables.

### 4.4 Topologie — `GET /api/labs/{labId}/topology`

`NetworkInterfaceController::getTopology()`
(`src/Controller/NetworkInterfaceController.php:305`) :

- `$includeHidden` calculé via `SEE_HIDDEN_DEVICE` (`:311-322`) ;
- pour un utilisateur non privilégié, l'API `NetworkInterfaceRepository::getTopology()`
  (`:70-86`) renvoie la topologie brute, que le **contrôleur filtre ensuite** :
  **chaque connexion (ligne) dont la liste `devices` contient un device caché
  est supprimée** (`:326-332`). C'est la garantie qu'aucun lien en suspension ne
  révèle le device caché.

### 4.5 Sérialisation JMS des collections de devices / instances

Deux listeners JMS en `pre_serialize`/`post_serialize`, déclarés dans
`config/services.yaml` (`:205`, `:209`) :

| Listener | Cible | Effet |
|---|---|---|
| `src/EventListener/LabSerializationListener.php` | `Lab` | Remplace temporairement `Lab::$devices` par la collection filtrée (devices non cachés) pendant la sérialisation, puis restaure l'originale (`:46-83`). |
| `src/EventListener/LabInstanceSerializationListener.php` | `LabInstance` | Idem sur `LabInstance::$deviceInstances` (`:47-84`) — les instances de devices cachés sont retirées. |

Les deux ne filtrent que :

- quand le groupe de sérialisation contient `api_get_lab` (resp.
  `api_get_lab_instance`) ;
- **sauf** les groupes `worker`, `sandbox`, `export_lab` (le worker a besoin de
  la liste complète pour créer/démarrer le lab) ;
- et uniquement pour un utilisateur qui n'est ni admin de site ni auteur du lab
  (les `InvitationCode` — invités — sont filtrés, ils ne sont pas des `User`).

Cela couvre tous les endpoints qui sérialisent un `LabInstance` avec
`api_get_lab_instance` : `GET /api/instances/by-uuid/{uuid}`,
`GET /api/instances/lab/{labUuid}/by-user|by-guest|by-group/…`
(`src/Controller/InstanceController.php:836-931`, protégés par
`InstanceVoter::VIEW`) et la liste complète `GET /api/instances`
(`apiIndexAction`, `:182`).

### 4.6 Autres points

- **Compteur de devices des cartes lab** : `templates/lab/index.html.twig:101-106`
  — les devices cachés sont exclus du nombre affiché, sauf pour
  `can_see_hidden` (admin ou auteur).
- **`GET /api/devices/{id}`** (`DeviceController::showAction`, `:417`) : un
  invité (`InvitationCode`) ne voit jamais un device caché (`:432-433`) ; un
  utilisateur ne le voit que s'il est admin **ou** auteur d'un des labs du
  device (`:469-488`), sinon 403.

## 5. Éditeur graphique

`assets/js/components/Editor2/themes/default/js/functions.js` :

- **Predicate** `canSeeHiddenDevices()` (`:55-59`) : `AUTHOR == 1` ou rôle
  `ROLE_ADMINISTRATOR` / `ROLE_SUPER_ADMINISTRATOR` (miroir de
  `LabVoter::SEE_HIDDEN_DEVICE`).
- **Indicateur** (`:2320-2323`) : quand `value['hidden']` et
  `canSeeHiddenDevices()`, la carte du nœud reçoit
  `<i class="fa fa-eye-slash node-hidden-badge" title="…">` ; l'attribut
  `data-hidden="1|0"` est posé sur le div du nœud (`:2332`).
- **Case à cocher** « Hidden device » (`:1915-1926`) : ajoutée au formulaire de
  création **et** d'édition de nœud (`printFormNode()`, `:1691`) quand
  `canSeeHiddenDevices()` ; active seulement en `EDITION == 1` et sans lock ;
  synchronisée avec un input caché `node[hidden]` (`:1935-1938`).
- **Messages** : `messages_en.js:208-209` (`[249] = 'Hidden device'`,
  `[250] = 'Hidden devices are only visible to you (the lab author) and to the
  system administrators. They are started with the lab.'`).

Pour les autres utilisateurs, aucun masquage côté client n'est nécessaire : le
nœud n'arrive tout simplement pas (filtres du §4).

### 5.1 Écriture du drapeau

Le formulaire est soumis en JSON (`form2Array('node')`) vers :

- création : `POST /api/labs/{labId}/node` → `DeviceController::newActionTest()`
  (`:857`, garde `LabVoter::EDIT_DEVICE` `:890`) → `addDevice()` applique
  `setHidden((bool) $data['hidden'])` (`:1028-1030`) ;
- édition : `PUT /api/labs/{labId}/node/{id}` → `DeviceController::updateActionTest()`
  (`:1481`, garde `LabVoter::EDIT_DEVICE` `:1491`) → même application
  (`:1527-1530`).

Seul un utilisateur avec les droits d'édition du lab (auteur/admin) peut donc
poser ou retirer le drapeau.

## 6. Cycle de vie

```
Join lab (autoStartDevices = false)
   │
   ▼
LabLaunchRequestMessageHandler : placement + ACTION_CREATE
   │  pour chaque DeviceInstance :
   │    device caché ?            → ACTION_START (toujours)
   │    device visible + autoStart → ACTION_START
   ▼
worker crée et démarre (devices cachés inclus, le descripteur worker est complet)
   │
   ▼
Leave lab → DELETE /api/instances/{uuid} → ACTION_DELETE (tous les devices)
```

### 6.1 Auto-démarrage

`src/MessageHandler/LabLaunchRequestMessageHandler.php:156-169` :

```php
$isHidden = $deviceInstance->getDevice()?->getHidden() ?? false;
// Hidden devices are always started with the lab: the lab users cannot see
// them, so they could never start them themselves.
if (!$isHidden && !$message->isAutoStartDevices()) {
    continue;
}
```

Le même garde d'état (`stopped` / `error`) s'applique. Le chemin d'actions
planifiées (`ScheduledActionService`) démarre déjà tout : inchangé.

### 6.2 Bouton *Leave lab*

`assets/js/components/Instances/InstanceManager.js` :

- `isHiddenDeviceInstance(instance)` (`:220-222`) =
  `instance.device && instance.device.hidden` ;
- `hasInstancesStillRunning()` (`:224-228`) **exclut** les instances de
  devices cachés (elles tournent en arrière-plan et ne peuvent pas bloquer le
  bouton `:373`).

Pour un utilisateur non privilégié, les instances cachées sont absentes du
payload (listeners du §4.5) : le calcul est naturellement inchangé pour lui.

### 6.3 Destruction à la sortie

Aucun code particulier : `DELETE /api/instances/{uuid}` et la variante en bloc
`DELETE /api/instances/bulk/leave-all` appellent `InstanceManager::delete()`,
qui sérialise **toute** l'instance de lab (devices cachés inclus) et envoie
`ACTION_DELETE` au worker. Côté base, `DeviceInstance::$labInstance` est en
cascade (`onDelete: 'CASCADE'`) et `LabInstance::$deviceInstances` en
`cascade: ['persist', 'remove']`.

## 7. Notifications

`src/MessageHandler/InstanceStateMessageHandler.php` :

- `getUserIdFromInstance()` (`:85-163`) : si l'instance notifiée est une
  `DeviceInstance` d'un device caché, **seul l'auteur du lab** est renvoyé
  (`:96-105`) — avant toute logique user/groupe.
- `isHiddenDeviceInstance()` (`:168-173`).
- `:260-270` : si le message porte un `user_id` explicite, il est
  **écrasé** pour les instances cachées (la restriction ne peut pas être
  contournée).
- `ReconcileStartingInstancesCommand` (`src/Command/ReconcileStartingInstancesCommand.php:133-151`)
  applique la même règle (auteur seul) pour les notifications des devices
  bloqués en `starting`.

`NotificationService` n'a pas besoin de changement : il persiste une
`UserNotification` par id.

## 8. Persistance du drapeau

| Situation | Comportement |
|---|---|
| Device enregistré comme template | `LabController::copyDevice()` (`:1097`) copie le drapeau (`setHidden($device->getHidden())`). |
| Import de lab | `src/Service/Lab/LabImporter.php:269-273` préserve le drapeau (l'importateur devient auteur du lab : il peut donc les voir). |
| Export/template de lab | groupe `export_lab` / `api_get_lab_template` : le champ est sérialisé. |
| Descripteur worker | groupe `worker` : **absent** du champ (inutile au worker). |

## 9. Matrice des permissions

| Action | Auteur du lab | Admin de site | Utilisateur du groupe (élevé) | Étudiant / invité |
|---|---|---|---|---|
| Voir le nœud caché dans l'éditeur (avec indicateur) | ✅ | ✅ | ❌ | ❌ |
| Voir le nœud caché dans la vue instance | ✅ | ✅ | ❌ | ❌ |
| Voir les liens touchant un device caché | ✅ | ✅ | ❌ | ❌ |
| Voir l'instance cachée dans le panneau *Instances* | ✅ | ✅ | ❌ | ❌ |
| Démarrer / arrêter / réinitialiser un device caché | ✅ | ✅ | ❌ | ❌ |
| Lire les logs d'un device caché | ✅ | ✅ | ❌ | ❌ |
| Basculer le drapeau `hidden` | ✅ | ✅ | ❌ | ❌ |
| Recevoir les notifications d'un device caché | ✅ | ❌ | ❌ | ❌ |
| Être bloqué en *Leave lab* par l'état d'un device caché | ❌ non bloqué | ❌ | ❌ | ❌ |

## 10. Tests

Aucun test automatisé ne couvre la feature pour l'instant (recherche de
`hidden` dans `tests/` : aucune correspondance). Scénarios de recette :

1. Auteur ouvre l'éditeur : nœuds cachés visibles avec l'indicateur
   `fa-eye-slash` ; liens visibles.
2. Étudiant ouvre la même vue du lab : nœuds cachés absents ; pas de liens en
   suspension.
3. Étudiant appelle directement `POST /api/labs/{id}/nodes` /
   `GET …/topology` : devices et connexions cachés absents du JSON.
4. Étudiant ouvre le panneau *Instances* : instance cachée non listée.
5. Jointure du lab : devices visibles à l'arrêt ; devices cachés démarrés
   automatiquement (même sans `autoStartDevices`).
6. *Leave lab* alors qu'un device caché tourne : bouton actif ; sortie →
   destruction de tout.
7. Changement d'état d'un device caché : seule l'auteur du lab est notifié.
8. Étudiant envoie `PUT /api/labs/{labId}/node/{id}` avec `hidden: 1` : refusé
   (`LabVoter::EDIT_DEVICE`).
9. Non-auteur appelle `POST /api/labs/{id}/nodes/{hiddenId}` : 404.
