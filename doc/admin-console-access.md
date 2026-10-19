# Console d'administration d'une instance (`view/admin`) — fonctionnement

> **Statut** : **implémenté**. Ce document décrit le comportement effectif du
> code : les règles de droits telles qu'appliquées côté serveur, et leur
> miroir côté frontend.

## 1. Les vues disponibles pour une instance

Une instance de device peut proposer plusieurs consoles, accessibles par la
route `/instances/{uuid}/view/{type}`
(`src/Controller/InstanceController.php:1201`, `viewInstanceAction`) :

| Vue | Type | Qui peut l'ouvrir |
| --- | --- | --- |
| Console VNC | `vnc` | tout utilisateur ayant accès à l'instance |
| Console login | `login` | tout utilisateur ayant accès à l'instance |
| Console série | `serial` | tout utilisateur ayant accès à l'instance |
| **Console d'administration** | `admin` | **profils restreints, voir §2** |

La route ne rend pas de page intermédiaire : après vérification des droits et
création de la règle de proxy correspondante, elle **redirige** (302) :

- `vnc` → `view/vnc?size=fullscreen`, qui rend la page noVNC fullscreen
  (`lab/vm_view_fullscreen.html.twig`) ;
- `login` / `serial` / `admin` → directement vers la console ttyd servie par le
  proxy (`https://<proxy>/device/<uuid>/`).

En cas d'échec (pas de protocole exposé par le device, erreur du proxy), elle
redirige vers la page précédente (le `referer`, s'il pointe vers le même hôte)
ou vers la page d'accueil, avec un message flash d'erreur.

La vue `admin` est techniquement une connexion `login` réalisée sur le port
d'administration (port + 1) du device : elle donne un accès élevé au système
embarqué. Elle n'est donc pas ouverte à tous ceux qui peuvent voir l'instance.

## 2. Qui a le droit d'ouvrir la console d'administration ?

La règle de référence est celle du serveur,
`InstanceController::viewInstanceAction()`
(`src/Controller/InstanceController.php:1243-1252`), branche
`$type === "admin"` (devices non physiques uniquement). Trois conditions
**suffisantes** (lien `OU`) :

1. **Administrateur de l'instance** : `ROLE_ADMINISTRATOR` ou
   `ROLE_SUPER_ADMINISTRATOR` (`$isAdmin`, `:1227`) ;
2. **Auteur du lab** : l'utilisateur est l'auteur du lab dont dépend l'instance
   (`$isAuthor`, `:1222`) ;
3. **Administrateur d'un groupe rattaché au lab** : l'utilisateur est `owner`
   ou `admin` d'un groupe qui fait partie des groupes du lab
   (`$isGroupAdmin`, `:1234`, via `isGroupAdmin()` → `Group::isElevatedUser()`).

Dans tous les autres cas, le serveur **redirige vers la page d'accueil**
(`:1247`) : la page de console n'est jamais servie.

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

À noter : le simple fait d'être membre du groupe **propriétaire de
l'instance** (`ownedBy = group`) donne accès aux vues « classiques »
(`login`, `vnc`, `serial`), mais **pas** à la console d'administration.

## 3. Ce que l'utilisateur voit dans l'interface

L'icône incognito (« Open admin console ») est rendue par le frontend :

- `assets/js/components/Instances/InstanceListItem.js` (page du lab et listes
  d'instances) ;
- `assets/js/components/Instances/OptimizedInstanceList.js` (détail d'une
  instance, page « All instances »).

Dans les deux cas, l'icône s'affiche si :

- l'instance est `started`,
- le device expose le protocole `login` et n'est pas un device physique/natif,
- l'utilisateur n'est pas en mode sandbox,
- **et** `canViewAdmin(user, lab)` renvoie `true`.

`canViewAdmin()`
(`assets/js/components/Instances/viewAdminPermissions.js`) reproduit
exactement les trois conditions serveur du §2 :

- `isSiteAdministrator(user)` : rôles `ROLE_ADMINISTRATOR` /
  `ROLE_SUPER_ADMINISTRATOR` ;
- auteur du lab (comparaison `id` ou `uuid`) ;
- `owner`/`admin` d'un groupe rattaché au lab (croisement
  `user.groups[].role` × `lab.groups`).

Les codes d'invitation (`user.code`) sont exclus d'office. Le serveur reste
l'autorité : ce contrôle sert uniquement à ne jamais proposer une icône qui
redirigerait l'utilisateur vers la page d'accueil.

## 4. Points d'attention

1. **Le serveur fait foi.** Le frontend ne fait qu'afficher ou masquer une
   icône ; toute URL `view/admin` saisie à la main est réévaluée côté serveur.
2. **Deux notions distinctes** :
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

## 5. Comment vérifier

```bash
# 1. Icône affichée ? ouvrir la page du lab et inspecter le rendu React
#    (l'icône n'existe que si canViewAdmin(user, lab) === true)

# 2. Accès réel, avec une session ouverte sur l'instance :
curl -skI -b cookies.txt \
  https://<hote>/instances/<uuid-Instance>/view/admin
# attendu : 302 Location: / pour un non autorisé
# attendu : 302 Location: https://<proxy>/device/<uuid-Instance>/ pour un
#          auteur / admin de site / owner-admin du groupe du lab

curl -skI -b cookies.txt \
  https://<hote>/instances/<uuid-Instance>/view/login
# attendu : 302 Location: https://<proxy>/device/<uuid-Instance>/

curl -skI -b cookies.txt \
  https://<hote>/instances/<uuid-Instance>/view/vnc
# attendu : 302 Location: /instances/<uuid-Instance>/view/vnc?size=fullscreen
```

Vérification des données exposées au frontend (après modification d'une
annotation `#[Serializer\Groups]`) :

```bash
curl -sk -b cookies.txt https://<hote>/labs/<idLab> | grep -o 'react-props-value'
# puis contrôler la présence de lab.groups et de user.groups[].role dans le JSON
```
