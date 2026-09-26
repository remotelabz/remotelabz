<?php

namespace App\Service;

use App\Entity\PracticalSubject;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class PracticalSubjectFileUploader extends FileUploader
{
    private ?PracticalSubject $practicalSubject = null;

    public function setPracticalSubject(PracticalSubject $practicalSubject): self
    {
        $this->practicalSubject = $practicalSubject;

        return $this;
    }

    public function getTargetDirectory(): string
    {
        if ($this->practicalSubject !== null && $this->practicalSubject->getId() !== null) {
            return $this->targetDirectory.'/'.$this->practicalSubject->getId();
        }

        return $this->targetDirectory;
    }

    public static function getFileExtension(UploadedFile $file): string
    {
        $extension = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
        if ('' === $extension) {
            $extension = strtolower($file->guessExtension() ?? '');
        }

        return $extension;
    }

    /**
     * @param UploadedFile $file File descriptor, must be a .md or .pdf file
     * @return string The file name
     */
    public function upload(UploadedFile $file): string
    {
        $extension = self::getFileExtension($file);
        if (!in_array($extension, ['md', 'pdf'], true)) {
            throw new FileException('Only .md and .pdf files are allowed.');
        }

        $targetDirectory = $this->getTargetDirectory();
        if (!is_dir($targetDirectory)) {
            (new Filesystem())->mkdir($targetDirectory);
        }

        return parent::upload($file);
    }

    public function setFileName(?UploadedFile $file): self
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = transliterator_transliterate('Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()', $originalFilename);
        $this->fileName = $safeFilename.'_'.uniqid().'.'.self::getFileExtension($file);

        return $this;
    }
}
