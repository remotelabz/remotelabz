# Console d'administration d'une instance (`view/admin`)

> Brouillon destiné à alimenter la future documentation utilisateur.
> Il décrit les règles **telles qu'elles sont appliquées**, après correction du
> décalage entre l'affichage frontend et le contrôle serveur.

## 1. Rappel : les vues disponibles pour une instance

Une instance de device peut proposer plusieurs consoles, accessibles par la route
`/instances/{uuid}/view/{type}` :

| Vue | Type | Icône | Qui peut l'ouvrir |
| --- | --- | --- | --- |
| Console VNC | `vnc` | lien externe | tout utilisateur ayant accès à l'instance |
| Console login | `login` | terminal | tout utilisateur ayant accès à l'instance |
| Console série | `serial` | badge `admin` | tout utilisateur ayant accès à l'instance |
| **Console d'administration** | `admin` | icône incognito | **profils restreints, voir ci-dessous** |

La vue `admin` est techniquement une connexion `login` réalisée sur le port
d'administration (port + 1) du device : elle donne un accès élevé au système
embarqué. Elle n'est donc pas ouverte à tous ceux qui peuvent voir l'instance.

## 2. Qui a le droit d'ouvrir la console d'administration ?

La règle de référence est celle du serveur,
`InstanceController::viewInstanceAction()` (`src/Controller/InstanceController.php`),
branche `type === "admin"`. Trois conditions **suffisantes** (lien `OU`) :

1. **Administrateur de l'instance** : `ROLE_ADMINISTRATOR` ou
   `ROLE_SUPER_ADMINISTRATOR`.
2. **Auteur du lab** : l'utilisateur est l'auteur du lab dont dépend l'instance
   (`Lab::getAuthor()`).
3. **Administrateur d'un groupe rattaché au lab** : l'utilisateur est `owner` ou
   `admin` d'un groupe qui fait partie des groupes du lab
   (`Group::isElevatedUser()`, via `InstanceController::isGroupAdmin()`).

Dans tous les autres cas, le serveur **redirige vers la page d'accueil** :
la page de console n'est jamais servie.

### Tableau par profil

| Profil | Voit l'icône ? | Accès réel à `view/admin` | Peut ouvrir `view/login` |
| --- | --- | --- | --- |
| Administrateur de site (`ROLE_ADMINISTRATOR`, `ROLE_SUPER_ADMINISTRATOR`) | Oui | Oui | Oui |
| Auteur du lab | Oui | Oui | Oui |
| `owner` ou `admin` d'un groupe rattaché au lab | Oui | Oui | Oui |
| **Simple membre** (`role = user`) d'un groupe rattaché au lab | **Non** | **Non** (redirection) | Oui |
| Enseignant (`ROLE_TEACHER` / `ROLE_TEACHER_EDITOR`) **non auteur** et sans rôle dans un groupe du lab | **Non** | **Non** (redirection) | Oui |
| Étudiant (`ROLE_USER`) sans rôle dans un groupe du lab | Non | Non | Oui |
| Code d'invitation (utilisateur invité) | Non | Non | Oui (selon l'instance) |

À noter : le simple fait d'être membre du groupe **propriétaire de l'instance**
(`ownedBy = group`) donne accès aux vues « classiques » (`login`, `vnc`,
`serial`), mais **pas** à la console d'administration.

## 3. Ce que l'utilisateur voit dans l'interface

L'icône incognito (« Open admin console ») est rendue par le frontend :

- `assets/js/components/Instances/InstanceListItem.js` (page du lab et listes d'instances) ;
- `assets/js/components/Instances/OptimizedInstanceList.js` (détail d'une instance, page « All instances »).

Dans les deux cas, l'icône s'affiche si :

- l'instance est `started`,
- le device expose le protocole `login` et n'est pas un device physique/natif,
- l'utilisateur n'est pas en mode sandbox,
- **et** `canViewAdmin(user, lab)` renvoie `true`.

`canViewAdmin()` (`assets/js/components/Instances/viewAdminPermissions.js`)
reproduit exactement les trois conditions serveur de la section 2. Le serveur
reste l'autorité : ce contrôle sert uniquement à ne jamais proposer une icône
qui redirigerait l'utilisateur vers la page d'accueil.

## 4. Décalage corrigé (contexte)

### Symptôme

Avec l'utilisateur `bdupre@example.org` (rôle `ROLE_TEACHER_EDITOR`, membre
**simple** du groupe `groupe2`, lab « Untitled Lab » #979 dont l'auteur est
`root@localhost`) :

- l'icône `view/admin` **s'affichait** sur l'instance en cours ;
- le clic redirigeait vers la page d'accueil (le serveur refusait).

### Cause

Le frontend testait uniquement le **rôle global** de l'utilisateur
(`ROLE_ADMINISTRATOR`, `ROLE_SUPER_ADMINISTRATOR`, `ROLE_TEACHER`,
`ROLE_TEACHER_EDITOR`) et **jamais** le fait d'être auteur du lab ou
administrateur d'un groupe du lab. Un enseignant non auteur voyait donc une
icône qui ne lui menait à rien.

### Correctifs appliqués

| Fichier | Changement |
| --- | --- |
| `assets/js/components/Instances/viewAdminPermissions.js` | **Nouveau** : helper `canViewAdmin(user, lab)` / `isSiteAdministrator(user)`. |
| `assets/js/components/Instances/InstanceListItem.js` | Icône `view/admin` conditionnée par `canViewAdmin()` au lieu des rôles globaux. |
| `assets/js/components/Instances/OptimizedInstanceList.js` | Idem, avec le lab de l'instance sélectionnée. |
| `src/Entity/Lab.php` | Sérialisation de `Lab::$groups` dans `api_get_lab` et `api_get_lab_instance` (le frontend doit savoir quels groupes sont rattachés au lab). |
| `src/Entity/GroupUser.php` | Sérialisation du `role` d'appartenance dans `api_get_lab_instance` (indispensable sur la page « All instances », qui n'utilise que ce groupe de sérialisation). |

### Résultat attendu

- `bdupre@example.org` : icône `view/admin` **masquée**, `view/login` conservé ;
- propriétaire/administrateur du groupe `groupe2` : icône `view/admin` conservée
  (le serveur l'autorise) ;
- auteur du lab et administrateurs de site : inchangé.

## 5. Points d'attention pour la documentation utilisateur future

1. **Le serveur fait foi.** Le frontend ne fait qu'afficher ou masquer une
   icône ; toute URL `view/admin` saisie à la main est réévaluée côté serveur.
2. **Deux notions distinctes** à bien expliquer aux utilisateurs :
   - *voir / commander une instance* (démarrage, arrêt, consoles classiques) —
     dépend des rôles globaux, du propriétaire de l'instance et des groupes ;
   - *ouvrir la console d'administration* — restrictif : auteur du lab,
     administrateur de site, ou `owner`/`admin` d'un groupe du lab.
3. **Contrôle des boutons « showControls »** : dans
   `assets/js/components/Instances/InstanceList.js`, la prop `showControls`
   reçue du parent est écrasée par `!is_native(instance)`. Le contrôle
   « membre du groupe propriétaire » calculé par `InstanceManager` n'a donc
   aucun effet sur cette liste. À trancher avant de documenter qui peut
   démarrer/arrêter les devices partagés.
4. **Cas `sandbox`** : les blocs `view/admin` sont désactivés en sandbox
   (`!isSandbox`), inutile de documenter ce cas côté utilisateur.
5. **Codes d'invitation** : jamais d'accès à la console d'administration.

## 6. Comment vérifier

```bash
# 1. Icône affichée ? ouvrir la page du lab et inspecter le rendu React
#    (l'icône n'existe que si canViewAdmin(user, lab) === true)

# 2. Accès réel, avec une session ouverte sur l'instance :
curl -skI -b cookies.txt \
  https://<hote>/instances/<uuid-Instance>/view/admin
# attendu : 302 Location: / pour un non autorisé
# attendu : 200 pour un auteur / admin de site / owner-admin du groupe du lab

curl -sk -b cookies.txt -o /dev/null -w '%{http_code}\n' \
  https://<hote>/instances/<uuid-Instance>/view/login
# attendu : 200
```

Vérification des données exposées au frontend (après modification d'une
annotation `#[Serializer\Groups]`) :

```bash
curl -sk -b cookies.txt https://<hote>/labs/<idLab> | grep -o 'react-props-value' 
# puis contrôler la présence de lab.groups et de user.groups[].role dans le JSON
```
