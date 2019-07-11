<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

/**
 * Base class for file upload. Every type of file uploaders should extend it.
 * 
 * @author Julien Hubert <julien.hubert@outlook.com>
 */
class FileUploader
{
    /**
     * @var string The directory in which files will be moved
     */
    protected $targetDirectory;

    /**
     * @var string The file name
     */
    protected $fileName;

    public function __construct(string $targetDirectory)
    {
        $this->targetDirectory = $targetDirectory;
    }

    /**
     * Upload the file
     *
     * @param UploadedFile $file File descriptor
     * @return string The file name
     */
    public function upload(UploadedFile $file): string
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

    public function getFileName(): string
    {
        return $this->fileName;
    }

    /**
     * @param UploadedFile|null $file
     * @return self
     */
    public function setFileName(?UploadedFile $file)
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = transliterator_transliterate('Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()', $originalFilename);
        $this->fileName = $safeFilename.'_'.uniqid().'.'.$file->guessExtension();

        return $this;
    }

    public function getTargetDirectory(): string
    {
        return $this->targetDirectory;
    }
}
