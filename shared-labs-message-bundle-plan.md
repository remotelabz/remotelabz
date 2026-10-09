# Plan de modification — Bundle partagé : `remotelabz-message-bundle`

> Issue associée : [#1128 Add sharing lab](https://github.com/remotelabz/remotelabz/issues/1128)
> (issue worker : [#212 Shared lab](https://github.com/remotelabz/remotelabz-worker/issues/212))
>
> Document à appliquer sur le dépôt **`remotelabz/remotelabz-message-bundle`**.
> Plans companions : [`shared-labs-front-plan.md`](./shared-labs-front-plan.md) (front, implémenté)
> et [`shared-labs-worker-plan.md`](./shared-labs-worker-plan.md) (worker).

## 1. Contexte & pourquoi ce bundle

Le front calcule la topologie des labs partagés d'un groupe et la diffuse vers
**tous** les workers : un `SecurityMessage` **par groupe et par worker**
(`SharedLabSecurityManager::syncGroup()`, plan front §4 et §5.2).

Le bundle est un *path repository* partagé par le front **et** par le worker
(`lib/remotelabz-message-bundle` dans les deux dépôts, `composer.json` →
`repositories` côté front) : la classe de message doit donc vivre ici pour être
visible des deux côtés sans duplication.

**Ce qui change dans le bundle** : uniquement l'ajout d'un message et de son
test. Aucune modification des messages existants, aucune dépendance ajoutée
(`php >= 7.3` reste inchangé).

## 2. Modifications à appliquer

### 2.1 Nouveau fichier `Message/SecurityMessage.php`

```php
<?php

namespace Remotelabz\Message\Message;

/**
 * Topology of the shared labs of one group.
 *
 * The content is the JSON of the whole topology of a single group:
 * {"group":"<group uuid>","links":[{"a":{...},"b":{...}}, ...]}.
 *
 * The group uuid is the state key on the worker side: a message replaces the
 * entry of that group and the worker rebuilds its rules from the union of every
 * group, so the messages of the other groups are never overwritten. A message
 * with an empty link list means "no shared lab in this group" and makes the
 * worker purge its rules and routes for that group.
 */
class SecurityMessage
{
    private string $content;

    private string $group;

    public function __construct(string $content, string $group = '')
    {
        $this->content = $content;
        $this->group = $group;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getGroup(): string
    {
        return $this->group;
    }

    public function setGroup(string $group): self
    {
        $this->group = $group;

        return $this;
    }
}
```

### 2.2 Nouveau fichier `Tests/SecurityMessageTest.php`

```php
<?php

namespace Remotelabz\Message\Tests;

use PHPUnit\Framework\TestCase;
use Remotelabz\Message\Message\SecurityMessage;

class SecurityMessageTest extends TestCase
{
    public function testConstruct()
    {
        $message = new SecurityMessage('{"group":"3f2b9c1e","links":[]}');

        $this->assertInstanceOf(SecurityMessage::class, $message);
        $this->assertEquals('{"group":"3f2b9c1e","links":[]}', $message->getContent());
        $this->assertEquals('', $message->getGroup());
    }

    public function testConstructWithGroup()
    {
        $message = new SecurityMessage('{"group":"3f2b9c1e","links":[]}', '3f2b9c1e');

        $this->assertEquals('3f2b9c1e', $message->getGroup());
    }

    public function testGettersAndSetters()
    {
        $message = new SecurityMessage('{"group":"3f2b9c1e","links":[]}', '3f2b9c1e');

        $message->setContent('{"group":"aa","links":[]}');
        $message->setGroup('aa');

        $this->assertEquals('{"group":"aa","links":[]}', $message->getContent());
        $this->assertEquals('aa', $message->getGroup());
    }

    public function testEmptyTopology()
    {
        $message = new SecurityMessage('{"group":"3f2b9c1e","links":[]}', '3f2b9c1e');

        $topology = json_decode($message->getContent(), true);

        $this->assertIsArray($topology);
        $this->assertEquals([], $topology['links']);
    }
}
```

Rien d'autre à changer : `RemotelabzMessageBundle.php`, `composer.json` et les
messages existants restent inchangés.

## 3. Contrat du message

| Champ | Type | Signification |
|---|---|---|
| `content` | `string` | JSON de la topologie d'**un seul** groupe (format §4) |
| `group` | `string` | UUID du groupe = **clé d'état** côté worker |

Format du `content` (plan front §6) :

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

Sémantique :

- liens **non orientés** (le worker génère les deux directions d'`ACCEPT`) ;
- un message **remplace** l'entrée `[group]` de l'état du worker ; l'état global
  est la **réunion de tous les groupes** (les autres messages ne sont jamais
  écrasés) ;
- `links: []` = « aucun lab partagé dans ce groupe » → purge des règles/routes de
  ce groupe sur chaque worker (nettoyage garanti, pas besoin de calcul de
  destinataires côté front) ;
- le worker filtre lui-même les liens qui le concernent (comparaison avec
  `app.worker.ip`).

## 4. Intégration côté front (déjà appliquée dans `remotelabz/remotelabz`)

Ces éléments sont donnés à titre de référence : ils sont **hors du bundle**.

1. `config/packages/messenger.yaml` :

   ```yaml
   routing:
       Remotelabz\Message\Message\SecurityMessage: worker
   ```

   Le transport `worker` est un exchange AMQP `direct` dont la binding key est
   l'IP du worker : chaque envoi est ciblé par `new AmqpStamp($workerIp, AMQP_NOPARAM, [])`
   (`SharedLabSecurityManager::syncGroup()`).

2. Émetteur : `src/Service/SharedLabSecurityManager.php`
   (`buildLinks(Group)` calcule la topologie, `syncGroup(Group)` diffuse un
   message par worker disponible).

3. Déclencheurs : placement d'un lab (`LabLaunchRequestMessageHandler`),
   start/stop d'un device (`InstanceManager`), suppression d'instance
   (`InstanceStateMessageHandler` sur `STATE_DELETED`), création/suppression
   d'une règle (`LabController::updateActionTest`), retrait d'un lab d'un groupe
   (`GroupController::removeLabAction`), handshake worker
   (`WorkerHandshakeMessageHandler`).

## 5. Côté worker

Le worker devra :

- consommer `SecurityMessage` (handler à écrire d'après
  [`shared-labs-worker-plan.md`](./shared-labs-worker-plan.md)) ;
- remplacer l'entrée `[group]` de son état, reconstruire les règles sur la
  **réunion de tous les groupes**, purger iptables/routes du groupe si
  `links` est vide.

Aucun changement de routage n'est attendu côté worker pour la réception : les
transports/routages Messenger sont du côté émetteur.

## 6. Vérifications

Dans le dépôt du bundle :

```bash
composer install
composer test      # simple-phpunit : Tests/SecurityMessageTest.php
composer phpcs     # PSR2, hors longueur de lignes
```

Côté front (une fois le bundle synchronisé dans `lib/remotelabz-message-bundle`) :

```bash
composer install   # path repository → recharge la classe
php bin/console cache:warmup
vendor/bin/phpunit tests/Service/SharedLabSecurityManagerTest.php tests/Service/LabShareManagerTest.php
php bin/console doctrine:migrations:migrate   # Version20261009120543 : lab_share + drop lab.shared
```

> **Important** : Symfony valide l'existence des classes listées dans
> `framework.messenger.routing` au chargement du conteneur. Tant que
> `SecurityMessage` n'existe pas côté front, **toute** commande `bin/console`
> (et les tests fonctionnels) échouent par
> `Invalid Messenger routing configuration: class ... not found`.
> C'est le comportement attendu tant que le bundle n'est pas déployé (plan front
> §5.1 : bundle → workers → front).

## 7. Déploiement

1. Pousser ces fichiers sur `remotelabz/remotelabz-message-bundle`.
2. Synchroniser `lib/remotelabz-message-bundle` côté front **et** côté worker
   (`composer install` ou `git pull` dans le path repository).
   *Si une copie locale des fichiers existe déjà dans `lib/`, la retirer avant
   le `git pull` (fichiers non suivis) pour éviter un conflit.*
3. Déployer les workers **puis** le front.
   - front ancien + bundle à jour : sans effet (aucun message émis) ;
   - worker ancien + message reçu : message inconnu (erreur côté worker, à
     éviter en déployant le worker avant le front).

## 8. Points d'attention

- **Rétrocompatibilité** : aucun message existant n'est modifié ; un worker à
  jour ignore un front qui n'émet rien.
- **Idempotence** : le front peut émettre des messages redondants (placement +
  start, handshake + start) — sans effet, le worker reconstruit le même état.
- **Isolation des groupes** : jamais de message « global » ; le périmètre d'un
  message est toujours un seul groupe (`group` = clé d'état).
- Le bundle ne doit **pas** dépendre de Doctrine, Symfony Messenger ni du
  réseau : il reste un simple DTO (aucune validation du JSON dans le message,
  validation d'intégrité déléguée aux émetteurs/récepteurs).
