# Labs partagés (« shared lab ») — fonctionnement côté front

> **Statut** : **implémenté** (issue
> [#1128 Add sharing lab](https://github.com/remotelabz/remotelabz/issues/1128) ;
> issue worker complémentaire :
> [#212 Shared lab](https://github.com/remotelabz/remotelabz-worker/issues/212)).
>
> Ce document décrit le comportement effectif du code front (`remotelabz`).
> Côté bundle : `doc/shared-labs-message-bundle.md`.

Un **lab partagé** échange du trafic avec d'autres labs **au sein d'un groupe**,
selon des **règles explicites** déclarées par l'auteur du lab.

## 1. Rappel : pourquoi pas un simple booléen

Le booléen `Lab::$shared` (ajouté en 2.5.0, jamais utilisé) ne disait **ni avec
quel lab** le lab devait être interconnecté, **ni pour quel groupe**. Le modèle
retenu est une **règle** `(S, L, G)` :

```
Lab S partagé avec L pour le groupe G :
   les instances de S appartenant à G  ↔  les instances de L appartenant à G
```

- une seule règle suffit (connectivité **bidirectionnelle**) ;
- `S` peut être partagé avec plusieurs labs et dans plusieurs groupes ;
- `S` **n'a pas besoin d'être listé dans `G`** : c'est le cas « lab caché »
  (partagé avec un groupe sans être visible de ses membres) ;
- le booléen `Lab::$shared` a été **supprimé** de l'entité et de la base.

## 2. Modèle de données

### 2.1 Entité `LabShare` — `src/Entity/LabShare.php`

| Champ | Relation | Contraintes |
|---|---|---|
| `lab` (source S) | `ManyToOne(Lab)`, `inversedBy: 'shares'` | `nullable: false, onDelete: 'CASCADE'` |
| `sharedWith` (cible L) | `ManyToOne(Lab)` | `nullable: false, onDelete: 'CASCADE'` |
| `group` (périmètre G) | `ManyToOne(Group)` | `nullable: false, onDelete: 'CASCADE'` |
| `createdBy` | `ManyToOne(User)` | `nullable: true, onDelete: 'SET NULL'` (traçabilité) |
| `createdAt` | `datetime` | nullable |

- Contrainte unique `uniq_share (lab_id, shared_with_id, group_id)` (`:17`) ;
- repository dédié `App\Repository\LabShareRepository` ;
- `Lab::$shares` (`src/Entity/Lab.php:105-107`) : `OneToMany`,
  `cascade: ['persist']`, `orphanRemoval: true`, getter `getShares()` (`:611`),
  `addShare()` / `removeShare()`.

**Sérialisation** : `Lab::$shares` est exposée dans les groupes
`api_get_lab`, `api_get_lab_template`, `api_groups`, `api_get_group`. Pour
éviter les cycles de sérialisation, `LabShare` n'expose que des **propriétés
virtuelles scalaires** (`LabShare.php:110-146`) : `getSharedWithId()`,
`getSharedWithUuid()`, `getSharedWithName()`, `getGroupId()`,
`getGroupUuid()` — jamais les objets `Lab`/`Group` bruts.

### 2.2 Migration

`migrations/Version20261009120543.php` :

- `CREATE TABLE lab_share (…)`, contrainte unique `uniq_share`, FK en cascade
  vers `lab` (×2), `_group`, et `created_by_id → user(id) ON DELETE SET NULL` ;
- **`ALTER TABLE lab DROP shared`** (`:27`) ;
- `down()` : table supprimée, colonne `lab.shared` réajoutée.

La rupture API (disparition de la clé `shared`) est consignée dans
`CHANGELOG.md` (remplacée par `shares`).

## 3. API d'administration des règles

### 3.1 Écriture — `PUT /api/labs/test/{id}`

`LabController::updateActionTest()` (`src/Controller/LabController.php:1273`,
garde `LabVoter::EDIT`) :

- la clé `shares` du payload doit être un tableau (`:1297-1305`) ;
- le remplacement est délégué à **`src/Service/LabShareManager.php`** :
  `replaceShares($lab, $rules)` (`:49-129`) — **atomique** (une règle invalide
  laisse la collection intacte) ;
- après le `flush`, le contrôleur diffuse `syncGroup()` pour **chaque groupe
  impacté** : les groupes des règles supprimées **et** celles ajoutées
  (`:1289-1295`, `:1320-1324`, `:1380-1386`, chacun dans son try/catch).

Format du payload :

```json
{
  "shares": [
    { "sharedWith": 42, "group": "3f2b9c1e-..." }
  ]
}
```

Si la clé `shares` est absente, les règles existantes ne sont pas touchées.

### 3.2 Validations — `LabShareManager::replaceShares()`

| Règle | Erreur |
|---|---|
| `S ≠ L` | « A lab cannot be shared with itself. » |
| `L` existe (id numérique) | « Target lab not found. » |
| `G` existe (uuid) | « Group not found. » |
| **`L` est disponible dans `G`** (`$target->getGroups()->contains($group)`) | « Lab {L} is not available in group {G}. » |
| `S` dans `G` ? | **Non exigé** (cas « lab caché ») |
| Unicité des paires `(L, G)` dans le payload | « Share rule … is duplicated. » |

`createdAt` est posé sur chaque règle à l'insertion.

`LabShareManager::purgeGroupShareRules($lab, $group)` (`:140-155`) supprime
seules les règles où le lab est la **cible** ; les règles où il est la source
sont conservées.

### 3.3 Lecture — `GET /api/labs/info/{id}`

`LabController::showActionTest()` (`:508-578`) renvoie :

- `"shares"` : `[{id, sharedWith, sharedWithUuid, sharedWithName, group, groupName}]` ;
- `"shareChoices"` : pour chaque groupe de l'utilisateur courant, la liste des
  labs disponibles dans ce groupe (cibles valides), chacun trié par nom —
  utilisé directement par l'UI.

### 3.4 Retrait d'un lab d'un groupe

`GroupController::removeLabAction()` (`src/Controller/GroupController.php:849`) :
`purgeGroupShareRules()` (`:870`) + `flush` + `syncGroup()` (`:879`).

## 4. Calcul des liens — `SharedLabSecurityManager`

`src/Service/SharedLabSecurityManager.php` (`final class`) :

### 4.1 `buildLinks(Group $group): array` (`:57-98`)

1. Chargement des règles du groupe : `labShareRepository->findBy(['group' => $group])` ;
2. pour chaque règle `(S, L)` : **produit cartésien**
   `instances(S, G) × instances(L, G)`, en ignorant les liens vers soi-même et
   en dédoublonnant par paire triée d'uuids (`linkKey()`, `:329-335`) ;
3. retour :

```json
{ "group": "<uuid de G>", "links": [ { "a": {…}, "b": {…} } ] }
```

chaque extrémité étant `{ "uuid", "network" (CIDR, `(string) $labInstance->getNetwork()`), "workerIp" }`.

### 4.2 Instances retenues — `eligibleInstances()` / `isEligibleInstance()`

Un lab ne produit d'instances que s'il est **virtuel** (`virtuality == 1`,
`:241-243`). Pour un lab donné et un groupe `G` (`:232-294`) :

| Type d'instance | Retenue si |
|---|---|
| lancée **par le groupe** (`ownedBy = group`) | le groupe propriétaire est exactement `G` |
| **user** (sandbox) | l'utilisateur propriétaire est **membre de `G`** (`$group->hasUser(...)`) |
| **guest** | **jamais** (commentaire explicite `:278-281`) |
| non placée | jamais (`workerIp` vide) |
| sans réseau | jamais (`network` null) |
| à l'arrêt | jamais : il faut **au moins un device `starting` ou `started`** (`hasRunningDevice()`, `:296-305`) |

Une instance sandbox d'un user membre de `G1` **et** `G2` entre dans la
topologie de chacun des deux groupes, **si et seulement si** le groupe porte
une règle concernant son lab. Aucune instance d'un autre groupe n'entre jamais
dans le calcul.

Exemple (cas « lab caché ») :

```
Règle : (S, L, G1) ; S n'est PAS listé dans G1.
m1 (membre de G1) joint L en sandbox → I_L(m1)
l'auteur (membre de G1) joint S      → I_S(auteur)
→ lien I_S(auteur) ↔ I_L(m1)
→ G2 : aucune règle → I_L(m1) n'entre dans aucune topologie de G2
```

### 4.3 Diffusion — `syncGroup()` / `syncLabInstance()` / `syncGroups()`

- **`syncGroup(Group)`** (`:103-151`) : sérialise la topologie, parcourt
  `configWorkerRepository->findAll()`, saute les workers sans IPv4 ou
  `available == false`, et dispatch **un `SecurityMessage` par worker** avec
  `new AmqpStamp($workerIp, AMQP_NOPARAM, [])` (transport `worker` = exchange
  direct, binding key = IP du worker). Log INFO par worker avec le payload ;
  WARNING « No available worker » sinon.
- **`syncLabInstance(?LabInstance)`** (`:174-216`) : collecte le groupe
  propriétaire de l'instance **et** tous les groupes où le lab est partagé en
  source **ou** en cible (`LabShareRepository::findGroupsWithLab`), puis
  `syncGroup()` pour chacun (try/catch par groupe).
- **`syncGroups(iterable)`** (`:158-163`) : simple boucle de `syncGroup()`.

Le worker utilise l'uuid du groupe comme **clé d'état** : il remplace l'entrée
de ce groupe et reconstruit ses règles sur la **réunion de tous les groupes**
(`links: []` purge le groupe). Chaque worker filtre les liens qui le concernent
(comparaison avec `app.worker.ip`). Pas de calcul de destinataires côté front :
le nettoyage est garanti par le broadcast systématique.

## 5. Déclencheurs de synchronisation

| # | Événement | Emplacement |
|---|---|---|
| 1 | **Placement** d'une instance | `LabLaunchRequestMessageHandler.php:154` — après `setWorkerIp` + dispatch `ACTION_CREATE` |
| 2 | **Démarrage** d'un device | `InstanceManager::start()` (`:435`), après le dispatch `ACTION_START` |
| 3 | **Arrêt** d'un device | `InstanceManager::stop()` (`:502`), après le dispatch `ACTION_STOP` |
| 4 | **Suppression** d'une instance | `InstanceStateMessageHandler.php:501` — sur `STATE_DELETED`, après `remove()` + flush et le retrait du groupe (try/catch) |
| 5 | **Création / suppression de règles** | `LabController::updateActionTest` (§3.1) — `syncGroup()` par groupe impacté |
| 6 | **Handshake** d'un worker | `WorkerHandshakeMessageHandler.php:58-77, 125-131` — pour chaque device instance `starting`/`started` placée sur ce worker, le **groupe propriétaire** est collecté, puis `syncGroups()` |
| 7 | **Retrait d'un lab d'un groupe** | `GroupController::removeLabAction` (§3.4) |

Le handshake est indispensable : après un reboot worker, iptables, routes et
état local sont perdus et la topologie est réappliquée intégralement.

Routage Messenger : `Remotelabz\Message\Message\SecurityMessage: worker`
(`config/packages/messenger.yaml:27`).

## 6. Interface utilisateur

Section « Sharing » de l'édition de lab (le lab n'étant modifiable que dans
l'éditeur) :

- **Template** : `assets/js/components/Editor2/themes/default/ejs/form_lab.ejs:120-130`
  — en mode `edit`, rendu du bloc `#lab-share-rules` avec les conteneurs
  header/rules/buttons.
- **Logique JS** : `assets/js/components/Editor2/themes/default/js/functions.js:1416-1600`
  — `getLabShareChoices()` (issues de `shareChoices`), `buildLabShareRow()`
  (sélecteur de groupe + sélecteur de lab cible + bouton de suppression,
  règles invalides signalées par `MESSAGES[247]`), `initLabShareRules()`
  (état vide, bouton « Add a sharing rule »), `collectLabShareRules(form)`
  (produit `[{sharedWith: <id>, group: <uuid>}]`, dédoublonné côté client).
- **Soumission** : `assets/js/components/Editor2/themes/default/js/actions.js:2258-2263`
  — `form_data['shares'] = collectLabShareRules(this)` à l'envoi du
  formulaire d'édition (`PUT /api/labs/test/{id}`) ; à la création de lab, la
  clé n'est pas envoyée.
- **i18n** : `messages_en.js:199-207` (`MESSAGES[240]`–`[248]`).

## 7. Séquence typique

```
Auteur : règle (S, L, G1) dans l'édition de lab
   │
   ▼
LabController::updateActionTest
  · LabShareManager::replaceShares (validations §3.2)
  · syncGroup(G1) pour chaque groupe impacté
   │
   ▼
SecurityMessage { group: G1, links: [ I_S(G1) ↔ I_L(G1) ] }
   broadcast → chaque worker disponible
   │
   ▼
worker : remplace l'entrée [G1], reconstruit iptables/routes sur l'union
   (les autres groupes ne sont jamais écrasés)
   │
   ▼
Ensuite, indépendamment : jointure/démarrage/arrêt d'instances
   → déclencheurs §5 → rebuild idempotent
```

## 8. Points d'attention

- **Isolation des groupes** : jamais de message « global » ; le périmètre d'un
  message est toujours un seul groupe. C'est la garantie qu'aucune instance de
  `L(G2)` n'accède à `S(G1)`.
- **Déploiement coordonné** : bundle → workers → front (sérialisation PHP
  native ; une classe de message absente côté récepteur échoue au décodage).
  Voir `doc/shared-labs-message-bundle.md` §6.
- **Idempotence** : le front peut émettre des messages redondants (placement +
  start) — sans effet, le worker reconstruit le même état.
- **Labs physiques** : hors périmètre (`virtuality == 1` requis).
- Le front **ne modifie pas** son routage de sous-réseaux vers le worker
  propriétaire : seule la connectivité inter-worker est gérée par les workers.

## 9. Tests

- `tests/Service/SharedLabSecurityManagerTest.php` (unitaires, repositories en
  mock) : produit cartésien, absence de règle → aucun lien, exclusion des
  instances non placées / à l'arrêt / physiques, inclusion `starting`,
  sandbox de non-membre exclue / de membre incluse, sandbox liée uniquement aux
  groupes de son propriétaire, dédoublonnage + auto-lien exclu, isolation
  multi-groupes, contenu et routage (`AmqpStamp` = IP du worker) des messages,
  warning sans worker, broadcast `syncLabInstance`, résilience du repository.
- `tests/Service/LabShareManagerTest.php` : règle valide (`createdAt` posé),
  auto-partage rejeté, cible hors groupe rejetée, **source hors groupe
  acceptée** (cas caché), groupe/lab inconnus rejetés, doublons rejetés,
  atomicité, purge des règles cible uniquement.
- `lib/remotelabz-message-bundle/Tests/SecurityMessageTest.php` : DTO.

Scénarios manuels d'acceptation :

1. **2 workers, règle `(A, L, G1)`** : pings `A ↔ L` OK ; un lab `B` du même
   groupe sans règle reste isolé ; suppression de la règle → connectivité
   coupée et règles purgées des workers ; stop d'un device de `A` → liens
   retirés sur tous les workers ; reboot d'un worker → handshake → état
   restauré.
2. **Isolation G1/G2** : `L` instancié dans `G1` (user A) et `G2` ; règle
   `(S, L, G1)` + lancement de `S` dans `G1` → ping `S(G1) ↔ L(A,G1)` OK ;
   ping `S(G1) ↔ L(G2)` **échoue** (`iptables -S shared_forward` ne mentionne
   pas le réseau de `L(G2)`).
