# 📁 Système de Répertoires Hiérarchique pour Symfony/Doctrine

## 📋 Vue d'ensemble

Ce système propose une solution complète, générique et évolutive pour organiser vos entités (Device, Iso, OperatingSystem) dans une structure de répertoires hiérarchique, similaire à un système de fichiers.

## ✨ Fonctionnalités principales

- ✅ **Structure arborescente illimitée** : profondeur illimitée avec relations parent/enfant
- ✅ **Interface générique** : extensible à d'autres entités facilement
- ✅ **Trait réutilisable** : évite la duplication de code
- ✅ **Calcul automatique** : chemins et niveaux calculés automatiquement
- ✅ **Soft delete** : possibilité de restaurer les répertoires supprimés
- ✅ **Protection contre les boucles** : validation des références circulaires
- ✅ **Performances optimisées** : index SQL, eager loading, méthodes optimisées
- ✅ **Repository riche** : nombreuses méthodes de requêtage

## 📦 Fichiers fournis

### 1. Entités et traits

| Fichier | Description |
|---------|-------------|
| `Directory.php` | Entité principale représentant un répertoire |
| `DirectoryAwareInterface.php` | Interface pour les entités organisables |
| `DirectoryAwareTrait.php` | Trait réutilisable pour la relation avec Directory |

### 2. Repository

| Fichier | Description |
|---------|-------------|
| `DirectoryRepository.php` | Repository avec 20+ méthodes de requêtage |

### 3. Documentation

| Fichier | Description |
|---------|-------------|
| `ENTITY_MODIFICATIONS_GUIDE.php` | Guide pour modifier vos entités existantes |
| `CONTROLLER_METHODS_EXAMPLES.php` | Exemples de méthodes pour vos contrôleurs |
| `BEST_PRACTICES.php` | Bonnes pratiques (performances, sécurité, etc.) |
| `QUERY_EXAMPLES.php` | 25+ exemples concrets d'utilisation |
| `Version20260101000000.php` | Migration Doctrine |
| `README.md` | Ce fichier |

## 🚀 Installation rapide

### Étape 1 : Copier les fichiers

```bash
# Copier les entités
cp Directory.php src/Entity/
cp DirectoryAwareInterface.php src/Entity/Interface/
cp DirectoryAwareTrait.php src/Entity/Trait/

# Copier le repository
cp DirectoryRepository.php src/Repository/

# Copier la migration
cp Version20260101000000.php migrations/
```

### Étape 2 : Modifier vos entités existantes

Pour chaque entité (Device, Iso, OperatingSystem), ajouter :

```php
use App\Entity\Interface\DirectoryAwareInterface;
use App\Entity\Trait\DirectoryAwareTrait;

class Device implements InstanciableInterface, DirectoryAwareInterface
{
    use DirectoryAwareTrait;
    
    // ... reste du code existant
}
```

Consultez `ENTITY_MODIFICATIONS_GUIDE.php` pour les détails complets.

### Étape 3 : Exécuter la migration

```bash
php bin/console doctrine:migrations:migrate
```

### Étape 4 : Ajouter les routes dans vos contrôleurs

Consultez `CONTROLLER_METHODS_EXAMPLES.php` et copiez les méthodes dont vous avez besoin.

## 💡 Utilisation de base

### Créer un répertoire

```php
$directory = new Directory();
$directory->setName('Projects');
$directory->setDescription('All projects');

$entityManager->persist($directory);
$entityManager->flush();
```

### Créer une hiérarchie

```php
$parent = new Directory();
$parent->setName('Projects');

$child = new Directory();
$child->setName('Web');
$child->setParent($parent);

$entityManager->persist($parent);
$entityManager->persist($child);
$entityManager->flush();

// Le path sera automatiquement calculé: /Projects/Web
echo $child->getPath(); // "/Projects/Web"
echo $child->getLevel(); // 1
```

### Ajouter un Device à un répertoire

```php
$device = $deviceRepository->find(1);
$directory = $directoryRepository->find(5);

$device->setDirectory($directory);
$entityManager->flush();

echo $device->getFullPath(); // "/Projects/Web/Production/web-server-01"
```

### Récupérer le contenu d'un répertoire

```php
// Méthode optimisée (évite N+1)
$directory = $directoryRepository->findWithContents($id);

// Accéder au contenu
$devices = $directory->getDevices();
$isos = $directory->getIsos();
$operatingSystems = $directory->getOperatingSystems();
$children = $directory->getChildren();

// Statistiques
$totalItems = $directory->getTotalItemsCount();
```

### Naviguer dans l'arborescence

```php
$device = $deviceRepository->find(1);

// Obtenir le chemin complet
$fullPath = $device->getFullPath(); // "/Projects/Web/Production/web-server-01"

// Obtenir le breadcrumb
$breadcrumb = $device->getBreadcrumb(); // [Directory, Directory, Directory, Device]

// Vérifier si à la racine
if ($device->isInRoot()) {
    echo "Device is at root level";
}
```

## 🔍 Exemples de requêtes courantes

### Rechercher des répertoires

```php
// Par nom
$results = $directoryRepository->searchByName('prod');

// Par chemin exact
$dir = $directoryRepository->findByPath('/Projects/Web/Production');

// Par pattern
$dirs = $directoryRepository->findByPathPattern('/Projects/%/Production');

// Par niveau de profondeur
$level2 = $directoryRepository->findByLevel(2);
```

### Obtenir les racines et enfants

```php
// Tous les répertoires racine
$roots = $directoryRepository->findRoots();

// Enfants directs d'un répertoire
$children = $directoryRepository->findChildren($directory);

// Tous les descendants (récursif)
$descendants = $directoryRepository->findDescendants($directory);
```

### Statistiques

```php
// Statistiques globales
$stats = $directoryRepository->getStatistics();
// ['total' => 156, 'roots' => 3, 'maxDepth' => 5, 'avgDepth' => 2.34]

// Compter les items par type
$counts = $directoryRepository->countItemsByType($directory);
// ['devices' => 12, 'isos' => 5, 'operatingSystems' => 8]

// Trouver les répertoires vides
$emptyDirs = $directoryRepository->findEmpty();
```

## ⚡ Optimisations et performances

### 1. Index SQL créés automatiquement

- `idx_directory_parent` : pour naviguer dans l'arborescence
- `idx_directory_path` : pour rechercher par chemin
- `idx_directory_deleted` : pour le soft delete
- `idx_directory_level` : pour filtrer par profondeur

### 2. Éviter le problème N+1

```php
// ❌ MAUVAIS (N+1 queries)
$directory = $directoryRepository->find($id);
foreach ($directory->getDevices() as $device) {
    echo $device->getName(); // Lazy loading = requête SQL
}

// ✅ BON (1 seule query)
$directory = $directoryRepository->findWithContents($id);
foreach ($directory->getDevices() as $device) {
    echo $device->getName(); // Déjà chargé en mémoire
}
```

### 3. Utiliser le cache

```php
$query = $directoryRepository->createQueryBuilder('d')
    ->where('d.parent IS NULL')
    ->getQuery();

$query->useResultCache(true, 3600, 'directory_roots');
$roots = $query->getResult();
```

## 🛡️ Sécurité et validation

### Empêcher les références circulaires

```php
function moveDirectory($directory, $newParent) {
    // Vérifier qu'on ne crée pas de boucle
    $current = $newParent;
    while ($current !== null) {
        if ($current->getId() === $directory->getId()) {
            throw new \RuntimeException('Circular reference detected');
        }
        $current = $current->getParent();
    }
    
    $directory->setParent($newParent);
    $entityManager->flush();
}
```

### Valider le nom du répertoire

```php
// Caractères interdits
$forbidden = ['/', '\\', ':', '*', '?', '"', '<', '>', '|'];

// Noms réservés
$reserved = ['.', '..', 'CON', 'PRN', 'AUX', 'NUL'];
```

## 🗑️ Gestion de la suppression

### Soft delete (recommandé)

```php
// Marquer comme supprimé
$directory->delete();
$entityManager->flush();

// Restaurer
$directory->restore();
$entityManager->flush();

// Vérifier
if ($directory->isDeleted()) {
    echo "Directory is deleted";
}
```

### Hard delete avec validation

```php
// Vérifier si vide
if (!$directory->isEmpty()) {
    throw new \RuntimeException('Cannot delete non-empty directory');
}

// Supprimer
$entityManager->remove($directory);
$entityManager->flush();
```

## 📊 Structure de la base de données

### Table `directory`

| Colonne | Type | Description |
|---------|------|-------------|
| id | INT | Clé primaire |
| parent_id | INT | Référence au répertoire parent (nullable) |
| name | VARCHAR(255) | Nom du répertoire |
| path | VARCHAR(1000) | Chemin complet (calculé automatiquement) |
| description | TEXT | Description (nullable) |
| level | INT | Niveau de profondeur (0 = racine) |
| created_at | DATETIME | Date de création |
| updated_at | DATETIME | Date de dernière modification |
| deleted_at | DATETIME | Date de suppression (soft delete, nullable) |

### Relations

```
directory
├── parent_id → directory.id (CASCADE)
├── device.directory_id → directory.id (SET NULL)
├── iso.directory_id → directory.id (SET NULL)
└── operating_system.directory_id → directory.id (SET NULL)
```

## 🎯 Cas d'usage typiques

### 1. Organisation par projet

```
/Projects
  /2024
    /Web
      /Frontend
      /Backend
    /Mobile
      /iOS
      /Android
  /2023
    /Legacy
```

### 2. Organisation par environnement

```
/Production
  /Web-Servers
  /Database-Servers
  /Cache-Servers
/Development
  /Test-VMs
  /Sandbox
/Archives
  /2023
  /2022
```

### 3. Organisation par type

```
/VMs
  /Linux
    /Ubuntu
    /Debian
    /CentOS
  /Windows
    /Server-2019
    /Server-2022
/Containers
  /Docker
  /Kubernetes
/Physical
  /Servers
  /Network-Equipment
```

## 📚 Documentation complète

- **ENTITY_MODIFICATIONS_GUIDE.php** : Comment modifier vos entités
- **CONTROLLER_METHODS_EXAMPLES.php** : 20+ exemples de méthodes pour contrôleurs
- **BEST_PRACTICES.php** : Bonnes pratiques détaillées
- **QUERY_EXAMPLES.php** : 25+ exemples d'utilisation concrets

## 🔧 Méthodes du Repository

### Navigation
- `findRoots()` : Répertoires racine
- `findChildren($parent)` : Enfants directs
- `findDescendants($directory)` : Tous les descendants

### Recherche
- `findByPath($path)` : Par chemin exact
- `findByPathPattern($pattern)` : Par pattern
- `searchByName($name)` : Par nom
- `findByLevel($level)` : Par niveau de profondeur

### Optimisations
- `findWithContents($id)` : Avec eager loading
- `findEmpty()` : Répertoires vides

### Statistiques
- `getStatistics()` : Statistiques globales
- `countItemsByType($directory)` : Compte par type d'item

### Utilitaires
- `getTreeStructure($parent, $maxDepth)` : Arbre hiérarchique
- `findByNameAndParent($name, $parent)` : Recherche ciblée
- `isPathUnique($path, $excludeId)` : Validation d'unicité

## ⚠️ Points d'attention

1. **Références circulaires** : Toujours valider avant de déplacer un répertoire
2. **Profondeur** : Limiter la profondeur si nécessaire (recommandé : max 10)
3. **Performance** : Utiliser `findWithContents()` pour éviter N+1
4. **Suppression** : Valider le contenu avant suppression
5. **Noms** : Valider les caractères interdits

## 🎓 Bonnes pratiques

✅ **À FAIRE**
- Utiliser `findWithContents()` pour éviter N+1
- Valider les références circulaires
- Limiter la profondeur maximale
- Utiliser des transactions pour opérations complexes
- Implémenter le soft delete
- Cacher les résultats fréquents
- Logger les opérations importantes

❌ **À ÉVITER**
- Ne pas valider les références circulaires
- Charger toute l'arborescence sans pagination
- Boucles avec lazy loading
- Supprimer sans vérifier le contenu
- Permettre une profondeur illimitée
- Ignorer les index SQL

## 🧪 Tests recommandés

1. Création de répertoire
2. Déplacement sans référence circulaire
3. Détection de référence circulaire
4. Calcul automatique du path
5. Suppression en cascade
6. Soft delete et restore
7. Performance sur grande arborescence
8. Gestion de la concurrence

## 📞 Support

Pour toute question ou problème :
1. Consultez `BEST_PRACTICES.php` pour les recommandations
2. Consultez `QUERY_EXAMPLES.php` pour des exemples concrets
3. Vérifiez les index SQL créés par la migration

## 📝 License

Adaptez cette solution à vos besoins spécifiques. Le code est fourni tel quel.

---

**Auteur** : Solution développée pour une application Symfony avec PHP 8.2+ et Doctrine ORM
**Version** : 1.0
**Date** : Janvier 2026