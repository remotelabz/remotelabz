# Conversion EVE-NG ⇄ RemoteLabz — import des labs `.unl` et export avec images

> **Statut** : **proposition de conception** (document de travail). Aucun code
> d'import `.unl` n'existe aujourd'hui dans `remotelabz` ; la branche courante
> `Import-from-eve-ng` porte uniquement les fichiers d'exemple
> (`doc/PNet-Lab/*.unl`). Ce document décrit le format EVE-NG, le mapping vers le
> modèle RemoteLabz, l'assistant d'import (avec résolution **interactive** des
> images manquantes) et l'export de lab **incluant les images de devices**.
>
> **Documents liés** : `doc/messenger-rabbitmq.md`,
> `doc/worker-network-interfaces.md`, `doc/hidden-devices.md`.

---

## 1. Contexte et objectif

EVE-NG (et son ancêtre UNetLab) est un hyperviseur de maquettes réseau. Un lab y
est décrit par un fichier **XML** d'extension `.unl`. RemoteLabz adresse le même
besoin (virtualiser des topologies réseau) mais ajoute tout ce qui manque à
EVE-NG : comptes, groupes, instances par étudiant, placement automatique sur
workers, sujets de TP, etc.

Deux chantiers sont couverts ici :

1. **Import** d'un lab EVE-NG (`.unl`, éventuellement zippé) dans RemoteLabz.
   Comme les images EVE-NG (IOSv, IOSvL2, pfSense, Windows, templates Linux
   EVE-NG…) **ne sont pas** celles de RemoteLabz, l'import doit gérer le cas
   « image introuvable » de façon **guidée et non bloquante** : réutiliser une
   image existante, en téléverser une nouvelle, fournir une URL, ou choisir une
   image **équivalente** dans une fenêtre de résolution.

2. **Export** d'un lab RemoteLabz en archive **portant les images des devices**,
   avec un moyen fiable de **récupérer sur le worker** les images de disque
   (QEMU `.qcow2` / conteneurs LXC) — l'export actuel le fait partiellement et
   de façon fragile, on le professionnalise.

L'objectif d'ergonomie est simple : **glisser le `.unl` (ou le `.zip`) → une
fenêtre de revue → corriger les quelques images signalées en rouge → Importer**.
Tout le reste est déduit automatiquement.

---

## 2. Le format EVE-NG `.unl` (entrée)

### 2.1 Enveloppe

Fichier XML, racine `<lab>` avec des attributs de métadonnées :

```xml
<lab name="VRF-BGP-Final" version="1" scripttimeout="300" password=""
     author="2" countdown="60" darkmode="" mode3d="" nogrid=""
     joinable="2" joinable_emails="" openable="2" openable_emails=""
     editable="2" editable_emails="" multi_config_active="Final">
  <topology>
    <nodes> … </nodes>
    <networks> … </networks>
  </topology>
  <objects>
    <textobjects> … </textobjects>
  </objects>
</lab>
```

Sections utiles :

| Section | Contenu |
|---|---|
| `<topology><nodes>` | les équipements (`<node>`) |
| `<topology><networks>` | les segments L2 (`<network>`) et objets système (NAT/cloud) |
| `<objects><textobjects>` | annotations de schéma (`<textobject>`) |

### 2.2 Un nœud (`<node>`)

```xml
<node id="1" type="qemu" template="vios" config="1"
      config_script="config_vios.py" script_timeout="1200"
      config_data="IQohIEx…"               <!-- startup-config, base64 -->
      multi_config="eyJGaW5hbCI6…"         <!-- variantes de config, base64(JSON) -->
      delay="0" icon="Router.png"
      image="vios-adventerprisek9-m.spa.159-3.M3"
      left="339" name="PE1" top="309"
      console="telnet" ethernet="4" nvram="1024" ram="1024"
      serial="0" idlepc="0x0" cpu="1" cpulimit="1"
      uuid="dca83425-…" firstmac="50:e7:cd:00:46:00"
      qemu_options="-machine type=pc,accel=kvm …"
      qemu_version="4.1.0" qemu_arch="x86_64" qemu_nic="virtio-net-pci"
      username="user" password="Aqwzsxedc">
    <interface id="0" name="e0" type="ethernet" network_id="1" … />
    <interface id="1" name="e1" type="ethernet" network_id="2" … />
</node>
```

Attributs déterminants pour la conversion :

| Attribut | Rôle |
|---|---|
| `type` | `qemu`, `vpcs`, `docker`, `iol`, `dynamips`, … |
| `template` | gabarit EVE-NG : `vios`, `viosl2`, `vpcs`, `linux`, `win`, `pfsense`, `docker`… |
| `image` | **identifiant d'image** (le point critique pour l'import) |
| `name` | nom du device |
| `icon` | icône du schéma (`Router.png`, `Desktop.png`, `cloud.png`…) |
| `left` / `top` | position dans le canevas (≈ `editorData.x` / `.y`) |
| `ram` | mémoire (Mo) → flavour RemoteLabz |
| `cpu` | nombre de vCPU |
| `ethernet` | nombre d'interfaces Ethernet |
| `console` | `telnet`, `vnc`, `rdp`, `serial` |
| `config_data` | startup-config **base64** |
| `<interface network_id>` | rattache l'interface à un `<network>` |

### 2.3 Un réseau (`<network>`)

```xml
<network id="1" type="bridge" name="R1iface_0" left="0" top="0"
         visibility="0" icon="cloud.png" size=""/>
<network id="2" type="nat0"   name="Net" left="720" top="144"
         visibility="1" icon="global.png" size=""/>
```

- `type="bridge"` → segment L2 reliant plusieurs interfaces.
- `type="nat0"` / `type="pnet0"` / `type="cloud"` → objet système (accès
  Internet / externe). À convertir en device « natif »/« Service » côté RLZ.
- Toutes les interfaces ayant le **même `network_id`** doivent partager le
  **même identifiant de connexion** côté RemoteLabz.

### 2.4 Les annotations (`<textobject>`)

```xml
<textobject id="1" name="txt 1" type="text">
  <data>JTNDZGl2JTIw…</data>   <!-- HTML, base64 -->
</textobject>
```

`data` est du **HTML encodé en base64** ; RemoteLabz manipule lui aussi du HTML
dans `TextObject::data`. La conversion est donc directe.

### 2.5 Les fichiers fournis en exemple

| Fichier | Taille | Nœuds | Templates/images utilisés |
|---|---|---|---|
| `PNet-Lab/VRF-BGP-Final.unl` | 39 Ko | 7 (3 `vios`, 4 `vpcs`) | `vios-adventerprisek9-m.spa.159-3.M3` |
| `PNet-Lab/RT905 a cloner.unl` | 109 Ko | 44 `qemu` | `linux-ubuntu-24.04-server`, `linux-ubuntu-21.04-desktop`, `win-10-x64-21H1v1`, `pfsense-CE-2.6`, `pfsense-pfs-2.6.0`, `viosl2-adventerprisek9-m.ssa.high_iron_20200929` |
| `PNet-Lab/Lab-VRF-PNet.zip` | — | idem VRF | contient `VRF-BGP-Final.unl` |
| `PNet-Lab/MegaLab-Secu-PNet.zip` | — | idem RT905 | contient `RT905 a cloner.unl` |

Les deux `.zip` ne contiennent **que** le `.unl` (pas d'images) : la résolution
des images est donc indispensable pour ces exemples.

---

## 3. Cible RemoteLabz (rappel)

### 3.1 Le JSON d'export (`export_lab`)

L'export actuel (`GET /admin/labs/{id}/export`) produit une archive
`lab_<uuid>.tar.gz` contenant :

```
lab_<uuid>/
├── lab_<uuid>.json      ← Linode du lab (JMS groupe export_lab)
├── <image>.qcow2        ← images des devices QEMU
└── <imageFilename>.tar.gz ← conteneurs LXC
```

Extrait de `lab_<uuid>.json` :

```json
{
  "name": "Test",
  "shortDescription": "",
  "isTemplate": false,
  "version": "1",
  "scripttimeout": 300,
  "locked": 0,
  "devices": [
    {
      "name": "Switch",
      "brand": "", "model": "",
      "networkInterfaces": [
        { "type": "tap", "name": "eth1", "vlan": 0, "connection": 2, "connectorType": "Straight" },
        { "type": "tap", "name": "eth0", "vlan": 0, "connection": 3, "connectorType": "Straight" }
      ],
      "networkInterfaceTemplate": "eth",
      "type": "switch",
      "nbCpu": 1,
      "virtuality": 1,
      "hypervisor": { "id": 5, "name": "natif" },
      "operatingSystem": { "name": "Natif", "image": "Natif", "hypervisor": { "id": 5, "name": "natif" } },
      "flavor": { "name": "xx-small-256", "memory": 256 },
      "controlProtocolTypes": [],
      "editorData": { "x": 174, "y": 276 },
      "icon": "Switch.png",
      "template": "90-switch"
    }
  ],
  "virtuality": 1,
  "textobjects": [],
  "hasTimer": false
}
```

### 3.2 Entités clés

| Entité | Fichier | Champs utiles |
|---|---|---|
| `Lab` | `src/Entity/Lab.php` | `name`, `shortDescription`, `description`, `virtuality`, `isTemplate`, `devices`, `textobjects`, `hasTimer`, `timer` |
| `Device` | `src/Entity/Device.php` | `name`, `type`, `icon`, `virtuality`, `nbCpu`, `hypervisor`, `operatingSystem`, `flavor`, `networkInterfaces`, `networkInterfaceTemplate`, `editorData`, `template`, `hidden`, `controlProtocolTypes` |
| `OperatingSystem` | `src/Entity/OperatingSystem.php` | `name`, `image` (virtuel), `imageUrl` (URL, `@Exclude`), `imageFilename` (fichier/conteneur **présent sur les workers**), `hypervisor`, `arch`, `flavorDisk` |
| `Flavor` | `src/Entity/Flavor.php` | `name`, `memory` (Mo) |
| `NetworkInterface` | `src/Entity/NetworkInterface.php` | `type` (`tap`), `name` (`eth0`…), `vlan`, **`connection`** (identifiant de lien partagé), `connectorType`, `connectorLabel` |
| `Hypervisor` | `src/Entity/Hypervisor.php` | ids en base : `3=qemu`, `4=lxc`, `5=natif`, `6=physical` |
| `ControlProtocolType` | — | ids en base : `4=vnc`, `5=login`, `6=serial` |
| `FlavorDisk` | `src/Entity/FlavorDisk.php` | `800MB`, `1GB`, `3GB` |

### 3.3 Images : trois natures

1. **Fichier présent sur tous les workers** : `OperatingSystem.imageFilename`
   (ex. `Ubuntu24LTS`, `CentOS7`, `Ubuntu22Desktop`). C'est **la** clé de
   jointure entre la base et les workers, qui doit être copiée sur chaque
   worker (`Files2WorkerManager::copyFileToAllWorkers`).
2. **URL distante** : `OperatingSystem.imageUrl` — le worker télécharge
   lui-même ; aucun fichier n'est géré côté front.
3. **Image virtuelle** : `OperatingSystem.image` n'est jamais persistée, elle
   est calculée à la sérialisation (`imageUrl` sinon `imageFilename`).

> ⚠️ Images disponibles aujourd'hui (base de dev) — extrait :
> `Alpine3.10` (URL), `Migration`, `Ubuntu24.04SrvLTS`→`Ubuntu24LTS`, `Debian11.4`,
> `Natif`, `Service`, `Alpine cnt`→`Alpine-edge`, `CentOS7`, `Fedora40`,
> `ArchLinux`, `openSUSE-Leap15`, `RockyLinux9`, `Ubuntu22.04-Desktop`→`Ubuntu22Desktop`,
> `FreeBSD14`, `KaliLinux2024`, `Debian12`, `Alpine cnt cfg`.
> **Aucune** ne correspond aux images EVE-NG : la résolution sera donc
> systématiquement sollicitée pour les labs PNet.

---

## 4. Correspondances EVE-NG → RemoteLabz

### 4.1 Niveau lab

| EVE-NG `<lab …>` | RemoteLabz `Lab` | Remarque |
|---|---|---|
| `name` | `name` | tronquer/nettoyer si > 255 |
| — | `shortDescription`, `description` | valeurs par défaut, éditables après import |
| `scripttimeout` | `scripttimeout` | défaut 300 |
| — | `virtuality` | `1` (tout est virtualisé) |
| — | `isTemplate = false` | l'import crée un lab normal |
| — | `author` | utilisateur importateur |
| — | `hasTimer = false` | EVE-NG n'a pas d'équivalent |

### 4.2 Niveau nœud

| EVE-NG | RemoteLabz | Règle |
|---|---|---|
| `name` | `Device.name` | direct |
| `type=qemu` + template VM | `Device.type = "vm"`, `hypervisor = qemu (id 3)` | `virtuality=1` |
| `type=vpcs` | `Device.type = "vm"` ou device « natif/Service » | voir §4.2.1 |
| `type=docker` | `Device.type = "container"`, `hypervisor = lxc (id 4)` | |
| `type=iol`/`dynamips` | `Device.type = "vm"`, `hypervisor = qemu` | images non supportées → résolution |
| `image` | `OperatingSystem` (nom + `imageUrl`/`imageFilename`) | **résolution interactive** (§7) |
| `template` | `Device.template` (gabarit RLZ) + `Device.icon` + `Device.type` | catalogue §4.4 |
| `ram` (Mo) | `Flavor.memory` | arrondi à la flavour ≥ la plus proche |
| `cpu` | `Device.nbCpu` | |
| `icon` | `Device.icon` | table §4.5 |
| `left`/`top` | `EditorData.x` / `.y` | direct |
| `console=telnet` | `ControlProtocolType` login/serial | `vnc` si `console=vnc` |
| `config_data` (base64) | `Device.config_data` | base64 → texte ; champ non exporté aujourd'hui (§11) |
| `ethernet=N` + `<interface>` | `NetworkInterface[]` | §4.3 |
| — | `networkInterfaceTemplate = "eth"` | défaut RLZ |

#### 4.2.1 Cas `vpcs` (PC sans image)

`vpcs` est un mini-simulateur IP sans image disque. Deux stratégies :

- **Recommandée** : créer un device « natif »/`Service` (hypervisor `natif`,
  OS `Natif`) portant la config IP reprise de `config_data`. Léger et
  immédiat, aucun téléversement.
- **Alternative** : mapper vers une petite VM Linux (`linux` + flavour
  `xx-small-256`) si on veut un vrai shell.

### 4.3 Niveau réseau : le point le plus délicat

EVE-NG regroupe les interfaces **par `network_id`** (un segment L2 partagé). La
vue topologique RemoteLabz regroupe les interfaces **par
`NetworkInterface.connection`** (entier partagé).

**Algorithme de conversion :**

```
connection_id = 1
pour chaque <network> dans le .unl (par id croissant) :
    si le réseau est un objet système (nat0/pnet0/cloud) :
        créer un device « natif/Service » dédié (option A)
        OU marquer le lien « Internet » (option B)
    sinon :
        connection[id] = connection_id ; connection_id += 1
pour chaque nœud, pour chaque <interface> :
    créer NetworkInterface{
        type   : "tap",
        name   : "eth" + index,            // rebasé sur networkInterfaceTemplate
        vlan   : 0,
        connection : connection[ network_id ],
        connectorType : "Straight"
    }
```

Remarques :

- Le **nom** des interfaces EVE-NG (`e0`, `e0/0`, `Gi0/0`) n'a pas à être
  conservé : RemoteLabz renomme selon `networkInterfaceTemplate` (`eth0`,
  `eth1`, …). On peut toutefois le stocker en `connectorLabel` pour
  l'affichage.
- Deux interfaces sur le **même** `network_id` obtiennent **le même**
  `connection` → RemoteLabz dessine le lien automatiquement
  (`NetworkInterfaceRepository::getTopology` fait un `GROUP BY connection`).
- Un `<network type="nat0">` doit devenir un accès Internet : soit via le
  device « Switch internet »/`Service` existant (`config/templates/6-switchInternet.yaml`),
  soit via le mécanisme « Internet autorisé » du lab.

### 4.4 Catalogue de correspondance des templates (drivers)

Créer un fichier de référence versionné `config/eve-ng/templates.yaml` :

```yaml
# template EVE-NG -> gabarit RemoteLabz
vios:
  device_type: vm
  hypervisor: qemu
  rlz_template: 90-router          # Device.template RLZ (nom du driver)
  icon: Router.png
  os_hint: [vios, iosv, ios]
viosl2:
  device_type: switch
  hypervisor: qemu
  rlz_template: 90-switch
  icon: Switch L3.png
  os_hint: [viosl2, iosv l2]
vpcs:
  device_type: vm
  hypervisor: natif
  rlz_template: 91-vpcs
  os: Natif
  icon: Desktop.png
linux:
  device_type: vm
  hypervisor: qemu
  rlz_template: 92-linux-vm
  icon: Desktop_linux.png
  os_hint: [ubuntu, debian, alpine, rocky, centos]
docker:
  device_type: container
  hypervisor: lxc
  rlz_template: 93-container
  icon: Server_Linux.png
win:
  device_type: vm
  hypervisor: qemu
  rlz_template: 94-windows
  icon: Desktop_Win.png
  os_hint: [win, windows]
pfsense:
  device_type: vm
  hypervisor: qemu
  rlz_template: 95-firewall
  icon: Firewall.png
  os_hint: [pfsense]
```

> ⚠️ Les valeurs `rlz_template` ci-dessus sont **illustratives**. Les drivers
> réellement présents aujourd'hui dans `config/templates/` sont, entre autres,
> `6-switchInternet` (switch internet), `14-DHCPservice`, `1-linuxAlpine`,
> `3-ubuntu24LTSCnt`, `57-ubuntu22DesktopVm`, `113-alpineCntCfg`, … La
> convention de nommage est `<deviceId>-<camelCaseName>`, donc les identifiants
> ci-dessus doivent être **relevés dynamiquement** (par lecture de
> `config/templates/` et du champ `Device.template` en base) plutôt que codés en
> dur.

Le `rlz_template` doit correspondre à un driver **existant** dans
`config/templates/`. Si le driver cible n'existe pas, l'import peut :
(a) réutiliser le driver le plus proche par `device_type`/`hypervisor`, ou
(b) proposer de le créer (l'admin étant `ROLE_ADMINISTRATOR`) — à trancher en
phase 2.

### 4.5 Table d'icônes

| Icône EVE-NG | Icône RemoteLabz | Présente ? |
|---|---|---|
| `Router.png` | `Router.png` | ✅ |
| `Switch L3.png` | `Switch L3.png` | ✅ |
| `Desktop.png` | `Desktop.png` | ✅ |
| `Desktop3.png` | `Desktop3.png` | ✅ |
| `Firewall.png` | `Firewall.png` | ✅ |
| `linux-1.png` | `Linux.png` | ✅ (renommer) |
| `cloud.png` | `Cloud.png` | ✅ (casse) |
| `global.png` | `Cloud.png` | ✅ (défaut) |

`public/build/images/icons/` contient 141 icônes ; la quasi-totalité des icônes
EVE-NG courantes existent. Prévoir un **fallback** `Server_Linux.png` + log.

### 4.6 Annotations

| EVE-NG | RemoteLabz |
|---|---|
| `<textobject><data>` (HTML base64) | `TextObject.data` (décodé) |
| `name` | `TextObject.name` |
| `type=text` | `TextObject.type` |

La position du texte est **dans le HTML** (`style="top:…px; left:…px"`),
conservé tel quel. Rien à extraire côté XML.

---

## 5. Architecture proposée

```
                 ┌──────────────────────────────────────────────────────┐
  .unl / .zip ──►│  Frontend React : « EVE-NG Import Wizard »           │
                 │  (assets/js/components/Lab/EveNgImporter.jsx)        │
                 └───────────────┬──────────────────────────────────────┘
                                 │ 1. POST /api/labs/import/eve-ng/analyze
                                 ▼
                 ┌──────────────────────────────────────────────────────┐
                 │  UnlImporter::analyze()  (src/Service/Lab/Unl/)      │
                 │  - parse XML + zip                                   │
                 │  - mapping topologie -> LabPreview (JSON)            │
                 │  - résolution images : statut par image              │
                 └───────────────┬──────────────────────────────────────┘
                                 │ 2. LabPreview + imageStatus[]
                                 ▼
         ┌────────────────────────  Fenêtre de revue  ──────────────────────┐
         │ devices, liens, images                                 [Importer] │
         │  image "vios-…159-3.M3"  ❓ manquante  [ Choisir ] [Upload] [URL] │
         │  image "linux-ubuntu-24.04-server" ✅ équivalente → Ubuntu24LTS   │
         └───────────────┬──────────────────────────────────────────────────┘
                         │ 3. POST /api/labs/import/eve-ng/commit
                         │    { lab, mapping: { image_id -> { action, os_id }, … } }
                         ▼
         ┌───────────────────────────────────────────────────────┐
         │  UnlImporter::commit()                                │
         │  - crée OperatingSystem manquants                     │
         │  - Files2WorkerManager::copyFileToAllWorkers()        │
         │  - crée Lab + Devices + NIC + EditorData + TextObjects│
         │  - set LabImporter (réutilise l'existant)             │
         └───────────────────────────────────────────────────────┘
```

Services et classes à créer :

| Élément | Rôle |
|---|---|
| `App\Service\Lab\Unl\UnlParser` | parse `.unl`/`.zip` → structure normalisée (`LabPreview`) |
| `App\Service\Lab\Unl\EveNgMapper` | applique `config/eve-ng/templates.yaml` + tables (icônes, flavours) |
| `App\Service\Lab\Unl\UnlImporter` | `analyze()` (dry-run) et `commit()` (persistance) |
| `App\Service\Lab\Unl\ImageResolver` | compare les images du `.unl` à `OperatingSystem` + `/os` worker, calcule le statut, applique le mapping choisi |
| `App\Service\EveNg\ImageCatalog` | CRUD du catalogue de correspondances persisté |
| Entité `EveNgImageMapping` | mémoire des correspondances image EVE-NG → OS RLZ (idempotence) |
| Routes | `POST /api/labs/import/eve-ng/analyze`, `POST /api/labs/import/eve-ng/commit`, `GET /api/eve-ng/images/mappings` |
| React | `assets/js/components/Lab/EveNgImporter.jsx` (modal, jumeau de `LabImporter.jsx`) |

---

## 6. Fenêtre de revue (assistant d'import)

### 6.1 Déroulé

**Étape 0 — Dépôt.** Le même point d'entrée que l'import actuel
(`templates/lab/index.html.twig`, `#importLabModal`) reçoit `.unl`, `.zip`
**et** `.json` (rétro-compatible). Détection du type par extension / contenu.

**Étape 1 — Analyse.** `analyze()` renvoie un `LabPreview` : liste des devices,
liens, annotations, **et** pour chaque image un statut :

| Statut | Sens | Couleur UI |
|---|---|---|
| `matched` | une image RLZ existe (nom/URL identique) | 🟢 |
| `auto` | équivalence proposée automatiquement (heuristique + catalogue) | 🔵 |
| `missing` | aucune correspondance | 🔴 |
| `not_applicable` | `vpcs`/`natif` (pas d'image) | ⚪ |

**Étape 2 — Revue.** Tableau éditable :

```
┌────────────────────────────────────────────────────────────────────────────┐
│ Importer un lab EVE-NG                                                      │
├────────────────────────────────────────────────────────────────────────────┤
│ Lab : T905 a cloner   Devices : 44   Liens : 52   Annotations : 69          │
│                                                                             │
│ Images des devices                                                          │
│  Statut  Image EVE-NG                            → Image RemoteLabz         │
│  🟢      linux-ubuntu-24.04-server                Ubuntu24.04SrvLTS (auto)  │
│  🔵      viosl2-…high_iron_20200929               CentOS7  [changer ▾]      │
│  🔴      vios-adventerprisek9-m.spa.159-3.M3      ⚠ introuvable            │
│             ( ) Choisir une image existante  [ ▾ ]                          │
│             (•) Téléverser un fichier        [ Choisir .qcow2/.img/.ova ]  │
│             ( ) Fournir une URL              [ https://… ]                  │
│             ( ) Créer une VM vierge          disque [ 3 GB ▾ ]             │
│             ( ) Ignorer (à corriger plus tard)                             │
│  🔴      win-10-x64-21H1v1                        ⚠ introuvable   (idem…)  │
│  ⚪      (vpcs)                                   → device natif            │
│                                                                             │
│ [ Annuler ]                                        [ Analyser ] [ Importer ]│
└────────────────────────────────────────────────────────────────────────────┘
```

**Étape 3 — Commit.** Chaque image reçoit un « plan » :
`{ action: "reuse"|"upload"|"url"|"blank"|"skip", os_id?, file?, url?, flavorDisk? }`.

### 6.2 Actions disponibles pour une image manquante

| Action | Effet backend | Cas d'usage |
|---|---|---|
| **Réutiliser** | `OperatingSystem` existant (`os_id`) | image proche déjà présente |
| **Téléverser** | `POST /api/images/upload` (endpoint **existant**) puis `OperatingSystem` + `copyFileToAllWorkers('image', …)` | on possède le `.qcow2`/`.img`/`.ova` |
| **Fournir une URL** | `OperatingSystem.imageUrl = url`, validée par `POST /api/images/validate-url` (existant) | image publique (ex. Alpine) |
| **VM vierge** | `newBlank` (existant) → `OperatingSystem` avec `imageFilename = <name>_<taille>.qcow2` | on installera plus tard |
| **Ignorer** | device créé sans OS résolu (ou OS « placeholder ») | import partiel volontaire |

> 💡 **Solution facile recommandée** : pour chaque image manquante, présélectionner
> l'action la plus probable selon le template EVE-NG :
> `linux-*` → liste des OS Linux RLZ ; `win-*` → OS Windows ; `pfsense-*` →
> pfSense ; `vios*`/`ios*` → catégorie « routeur/switch Cisco » (souvent absente
> → téléversement). L'utilisateur ne fait que valider ou ouvrir la liste.

### 6.3 Réutilisation de l'existant

- **Upload** : `OperatingSystemController::upload()` accepte déjà `.tar` (OVA →
  conversion `convertOva2qcow2`), `.img`, `.qcow2`, et limite la taille à
  `min(upload_max_filesize, post_max_size)`. Le wizard le réutilise tel quel.
- **Form** : `OperatingSystemType` et `assets/js/components/OperatingSystem/form-handler.js`
  couvrent déjà `imageUrl`, `image_Filename`, `hypervisor`, `arch`, `flavorDisk`.
- **Diffusion worker** : `Files2WorkerManager::copyFileToAllWorkers('image', $filename)`
  (AMQP `ACTION_COPYFROMFRONT`) — déjà en place.

---

## 7. Résolution des images : détails

### 7.1 Calcul du statut (côté `analyze`)

```
identifiants_du_unl = { image de chaque <node> }   # ex. {"vios-…", "linux-ubuntu-24.04-server"}
os_rlz              = SELECT name, image_url, image_filename FROM operating_system
images_worker       = GET http://<worker>:<port>/os   → { "qemu":[...], "lxc":[...] }

pour chaque identifiant :
    si identifiant ∈ os_rlz (image_url ou image_filename)          → matched
    sinon si catalogue a une correspondance mémorisée               → auto (os_id)
    sinon si heuristique (même famille/preview nom) forte           → auto (os_id)
    sinon                                                            → missing
    si matched/auto mais l'OS est un fichier absent de images_worker → missing (fichier à (re)diffuser)
```

> L'appel `GET /os` du worker (déjà utilisé par
> `ConfigWorkerController::getOS_Worker`) est essentiel : il permet de
> distinguer « l'OS existe en base » de « **l'image est réellement présente sur
> le worker** ».

### 7.2 Heuristique d'équivalence (catalogue + fuzzy)

1. **Correspondances mémorisées** : table `eve_ng_image_mapping(eve_image → os_id)`.
   Dès qu'un admin a résolu une image, la décision est **réutilisée** (et
   proposée) pour les imports suivants.
2. **Normalisation** : minuscules, suppression des suffixes de version
   (`-21H1v1`, `-2.6.0`, `-159-3.M3`), tokens.
3. **Famille** : `linux|ubuntu|debian|alpine|rocky|centos` → OS Linux RLZ ;
   `win|windows` → OS Windows ; `pfsense` → pfSense ; `vios|iosv` →
   routeur/switch Cisco.
4. **Score** de similarité (Levenshtein / `similar_text` sur le nom normalisé) ;
   au-dessus d'un seuil → proposition `auto`, sinon `missing`.

### 7.3 Persistance des décisions

```sql
CREATE TABLE eve_ng_image_mapping (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    eve_image      VARCHAR(255) NOT NULL UNIQUE,   -- ex. "vios-…159-3.M3"
    operating_system_id INT NOT NULL,              -- FK operating_system
    created_at     DATETIME NOT NULL,
    created_by_id  INT NULL,
    FOREIGN KEY (operating_system_id) REFERENCES operating_system(id) ON DELETE CASCADE
);
```

Cette table rend les imports suivants « one-click » et constitue un
**glossaire partagé** EVE-NG ↔ RemoteLabz.

---

## 8. Export de lab

### 8.1 Ce qui existe déjà

`src/Controller/LabController.php::exportAction()` (`GET /admin/labs/{id}/export`) :

1. sérialise le lab (`LabImporter::export`, groupe `export_lab`) ;
2. vide `public/uploads/lab/export` puis recrée `lab_<uuid>/` ;
3. **QEMU** : pour chaque device dont l'OS a un `imageFilename` **égal** à
   `image`, télécharge `GET http://<worker>:<port>/images/<image>` (cURL, timeout
   600 s) depuis le **premier worker disponible** ;
4. **LXC** : `tar -czf` de `/var/lib/lxc/<imageFilename>` en local, puis `PUT`
   vers `http://<worker>:<port>/images/containers/<archive>.tar.gz` ;
5. écrit `lab_<uuid>.json`, crée `lab_<uuid>.tar.gz`, le renvoie en
   `StreamedResponse` (`Content-Disposition: attachment`).

### 8.2 Limites constatées

| # | Limite | Correctif proposé |
|---|---|---|
| 1 | `FileSystem::remove(.../export)` efface **tous** les exports précédents | ne supprimer que `lab_<uuid>/` et purger par âge |
| 2 | Images **URL** (`imageUrl`) silencieusement absentes de l'archive | option « inclure les images URL » (télécharger côté front, ou conserver la seule URL dans le JSON) |
| 3 | QEMU inclus **uniquement** si `imageFilename === image` | condition inutile/erronée → se baser sur la nature réelle de l'OS |
| 4 | `exec("tar -czf …".$nom)` (injection shell, pas d'erreur gérée) | `Symfony\Component\Process\Process` avec tableau d'arguments |
| 5 | Images volumineuses : `set_time_limit(120)` et `readfile()` en mémoire | streaming direct, exports **asynchrones** pour les gros labs |
| 6 | Pas de choix « avec/sans images » | paramètre `?images=0|1` |
| 7 | Une seule image par nom ; pas de dédoublonnage explicite | dédoublonner avant téléchargement (déjà partiel) |
| 8 | LXC : le conteneur doit exister **localement** sur le front | fallback : rapatrier depuis le worker |

### 8.3 Export enrichi proposé

Route : `GET /admin/labs/{id}/export?images=1&format=tar.gz`

```
lab_<uuid>/
├── lab_<uuid>.json          # + section "images" (manifeste)
├── manifest.json            # { images: [ {name, file, type, bytes, sha256, source_worker} ] }
├── images/<fichier>.qcow2   # images QEMU
└── images/<fichier>.tar.gz  # conteneurs LXC
```

Améliorations :

- **Manifeste** `manifest.json` : liste les images, leurs tailles, provenance
  (worker), et un hash — facilite la ré-importation et l'audit.
- **Asynchrone** : pour les labs lourds, générer l'archive en tâche de fond
  (Messenger) et notifier via Mercure, avec un endpoint de statut
  `GET /api/labs/exports/{token}`.
- **Sans images** : `?images=0` produit uniquement le `.json` (rapide, pour un
  simple partage de topologie).
- **Sélection** : `?devices=1,3,5` pour n'embarquer que certaines images.

---

## 9. Télécharger les images depuis le worker

### 9.1 Existant

| Endpoint worker | Méthode | Usage actuel |
|---|---|---|
| `/os` | `GET` | liste `{ "qemu":[…], "lxc":[…] }` (utilisé par `ConfigWorkerController`) |
| `/images/<image>` | `GET` | téléchargement d'une image QEMU (utilisé par l'export) |
| `/images/containers/<name>.tar.gz` | `PUT` | **upload** d'un conteneur LXC vers le worker |
| `/healthcheck`, `/stats/hardware`, `/stats/hardwarelight` | `GET` | supervision |
| `/worker/port/free` | `GET` | port libre |

Il manque : un **listing complet avec tailles** et un endpoint de
**téléchargement direct** utilisable par l'UI (aujourd'hui le download est
enfermé dans `exportAction`).

### 9.2 Endpoints front proposés

| Route front | Rôle |
|---|---|
| `GET /api/workers/{id}/images` | agrège `GET /os` des workers + métadonnées `OperatingSystem` → liste unifiée (nom, type, taille, présent sur quels workers, OS RLZ associé) |
| `GET /api/operating-systems/{id}/download` | streame l'image depuis le premier worker disponible (`GET /images/<image>`) en `StreamedResponse` — utile pour récupérer une image à la main |
| `GET /api/labs/{id}/export` (+`?images=`) | export complet (cf. §8) |

### 9.3 Solution de téléchargement robuste (front ← worker)

Remplacer le couple `fopen`/`curl` par un **streaming** :

```php
$response = new StreamedResponse(function () use ($workerIp, $port, $image) {
    $in  = fopen("http://$workerIp:$port/images/".rawurlencode($image), 'rb');
    $out = fopen('php://output', 'wb');
    if ($in) { stream_copy_to_stream($in, $out); fclose($in); }
    fclose($out);
});
$response->headers->set('Content-Type', 'application/octet-stream');
$response->headers->set('Content-Disposition', 'attachment; filename="'.$image.'"');
$response->headers->set('X-Accel-Buffering', 'no');   // + proxy buffering off
```

- **Progression** : taille connue via `HEAD`/`Content-Length` → barre de
  progression côté UI (`onUploadProgress`/`onDownloadProgress` Axios déjà
  utilisé pour les uploads d'ISO).
- **Reprise** : supporter `Range` si le worker le permet ; sinon télécharger en
  un flux et documenter la limite.
- **Multi-workers** : boucler sur les workers `available = 1` jusqu'au premier
  HTTP 200 (logique déjà présente dans `exportAction`, à factoriser dans
  `WorkerImageDownloader`).
- **Cohérence** : après un téléversement depuis le wizard, appeler
  `Files2WorkerManager::copyFileToAllWorkers('image', $filename)` **avant** de
  rendre le lab lançable, sinon le device échouera au démarrage sur les autres
  workers.

---

## 10. Flux complet d'import (résumé opérationnel)

1. L'utilisateur (`ROLE_TEACHER`/`ROLE_ADMINISTRATOR`) ouvre **Labs → Importer**
   et dépose `MegaLab-Secu-PNet.zip`.
2. `POST …/analyze` : le front dézippe, `UnlParser` lit le XML, `EveNgMapper`
   produit 44 devices + 52 liens + 69 annotations, `ImageResolver` renvoie :
   - `linux-ubuntu-24.04-server` → 🟢 (ou 🔵 vers `Ubuntu24LTS`),
   - `viosl2-…high_iron_20200929` → 🔴,
   - `vios-adventerprisek9-m.spa.159-3.M3` → 🔴,
   - `win-10-x64-21H1v1` → 🔴,
   - `pfsense-*` → 🔴.
3. La **fenêtre de revue** affiche la topologie et les images. Pour chaque 🔴,
   l'utilisateur choisit une action (§6.2). Les choix sont mémorisés dans
   `eve_ng_image_mapping`.
4. `POST …/commit` : `UnlImporter` crée les `OperatingSystem` manquants, diffuse
   les fichiers sur tous les workers, puis crée `Lab`, `Device`,
   `NetworkInterface`, `EditorData`, `TextObject`.
5. Redirection vers `show_lab` du nouveau lab (même mécanisme que
   `LabImporter.jsx`).

---

## 11. Points d'attention et bugs connus à corriger en parallèle

Ces défauts de l'import/export existants impactent directement la conversion :

| Réf. | Fichier | Problème |
|---|---|---|
| B1 | `src/Service/Lab/LabImporter.php:223` | `new OperatingSystem()` au lieu de `new Flavor()` quand aucune flavour ne matche |
| B2 | `LabImporter.php:168` | accès à `$labJson["hasTimer"]` sans `array_key_exists` → warning |
| B3 | `LabImporter.php:190-207` | un OS créé à l'import n'a **ni `hypervisor` ni `arch`** alors que l'export les porte |
| B4 | `LabController.php:1642` | l'export efface **tous** les exports précédents |
| B5 | `LabController.php:1646` | images URL exclues de l'archive |
| B6 | `LabController.php:175,276,293` | itérations non défensives → warnings sur JSON minimal |
| B7 | `OperatingSystemSerializationListener` **et** `InstanceSerializationListener` | double listener `pre_serialize` pour `OperatingSystem` |

Côté `.unl`, prévoir :

- **encodages** : toujours décoder en UTF-8 ; EVE-NG peut produire des
  `config_data` base64 volumineux (le `RT905` pèse 109 Ko, surtout à cause des
  configs `viosl2`/`win`).
- **configs de démarrage** : `Device.config_data` existe mais **n'est pas** dans
  le groupe `export_lab`. Pour un vrai aller-retour, soit l'ajouter à l'export,
  soit écrire un champ dédié dans le manifeste.
- **`multi_config`** : notion de variantes EVE-NG sans équivalent RLZ → ignorer
  proprement (garder la variante « active »).
- **IDs** : ne jamais réutiliser `id`/`uuid` EVE-NG comme clés RLZ.

---

## 12. Images propriétaires : avertissement

Les images EVE-NG (`vios`, `viosl2`, `win-10-*`, `pfsense-*`) sont soit
propriétaires (Cisco IOSv/IOSvL2, Windows), soit soumises à des licences
particulières. Le wizard **ne doit pas** proposer de les télécharger
automatiquement depuis Internet. Il doit :

- exiger un **téléversement explicite** par un admin (qui atteste détenir les
  droits), ou
- proposer une **image équivalente** (Linux + FRR/Quagga, pfSense CE,
  Alpine/Debian…),
- tracer qui a fourni l'image (`created_by_id` sur `OperatingSystem` /
  `eve_ng_image_mapping`).

---

## 13. Plan de mise en œuvre

**Phase 1 — Socle (import `.unl` sans résolution interactive)**
1. `UnlParser` (XML + `ZipArchive`) + `LabPreview`.
2. `config/eve-ng/templates.yaml` (catalogue) + `EveNgMapper`.
3. Conversion topologie → `connection` (§4.3), icônes (§4.5), textobjects.
4. `UnlImporter::commit()` réutilisant `LabImporter` + corrections B1–B3.
5. Modale React minimale (dépôt → import direct).

**Phase 2 — Résolution interactive des images**
6. `ImageResolver::analyze()` (`OperatingSystem` + `GET /os` worker).
7. Entité `EveNgImageMapping` + migration.
8. Fenêtre de revue (tableau images, 4 actions, upload via endpoint existant).
9. Diffusion systématique sur tous les workers.

**Phase 3 — Export avec images**
10. `?images=0|1`, manifeste, dédoublonnage, `Process` au lieu de `exec`.
11. Factorisation `WorkerImageDownloader` + streaming + progression.
12. (Option) export asynchrone + notification Mercure.

**Phase 4 — Confort**
13. Heuristique d'équivalence enrichie + glossaire partagé.
14. Documentation utilisateur + capture d'écran du wizard.

---

## 14. Annexe A — Exemple de mapping `RT905 a cloner.unl`

| Device EVE-NG | template | image EVE-NG | → type RLZ | → OS RLZ proposé | Action |
|---|---|---|---|---|---|
| Switch (×11) | `viosl2` | `viosl2-…high_iron_20200929` | switch | — | upload / équivalent |
| Linux, DNS, Mail server, Server web 1/2/3, … (×16) | `linux` | `linux-ubuntu-24.04-server` | vm | `Ubuntu24.04SrvLTS` | réutiliser |
| Linux (×1) | `linux` | `linux-ubuntu-21.04-desktop` | vm | — | équivalent (Ubuntu22-Desktop) |
| Win, Win - audit, … (×9) | `win` | `win-10-x64-21H1v1` | vm | — | upload / équivalent |
| pfSense, Passerelle IPSEC/SSL, FW admin (×8) | `pfsense` | `pfsense-pfs-2.6.0`, `pfsense-CE-2.6` | vm | — | équivalent pfSense CE |

Réseaux : 52 `bridge` → autant de `connection` distinctes ; le `nat0` « Net »
→ accès Internet. 69 annotations → autant de `TextObject`.

## 15. Annexe B — Squelette de `LabPreview` renvoyé par `analyze()`

```json
{
  "source": { "format": "eve-ng", "file": "Lab-VRF-PNet.zip", "entry": "VRF-BGP-Final.unl" },
  "lab": { "name": "VRF-BGP-Final", "virtuality": 1, "devices": 7, "links": 6, "textobjects": 15 },
  "devices": [
    {
      "eve_id": 1, "name": "PE1", "eve_template": "vios", "type": "vm",
      "hypervisor": "qemu", "nbCpu": 1, "ram": 1024, "icon": "Router.png",
      "editorData": { "x": 339, "y": 309 },
      "interfaces": [ { "name": "eth0", "connection": 1 }, { "name": "eth1", "connection": 2 } ]
    },
    { "eve_id": 4, "name": "PC_A1", "eve_template": "vpcs", "type": "vm", "hypervisor": "natif", "icon": "Desktop.png" }
  ],
  "images": [
    { "eve_image": "vios-adventerprisek9-m.spa.159-3.M3", "status": "missing",
      "used_by": [1, 2, 3], "suggested_action": "upload", "candidates": [] },
    { "eve_image": "linux-ubuntu-24.04-server", "status": "matched",
      "used_by": [5, 7], "os_id": 7, "os_name": "Ubuntu24.04SrvLTS" }
  ],
  "warnings": [ "template 'vios' absent du catalogue RLZ", "8 images non résolues" ]
}
```

## 16. Annexe C — Glossaire de conversion

| Concept EVE-NG | Concept RemoteLabz |
|---|---|
| `lab` (`.unl`) | `Lab` |
| `node` | `Device` |
| `node.template` | driver (`Device.template` / `config/templates/*.yaml`) |
| `node.image` | `OperatingSystem` (`imageUrl`/`imageFilename`) |
| `node.ram` | `Flavor.memory` |
| `node.left/top` | `EditorData.x/y` |
| `network` (bridge) | `NetworkInterface.connection` (lien) |
| `network` (nat0/pnet0) | accès Internet / device `Service` |
| `interface network_id` | `NetworkInterface.connection` |
| `textobject` | `TextObject` |
| `config_data` | `Device.config_data` |
| worker (EVE) | `ConfigWorker` (machine `qemu`/`lxc`) |
