# Plan de modification — Front : partage des labs (« shared lab »)

> Issue associée : [#1128 Add sharing lab](https://github.com/remotelabz/remotelabz/issues/1128)
> — *« a shared lab can exchange data with all labs which are in the same group »*
> (issue worker complémentaire : [#212 Shared lab](https://github.com/remotelabz/remotelabz-worker/issues/212))
>
> Document à **soumettre au projet front** (`remotelabz/remotelabz`, branche `dev`).
> Le plan côté worker est détaillé dans [`shared-labs-worker-plan.md`](./shared-labs-worker-plan.md).

## 1. Contexte & analyse de l'existant

### 1.1 L'attribut `shared` actuel est insuffisant

- Entité `Lab::$shared` : booléen `false` par défaut
  (`src/Entity/Lab.php:99-100`, `isShared():583`, `setShared():588`),
  colonne ajoutée par la migration `Version20250831194129`.
- Utilisations actuelles (aucune logique réseau) :
  - `LabController::seeAction` (`:525`) : renvoie `"shared"` dans la réponse ;
  - `LabController::update` (`:1250-1252`) : lit/clôt la clé `shared` du payload ;
  - `LabRepository:73` : sélection SQL `L.shared`.
- Commentaire sur l'issue #1128 : *« The property is added in the lab entity in 2.5.0
  release but is not used yet »*.

**Pourquoi ce n'est pas suffisant** : un simple booléen ne dit **ni avec quel lab**
ce lab doit être interconnecté, **ni pour quel groupe**. Or le besoin réel est :

```
Lab L disponible dans G1 et dans G2 (même template, instances distinctes)
Prof définit : « S est partagé avec L pour le groupe G1 »

G1 :  S(G1)  ↔  instance de L rejointe par l'utilisateur A   ✅ atteignable
G2 :  instance(s) de L appartenant à G2                       ❌ injoignable depuis S(G1)
```

Le partage doit donc être une **règle explicite** : **(lab source S, lab cible L, groupe G)**.

### 1.2 Modèle existant exploité

| Concept | Relation | Emplacement |
|---|---|---|
| Un lab est **disponible** dans un groupe | M2M `Lab::$groups` ↔ `Group::$labs` | `Lab.php:92`, `Group.php:89` ; géré par `GroupController::addLabAction/removeLabAction` (`:809`, `:848`) et l'édition de lab (`LabController.php:694`) |
| Une **instance** appartient à un groupe | `LabInstance::_group` + `ownedBy == 'group'` | `LabInstance::populate():324`, `Group::$labInstances` (`Group.php:74`) |
| Édition de lab | payload JSON `LabController::update` | `:1235-1265` (où vivait la clé `shared`) |

## 2. Révision du modèle de partage (décisions)

### 2.1 Décisions retenues

1. **Remplacer** le booléen `Lab::$shared` par une **entité de règles de partage** (pas de conservation).
2. **Granularité** : paire explicite `(S, L, G)` — seules les instances de `S` et de `L`
   appartenant à `G` sont interconnectées.
3. **Administration** : règles créées/modifiées par l'**auteur du lab ou un admin**
   (droits existants sur `LabController::update` / `LabVoter`), côté édition de lab.

### 2.2 Nouvelle entité — `LabShare` (proposition)

```php
#[ORM\Entity]
#[ORM\Table(name: 'lab_share')]
#[ORM\UniqueConstraint(name: 'uniq_share', columns: ['lab_id', 'shared_with_id', 'group_id'])]
class LabShare
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id;

    #[ORM\ManyToOne(targetEntity: Lab::class, inversedBy: 'shares')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Lab $lab;              // lab source S (le lab « partagé »)

    #[ORM\ManyToOne(targetEntity: Lab::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Lab $sharedWith;       // lab cible L (« avec lequel » S est partagé)

    #[ORM\ManyToOne(targetEntity: Group::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Group $group;          // groupe G (périmètre de la règle)

    // éventuels : createdBy (User), createdAt (datetime) pour la traçabilité
}
```

Côté `Lab` : `#[ORM\OneToMany(targetEntity: LabShare::class, mappedBy: 'lab', orphanRemoval: true)] private $shares;`

**Sémantique** : une règle `(S, L, G)` signifie que **les instances de `S` dans `G` et
les instances de `L` dans `G` sont mutuellement atteignables** (connectivité
bidirectionnelle ; une seule règle suffit, pas besoin de `(L, S, G)` en miroir).
Un lab `S` peut être partagé avec **plusieurs labs** dans un même groupe
(N règles), et avec le même lab dans **plusieurs groupes** (lignes distinctes).

### 2.3 Validations à l'écriture

- `S ≠ L` ;
- `S` et `L` doivent tous deux être **disponibles dans `G`** (`Lab::$groups` contient `G`)
  — sinon la règle est sans effet et doit être rejetée (message d'erreur clair) ;
- unicité `(S, L, G)` ;
- suppression **en cascade** : quand un lab est retiré d'un groupe
  (`GroupController::removeLabAction`) → purger les règles `(lab, *, G)` ou
  `(*, lab, G)` ; quand un lab est supprimé → cascade FK déjà prévue.

### 2.4 Migration & suppression du booléen `shared`

- **Nouvelle migration Doctrine** : `CREATE TABLE lab_share (...)` +
  `ALTER TABLE lab DROP shared` ;
- **Suppressions** :
  - `src/Entity/Lab.php` : propriété `:99-100`, `isShared()` `:583`, `setShared()` `:588` → remplacés par `getShares()` ;
  - `src/Repository/LabRepository.php:73` : retirer `L.shared` du SELECT
    (remplacer par un compteur/liste de règles si nécessaire) ;
  - `src/Controller/LabController.php:525` : clé `"shared"` de la réponse `seeAction` → remplacer par `"shares"` (liste des règles) ;
  - `src/Controller/LabController.php:1250-1252` : `setShared(...)` → traitement de la nouvelle clé `shares` du payload (cf. §2.5) ;
  - fixtures/tests éventuels référençant `shared`.
- **Point d'attention API** : la disparition de la clé `shared` est une **rupture**
  pour les clients externes (à mentionner dans `CHANGELOG.md`). Aucun usage worker
  (le worker ne reçoit jamais l'entité `Lab` avec ce champ — `Serializer\Groups`
  absent, exclu du groupe `worker`), donc **aucune coordination worker** pour cette partie.

### 2.5 API d'administration des règles

Étendre `LabController::update` (payload de l'édition de lab, droits auteur/admin déjà en place) :

```json
{
  "shares": [
    { "sharedWith": 42, "group": "3f2b9c1e-..." },   // S (lab édité) partagé avec L(id=42) pour G(uuid)
    { "sharedWith": 57, "group": "3f2b9c1e-..." }
  ]
}
```

- Remplacement **atomique** des règles du lab à chaque envoi (comme pour les collections
  existantes), avec validations §2.3.
- Lecture : ajouter les règles aux groupes de sérialisation `api_get_lab` (et `api_groups`
  si l'UI de groupe doit les afficher).
- UI (plus tard) : section « Partage » dans l'édition de lab
  (`edit_lab` / `LabController:915`), sélecteurs de lab cible + de groupe,
  en s'appuyant sur `Lab::$groups` pour ne proposer que des couples valides.

## 3. Périmètre des liens (calcul)

Pour un groupe `G`, la topologie est calculée à partir des **règles `LabShare`
dont `group == G`** :

1. **Instances retenues** (pour chaque lab impliqué) — `G->getLabInstances()`
   (`src/Entity/Group.php:509-513`) filtrées par :
   - `workerIp != null` (instance placée sur un worker) ;
   - `lab.virtuality == 1` (labs virtuels uniquement — pas de bridge OVS pour le physique) ;
   - **au moins un device** en état `starting` ou `started`
     (une instance en cours d'arrêt/stoppée sort de la topologie ; elle y revient au lancement) ;
   - instances **user-owned** exclues par construction (`_group` null) — dont les labs
     *Sandbox*, préfixés `Sandbox_`.
2. **Liens** =, pour chaque règle `(S, L, G)` :
   `instances(S, G) × instances(L, G)` (produit cartésien des instances retenues),
   dédupliqués, sans lien vers soi-même.
3. Aucune instance d'un **autre groupe** n'entre jamais dans la calcul : le lien est
   borné à `G` par la règle elle-même.

Exemple :

```
Règles : (S, L, G1)          // seul
G1 :  S(shared)   L(not shared)   + autres labs du groupe
      → liens : chaque instance de S(G1) ↔ chaque instance de L(G1)
      → aucun lien vers les autres labs de G1 (pas de règle)
G2 :  instances de L(G2)      → aucune règle (S, L, G2) → injoignable de S(G1)
```

> Comparaison avec l'idée initiale de l'issue #1128 (« shared lab ↔ tous les labs du
> groupe ») : le modèle par règles le couvre aussi, en déclarant une règle pour chaque
> lab cible du groupe — ou en ajoutant plus tard, si besoin, un cas particulier
> « tous les labs de `G` » (hors périmètre actuel).

## 4. Mécanisme de diffusion vers les workers

- **Broadcast** : un `SecurityMessage` **par groupe et par worker** disponible
  (`configWorkerRepository->findAll()`, pattern de `InstanceManager:626-647`),
  ciblé avec `new AmqpStamp($workerIP, AMQP_NOPARAM, [])` (transport `worker` = exchange
  `direct`, binding key = IP du worker — `config/packages/messenger.yaml`).
- Le message contient la **topologie complète d'un seul groupe** (UUID du groupe inclus) ;
  le worker l'utilise comme **clé d'état** : il remplace l'entrée de ce groupe et reconstruit
  les règles sur la **réunion de tous les groupes** — les messages des autres groupes
  ne sont jamais écrasés (cf. plan worker §3).
- Chaque worker filtre lui-même les liens le concernant (comparaison avec son
  `app.worker.ip`).
- → **Pas de calcul de destinataires côté front**, et le nettoyage est garanti :
  un worker qui ne détient plus aucun lien reçoit un message à liens vides et purge ses
  règles/routes pour ce groupe.

> Alternatives écartées : envoi ciblé (nécessite de mémoriser les destinataires passés,
> y compris sur suppression) ; scan disque côté worker (ne couvre ni la mise à jour
> dynamique, ni le cross-worker).

## 5. Modifications concrètes

### 5.1 Bundle partagé — `remotelabz-message-bundle`

> Dépôt : `remotelabz/remotelabz-message-bundle` (path repository, `lib/remotelabz-message-bundle`
> côté front **et** côté worker). À pousser sur le dépôt puis synchronisé dans les deux `lib/`.

- **Nouveau** `Message/SecurityMessage.php` :

  ```php
  class SecurityMessage
  {
      private string $content; // JSON de la topologie d'un groupe
      private string $group;   // UUID du groupe = clé d'état côté worker

      public function __construct(string $content, string $group = '') { ... }
  }
  ```

- **Nouveau** `Tests/SecurityMessageTest.php`.
- Le front et le worker doivent être déployés **après** le bundle (sinon classe inconnue).

### 5.2 Nouveau service — `SharedLabSecurityManager`

```php
final class SharedLabSecurityManager
{
    public function buildLinks(Group $group): array;   // topologie d'un groupe donné
    public function syncGroup(Group $group): void;     // broadcast de cette topologie
}
```

- `buildLinks(Group $group)` :
  - charger les règles `LabShare` du groupe (repository `findBy(['group' => $group])`) ;
  - pour chaque règle `(S, L)` : instances retenues de `S` et de `L` dans `G` (§3) ;
  - sérialisation JSON `{group, links:[{a:{uuid,network,workerIp}, b:{...}}]}` (dédup) ;
  - réseau via `(string) $labInstance->getNetwork()` → CIDR
    (`Network::__toString`, `lib/network-bundle/Entity/Network.php:159`).
- `syncGroup(Group $group)` : boucle sur les workers, `SecurityMessage($json, $group->getUuid())`
  + `AmqpStamp($workerIp)`.
- Emplacement : `src/Service/…` selon les conventions du front (service Symfony,
  autoconfigure).

### 5.3 Déclencheurs (`syncGroup()`)

| # | Événement | Fichier / point | Quand |
|---|---|---|---|
| 1 | **Placement** d'un lab du groupe | `LabLaunchRequestMessageHandler` | après `setWorkerIp` + dispatch `ACTION_CREATE` |
| 2 | **Lancement** device | `InstanceManager::start()` (front) | couvre le restart après stop |
| 3 | **Arrêt** device | `InstanceManager::stop()` (front) | l'instance sort de la topologie de **son** groupe |
| 4 | **Suppression** lab | `InstanceStateMessageHandler` | réception `STATE_DELETED` (broadcast → pas besoin du `workerIp` de l'instance supprimée) |
| 5 | **Création / suppression d'une règle** | `LabController::update` (§2.5) | après flush : `syncGroup()` pour le groupe de la règle ; si le lab retirait ses dernières instances partagées, message à liens vides → nettoyage |
| 6 | **Handshake** worker | `WorkerHandshakeMessageHandler` | renvoi, **pour chaque groupe** ayant des instances placées sur ce worker (un message par groupe) |
| 7 | **Retrait d'un lab d'un groupe** | `GroupController::removeLabAction` (`:848`) | purge des règles impactées + `syncGroup()` |

Ces points couvrent les cas arbitrés : **lancement + arrêt/suppression + handshake**,
plus les évolutions de configuration des règles. Le handshake est indispensable :
après un reboot worker, iptables, routes et état local sont perdus et la topologie est
réappliquée intégralement.

### 5.4 Routage Messenger — `config/packages/messenger.yaml`

Ajouter `SecurityMessage` vers le transport `worker` :

```yaml
remotelabz_message_routing:
    Remotelabz\Message\Message\SecurityMessage: worker
```

(à adapter à la syntaxe exacte du fichier — le front route déjà
`InstanceActionMessage`, `LabLaunchRequestMessage`, etc. vers `worker`.)

## 6. Format du payload (JSON)

```json
{
  "group": "3f2b9c1e-....-....-....-............",
  "links": [
    {
      "a": { "uuid": "9a1c...", "network": "10.11.0.0/24", "workerIp": "192.168.11.132" },
      "b": { "uuid": "77bd...", "network": "10.11.1.0/24", "workerIp": "192.168.11.133" }
    }
  ]
}
```

- Liens **non orientés** : le worker génère les deux directions d'`ACCEPT`.
- `group` : un message = la topologie d'**un** groupe ; le worker l'utilise comme clé
  d'état (remplacement par groupe, union de tous les groupes — cf. plan worker §5).

## 7. Séquence (textuelle)

```
Prof configure : règle de partage (S, L, G1) — édition de lab
        │
        ▼
LabController::update
  · persist LabShare (validations §2.3)
  · syncGroup(G1) ────────────────────┐
                                      ▼
                        SecurityMessage { group: G1, links: [S(G1)↔L(G1)] }
                        broadcast → worker.1, worker.2 …
                                      │
                                      ▼
                        worker : remplace l'entrée [G1] dans l'état
                        · union avec les autres groupes (G2 intact)
                        · iptables : rebuild shared_forward (union)
                        · routes : diff vs var/shared-security.json

Puis, indépendamment de la configuration :
L'utilisateur A rejoint L dans G1 ; le prof lance S dans G1
  → triggers 1/2 (placement, start) → rebuild idempotent → S(G1) ↔ L(A,G1) ✅
Instances de L dans G2 : aucune règle (S,L,G2) → jamais de lien avec S(G1) ❌
```

## 8. Points d'attention

- **Isolation des groupes** : jamais de message « global » ; le périmètre d'un message est
  toujours l'ensemble des instances portant le même `_group` et les règles de ce groupe.
  C'est la garantie qu'aucune instance de `L(G2)` n'accède à `S(G1)`.
- **Rupture API** : disparition de la clé `shared` (`seeAction`, payload d'édition) →
  documenter dans `CHANGELOG.md` ; remplacer par `shares`.
- **Déploiement coordonné** : bundle → workers → front. Un front ancien n'envoie rien
  (sans effet) ; un worker ancien reçoit un message inconnu.
- **Idempotence** : le front peut envoyer des messages redondants (placement + start,
  par exemple) — sans effet, le worker reconstruit le même état.
- **Labs physiques** : hors périmètre (filtre `virtuality == 1`).
- Le front **ne change pas** son routage de sous-réseaux (`routeAdd` vers le worker
  propriétaire) : seul l'inter-worker est nouveau, géré par les workers.
- **Prochaine étape** : ce document sert de base au ticket ; l'implémentation front
  (entité, migration, API, UI, service) sera faite ultérieurement.

## 9. Tests & scénarios d'acceptation

1. **Unitaires — modèle** :
   - création de règle valide / rejet (`S == L`, lab cible absent du groupe, doublon) ;
   - purge des règles lors du retrait d'un lab du groupe et lors de la suppression d'un lab ;
   - `buildLinks()` : produit cartésien `instances(S) × instances(L)` sur une règle ;
     **aucun lien sans règle**, même si les deux labs sont dans le même groupe ;
     instances non placées / stopped / physiques / user-owned exclues ;
     dédup, lien vers soi-même exclu ;
   - isolation multi-groupes : `(S, L, G1)` ne produit aucun lien pour `G2`.
2. **Manuel — 2 workers, règle `(A, L, G1)`** :
   - labs **A**, **L** répartis sur les deux workers, une règle `(A, L, G1)` ;
   - ping `A ↔ L` joignables ; un lab **B** du même groupe **sans règle** reste isolé ;
   - suppression de la règle → connectivité coupée, règles purgées des workers ;
   - stop device de A → liens retirés sur **tous** les workers ; start → rétablis ;
   - delete de L → topologie réduite ; reboot d'un worker → handshake → état restauré.
3. **Manuel — isolation G1/G2 (scénario §1.1)** :
   - lab `L` instancié dans `G1` (utilisateur A) **et** dans `G2` ;
   - règle `(S, L, G1)` + lancement de `S` dans `G1` → ping `S(G1) ↔ L(A,G1)` OK ;
   - ping `S(G1) ↔ L(G2)` **échoue** (vérifier aussi `iptables -S shared_forward`
     : aucune règle mentionnant le réseau de `L(G2)`) ;
   - mettre à jour la topologie de `G2` (lancement/arrêt d'une instance `L(G2)`) →
     les règles de `G1` subsistent sur les workers.
