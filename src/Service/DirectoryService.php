<?php

namespace App\Service;

use App\Entity\Directory;
use App\Repository\DirectoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class DirectoryService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function getRepository(): DirectoryRepository
    {
        return $this->entityManager->getRepository(Directory::class);
    }

    /**
     * Build the select options for a directory dropdown:
     * 'all' => "All directories", 'root' => unclassified, then each directory
     * indented by its tree level (non-breaking spaces so HTML keeps the indent).
     */
    public function getSelectOptions(?string $selected = null): array
    {
        $options = [
            'all' => 'All directories',
            'root' => '(Root - no directory)',
        ];

        foreach ($this->getRepository()->findActiveOrdered() as $directory) {
            $options[(string) $directory->getId()] = self::indent($directory);
        }

        return $options;
    }

    public static function indent(Directory $directory): string
    {
        return str_repeat("\u{00A0}\u{00A0}\u{00A0}\u{00A0}", $directory->getLevel())
            . ($directory->getLevel() > 0 ? '↳ ' : '')
            . $directory->getName();
    }

    /**
     * Resolve a "directory" query/form parameter into a scope.
     *
     * @return array{scope: string, directories: Directory[]}
     *   scope: 'all' | 'root' | 'dir'
     *   directories: the directory plus all its descendants (when scope is 'dir')
     */
    public function resolveScope(?string $param): array
    {
        if ($param === null || $param === '' || $param === 'all') {
            return ['scope' => 'all', 'directories' => []];
        }

        if ($param === 'root') {
            return ['scope' => 'root', 'directories' => []];
        }

        $directory = $this->getRepository()->find((int) $param);

        if (!$directory || $directory->isDeleted()) {
            return ['scope' => 'all', 'directories' => []];
        }

        $directories = array_merge([$directory], $this->getRepository()->findDescendants($directory));

        return ['scope' => 'dir', 'directories' => $directories];
    }

    /**
     * Resolve a target directory from a form value ('' or 'root' => null).
     */
    public function resolveTarget(?string $param): ?Directory
    {
        if ($param === null || $param === '' || $param === 'root') {
            return null;
        }

        $directory = $this->getRepository()->find((int) $param);

        if (!$directory || $directory->isDeleted()) {
            return null;
        }

        return $directory;
    }

    /**
     * Parse a comma-separated list of ids.
     *
     * @return int[]
     */
    public function parseIds(?string $ids): array
    {
        if (!$ids) {
            return [];
        }

        $values = array_filter(array_map('trim', explode(',', $ids)), fn ($v) => $v !== '');

        return array_map('intval', $values);
    }

    /**
     * Validate and return the "return_to" redirect target.
     * Only absolute local paths are accepted.
     */
    public function getReturnTo(Request $request, string $fallbackUrl): string
    {
        $returnTo = $request->request->get('return_to') ?? $request->query->get('return_to');

        if (!is_string($returnTo) || $returnTo === '') {
            return $fallbackUrl;
        }

        // Accept same-origin absolute URLs (strip scheme+host)
        $base = $request->getSchemeAndHttpHost();
        if (str_starts_with($returnTo, $base)) {
            $returnTo = substr($returnTo, strlen($base));
        }

        if (
            str_starts_with($returnTo, '/')
            && !str_starts_with($returnTo, '//')
        ) {
            return $returnTo;
        }

        return $fallbackUrl;
    }

    public function create(string $name, ?int $parentId = null, ?string $description = null): Directory
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Directory name cannot be empty');
        }

        $parent = null;
        if ($parentId) {
            $parent = $this->getRepository()->find($parentId);
            if (!$parent || $parent->isDeleted()) {
                throw new \InvalidArgumentException('Parent directory not found');
            }
        }

        if ($this->getRepository()->findByNameAndParent($name, $parent)) {
            throw new \InvalidArgumentException(
                sprintf('A directory named "%s" already exists in this parent directory', $name)
            );
        }

        $directory = new Directory();
        $directory->setName($name);
        $directory->setDescription($description);
        $directory->setParent($parent);

        $this->entityManager->persist($directory);
        $this->entityManager->flush();

        return $directory;
    }

    public function rename(Directory $directory, string $newName): void
    {
        $newName = trim($newName);
        if ($newName === '') {
            throw new \InvalidArgumentException('Directory name cannot be empty');
        }

        if ($newName !== $directory->getName()
            && $this->getRepository()->findByNameAndParent($newName, $directory->getParent())
        ) {
            throw new \InvalidArgumentException(
                sprintf('A directory named "%s" already exists in this parent directory', $newName)
            );
        }

        $oldPath = $directory->getPath();
        $directory->setName($newName);
        $this->entityManager->flush();

        if ($oldPath && $oldPath !== $directory->getPath()) {
            $this->refixDescendantPaths($oldPath, $directory->getPath(), 0);
        }
    }

    public function move(Directory $directory, ?Directory $newParent): void
    {
        if ($newParent && $this->wouldCreateCircularReference($directory, $newParent)) {
            throw new \InvalidArgumentException('Cannot move a directory into itself or one of its children');
        }

        $oldParentId = $directory->getParent()?->getId();
        $newParentId = $newParent?->getId();
        if ($oldParentId === $newParentId) {
            return;
        }

        $oldPath = $directory->getPath();
        $oldLevel = $directory->getLevel();
        $directory->setParent($newParent);
        $this->entityManager->flush();

        if ($oldPath && $oldPath !== $directory->getPath()) {
            $this->refixDescendantPaths($oldPath, $directory->getPath(), $directory->getLevel() - $oldLevel);
        }
    }

    public function wouldCreateCircularReference(Directory $directory, Directory $candidateParent): bool
    {
        $current = $candidateParent;
        while ($current !== null) {
            if ($current->getId() === $directory->getId()) {
                return true;
            }
            $current = $current->getParent();
        }

        return false;
    }

    /**
     * Soft delete a directory.
     *
     * The content (devices, ISOs, operating systems) and the child
     * directories are moved to the parent directory (or root) so that
     * nothing is orphaned, then the directory is soft-deleted.
     */
    public function softDelete(Directory $directory, bool $force = false): void
    {
        $parent = $directory->getParent();
        $deletedPath = $directory->getPath();

        foreach ($directory->getDevices()->toArray() as $device) {
            $device->setDirectory($parent);
        }
        foreach ($directory->getIsos()->toArray() as $iso) {
            $iso->setDirectory($parent);
        }
        foreach ($directory->getOperatingSystems()->toArray() as $os) {
            $os->setDirectory($parent);
        }
        foreach ($directory->getChildren()->toArray() as $child) {
            $child->setParent($parent);
        }

        $directory->delete();
        $this->entityManager->flush();

        // Rebuild stored path/level of the re-parented subtree
        // (stored paths still carry the deleted directory as prefix)
        $stale = $this->getRepository()->findByPathPattern($deletedPath . '/%');
        usort($stale, fn (Directory $a, Directory $b) => $a->getPath() <=> $b->getPath());

        $newPrefix = $parent ? $parent->getPath() : '';
        foreach ($stale as $staleDirectory) {
            $newPath = $newPrefix . substr($staleDirectory->getPath(), strlen($deletedPath));
            $staleDirectory->setPath($newPath);
            $staleDirectory->setLevel(substr_count($newPath, '/') - 1);
        }

        $this->entityManager->flush();
    }

    public function restore(Directory $directory): void
    {
        $directory->restore();
        $this->entityManager->flush();
    }

    /**
     * After renaming or moving a directory, refresh the stored path
     * (and optionally the level) of every descendant, parents first.
     */
    private function refixDescendantPaths(string $oldPath, string $newPath, int $levelDelta): void
    {
        $descendants = $this->getRepository()->findByPathPattern($oldPath . '/%');
        usort($descendants, fn (Directory $a, Directory $b) => $a->getPath() <=> $b->getPath());

        foreach ($descendants as $descendant) {
            if (str_starts_with($descendant->getPath(), $oldPath)) {
                $descendant->setPath($newPath . substr($descendant->getPath(), strlen($oldPath)));
            }
            if ($levelDelta !== 0) {
                $descendant->setLevel(max(0, $descendant->getLevel() + $levelDelta));
            }
        }

        $this->entityManager->flush();
    }
}
