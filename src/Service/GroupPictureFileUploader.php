<?php

namespace App\Service;

use App\Entity\Group;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class GroupPictureFileUploader extends FileUploader
{
    public function __construct(string $targetDirectory)
    {
        $this->targetDirectory = $targetDirectory;
    }

    public function setFileName(?UploadedFile $file): self
    {
        $this->fileName = "avatar." . $file->guessExtension();

        return $this;
    }

    public function setGroup(Group $group): self
    {
        $this->targetDirectory = $this->targetDirectory . '/' . $group->getId();
        return $this;
    }
}
