<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ProfilePictureFileUploader extends FileUploader
{
    /**
     * @var User
     */
    protected $user;

    public function __construct(string $targetDirectory, TokenStorageInterface $user)
    {
        parent::__construct($targetDirectory);
        $this->user = $user->getToken()->getUser();
    }

    public function setFileName(?UploadedFile $file): self
    {
        $this->fileName = "avatar." . $file->guessExtension();

        return $this;
    }

    public function getTargetDirectory(): string
    {
        return $this->targetDirectory . '/' . $this->user->getId();
    }
}
