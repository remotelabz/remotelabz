<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class FileUploader
{
    protected $targetDirectory;
    protected $fileName;

    public function __construct($targetDirectory)
    {
        $this->targetDirectory = $targetDirectory;
    }

    public function upload(UploadedFile $file)
    {
        $this->setFileName($file);
        
        try {
            $file->move($this->getTargetDirectory(), $this->getFileName());
        } catch (FileException $e) {
            // ... handle exception if something happens during file upload
            throw $e;
        }

        return $this->getFileName();
    }

    public function getFileName()
    {
        return $this->fileName;
    }

    public function setFileName(?UploadedFile $file)
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        // $safeFilename = transliterator_transliterate('Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()', $originalFilename);
        $this->fileName = $originalFilename.'-'.uniqid().'.'.$file->guessExtension();
    }

    public function getTargetDirectory()
    {
        return $this->targetDirectory;
    }
}
