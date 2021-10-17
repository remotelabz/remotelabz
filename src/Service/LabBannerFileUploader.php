<?php

namespace App\Service;

use App\Entity\Lab;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class LabBannerFileUploader extends FileUploader
{
    protected $router;

    public function __construct(
        string $targetDirectory,
        UrlGeneratorInterface $router
    ) {
        $this->targetDirectory = $targetDirectory;
        $this->router = $router;
    }

    public function setFileName(?UploadedFile $file): self
    {
        $this->fileName = "banner." . $file->guessExtension();

        return $this;
    }

    public function setLab(Lab $lab): self
    {
        $this->targetDirectory = $this->targetDirectory . '/' . $lab->getId();

        return $this;
    }
}
