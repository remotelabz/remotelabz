# Carte réseau QEMU + minimum d'interfaces réseau — fonctionnement

> **Statut** : **implémenté des deux côtés**.
> - Côté **front** (`remotelabz`) : champs sur l'entité `Device`, whitelist UI,
>   sérialisation dans le descripteur worker.
> - Côté **worker** (`remotelabz-worker`, implémenté sur la branche
>   `Get-IP-From-Worker` / `dev`) : sélection du modèle de carte, interfaces
>   dummy de complément, re-validation.

Objectif : laisser le modèle de carte réseau QEMU et un **nombre minimum
d'interfaces** à la VM, pour les images qui exigent N interfaces au démarrage.

## 1. Champs du descripteur

Le front sérialise le `LabInstance` avec le groupe `worker` (JMS Serializer,
naming strategy identique aux propriétés PHP). Les deux champs sont sur
l'entité `Device` (`src/Entity/Device.php:268`, `:278`) et donc dans
`deviceInstance.device` :

| Clé JSON | Type | Défaut | Signification |
|---|---|---|---|
| `network_card_type` | string | `e1000` | Modèle de carte réseau QEMU utilisé pour **toutes** les NIC de la VM |
| `minimum_network_interfaces` | integer | `0` | Nombre minimum d'interfaces réseau que la VM doit voir au démarrage |

Valeurs possibles : `e1000`, `e1000e`, `virtio-net-pci`, `rtl8139`, `vmxnet3`
(whitelist côté front : `Assert\Choice` + `ChoiceType` dans le formulaire du
device).

Exemple de fragment du descripteur reçu par le worker :

```json
{
  "deviceInstances": [
    {
      "device": {
        "name": "Routeur-1",
        "hypervisor": { "name": "qemu" },
        "network_card_type": "virtio-net-pci",
        "minimum_network_interfaces": 4
      },
      "networkInterfaceInstances": [
        { "macAddress": "52:54:00:aa:bb:01", "networkInterface": { "type": "tap", "connection": 1 } },
        { "macAddress": "52:54:00:aa:bb:02", "networkInterface": { "type": "tap", "connection": 2 } }
      ]
    }
  ]
}
```

## 2. Implémentation worker

`src/Service/Instance/InstanceManager.php` (worker) :

### 2.1 Résolution des paramètres — `resolveQemuNetworkSettings()` (`:6207-6217`)

Appelée à la construction du descripteur interne (`:6213-6214`) :

- **uniquement pour `hypervisor == 'qemu'`** ; LXC / natif / physique :
  comportement inchangé (`e1000`, minimum `0`) ;
- `resolveNetworkCardType()` (`:6220-6240`) : **re-validation** côté worker —
  regex `^[a-zA-Z0-9_-]+$` + whitelist `QEMU_NETWORK_CARD_TYPES`
  (`:30`) ; toute valeur invalide → repli **`e1000`** (protection contre
  l'injection dans l'argv) ;
- `resolveMinimumNetworkInterfaces()` (`:6242-6247`) : borné à
  `[0, QEMU_MINIMUM_NETWORK_INTERFACES_MAX]` (`= 64`, `:31`).

### 2.2 Ligne de commande QEMU (`create_qemu_device()`)

- **Modèle de carte** : le `-device e1000` codé en dur est remplacé par
  `-device {$card}` pour **chaque** interface tap réelle (`:5422-5428`) ;
- **Complément dummy** — `buildDummyNetworkInterfaceParameters()`
  (`:6249-6274`) :

  ```php
  $dummyCount = max(0, $minimumNetworkInterfaces - count($networkInterfaces));
  for ($k = 1; $k <= $dummyCount; $k++) {
      '-netdev', 'user,id=dummy'.$k
      '-device', "{$card},netdev=dummy{$k},mac={$mac}"
  }
  ```

  Exemple avec `virtio-net-pci`, 2 interfaces connectées et minimum `4` :

  ```
  ... -netdev tap,id=tap0,... -device virtio-net-pci,netdev=tap0,mac=52:54:00:aa:bb:01
      -netdev tap,id=tap1,... -device virtio-net-pci,netdev=tap1,mac=52:54:00:aa:bb:02
      -netdev user,id=dummy1  -device virtio-net-pci,netdev=dummy1,mac=<MAC dérivé>
      -netdev user,id=dummy2  -device virtio-net-pci,netdev=dummy2,mac=<MAC dérivé>
  ```

  Les dummy sont ajoutés au bucket `network` de `$parameters`
  (`:5499-5502`), et le log de démarrage porte
  `minimum_network_interfaces` et `dummy_network_interfaces`
  (`:5506-5508`).

### 2.3 Pourquoi `user` et pas `tap`

Les interfaces de complément sont en **netdev user** : aucun tap n'est créé
sur l'hôte, le nombre d'interfaces de l'hôte reste inchangé, et il n'y a rien
à détruire à l'arrêt (les `tap`/`bridge` ne sont gérés que pour les
interfaces réelles). Ces NIC sont « hors lab » (pas de connectivité vers un
autre équipement) — leur seul but est de satisfaire le minimum exigé par
l'image.

### 2.4 Adresses MAC — `generateDummyMac()` (`:6276-6292`)

- OUI QEMU `52:54:00` + 3 octets dérivés de
  `hash('sha256', $deviceUuid . '|' . $index . '|' . $salt)` ;
- le salt est incrémenté tant que la MAC est déjà utilisée (`$usedMacAddresses`,
  initialisé avec les MAC des interfaces réelles) : **uniques dans la VM**,
  sans conflit avec les MAC générées par `DeviceInstance::populate()` côté
  front ;
- les MAC des interfaces réelles ne changent pas (elles viennent du
  descripteur).

### 2.5 Cycle de vie

- `create` / `start` : les dummy sont recalculés à la construction de l'argv ;
- `stop` / `delete` / `reset` : aucun nettoyage spécifique (pas de ressource
  hôte créée) ; les dummy ne comptent pas dans le décompte des taps à détruire ;
- re-démarrage : même complément recalculé depuis le descripteur.

## 3. Rétrocompatibilité

| Front \ Worker | Worker à jour | Worker ancien |
|---|---|---|
| **Ancien** (pas de champs) | défauts `e1000` / `0` → comportement actuel | inchangé |
| **Nouveau** | feature active | les champs sont ignorés → comportement actuel |

## 4. Tests worker (scénarios)

1. `network_card_type = virtio-net-pci` avec 2 interfaces → `-device e1000`
   absent, les 2 NIC en `virtio-net-pci`.
2. `minimum_network_interfaces = 4` avec 2 interfaces → exactement 2 paires
   `-netdev user,id=dummyN` / `-device …,netdev=dummyN,mac=…`.
3. `minimum_network_interfaces = 2` avec 2 interfaces → **aucun** dummy.
4. Descripteur sans les 2 champs → comportement identique à avant (`e1000`,
   pas de dummy).
5. `hypervisor != qemu` → aucun impact.
6. `network_card_type = "virtio-net-pci; rm -rf /"` (injection) → repli
   `e1000`.
