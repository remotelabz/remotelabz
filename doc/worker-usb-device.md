# Option QEMU `usb_enabled` — modifications à faire dans le worker

> **Statut** : implémenté côté **front** (`remotelabz`).
> Ce document décrit la modification à apporter côté **worker**
> (`remotelabz-worker`), qui n'a pas encore été modifiée.

## 1. Nouveau champ dans le descripteur

Une case à cocher **« Enable usb device »** a été ajoutée :

- dans le formulaire de définition des devices (`/admin/devices/new`,
  `/admin/devices/{id}/edit`) ;
- dans l'interface graphique de l'éditeur de lab (options du node) ;
- dans la page de visualisation du device.

Elle n'est affichée que pour un OS utilisant l'hyperviseur **qemu**. Pour tout
autre hyperviseur, elle est masquée et forcée à `false`.

Le champ est porté par l'entité `Device` (`src/Entity/Device.php`) et
sérialisé dans le groupe `worker`. Il est donc présent dans
`deviceInstance.device` du JSON envoyé au worker :

| Clé JSON | Type | Défaut | Signification |
|---|---|---|---|
| `usb_enabled` | bool | `false` | Ajoute le support USB QEMU (`-usb`, `usb-tablet`, `usb-ehci`) à la VM |

Exemple de fragment reçu par le worker :

```json
{
  "deviceInstances": [
    {
      "uuid": "0d7c…",
      "device": {
        "name": "Windows-10",
        "hypervisor": { "name": "qemu" },
        "usb_enabled": true
      }
    }
  ]
}
```

Rétrocompatibilité : un descripteur produit par un ancien front ne contient
pas la clé. Le worker doit donc utiliser `false` par défaut
(`!empty($deviceInstance['device']['usb_enabled'])`).

### 1.1 Migration côté front

La migration `Version20261125120000` ajoute la colonne (`DEFAULT 0`) et passe
`usb_enabled = 1` sur les devices existants dont l'hyperviseur est `qemu` :
le worker ajoutait jusqu'ici le support USB dans tous les cas, on préserve
donc le comportement des VM existantes. Les nouveaux devices sont créés avec
la case décochée.

**Ordre de déploiement** : déployer le front (avec sa migration) avant le
worker. Un descripteur émis par un ancien front ne contient pas la clé ; le
worker l'interprète alors comme `false`, ce qui retirerait le support USB de
toutes les VM pendant la fenêtre de transition.

## 2. Modification à faire dans le worker

Fichier : `src/Service/Instance/InstanceManager.php`, dans
`create_qemu_device()`.

Le bucket `'usb' => []` est déclaré avec les autres buckets de `$parameters`
(autour de la **ligne 5342**) :

```php
$parameters = [
    'system' => [],
    'smp' => ['-smp'],
    'network' => [],
    'local' => [],
    'usb' => [],
    'agent' => [],
    'access' => [],
    'uefi' => [],
    'cdrom' => [],
    'other_options' => []
];
```

Le bloc qui ajoute le support USB est actuellement exécuté **dans tous les
cas** (autour des **lignes 5618-5622**) :

```php
//Add usb support
array_push($parameters['usb'],
    '-usb', '-device','usb-tablet,bus=usb-bus.0',
    '-device','usb-ehci,id=ehci'
);
```

Il faut l'encadrer dans un test sur le nouveau champ :

```php
// Support USB QEMU : uniquement si le device a l'option usb_enabled.
if (!empty($deviceInstance['device']['usb_enabled'])) {
    array_push($parameters['usb'],
        '-usb', '-device','usb-tablet,bus=usb-bus.0',
        '-device','usb-ehci,id=ehci'
    );
}
```

Le bucket `'usb' => []` reste déclaré : quand l'option est désactivée, il
reste simplement vide. L'ordre des buckets (et donc des arguments QEMU) est
inchangé.

Aucune autre modification n'est nécessaire : le bloc `qemu_start()` concatène
déjà tous les buckets de `$parameters` dans l'ordre du tableau.

## 3. Rétrocompatibilité

| Front \ Worker | Worker à jour | Worker ancien |
|---|---|---|
| **Ancien front** (clé absente) | `false` par défaut → pas d'USB (déployer le front en premier) | inchangé |
| **Nouveau front**, option décochée | pas d'arguments USB | option ignorée → USB toujours présent |
| **Nouveau front**, option cochée | `-usb -device usb-tablet,… -device usb-ehci,…` | option ignorée → USB toujours présent |

## 4. Tests worker

1. `usb_enabled = true` → la commande QEMU contient `-usb`,
   `-device usb-tablet,bus=usb-bus.0` et `-device usb-ehci,id=ehci`.
2. `usb_enabled = false` → aucun de ces trois arguments.
3. Clé `usb_enabled` absente du descripteur → aucun argument USB.
4. Device LXC / natif / physique → aucun changement (champ ignoré).
