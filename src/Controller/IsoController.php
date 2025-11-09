<?php

namespace App\Controller;

use App\Entity\Iso;
use App\Entity\Arch;
use App\Entity\User;
use App\Entity\ConfigWorker;
use App\Entity\OperatingSystem;

use App\Form\IsoType;
use App\Repository\IsoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Psr\Log\LoggerInterface;
use App\Service\SshService;
use App\Repository\ConfigWorkerRepository;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Service\Files2WorkerManager;

#[IsGranted("ROLE_TEACHER_EDITOR", message: "Access denied.")]
class IsoController extends AbstractController
{
    private LoggerInterface $logger;
    private Files2WorkerManager $Files2WorkerManager;
    
    public function __construct(LoggerInterface $logger, Files2WorkerManager $Files2WorkerManager)
    {
        $this->logger = $logger;
        $this->Files2WorkerManager = $Files2WorkerManager;
    }   

    #[Route('/admin/isos', name: 'app_iso_index', methods: ['GET'])]
    public function index(IsoRepository $isoRepository): Response
    {
        return $this->render('iso/index.html.twig', [
            'isos' => $isoRepository->findAll(),
        ]);
    }

    #[Route('/admin/isos/new', name: 'app_iso_new', methods: ['GET','POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $iso = new Iso();
        $form = $this->createForm(IsoType::class, $iso);
        $form->handleRequest($request);
        $maxUploadSize = min(
            ini_get('upload_max_filesize'),ini_get('post_max_size')
        );

        if ($form->isSubmitted() && $form->isValid()) {
            $this->logger->info('Creating ISO entry by user: ' . $this->getUser()->getName());
    
            $this->logger->debug('[IsoController:new]::Form data: ' . json_encode($form->getData()));
            $this->logger->debug('[IsoController:new]::Request data: ' . json_encode($request->request->all()));
   
            $fileSourceType = $form->get('fileSourceType')->getData();
            
            if ($fileSourceType === 'upload') {
                $uploadedFilename = $form->get('uploaded_filename')->getData();
                
                $this->logger->debug('[IsoController:new]::Uploaded filename from form: ' . $uploadedFilename);
                
                if ($uploadedFilename && trim($uploadedFilename) !== '') {
                    $iso->setFilename($uploadedFilename);
                    $iso->setFilenameUrl(null);
                    
                    $localFilePath = $this->getParameter('iso_directory') . '/' . $uploadedFilename;
                    $remoteFilePath = '/images/'.$uploadedFilename;
                    $results = $this->Files2WorkerManager->CopyFileToAllWorkers($localFilePath, $remoteFilePath);

                    $failures = array_filter($results, function($result) {
                        return !$result['success'];
                    });
                    
                    if (!empty($failures)) {
                        $this->addFlash('warning', 'ISO created but some workers failed to send the file.');
                    } else {
                        unlink($localFilePath);
                        $this->addFlash('success', 'ISO created and file copied to all workers successfully.');
                    }
                } else {
                    $this->addFlash('danger', 'No file was uploaded. Please upload a file first.');
                    return $this->render('iso/new.html.twig', [
                        'iso' => $iso,
                        'sizeLimit' => $maxUploadSize,
                        'form' => $form,
                    ]);
                }
            } elseif ($fileSourceType === 'url') {
                // Mode URL : nettoyer le filename si présent
                $iso->setFilename(null);
                // L'URL est déjà définie par le formulaire via setFilenameUrl
            } elseif ($fileSourceType === 'filename') {
                // Mode filename only : nettoyer l'URL si présente
                $iso->setFilenameUrl(null);
                // Le filename est déjà défini par le formulaire
            }

            $entityManager->persist($iso);
            $entityManager->flush();

            $this->addFlash('success', 'ISO created successfully');

            return $this->redirectToRoute('app_iso_index');
        } elseif ($form->isSubmitted()) {
            $this->logger->error('Form submitted but invalid');
            $this->logger->debug('[IsoController:new]::Form errors: ' . (string) $form->getErrors(true));

            foreach ($form->getErrors(true) as $error) {
                $this->logger->error('Form error: ' . $error->getMessage());
            }
        }

        return $this->render('iso/new.html.twig', [
            'iso' => $iso,
            'sizeLimit' => $maxUploadSize,
            'form' => $form,
        ]);
    }

    #[Route('/admin/iso/{id}', name: 'app_iso_show', methods: ['GET'])]
    public function show(Iso $iso): Response
    {
        return $this->render('iso/view.html.twig', [
            'iso' => $iso,
        ]);
    }

    #[Route('/admin/isos/{id}/edit', name: 'app_iso_edit', methods: ['GET','POST'])]
    public function edit(Request $request, Iso $iso, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        // Sauvegarder l'ancien filename pour comparaison
        $oldFilename = $iso->getFilename();
        $oldFilenameUrl = $iso->getFilenameUrl();
        
        $form = $this->createForm(IsoType::class, $iso);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->logger->info('Editing ISO entry by user: ' . $this->getUser()->getName());

            $fileSourceType = $form->get('fileSourceType')->getData();
            
            $this->logger->debug('[IsoController:edit]::File source type: ' . $fileSourceType);
            $this->logger->debug('[IsoController:edit]::Old filename: ' . $oldFilename);
            $this->logger->debug('[IsoController:edit]::Old URL: ' . $oldFilenameUrl);
            $this->logger->debug('[IsoController:edit]::New filename from form: ' . $iso->getFilename());
            $this->logger->debug('[IsoController:edit]::New URL from form: ' . $iso->getFilenameUrl());

            if ($fileSourceType === 'upload') {
                $uploadedFileName = $form->get('uploaded_filename')->getData();
                $this->logger->debug('[IsoController:edit]::Uploaded filename from form: ' . $uploadedFileName);
                
                if ($uploadedFileName && trim($uploadedFileName) !== '') {
                    // Nouveau fichier uploadé
                    
                    // Supprimer l'ancien fichier physique s'il existe et qu'il est différent
                    if ($oldFilename && $oldFilename !== $uploadedFileName) {
                        $oldFile = $this->getParameter('iso_directory') . '/' . $oldFilename;
                        if (file_exists($oldFile)) {
                            $this->logger->debug('[IsoController:edit]::Deleting old file: ' . $oldFile);
                            unlink($oldFile);
                            $oldFilePath = '/images/' . $oldFilename;
                            $this->Files2WorkerManager->deleteFileFromAllWorkers($oldFilePath);
                        }
                    }

                    // Copier le nouveau fichier vers les workers
                    $localFilePath = $this->getParameter('iso_directory') . '/' . $uploadedFileName;
                    $remoteFilePath = '/images/' . $uploadedFileName;
                    $results = $this->Files2WorkerManager->CopyFileToAllWorkers($localFilePath, $remoteFilePath);
                    
                    $failures = array_filter($results, function($result) {
                        return !$result['success'];
                    });
                    
                    if (empty($failures)) {
                        unlink($localFilePath);
                    }
                    
                    // Mettre à jour l'entité
                    $iso->setFilename($uploadedFileName);
                    $iso->setFilenameUrl(null);
                    
                } else {
                    // Pas de nouveau fichier uploadé
                    if ($oldFilename) {
                        // Conserver l'ancien filename
                        $iso->setFilename($oldFilename);
                        $iso->setFilenameUrl(null);
                    } else {
                        $this->addFlash('danger', 'No file was uploaded. Please upload a file first.');
                        return $this->render('iso/new.html.twig', [
                            'iso' => $iso,
                            'form' => $form,
                            'sizeLimit' => min(ini_get('upload_max_filesize'), ini_get('post_max_size'))
                        ]);
                    }
                }
                
            } elseif ($fileSourceType === 'url') {
                // Mode URL
                $this->logger->debug('[IsoController:edit]::Switching to URL mode');
                
                // Supprimer l'ancien fichier physique s'il existe
                if ($oldFilename) {
                    $oldFile = $this->getParameter('iso_directory') . '/' . $oldFilename;
                    if (file_exists($oldFile)) {
                        $this->logger->debug('[IsoController:edit]::Deleting old file when switching to URL: ' . $oldFile);
                        unlink($oldFile);
                        $this->Files2WorkerManager->deleteFileFromAllWorkers('/images/' . $oldFilename);
                    }
                }
                
                // Mettre à jour l'entité
                $iso->setFilename(null);
                // L'URL est déjà mise à jour par le formulaire
                
            } elseif ($fileSourceType === 'filename') {
                // Mode filename only
                $this->logger->debug('[IsoController:edit]::Switching to filename only mode');
                $this->logger->debug('[IsoController:edit]::Filename from form: ' . $iso->getFilename());
                
                // Si on avait un fichier uploadé avant, le supprimer
                if ($oldFilename && file_exists($this->getParameter('iso_directory') . '/' . $oldFilename)) {
                    $oldFile = $this->getParameter('iso_directory') . '/' . $oldFilename;
                    $this->logger->debug('[IsoController:edit]::Deleting old uploaded file when switching to filename only: ' . $oldFile);
                    unlink($oldFile);
                    $this->Files2WorkerManager->deleteFileFromAllWorkers('/images/' . $oldFilename);
                }
                
                // Nettoyer l'URL
                $iso->setFilenameUrl(null);
                
                // Le filename est déjà défini par le formulaire via le mapping
                $this->logger->debug('[IsoController:edit]::Final filename: ' . $iso->getFilename());
            }

            $entityManager->persist($iso);
            $entityManager->flush();
            
            $this->logger->info('[IsoController:edit]::ISO updated successfully');
            $this->addFlash('success', 'ISO modifiée avec succès');
            return $this->redirectToRoute('app_iso_index');
            
        } elseif ($form->isSubmitted()) {
            $this->logger->error('Form submitted but invalid during edit');
            $this->logger->debug('[IsoController:edit]::Form errors: ' . (string) $form->getErrors(true));

            $uploadedFileName = $form->get('uploaded_filename')->getData();
            if ($uploadedFileName && trim($uploadedFileName) !== '') {
                if ($this->deleteLocalTempFile($uploadedFileName)) {
                    $this->logger->debug('[IsoController:edit]::Deleted temporary file: ' . $uploadedFileName);
                }
            }

            foreach ($form->getErrors(true) as $error) {
                $this->logger->error('Form error: ' . $error->getMessage());
            }
        }

        $maxUploadSize = min(ini_get('upload_max_filesize'), ini_get('post_max_size'));

        return $this->render('iso/new.html.twig', [
            'iso' => $iso,
            'form' => $form,
            'sizeLimit' => $maxUploadSize
        ]);
    }

    #[Route('/admin/isos/{id}/delete', name: 'app_iso_delete', methods: ['DELETE'])]
    public function delete(Request $request, Iso $iso, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$iso->getId(), $request->request->get('_token'))) {
            if ($iso->getFilename()) {
                $file = $this->getParameter('iso_directory') . '/' . $iso->getFilename();
                if (file_exists($file)) {
                    unlink($file);
                    $this->Files2WorkerManager->deleteFileFromAllWorkers('/images/'.$iso->getFilename());
                    $this->logger->debug('[IsoController:delete]::Deleted file '.$file.' from all active workers');
                }
            }
            
            $entityManager->remove($iso);
            $entityManager->flush();
            
            $this->addFlash('success', 'ISO supprimée avec succès');
        }

        return $this->redirectToRoute('app_iso_index');
    }

    #[Route('/api/isos/upload', name: 'app_iso_upload', methods: ['POST'])]
    public function upload(Request $request, SluggerInterface $slugger): Response
    {
        $this->logger->info('Uploading ISO file requested by user '.$this->getUser()->getName());
        
        $file = $request->files->get('file');
        
        if ($file && $file->isValid()) {
            $this->logger->debug('[IsoController:upload]::The file to upload will be '.$file.' of size '.$file->getSize());

            $maxUploadSize = min(
                $this->convertPHPSizeToBytes(ini_get('upload_max_filesize')),
                $this->convertPHPSizeToBytes(ini_get('post_max_size'))
            );

            if ($file->getSize() > $maxUploadSize) {
                return $this->json(['error' => 'File too large'], 413);
            }

            $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

            try {
                $uploadDir = $this->getParameter('iso_directory');
                $this->logger->debug('[IsoController:upload]::Try to move '.$file.' file to '.$newFilename);
                $file->move($uploadDir, $newFilename);
        
                $newFile = $uploadDir . '/' . $newFilename;
                $fileSize = file_exists($newFile) ? filesize($newFile) : null;

                $this->logger->debug('[IsoController:upload]::Move '.$file.' file to '.$newFile.' done.');

            } catch (FileException $e) {
                return $this->json(['error' => 'Upload failed'], 500);
            } catch (\Exception $e) {
                $this->logger->error('[IsoController:upload]::Error during file upload: ' . $e->getMessage());
                return $this->json(['error' => 'Upload failed due to an unexpected error'], 500);
            }

            return $this->json([
                'success' => true, 
                'filename' => $newFilename,
                'originalName' => $file->getClientOriginalName(),
                'size' => $fileSize
            ]);
        }
        
        return $this->json(['error' => 'No file uploaded'], 400);
    }

    #[Route('/api/isos/delete-temp-file', name: 'app_iso_delete_temp_file', methods: ['POST','DELETE'])]
    public function deleteTempFile(Request $request): Response
    {
        $filename = $request->request->get('filename');
        $this->logger->info('Deleting temporary ISO file '.$filename.' by user '.$this->getUser()->getName().' requested');

        if (!$filename) {
            return $this->json(['error' => 'No filename provided'], 400);
        }

        if ($this->deleteLocalTempFile($filename)) {
            return $this->json(['success' => true]);
        } else {
            $this->logger->warning('Temporary ISO file '.$filename.' not found for deletion');
            return $this->json(['error' => 'File not found'], 404);
        }
    }

    private function deleteLocalTempFile(string $filename): bool
    {
        $filePath = $this->getParameter('iso_directory') . '/' . $filename;
        if (file_exists($filePath)) {
            unlink($filePath);
            return true;
        }
        return false;
    }

    #[Route('/api/isos/validate-url', name: 'app_iso_validate_url', methods: ['POST'])]
    public function validateUrl(Request $request): Response
    {
        $this->logger->debug('[IsoController:validateUrl]::Validating ISO URL by user :'.$this->getUser()->getName());
        $url = $request->request->get('url');
        if (!$url) {
            return $this->json(['error' => 'No URL provided'], 400);
        }

        $validation = $this->validateIsoUrl($url);
        
        if ($validation['valid']) {
            return $this->json([
                'success' => true,
                'valid' => true,
                'fileSize' => $validation['fileSize'],
                'contentType' => $validation['contentType'],
                'fileName' => $validation['fileName']
            ]);
        } else {
            return $this->json([
                'success' => true,
                'valid' => false,
                'error' => $validation['error']
            ]);
        }
    }

    private function validateIsoUrl(string $url): array
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return ['valid' => false, 'error' => 'Invalid URL format'];
        }

        if (!preg_match('/\.iso(\?|$|#)/i', $url)) {
            return ['valid' => false, 'error' => 'URL does not appear to be an ISO file'];
        }

        try {
            // Initialiser cURL
            $ch = curl_init();
            
            // Configuration de cURL pour une requête HEAD
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_NOBODY => true,              // Requête HEAD uniquement
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true,
                CURLOPT_FOLLOWLOCATION => true,      // Suivre les redirections
                CURLOPT_MAXREDIRS => 5,              // Maximum 5 redirections
                CURLOPT_TIMEOUT => 30,               // Timeout de 30 secondes
                CURLOPT_SSL_VERIFYPEER => true,      // Vérifier les certificats SSL
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; ISO-Validator/1.0)',
            ]);

            // Exécuter la requête
            $response = curl_exec($ch);
            
            // Vérifier les erreurs cURL
            if ($response === false) {
                $error = curl_error($ch);
                curl_close($ch);
                $this->logger->error('ISO URL validation cURL error: ' . $error . ' for URL: ' . $url);
                return ['valid' => false, 'error' => 'Unable to reach the URL: ' . $error];
            }

            // Récupérer le code HTTP
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if ($httpCode !== 200) {
                curl_close($ch);
                return ['valid' => false, 'error' => 'URL is not accessible (HTTP ' . $httpCode . ')'];
            }

            // Récupérer les informations
            $contentLength = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            
            curl_close($ch);

            // Valider la taille du fichier
            $fileSize = null;
            if ($contentLength > 0) {
                $fileSize = (int)$contentLength;
                
                // Vérifier si la taille est raisonnable pour un ISO (entre 1 MB et 10 GB)
                if ($fileSize < 1024 * 1024 || $fileSize > 10 * 1024 * 1024 * 1024) {
                    return ['valid' => false, 'error' => 'File size seems unusual for an ISO file'];
                }
            }

            // Valider le type de contenu
            if ($contentType) {
                $validMimeTypes = [
                    'application/x-iso9660-image',
                    'application/octet-stream',
                    'application/x-cd-image',
                    'application/x-raw-disk-image'
                ];
                
                $isValidMime = false;
                foreach ($validMimeTypes as $validType) {
                    if (strpos($contentType, $validType) !== false) {
                        $isValidMime = true;
                        break;
                    }
                }
                
                if (!$isValidMime) {
                    $this->logger->warning('ISO URL validation: Unexpected content type: ' . $contentType . ' for URL: ' . $url);
                }
            }

            // Extraire le nom du fichier depuis l'URL
            $fileName = basename(parse_url($url, PHP_URL_PATH));
            if (empty($fileName) || !preg_match('/\.iso$/i', $fileName)) {
                $fileName = 'downloaded.iso';
            }

            return [
                'valid' => true,
                'fileSize' => $fileSize,
                'contentType' => $contentType,
                'fileName' => $fileName
            ];

        } catch (\Exception $e) {
            $this->logger->error('ISO URL validation error: ' . $e->getMessage() . ' for URL: ' . $url);
            return ['valid' => false, 'error' => 'Network error or timeout'];
        }
    }

    private function convertPHPSizeToBytes($size)
    {
        $unit = strtolower(substr($size, -1));
        $bytes = (int) $size;

        switch($unit) {
            case 'g':
                $bytes *= 1024;
            case 'm':
                $bytes *= 1024;
            case 'k':
                $bytes *= 1024;
        }

        return $bytes;
    }
}