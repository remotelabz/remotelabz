# Spécification worker : type de carte réseau QEMU + minimum d'interfaces

> Côté **front** (`remotelabz`) : champs déjà implémentés.
> Côté **worker** (`remotelabz-worker`) : ce document décrit les changements à faire.

## 1. Nouveaux champs dans le descripteur

Le front sérialise le `LabInstance` avec le groupe `worker` (JMS Serializer,
`identical_property_naming_strategy` → les clés JSON sont identiques aux noms
des propriétés PHP).

Champ(s) ajouté(s) sur l'entité `Device` :

| Clé JSON (dans `deviceInstance.device`) | Type    | Défaut   | Signification |
|-----------------------------------------|---------|----------|---------------|
| `network_card_type`                     | string  | `e1000`  | Modèle de carte réseau QEMU utilisé pour **toutes** les NIC de la VM |
| `minimum_network_interfaces`            | integer | `0`      | Nombre minimum d'interfaces réseau que la VM doit voir au démarrage |

Valeurs possibles de `network_card_type` : `e1000`, `e1000e`, `virtio-net-pci`,
`rtl8139`, `vmxnet3`.

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

## 2. Logique attendue à la construction de la ligne de commande QEMU

```php
// Rétrocompatibilité : descripteurs produits par un front ancien
$card = $device->network_card_type ?? 'e1000';
$min  = (int) ($device->minimum_network_interfaces ?? 0);

// Uniquement pour les VM QEMU (lxc / physical / natif : comportement inchangé)
$isQemu = ($device->hypervisor->name ?? '') === 'qemu';
```

1. **Modèle de carte** : remplacer le `-device e1000` codé en dur par
   `-device {$card}` pour **chaque** interface déjà réalisée (tap).

2. **Comptage** : `n = count(deviceInstance.networkInterfaceInstances)`
   (toutes les interfaces, connectées ou non).

3. **Complément dummy** :
   ```php
   $pad = max(0, $min - $n);
   for ($k = 1; $k <= $pad; $k++) {
       $id   = 'dummy' . $k;
       $mac  = /* MAC unique, voir §3 */;
       $args[] = '-netdev';  $args[] = "user,id={$id}";
       $args[] = '-device';  $args[] = "{$card},netdev={$id},mac={$mac}";
   }
   ```

   Exemple avec `virtio-net-pci`, 2 interfaces connectées et `minimum = 4` :

   ```
   ... -netdev tap,id=tap0,... -device virtio-net-pci,netdev=tap0,mac=52:54:00:aa:bb:01
       -netdev tap,id=tap1,... -device virtio-net-pci,netdev=tap1,mac=52:54:00:aa:bb:02
       -netdev user,id=dummy1  -device virtio-net-pci,netdev=dummy1,mac=52:54:00:11:22:01
       -netdev user,id=dummy2  -device virtio-net-pci,netdev=dummy2,mac=52:54:00:11:22:02
   ```

### Pourquoi `user` et pas `tap`

Les interfaces de complément sont en **netdev user** : aucun tap n'est créé sur
l'hôte, le nombre d'interfaces de l'hôte reste inchangé, et il n'y a rien à
détruire à l'arrêt (`tap`/`bridge` ne sont gérés que pour les interfaces
réelles). Ces NIC sont donc « hors lab » (pas de connectivité vers un autre
équipement) — leur seul but est de satisfaire le minimum exigé par l'image au
démarrage.

## 3. Adresses MAC

- Les MAC doivent être **uniques à l'intérieur d'une même VM** (QEMU le
  vérifie), même si la VM est hébergée avec d'autres VMs (le netdev `user`
  étant isolé, une collision inter-VM n'a pas d'impact, mais évitons-la).
- OUI QEMU : `52:54:00`.
- Recommandation : dériver la MAC de `hash(instanceUuid + index)` plutôt que
  d'une constante `52:54:00:11:22:33` fixe, pour éviter tout conflit avec les
  MAC déjà générées par `DeviceInstance::populate()` côté front.
- Les MAC des interfaces réelles ne changent pas (elles viennent du descripteur).

## 4. Validation / sécurité

- Le champ `network_card_type` est **whitelisté** côté front
  (`Assert\Choice` + `ChoiceType`), mais le worker doit **re-vérifier** avant
  injection dans l'argv : regex `^[a-zA-Z0-9_-]+$` + liste des modèles
  autorisés ; sinon, repli sur `e1000`.
- `minimum_network_interfaces` borné à `[0, 64]` côté front ; le worker borne
  aussi (`max(0, min($min, 64))`) pour éviter un argv géant.
- Les ids de netdev dummy (`dummy1`, `dummy2`, …) ne doivent pas entrer en
  collision avec les ids des netdevs tap existants.

## 5. Rétrocompatibilité

| Front \ Worker | Worker à jour | Worker ancien |
|---|---|---|
| **Ancien** (pas de champs) | défauts `e1000` / `0` → comportement actuel | inchangé |
| **Nouveau** | feature active | les champs sont ignorés → comportement actuel |

→ version minimale worker à documenter dès que la feature est utilisée.

## 6. Cycle de vie

- `create` / `start` : les dummy sont générés à la construction de l'argv.
- `stop` / `delete` / `reset` : aucun nettoyage spécifique (pas de ressource
  hôte créée). Ne pas compter les interfaces dummy dans le décompte des taps à
  détruire.
- Re-démarrage : le même complément est recalculé depuis le descripteur.

## 7. Tests à faire côté worker

1. Device avec `network_card_type = virtio-net-pci` et 2 interfaces → vérifier
   que `-device e1000` n'apparaît plus et que les 2 NIC sont en `virtio-net-pci`.
2. `minimum_network_interfaces = 4` avec 2 interfaces connectées → exactement 2
   paires `-netdev user,id=dummyN` / `-device …,netdev=dummyN,mac=…`.
3. `minimum_network_interfaces = 2` avec 2 interfaces → **aucun** dummy ajouté.
4. Descripteur sans les 2 champs → behaviour identique à avant (`e1000`, pas de dummy).
5. Device `hypervisor != qemu` → aucun impact.
6. `network_card_type = "virtio-net-pci; rm -rf /"` (injection) → repli `e1000`.
