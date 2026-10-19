# Message partagé `SecurityMessage` — bundle `remotelabz-message-bundle`

> **Statut** : **implémenté** (issue
> [#1128 Add sharing lab](https://github.com/remotelabz/remotelabz/issues/1128) ;
> issue worker :
> [#212 Shared lab](https://github.com/remotelabz/remotelabz-worker/issues/212)).
>
> Ce document décrit le message `SecurityMessage` du bundle partagé et le
> comportement effectif des trois dépôts qui l'échangent.
>
> Doc compagnon côté front : [`shared-labs-front.md`](./shared-labs-front.md).

## 1. Rôle du bundle

Le bundle `remotelabz-message-bundle` est un **path repository Composer**
présent à l'identique dans les deux dépôts (`lib/remotelabz-message-bundle`),
namespace `Remotelabz\Message\` :

- il ne contient que des **DTO** : aucun code d'exécution, aucune dépendance
  Symfony, Doctrine ou réseau ;
- la sérialisation des messages est **PHP native** (`PhpSerializer`), donc les
  classes de message doivent être **identiques des deux côtés** : d'où le
  bundle partagé ;
- un **ordre de déploiement est imposé** : **bundle → workers → front** (un
  récepteur sans la classe échoue au décodage).

`SecurityMessage` a été ajouté au bundle avec son test
(`Tests/SecurityMessageTest.php`) ; aucun message existant n'a été modifié et
aucune dépendance ajoutée (`php >= 7.3` inchangé).

## 2. Le message — `Message/SecurityMessage.php`

```php
class SecurityMessage
{
    private string $content; // JSON de la topologie d'un seul groupe
    private string $group;   // UUID du groupe = clé d'état côté worker

    public function __construct(string $content, string $group = '')
    // getters / setters
}
```

### Contrat

| Champ | Type | Signification |
|---|---|---|
| `content` | `string` | JSON de la topologie d'**un seul** groupe (format ci-dessous) |
| `group` | `string` | UUID du groupe — **clé d'état** côté worker |

Format du `content` :

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

Sémantique (commentaire de classe + comportement worker) :

- liens **non orientés** : le worker génère les deux directions d'`ACCEPT` ;
- un message **remplace** l'entrée `[group]` de l'état du worker ; l'état
  global est la **réunion de tous les groupes** — les messages des autres
  groupes ne sont jamais écrasés ;
- `links: []` = « aucun lab partagé dans ce groupe » → le worker **purge** ses
  règles et routes pour ce groupe (nettoyage garanti, sans calcul de
  destinataires côté front) ;
- chaque worker filtre lui-même les liens qui le concernent (comparaison avec
  `app.worker.ip`).

## 3. Côté front (producteur)

`SecurityMessage` est **émis par le front uniquement** :

- **Routage** : `Remotelabz\Message\Message\SecurityMessage: worker`
  (`config/packages/messenger.yaml:27`) — le transport `worker` est un exchange
  AMQP `direct` dont la binding key est l'IP du worker ;
- **Émetteur** : `src/Service/SharedLabSecurityManager.php`
  (`buildLinks(Group)` calcule la topologie, `syncGroup(Group)` diffuse un
  message **par worker disponible**, ciblé par
  `new AmqpStamp($workerIp, AMQP_NOPARAM, [])`).

**Déclencheurs** (détails : `shared-labs-front.md` §5) :

1. placement d'un lab — `LabLaunchRequestMessageHandler` ;
2. start/stop d'un device — `InstanceManager::start()` / `stop()` ;
3. suppression d'une instance — `InstanceStateMessageHandler` sur `STATE_DELETED` ;
4. création/suppression d'une règle de partage — `LabController::updateActionTest` ;
5. retrait d'un lab d'un groupe — `GroupController::removeLabAction` ;
6. handshake worker — `WorkerHandshakeMessageHandler`.

Le front **consomme** `SecurityMessage` ? Non — il n'y a pas de handler front ;
la classe n'est référencée côté front que par le routage (le front ne l'émet
que vers le transport `worker`).

## 4. Côté worker (consommateur)

Le worker consomme `SecurityMessage` via son handler de shared labs
(`SecurityMessageHandler` → `InstanceManager::updateSharedSecurity()`), avec la
sémantique décrite en §2 : remplacement par groupe, union de tous les groupes,
purge si `links: []`, reconstruction des règles iptables et des routes. Aucun
changement de routage n'est nécessaire côté worker pour la réception (le
routage Messenger ne s'applique qu'à l'émission).

## 5. Vérifications

Dans le dépôt du bundle :

```bash
composer install
composer test      # simple-phpunit : Tests/SecurityMessageTest.php
composer phpcs     # PSR2, hors longueur de lignes
```

Côté front (bundle synchronisé dans `lib/remotelabz-message-bundle`) :

```bash
composer install
php bin/console cache:warmup
vendor/bin/phpunit tests/Service/SharedLabSecurityManagerTest.php tests/Service/LabShareManagerTest.php
```

> **À noter** : Symfony valide l'existence des classes listées dans
> `framework.messenger.routing` au chargement du conteneur. Tant que
> `SecurityMessage` n'existe pas côté front, **toute** commande `bin/console`
> échoue par `Invalid Messenger routing configuration: class ... not found`.
> D'où l'ordre de déploiement **bundle → workers → front**.

## 6. Déploiement

1. Pousser les fichiers sur `remotelabz/remotelabz-message-bundle` ;
2. Synchroniser `lib/remotelabz-message-bundle` côté front **et** côté worker
   (`composer install` ou `git pull` dans le path repository) ;
3. Déployer les workers **puis** le front :
   - front ancien + bundle à jour : sans effet (aucun message émis) ;
   - worker ancien + message inconnu : erreur de décodage — à éviter.

## 7. Points d'attention

- **Rétrocompatibilité** : aucun message existant n'est modifié ; un worker à
  jour ignore un front qui n'émet rien.
- **Idempotence** : le front peut émettre des messages redondants (placement +
  start, handshake + start) — sans effet, le worker reconstruit le même état.
- **Isolation des groupes** : jamais de message « global » ; le périmètre d'un
  message est toujours un seul groupe (`group` = clé d'état).
- Le bundle ne doit **pas** dépendre de Doctrine, Symfony Messenger ni du
  réseau : il reste un simple DTO (aucune validation du JSON dans le message,
  validation d'intégrité déléguée aux émetteurs/récepteurs).
