<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * @author Julien Hubert <julien.hubert@outlook.com>
 */
class ImageFileUploader extends FileUploader
{
    public function setFileName(?UploadedFile $file)
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = transliterator_transliterate('Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()', $originalFilename);
        $this->fileName = $safeFilename.'_'.uniqid().'.'.$file->getClientOriginalExtension();

        return $this;
    }
}
