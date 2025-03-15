<?php

namespace App\Service\Lab;

use App\Entity\Lab;
use App\Repository\LabRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;


class BannerManager
{
    protected $logger;
    protected $entityManager;
    protected $labRepository;
    protected $bannerDirectory;
    protected $router;

    public function __construct(
        LoggerInterface $logger,
        LabRepository $labRepository,
        EntityManagerInterface $entityManager,
        UrlGeneratorInterface $router,
        string $bannerDirectory
    ) {
        $this->logger = $logger;
        $this->entityManager = $entityManager;
        $this->labRepository = $labRepository;
        $this->bannerDirectory = $bannerDirectory;
        $this->router = $router;
    }

    public function copyBanner(int $id, int $newId){
        if (!$lab = $this->labRepository->find($id)) {
            throw new NotFoundHttpException();
        }
  
        if (!$newLab = $this->labRepository->find($newId)) {
            throw new NotFoundHttpException();
        }
        try {
        $fileName = $lab->getBanner();
        $src = $this->bannerDirectory.'/'.$lab->getId().'/'.$fileName;
        //$image = file_get_contents($file);
        $dst=$this->bannerDirectory.'/'.$newLab->getId().'/'.$fileName;
        $filesystem = new Filesystem();
        $filesystem->copy($src,$dst);
        //file_put_contents($this->bannerDirectory.'/'.$newLab->getId().'/'.$fileName, $image);

        $newLab->setBanner($fileName);

        $this->entityManager->persist($newLab);
        $this->entityManager->flush();
        }
        catch (IOExceptionInterface $exception) {
            $this->logger->error("An error occurred while creating your directory at ".$exception->getPath());
        }

        return new JsonResponse(['url' => $this->router->generate('api_get_lab_banner', ['id' => $newId], UrlGeneratorInterface::ABSOLUTE_URL)]);

    }
}