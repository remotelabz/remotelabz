<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ProfilePictureFileUploader extends FileUploader
{
    protected $targetDirectory;
    protected $fileName;
    protected $user;

    public function __construct(string $targetDirectory, TokenStorageInterface $user)
    {
        parent::__construct($targetDirectory);
        $this->user = $user->getToken()->getUser();
    }

    public function setFileName(?UploadedFile $file)
    {
        $this->fileName = "avatar." . $file->guessExtension();
    }

    public function getTargetDirectory()
    {
        return $this->targetDirectory . '/' . $this->user->getId();
    }
}
