# Connexion Internet des labs — permission `isInternetAuthorized`, état `isInternetConnected` et messages `InstanceActionMessage`

## Fichiers concernés

| Rôle | Fichier |
|------|---------|
| Drapeau de lab (permission, défaut `true`) | `src/Entity/Lab.php` (`isInternetAuthorized`) |
| État d'une instance | `src/Entity/LabInstance.php` (`isInternetConnected`) |
| Agrégat affiché (page + éditeur) | `src/Repository/LabInstanceRepository.php` (`isLabInternetConnected`) |
| Migration (labs existants → `true`) | `migrations/Version20261012120000.php` |
| Envoi des messages sur toutes les instances | `src/Service/LabInternetManager.php` |
| Routes page + API (état seulement, drapeau inchangé) | `src/Controller/LabController.php` (`connect_internet`, `disconnect_internet`, `api_connect_internet`, `api_disconnect_internet`) |
| Écriture du drapeau + agrégat de l'éditeur | `src/Controller/LabController.php` (`updateActionTest`, `api_get_lab_test`, `showAction`) |
| Auto-connexion au lancement | `src/MessageHandler/LabLaunchRequestMessageHandler.php` |
| Sérialisation du lab côté éditeur | `src/Repository/LabRepository.php` (`findLabInfoById`) |
| Bouton Connect/Disconnect sur la page du lab | `templates/lab/view.html.twig` |
| Case « Enable Internet access » du formulaire (édition) | `assets/js/components/Editor2/themes/default/ejs/form_lab.ejs` |
| Globals d'état de l'éditeur + setters | `assets/js/components/Editor2/themes/default/js/javascript.js` |
| Lecture de l'état à l'ouverture du lab | `assets/js/components/Editor2/themes/default/js/functions.js` |
| Menu « More actions » + requête AJAX + formulaire | `assets/js/components/Editor2/themes/default/js/actions.js` |
| Routage Messenger | `config/packages/messenger.yaml` (`InstanceActionMessage` → `worker`) |
| Traitement côté worker | `remotelabz-worker/src/MessageHandler/InstanceActionMessageHandler.php` |
| Règles réseau appliquées sur le worker | `remotelabz-worker/src/Service/Instance/InstanceManager.php` (`connectToInternet` / `disconnectFromInternet`) |

Voir aussi `README-LabPlacement.md` : le placement du lab sur le worker (et
donc le `workerIp` utilisé comme clé de routage) en découle directement.

---

## 1. Modèle : le drapeau est la permission, l'état vient des instances

`Lab::isInternetAuthorized` (colonne `lab.is_internet_authorized`, défaut `true`)
est uniquement la **permission** : dit si le lab a le droit d'accéder à
Internet.

- à `true`, chaque instance du lab est connectée automatiquement dès qu'elle
  est lancée (§3), et l'auteur (ou un admin) voit le bouton de la page du lab
  ainsi que l'entrée du menu *More actions* de l'éditeur ;
- à `false`, **aucun bouton ni aucune entrée de menu n'est affiché** : la seule
  façon de (re)donner l'accès est la case *Enable Internet access* du
  formulaire d'édition.

Le drapeau n'est écrit que par ce formulaire (`LabController::updateActionTest`) :
les boutons Connect/Disconnect ne le modifient jamais. Conséquence : couper
Internet depuis la page ou l'éditeur ne retire pas la permission, le lab se
reconnecte donc tout seul au prochain lancement.

`LabInstance::isInternetConnected` (colonne `lab_instance.is_internet_connected`)
est l'**état constaté**, écrit par le front quand le message part (et remis à
zéro au lancement d'un lab non autorisé, cf. §3). C'est lui qui, permission
accordée, choisit le libellé affiché :

| Permission (lab) | État agrégé | Affiché |
|------------------|-------------|---------|
| `false`          | indifférent | **rien** (page et éditeur) |
| `true`           | non connecté | *Connect Internet* |
| `true`           | connecté    | *Disconnect Internet* |

L'état affiché est un **agrégat au niveau du lab** :
`LabInstanceRepository::isLabInternetConnected()` renvoie vrai dès qu'au moins
une instance vivante du lab est connectée (les instances `deleting`,
`deleted` et `error` sont ignorées : elles gardent un drapeau périmé). La page
du lab et l'éditeur regardent la même valeur, ce qui a du sens puisque les
boutons agissent sur **toutes** les instances du lab.

Le drapeau est exposé aux groupes de sérialisation `api_get_lab`,
`api_get_lab_template`, `export_lab` et `sandbox` → un lab exporté ou importé
conserve sa politique, et les labs créés après la migration héritent de `true`
(la migration met également `DEFAULT 1`).

---

## 2. Du bouton au worker

```
Page du lab (view.html.twig)             Éditeur (actions.js)            Formulaire (form_lab.ejs)
  GET/POST /labs/{id}/connect              .action-labinternet           #form-lab-edit → PUT /api/labs/test/{id}
  GET/POST /labs/{id}/disconnect           POST /api/labs/{id}/internet/{connect|disconnect}
        │                                           │                              │
        └───────────────────────┬───────────────────┴──────────────────────────────┘
                                ▼
                     LabInternetManager::connectLab / disconnectLab
                       │   (le drapeau lab.isInternetAuthorized n'est PAS touché :
                       │    il ne change que via le formulaire d'édition)
                       └─ pour chaque LabInstance du lab :
                       │     ├─ workerIp null (jamais placée)      → ignorée
                       │     ├─ état deleting/deleted/error        → ignorée
                       │     ├─ serialize(..., groupes ['worker']) → descriptor JSON
                       │     ├─ dispatch InstanceActionMessage
                       │     │     (ACTION_CONNECT / ACTION_DISCONNECT)
                       │     │     AmqpStamp(workerIp)  ───────────► file `worker`
                       │     └─ labInstance.isInternetConnected = connect  (optimiste)
                       └─ flush

File `worker` (routée `InstanceActionMessage: worker`, binding key = IP du worker)
                                ▼
           InstanceActionMessageHandler (worker)
             case ACTION_CONNECT    → InstanceManager::connectToInternet(descriptor)
             case ACTION_DISCONNECT → InstanceManager::disconnectFromInternet(descriptor)   ← ABSENT, cf. §5.2
```

Points de vigilance :

- **Routage.** `AmqpStamp($labInstance->getWorkerIp())` reprend le même mécanisme
  que toutes les actions d'instance : la clé de binding du worker est son IP
  (`binding_keys: [192.168.11.132]` côté front, `[127.0.0.1]` côté worker).
- **Pas d'HTTP.** Les anciennes méthodes privées `connect`/`disconnect` qui
  appelaient `GET/POST http://{worker}/lab/connect/internet` ont été supprimées :
  ce chemin n'a jamais existé côté worker (aucune route du même nom sur aucune
  branche), il était donc mort — d'où l'intérêt de repasser par Messenger.
  `config/services.yaml` du worker n'a rien à changer, `InstanceActionMessage`
  est déjà routé vers le worker sur les deux applications.
- **Silence radio.** Le front écrit `isInternetConnected` lui-même ; il n'attend
  aucune réponse (cf. §5.3, côté worker le retour d'état est volontairement
  ignoré).
- **`updateActionTest`.** Le `PUT /api/labs/test/{id}` de l'éditeur est le
  **seul** écrivain du drapeau : si la case *Enable Internet access* a changé,
  il écrit le drapeau **et** déclenche la connexion/déconnexion de toutes les
  instances (§2). La réponse renvoie `isInternetAuthorized` et
  `isInternetConnected`, ce qui permet à l'éditeur de rafraîchir son menu
  immédiatement, et `api_get_lab_test` renvoie les deux de la même façon pour
  pré-remplir l'état au chargement (`getLabInfo()`).
- **Boutons sans effet sur la permission.** `connect_internet`,
  `disconnect_internet` et leurs homologues API `api_connect_internet` /
  `api_disconnect_internet` ne font que propager l'état aux instances et
  renvoyer l'agrégat `isInternetConnected` : le drapeau reste inchangé.

---

## 3. Auto-connexion au lancement

`LabLaunchRequestMessageHandler` :

```php
// dispatch ACTION_CREATE (file worker, workerIp déjà posé par le placement)
if ($labInstance->getLab()->isInternetAuthorized()) {
    $this->bus->dispatch(
        new InstanceActionMessage($labJson, $labInstance->getUuid(), InstanceActionMessage::ACTION_CONNECT),
        [new AmqpStamp($labInstance->getWorkerIp(), ...)]
    );
    $labInstance->setInternetConnected(true);
} else {
    // lab non autorisé : l'instance démarre déconnectée et un éventuel
    // drapeau périmé d'une session précédente est remis à zéro.
    $labInstance->setInternetConnected(false);
}
```

- **Ordre garanti.** `ACTION_CREATE` puis `ACTION_CONNECT` partent sur la même
  file, consommée séquentiellement par le worker : la création du bridge
  `br-<uuid8>` (qui porte le lab) précède toujours son rattachement à l'interface
  Internet (`LinkTwoOVS`). Aucun verrou n'est nécessaire.
- Le `flush` qui suit repère le message de placeur libéré (état `created`) —
  cf. `README-LabPlacement.md` §4.
- Une instance déjà placée et connectée n'est pas re-connectée au lancement :
  c'est la permission qui décide, pas l'historique. C'est aussi ce qui rend
  une déconnexion manuelle transitoire : elle tient jusqu'au prochain
  lancement.

---

## 4. Interface

| Où | Ligne | Condition d'affichage / libellé |
|----|-------|----------------------------------|
| Page du lab, bouton *Connect/Disconnect Internet* | `templates/lab/view.html.twig:30` | visible **seulement** si `lab.isInternetAuthorized` (dans le bloc déjà réservé à l'auteur et aux admins) ; libellé selon `internetConnected` (agrégat transmis par `showAction`) |
| Éditeur, entrée du menu *More actions* | `assets/js/.../actions.js:1551` | visible **seulement** si `IS_INTERNET_AUTHORIZED`, et si teacher/teacher-editor avec `AUTHOR == 1` ou admin/superadmin — **sans** le verrou `EDITION`/`LOCK` (on peut couper Internet même lab verrouillé en lecture) ; libellé selon `IS_INTERNET_CONNECTED` |
| Éditeur, handler AJAX | `assets/js/.../actions.js:1034` | classe `.action-labinternet` ; `connect = !IS_INTERNET_CONNECTED`, et la réponse `isInternetConnected` met à jour l'état |
| Formulaire d'édition, case *Enable Internet access* | `assets/js/.../form_lab.ejs:54` | bloc `<% if (action == 'edit') %>` : **édition uniquement** (la création n'envoie pas la clé → défaut `true`); c'est elle qui écrit la permission |

L'éditeur lit les deux valeurs via `GET /api/labs/info/{id}`
(`LabRepository::findLabInfoById` renvoie `isInternetAuthorized`,
`showActionTest` ajoute l'agrégat `isInternetConnected`), les met en cache dans
les globals `IS_INTERNET_AUTHORIZED` / `IS_INTERNET_CONNECTED`
(`javascript.js`, setters `setIsInternetAuthorized` / `setIsInternetConnected`)
et les réactualise après chaque requête réussie : ouverture du lab
(`getLabInfo()` dans `printLabTopology`), requête d'Internet (`isInternetConnected`
de la réponse) et sauvegarde du formulaire (les deux champs de la réponse
`PUT /api/labs/test/{id}`). La normalisation de `isInternetAuthorized` reste
permissive (absent → `true`, défaut du modèle) tandis que celle de
`isInternetConnected` est stricte (absent → `false`).

> **Pourquoi pas `layout.ejs`.** Ce gabarit n'est référencé par aucun JS ni twig :
> il est mort. La seule entrée du menu contextuel de l'éditeur qui existe est
> construite dans `actions.js` à partir de `.action-moreactions` (icône
> *More actions*).

---

## 5. Côté worker : changements requis

### 5.1 Déjà en place — rien à faire

- Routage : `InstanceActionMessage` → transport `worker` sur les deux apps,
  clé de binding = IP du worker. Le descriptor est sérialisé avec les groupes
  `worker` (contient `bridgeName`, réseau, uuid, …) : c'est ce qu'attend
  `connectToInternet()`.
- Ordre `CREATE` → `CONNECT` : même file, consommateur unique.
- Les périphériques `natif` (switchs internes, Services) et les interfaces de
  périphériques ne sont pas touchés : `connectToInternet`/`disconnectFromInternet`
  ne modifient que le lien patch OVS entre le bridge du lab et l'interface
  Internet, la table de routage 4 et la règle NAT `POSTROUTING`.

### 5.2 `ACTION_DISCONNECT` n'est pas gérée — bloquant

`InstanceActionMessageHandler.php:92` ne contient que :

```php
case InstanceActionMessage::ACTION_CONNECT:
    $ReturnArray = $this->instanceManager->connectToInternet($message->getContent(), $message->getUuid());
    $returnState = InstanceStateMessage::STATE_STARTED;
    break;
```

Il n'y a **aucun** `case InstanceActionMessage::ACTION_DISCONNECT`. Un message
de déconnexion tombe dans le `switch` sans rien faire : `$returnState` reste
`""`, `$ReturnArray` reste `null`, et la construction du message de retour
(`new InstanceStateMessage(...)`, hors `try`) lève une exception
(`Wrong state provided`) → 3 retries (2 s), puis rejet. **`disconnectFromInternet()`
n'est donc jamais appelé** : couper Internet côté front ne coupe rien.

Correction attendue :

```php
case InstanceActionMessage::ACTION_DISCONNECT:
    $this->instanceManager->disconnectFromInternet($message->getContent());
    return;   // voir §5.3
```

### 5.3 Le retour d'état est cassé pour CONNECT/DISCONNECT — bloquant

Même en suivant le pattern des autres cas, `ACTION_CONNECT` échoue après le
travail réseau déjà effectué :

1. `connectToInternet()` est `void` → `$ReturnArray = null` et `$instanceType`
   reste `""` (le cas ne l'a jamais affecté) ;
2. `new InstanceStateMessage("", null, "started", null)` lève une **TypeError**
   (`Argument #2 ($uuid) must be of type string, null given`) — le `dispatch` se
   trouve **hors** du `try/catch` ;
3. le handler échoue → le worker rejoue le message **jusqu'à 3 fois** (son
   `retry_strategy` : `max_retries: 3`, `delay: 2000`), et chaque replay
   ré-applique `connectToInternet()` : règles NAT et `ip rule` **doublonnées**
   (cf. §5.4).

Deux façons de corriger, la première recommandée :

- **(A) Ne rien renvoyer** — `return;` juste après le travail réseau pour
  CONNECT et DISCONNECT. Le front suit `isInternetConnected` lui-même et aucun
  état dédié n'existe dans `InstanceStateMessage` ;
- **(B) Renvoyer un état valide** — affecter `$instanceType =
  InstanceStateMessage::TYPE_LAB` et construire `$ReturnArray` (au minimum
  `['uuid' => $message->getUuid(), 'options' => []]`). Attention, deux pièges :
  un retour `STATE_STARTED` ferait passer `LabInstance.state` à `started` (état
  jamais atteint normalement par un lab), et un retour `STATE_ERROR` sur une
  instance encore `created` tomberait dans le cas
  `InstanceStateMessageHandler` → `setState(STATE_DELETED)` : l'instance entière
  serait **supprimée** à cause d'un échec `iptables`. Si le chemin des erreurs
  est conservé, il faut envoyer un état que le front ignorera, ou filtrer côté
  front les messages dont `options['state']` vaut `connect`/`disconnect`.

Quel que soit le choix, **ne jamais laisser partir `STATE_ERROR` en `TYPE_LAB`
pour ces deux actions.**

### 5.4 Idempotence de `connectToInternet()`

Le travail réseau n'est pas rejouable en l'état :

| Commande | Garde-fou | Défaut |
|----------|-----------|--------|
| `IPTables::append(POSTROUTING, MASQUERADE …)` | `IPTables::exists()` dans `disconnectFromInternet` | **absente** dans `connectToInternet` → une règle par replay |
| `IPTools::ruleAdd('from <cidr>', 'lookup 4')` et `ruleAdd('to …')` | `IPTools::ruleExists()` | **absent** → des entrées `ip rule` dupliquées |
| routes table 4 (`routeAdd`) | `IPTools::routeExists()` | déjà gardées |
| `OVS::LinkTwoOVS` (`portAdd … --may-exist`) | oui | déjà idempotent |

Corriger au minimum : garder `append` avec `IPTables::exists(...)` et `ruleAdd`
avec `IPTools::ruleExists(...)` — cela rend le replay du §5.3 inoffensif et
permet de reconnecter un lab déjà connecté.

### 5.5 Désymétrisation connect/disconnect

`connectToInternet` ajoute **deux** règles de politique : `from <cidr> lookup 4`
et `to <cidr> lookup 4`. `disconnectFromInternet` ne supprime que la règle
`from` : la règle `to` reste (et se duplique à chaque cycle, cf. §5.4). Ajouter :

```php
if (IPTools::ruleExists('to ' . $labNetwork, 'lookup 4')) {
    IPTools::ruleDelete('to ' . $labNetwork, 'lookup 4');
}
```

### 5.6 Plusieurs labs connectés sur le même worker — limitation connue

Tout ce que fait `connectToInternet()` est **global au worker**, pas à l'instance :

- `labNetwork = app.network.lab.cidr` (`LAB_NETWORK=10.11.0.0/16`) : le CIDR de
  **tous** les labs du worker, pas le réseau de l'instance (pourtant disponible
  dans le descriptor) ;
- `bridgeInt = app.network.lab.internet_interface` (`INTERNET_INTERFACE_ACCESS`)
  : une seule interface de données par worker ;
- le port patch côté interface Internet s'appelle
  **`Patch-ovs-<bridgeInt>`** : le **même nom pour tous les labs**
  (`OVS::LinkTwoOVS`, `src/Bridge/Network/OVS.php:184`) et `setInterface` fait
  pointer son `peer` vers le dernier lab connecté.

Conséquences avec deux labs A et B sur un worker :

| Événement | Effet réel |
|-----------|------------|
| A connecté, puis B connecté | le port partagé `Patch-ovs-<bridgeInt>` est réutilisé (`--may-exist`) mais son `peer` est **repointé** vers le bridge de B : le trafic retour Internet → A arrive sur B |
| déconnexion de A | `UnlinkTwoOVS` supprime le port `Patch-ovs-<bridgeInt>` **partagé** (OVS.php:193) : B perd son lien ; `ruleDelete`/`routeDelete`/`IPTables::delete` suppriment aussi les règles globales dont B dépend |
| double `connectToInternet` | règles NAT/routage dupliquées (§5.4) mais partagées par tout le worker |

En clair : **aujourd'hui un seul lab par worker peut réellement être connecté
à Internet** ; la désactivation de ce lab casse le lien des autres s'il y en a.
Corrections possibles côté worker, par ordre de coût :

1. nommer le port patch par lab (`Patch-ovs-<bridgeInt>-<bridge>`), supprimer
   uniquement le sien à la déconnexion ;
2. déclarer les règles `ip rule`/routes/NAT **au nom du réseau de l'instance**
   (`$labInstance['network']`) et ne les retirer que si plus aucune instance de
   ce worker n'est connectée (compteur en base, ou `refcount` sur les règles) ;
3. ou, plus simplement, garantir qu'un worker ne porte qu'un lab connecté
   (repli documenté : le front envoie quand même les messages, l'effet est
   partiel).

Cette limite est **antérieure** à cette évolution (le chemin HTTP d'origine
appelait exactement les mêmes méthodes) : elle est documentée ici parce que le
basculement par lab la rend visible — auparavant le pilotage était cassé de
bout en bout, donc jamais observée.

### 5.7 Divers

- `InstanceManager::interconnect()`/`disinterconnect()` (anciens helpers
  appelés par le code HTTP supprimé) utilisent toujours `$labInstance->getWorketIp()`
  — typo pré-existante sur un getter qui s'appelle `getWorkerIp()` : ces
  méthodes sont mortes, ne pas les réactiver en l'état.
- `connectToInternet()` est déclarée `connectToInternet(string $descriptor)`
  mais appelée avec deux arguments ; PHP les ignore, mais autant aligner la
  signature.
- La description des erreurs (JSON invalide → `BadDescriptorException`, sortie
  `ProcessFailedException`) reste gérée par le `try/catch` existant du handler :
  c'est la construction du message de retour qui pose problème, pas le `catch`.

---

## 6. Ce qui n'est pas affecté

| Sujet | Pourquoi |
|-------|----------|
| Exports (`ACTION_EXPORT_*`), copies, sandbox | n'utilisent pas `connectToInternet` ; le lab exporté conserve juste le drapeau |
| Démarrage/arrêt des périphériques | `natif` et les interfaces de devices ne sont jamais modifiés par connect/disconnect (seuls le lien patch, la table 4 et la NAT) |
| Placement / `LabPlacementCache` | `LabInternetManager` ne fait que dispatcher, il ne touche ni à `getFreeWorker()` ni aux réservations |
| Sérialisation worker/sandbox/export | `isInternetAuthorized` n'est pas ajouté aux groupes `worker` : le descriptor envoyé au worker ne change pas de forme |

---

## 7. Vérification

```bash
# Syntaxe + routes
php -l src/Service/LabInternetManager.php
php bin/console debug:router | grep -E 'connect_internet|disconnect_internet|api_.*internet'

# Migration appliquée
php bin/console doctrine:migration:list | grep 20261012120000

# Front compilé (pas de lint JS exploitable : .eslintrc legacy, eslint 9, aucun script)
npm run build            # ou, si npm est absent : node_modules/.bin/encore dev

# Agrégat affiché par la page et par l'éditeur (doit valoir 0/1 selon l'état)
php bin/console doctrine:query:dql \
  "SELECT COUNT(l.id) FROM App\Entity\LabInstance l WHERE l.lab = <id> AND l.isInternetConnected = TRUE AND l.state NOT IN ('deleting','deleted','error')"
```

Sur une installation réelle :

```bash
# Le drapeau part bien en message et pas en HTTP (aucun appel /lab/connect/internet)
grep -a 'LabInternetManager:toggleLab' var/log/*.log | tail

# Le worker reçoit bien les deux actions (après correction §5.2/§5.3)
journalctl -u remotelabz.service --since "10 minutes" | grep -E 'ACTION_(CONNECT|DISCONNECT)'

# État
mysql -e "SELECT name, is_internet_authorized FROM lab WHERE id = <id>;"
mysql -e "SELECT uuid, worker_ip, is_internet_connected FROM lab_instance WHERE lab_id = <id>;"
```

À noter : les deux workers de l'environnement de dev sont actuellement
`available = 0` — sans worker disponible, `LabInternetManager` ignore
silencieusement les instances sans `workerIp` (c'est voulu : elles suivront le
drapeau au lancement).
