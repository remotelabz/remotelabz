<?php

namespace App\EventListener;

use App\Entity\Device;
use App\Service\FileUploader;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Doctrine\ORM\Event\PostLoadEventArgs;

class LaunchScriptUploadListener
{
    private $uploader;

    public function __construct(FileUploader $uploader)
    {
        $this->uploader = $uploader;
    }

    public function postLoad(PostLoadEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Device) {
            return;
        }

        if ($fileName = $entity->getLaunchScript()) {
            $entity->setLaunchScript(new File($this->uploader->getTargetDirectory().'/'.$fileName));
        }
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();

        $this->uploadFile($entity);
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        $this->uploadFile($entity);
    }

    private function uploadFile($entity): void
    {
        // upload only works for Device entities
        if (!$entity instanceof Device) {
            return;
        }

        $file = $entity->getLaunchScript();

        // only upload new files
        if ($file instanceof UploadedFile) {
            $fileName = $this->uploader->upload($file);
            $entity->setLaunchScript($fileName);
        } elseif ($file instanceof File) {
            // prevents the full file path being saved on updates
            // as the path is set on the postLoad listener
            $entity->setLaunchScript($file->getFilename());
        }
    }
}
