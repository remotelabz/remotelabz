<?php

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * BONNES PRATIQUES - SYSTÈME DE RÉPERTOIRES HIÉRARCHIQUE
 * ═══════════════════════════════════════════════════════════════════════════
 * 
 * Ce document détaille les bonnes pratiques pour utiliser efficacement
 * le système de répertoires dans votre application Symfony/Doctrine.
 */

// ═══════════════════════════════════════════════════════════════════════════
// 1. INDEXATION SQL ET PERFORMANCES
// ═══════════════════════════════════════════════════════════════════════════

/**
 * INDEX CRÉÉS AUTOMATIQUEMENT
 * 
 * Les index suivants sont automatiquement créés par la migration:
 * 
 * 1. idx_directory_parent (parent_id)
 *    - Utilisé pour: trouver les enfants d'un répertoire
 *    - Critique pour les opérations de navigation
 * 
 * 2. idx_directory_path (path)
 *    - Utilisé pour: recherche par chemin, récupération de sous-arborescences
 *    - Permet des recherches efficaces avec LIKE '/path/%'
 * 
 * 3. idx_directory_deleted (deleted_at)
 *    - Utilisé pour: filtrer les répertoires supprimés (soft delete)
 *    - Important si vous utilisez le soft delete
 * 
 * 4. idx_directory_level (level)
 *    - Utilisé pour: recherche par profondeur
 *    - Utile pour limiter la profondeur de navigation
 * 
 * 5. IDX_XXX_directory_id sur device, iso, operating_system
 *    - Utilisé pour: jointures et filtrage par répertoire
 */

/**
 * INDEX COMPOSITES RECOMMANDÉS (à ajouter si besoin)
 */

// Si vous filtrez souvent par parent ET par nom:
// CREATE INDEX idx_directory_parent_name ON directory (parent_id, name);

// Si vous utilisez intensivement le soft delete avec recherches:
// CREATE INDEX idx_directory_deleted_path ON directory (deleted_at, path);

// Si vous recherchez souvent des répertoires d'un niveau spécifique non supprimés:
// CREATE INDEX idx_directory_level_deleted ON directory (level, deleted_at);


// ═══════════════════════════════════════════════════════════════════════════
// 2. ÉVITER LE PROBLÈME N+1
// ═══════════════════════════════════════════════════════════════════════════

/**
 * MAUVAISE PRATIQUE (N+1 queries)
 */
function badExample_displayDirectoryContents($directoryId, $directoryRepo) {
    $directory = $directoryRepo->find($directoryId);
    
    // Cette approche génère:
    // 1 requête pour le répertoire
    // N requêtes pour charger les devices (lazy loading)
    // N requêtes pour charger les isos
    // N requêtes pour charger les operating systems
    // N requêtes pour charger les children
    
    foreach ($directory->getDevices() as $device) {
        echo $device->getName(); // LAZY LOAD - requête SQL !
    }
    
    foreach ($directory->getIsos() as $iso) {
        echo $iso->getName(); // LAZY LOAD - requête SQL !
    }
    
    // ... etc
}

/**
 * BONNE PRATIQUE (utilisation de findWithContents)
 */
function goodExample_displayDirectoryContents($directoryId, $directoryRepo) {
    // Cette méthode fait un seul SELECT avec tous les JOINs nécessaires
    $directory = $directoryRepo->findWithContents($directoryId);
    
    // Maintenant tout est chargé en mémoire, pas de lazy loading
    foreach ($directory->getDevices() as $device) {
        echo $device->getName(); // PAS de requête SQL supplémentaire
    }
    
    foreach ($directory->getIsos() as $iso) {
        echo $iso->getName(); // PAS de requête SQL supplémentaire
    }
}

/**
 * EAGER LOADING PERSONNALISÉ
 */
function customEagerLoading($directoryId, $entityManager) {
    $qb = $entityManager->createQueryBuilder();
    
    $directory = $qb->select('d', 'dev', 'os') // Sélectionner ce dont vous avez besoin
        ->from(Directory::class, 'd')
        ->leftJoin('d.devices', 'dev')
        ->leftJoin('d.operatingSystems', 'os')
        // Pas besoin de charger les ISOs si vous ne les utilisez pas
        ->where('d.id = :id')
        ->setParameter('id', $directoryId)
        ->getQuery()
        ->getOneOrNullResult();
    
    return $directory;
}


// ═══════════════════════════════════════════════════════════════════════════
// 3. GESTION DE LA SUPPRESSION
// ═══════════════════════════════════════════════════════════════════════════

/**
 * STRATÉGIE 1: SOFT DELETE (recommandé)
 * 
 * Avantages:
 * - Possibilité de restaurer
 * - Traçabilité
 * - Pas de perte de données
 * 
 * Inconvénients:
 * - Nécessite de toujours filtrer sur deleted_at IS NULL
 * - Les données s'accumulent dans la base
 */

function softDeleteDirectory($directory, $entityManager) {
    // Marque le répertoire comme supprimé
    $directory->delete();
    
    // Options pour les éléments contenus:
    
    // Option A: Soft delete en cascade (recommandé)
    foreach ($directory->getChildren() as $child) {
        $child->delete();
        // Récursif si nécessaire
    }
    
    // Option B: Déplacer les éléments vers la racine
    foreach ($directory->getDevices() as $device) {
        $device->setDirectory(null); // Vers root
    }
    
    // Option C: Empêcher la suppression si non vide
    if (!$directory->isEmpty()) {
        throw new \RuntimeException('Cannot delete non-empty directory');
    }
    
    $entityManager->flush();
}

/**
 * STRATÉGIE 2: HARD DELETE avec ON DELETE CASCADE
 * 
 * Configuration dans la migration:
 * - FOREIGN KEY (parent_id) ... ON DELETE CASCADE
 * 
 * Comportement:
 * - Suppression d'un répertoire supprime tous ses enfants
 * - Les entités (device, iso, os) ont ON DELETE SET NULL
 *   donc elles ne sont PAS supprimées, juste orphelines
 */

function hardDeleteDirectory($directory, $entityManager) {
    // Si ON DELETE CASCADE est configuré, ceci supprime aussi les enfants
    $entityManager->remove($directory);
    
    // Les devices, isos, os auront leur directory_id à NULL
    // grâce à ON DELETE SET NULL
    
    $entityManager->flush();
}

/**
 * STRATÉGIE 3: HYBRID - Validation avant suppression
 */

function safeDeleteDirectory($directory, $entityManager, $force = false) {
    // Vérifier si le répertoire contient des éléments
    $stats = [
        'devices' => $directory->getDevices()->count(),
        'isos' => $directory->getIsos()->count(),
        'os' => $directory->getOperatingSystems()->count(),
        'children' => $directory->getChildren()->count()
    ];
    
    $totalItems = array_sum($stats);
    
    if ($totalItems > 0 && !$force) {
        throw new \RuntimeException(
            "Directory contains $totalItems items. Use force=true to delete anyway."
        );
    }
    
    // Option: déplacer les items avant de supprimer
    if ($totalItems > 0 && $force) {
        $rootOrParent = $directory->getParent(); // Vers parent ou null (root)
        
        foreach ($directory->getDevices() as $device) {
            $device->setDirectory($rootOrParent);
        }
        
        foreach ($directory->getIsos() as $iso) {
            $iso->setDirectory($rootOrParent);
        }
        
        foreach ($directory->getOperatingSystems() as $os) {
            $os->setDirectory($rootOrParent);
        }
        
        // Déplacer les enfants
        foreach ($directory->getChildren() as $child) {
            $child->setParent($rootOrParent);
        }
    }
    
    // Maintenant on peut supprimer en toute sécurité
    $entityManager->remove($directory);
    $entityManager->flush();
}


// ═══════════════════════════════════════════════════════════════════════════
// 4. VALIDATION ET INTÉGRITÉ DES DONNÉES
// ═══════════════════════════════════════════════════════════════════════════

/**
 * VALIDATION 1: Empêcher les références circulaires
 */

function validateNoCircularReference($directory, $newParent) {
    if ($newParent === null) {
        return true; // Déplacement vers root est toujours OK
    }
    
    // Vérifier qu'on ne crée pas une boucle
    $current = $newParent;
    $visited = [];
    
    while ($current !== null) {
        // Si on rencontre le répertoire qu'on veut déplacer, c'est une boucle
        if ($current->getId() === $directory->getId()) {
            throw new \RuntimeException('Circular reference detected');
        }
        
        // Protection contre les boucles infinies
        if (in_array($current->getId(), $visited)) {
            throw new \RuntimeException('Circular reference in existing structure');
        }
        
        $visited[] = $current->getId();
        $current = $current->getParent();
    }
    
    return true;
}

/**
 * VALIDATION 2: Limiter la profondeur maximale
 */

function validateMaxDepth($directory, $maxDepth = 10) {
    $level = $directory->getLevel();
    
    if ($level > $maxDepth) {
        throw new \RuntimeException("Maximum directory depth ($maxDepth) exceeded");
    }
    
    return true;
}

/**
 * VALIDATION 3: Unicité du nom dans un répertoire parent
 */

function validateUniqueNameInParent($directory, $directoryRepo) {
    $existing = $directoryRepo->findByNameAndParent(
        $directory->getName(),
        $directory->getParent()
    );
    
    if ($existing && $existing->getId() !== $directory->getId()) {
        throw new \RuntimeException(
            'A directory with this name already exists in the parent directory'
        );
    }
    
    return true;
}

/**
 * VALIDATION 4: Caractères interdits dans le nom
 */

function validateDirectoryName($name) {
    // Interdire les caractères problématiques
    $forbidden = ['/', '\\', ':', '*', '?', '"', '<', '>', '|'];
    
    foreach ($forbidden as $char) {
        if (strpos($name, $char) !== false) {
            throw new \RuntimeException("Directory name cannot contain '$char'");
        }
    }
    
    // Interdire les noms réservés
    $reserved = ['.', '..', 'CON', 'PRN', 'AUX', 'NUL'];
    if (in_array(strtoupper($name), $reserved)) {
        throw new \RuntimeException("'$name' is a reserved name");
    }
    
    return true;
}


// ═══════════════════════════════════════════════════════════════════════════
// 5. OPTIMISATION DES REQUÊTES
// ═══════════════════════════════════════════════════════════════════════════

/**
 * OPTIMISATION 1: Utiliser le cache de résultats
 */

use Doctrine\ORM\Query;

function getCachedDirectoryTree($directoryRepo) {
    $query = $directoryRepo->createQueryBuilder('d')
        ->where('d.parent IS NULL')
        ->andWhere('d.deletedAt IS NULL')
        ->getQuery();
    
    // Cache les résultats pendant 1 heure
    $query->useResultCache(true, 3600, 'directory_tree_roots');
    
    return $query->getResult();
}

/**
 * OPTIMISATION 2: Pagination pour les grands répertoires
 */

use Doctrine\ORM\Tools\Pagination\Paginator;

function getPaginatedDirectoryContents($directory, $page = 1, $pageSize = 50) {
    $qb = $directory->getDevices()->createQueryBuilder();
    
    $query = $qb->setFirstResult(($page - 1) * $pageSize)
        ->setMaxResults($pageSize)
        ->getQuery();
    
    $paginator = new Paginator($query);
    
    return [
        'items' => iterator_to_array($paginator),
        'total' => count($paginator),
        'page' => $page,
        'pageSize' => $pageSize,
        'totalPages' => ceil(count($paginator) / $pageSize)
    ];
}

/**
 * OPTIMISATION 3: Sélection partielle (ne charger que ce dont on a besoin)
 */

function getDirectoryListOptimized($directoryRepo) {
    // Ne charger que l'ID, le nom et le path (pas toutes les relations)
    return $directoryRepo->createQueryBuilder('d')
        ->select('d.id', 'd.name', 'd.path', 'd.level')
        ->where('d.deletedAt IS NULL')
        ->getQuery()
        ->getArrayResult(); // Retourne des tableaux, pas des objets
}

/**
 * OPTIMISATION 4: Comptage efficace sans charger les entités
 */

function countDirectoryItems($directoryId, $entityManager) {
    $sql = "
        SELECT 
            (SELECT COUNT(*) FROM device WHERE directory_id = :id) as devices,
            (SELECT COUNT(*) FROM iso WHERE directory_id = :id) as isos,
            (SELECT COUNT(*) FROM operating_system WHERE directory_id = :id) as os,
            (SELECT COUNT(*) FROM directory WHERE parent_id = :id) as children
    ";
    
    $stmt = $entityManager->getConnection()->prepare($sql);
    $result = $stmt->executeQuery(['id' => $directoryId]);
    
    return $result->fetchAssociative();
}


// ═══════════════════════════════════════════════════════════════════════════
// 6. TRANSACTION ET COHÉRENCE
// ═══════════════════════════════════════════════════════════════════════════

/**
 * BONNE PRATIQUE: Utiliser des transactions pour les opérations complexes
 */

function moveDirectoryWithTransaction($directory, $newParent, $entityManager) {
    $entityManager->getConnection()->beginTransaction();
    
    try {
        // Valider l'opération
        validateNoCircularReference($directory, $newParent);
        
        // Effectuer le déplacement
        $directory->setParent($newParent);
        
        // Les chemins et niveaux des enfants doivent être recalculés
        // (géré automatiquement par les lifecycle callbacks)
        $entityManager->flush();
        
        // Tout s'est bien passé
        $entityManager->getConnection()->commit();
        
    } catch (\Exception $e) {
        // Annuler toutes les modifications
        $entityManager->getConnection()->rollBack();
        throw $e;
    }
}


// ═══════════════════════════════════════════════════════════════════════════
// 7. SÉCURITÉ ET PERMISSIONS
// ═══════════════════════════════════════════════════════════════════════════

/**
 * CONSIDÉRATIONS DE SÉCURITÉ
 */

/**
 * 1. Contrôle d'accès basé sur les répertoires
 */

// Vous pouvez ajouter un système de permissions par répertoire:
// - Ajouter une relation ManyToMany entre Directory et User/Role
// - Vérifier les permissions avant toute opération

function checkDirectoryPermission($directory, $user, $permission = 'read') {
    // Exemple simplifié - à adapter selon votre système d'autorisation
    
    // Vérifier la permission sur le répertoire
    if (!$directory->hasPermission($user, $permission)) {
        throw new \RuntimeException('Access denied');
    }
    
    // Éventuellement, hériter des permissions du parent
    if ($directory->getParent()) {
        return checkDirectoryPermission($directory->getParent(), $user, $permission);
    }
    
    return true;
}

/**
 * 2. Validation des entrées utilisateur
 */

function sanitizeDirectoryInput($data) {
    return [
        'name' => trim(strip_tags($data['name'] ?? '')),
        'description' => trim(strip_tags($data['description'] ?? '')),
        'parent_id' => filter_var($data['parent_id'] ?? null, FILTER_VALIDATE_INT)
    ];
}


// ═══════════════════════════════════════════════════════════════════════════
// 8. MONITORING ET LOGS
// ═══════════════════════════════════════════════════════════════════════════

/**
 * BONNE PRATIQUE: Logger les opérations importantes
 */

use Psr\Log\LoggerInterface;

function logDirectoryOperation($operation, $directory, $logger) {
    $logger->info("Directory $operation", [
        'directory_id' => $directory->getId(),
        'directory_name' => $directory->getName(),
        'directory_path' => $directory->getPath(),
        'operation' => $operation,
        'timestamp' => new \DateTime()
    ]);
}


// ═══════════════════════════════════════════════════════════════════════════
// 9. TESTS RECOMMANDÉS
// ═══════════════════════════════════════════════════════════════════════════

/**
 * TESTS À IMPLÉMENTER
 */

/**
 * 1. Test de création de répertoire
 * 2. Test de déplacement sans référence circulaire
 * 3. Test de détection de référence circulaire
 * 4. Test de calcul automatique du path
 * 5. Test de suppression en cascade
 * 6. Test de soft delete
 * 7. Test de performance sur grande arborescence (>1000 répertoires)
 * 8. Test de concurrence (deux utilisateurs déplacent le même répertoire)
 */


// ═══════════════════════════════════════════════════════════════════════════
// 10. RÉSUMÉ DES BONNES PRATIQUES
// ═══════════════════════════════════════════════════════════════════════════

/**
 * ✅ À FAIRE:
 * 
 * 1. Utiliser findWithContents() pour éviter N+1
 * 2. Valider les références circulaires avant déplacement
 * 3. Limiter la profondeur maximale de l'arborescence
 * 4. Utiliser des transactions pour les opérations complexes
 * 5. Implémenter le soft delete pour la traçabilité
 * 6. Cacher les résultats des requêtes fréquentes
 * 7. Paginer les contenus de grands répertoires
 * 8. Logger les opérations importantes
 * 9. Valider les noms de répertoires
 * 10. Tester les cas limites
 * 
 * ❌ À ÉVITER:
 * 
 * 1. Ne pas valider les références circulaires
 * 2. Charger toute l'arborescence sans pagination
 * 3. Utiliser des boucles imbriquées avec lazy loading
 * 4. Supprimer sans vérifier le contenu
 * 5. Permettre une profondeur illimitée
 * 6. Ne pas gérer les erreurs de concurrence
 * 7. Oublier les index SQL
 * 8. Ne pas utiliser de transactions
 * 9. Ignorer le soft delete
 * 10. Ne pas tester les performances
 */