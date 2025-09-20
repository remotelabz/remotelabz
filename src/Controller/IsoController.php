<?php

namespace App\Controller;

use App\Entity\Iso;
use App\Entity\Arch;
use App\Entity\User;

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

#[IsGranted("ROLE_TEACHER_EDITOR", message: "Access denied.")]
class IsoController extends AbstractController
{

     /** @var LoggerInterface $logger */
    private $logger;
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
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
                // Récupérer le nom du fichier depuis le champ du formulaire
                $uploadedFileName = $form->get('uploaded_filename')->getData();
                
                $this->logger->debug('[IsoController:new]::Uploaded filename from form: ' . $uploadedFileName);
                
                if ($uploadedFileName && trim($uploadedFileName) !== '') {
                    $iso->setFilename($uploadedFileName);
                    $iso->setFilenameUrl(null);
                } else {
                    $this->addFlash('error', 'No file was uploaded. Please upload a file first.');
                    return $this->render('iso/new.html.twig', [
                        'iso' => $iso,
                        'sizeLimit' => $maxUploadSize,
                        'form' => $form,
                    ]);
                }
            }

            $entityManager->persist($iso);
            $entityManager->flush();

            $this->addFlash('success', 'ISO created successfully');
            return $this->redirectToRoute('app_iso_index');
        }   elseif ($form->isSubmitted()){
            $this->logger->error('Form submitted but invalid');
            $this->logger->debug('[IsoController:new]::Form errors: ' . (string) $form->getErrors(true));

            // Afficher les erreurs pour debug
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
        $form = $this->createForm(IsoType::class, $iso);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->logger->info('Editing ISO entry by user: ' . $this->getUser()->getName());
            
            $fileSourceType = $form->get('fileSourceType')->getData();
            
            $this->logger->debug('[IsoController:edit]::File source type: ' . $fileSourceType);
            $this->logger->debug('[IsoController:edit]::Current ISO filename: ' . $iso->getFilename());
            $this->logger->debug('[IsoController:edit]::Current ISO URL: ' . $iso->getFilenameUrl());
            
            if ($fileSourceType === 'upload') {
                // CORRECTION: Récupérer le nom du fichier depuis le champ du formulaire
                $uploadedFileName = $form->get('uploaded_filename')->getData();
                $this->logger->debug('[IsoController:edit]::Uploaded filename from form: ' . $uploadedFileName);
                
                if ($uploadedFileName && trim($uploadedFileName) !== '') {
                    // Supprimer l'ancien fichier physique si il existe et qu'il est différent
                    if ($iso->getFilename() && $iso->getFilename() !== $uploadedFileName) {
                        $oldFile = $this->getParameter('iso_directory') . '/' . $iso->getFilename();
                        if (file_exists($oldFile)) {
                            $this->logger->debug('[IsoController:edit]::Deleting old file: ' . $oldFile);
                            unlink($oldFile);
                        }
                    }
                    
                    // Si on passe d'URL à upload, supprimer l'URL
                    if ($iso->getFilenameUrl()) {
                        $this->logger->debug('[IsoController:edit]::Switching from URL to file upload');
                        $iso->setFilenameUrl(null);
                    }
                    
                    $iso->setFilename($uploadedFileName);
                } else {
                    // Pas de nouveau fichier uploadé, conserver l'existant si c'était déjà un fichier
                    if (!$iso->getFilename()) {
                        $this->addFlash('error', 'No file was uploaded. Please upload a file first.');
                        return $this->render('iso/new.html.twig', [
                            'iso' => $iso,
                            'form' => $form,
                            'sizeLimit' => min(ini_get('upload_max_filesize'), ini_get('post_max_size'))
                        ]);
                    }
                    // Si on avait déjà un fichier, on le garde
                }
            } else {
                // Mode URL sélectionné
                $this->logger->debug('[IsoController:edit]::Switching to URL mode');
                
                // Si on passe de fichier à URL, supprimer l'ancien fichier
                if ($iso->getFilename()) {
                    $oldFile = $this->getParameter('iso_directory') . '/' . $iso->getFilename();
                    if (file_exists($oldFile)) {
                        $this->logger->debug('[IsoController:edit]::Deleting old file when switching to URL: ' . $oldFile);
                        unlink($oldFile);
                    }
                    $iso->setFilename(null);
                }
                
                // L'URL est déjà mise à jour par le formulaire automatiquement
            }

            $entityManager->flush();
            
            $this->logger->info('[IsoController:edit]::ISO updated successfully');
            $this->addFlash('success', 'ISO modifiée avec succès');
            return $this->redirectToRoute('app_iso_index');
        } elseif ($form->isSubmitted()) {
            $this->logger->error('Form submitted but invalid during edit');
            $this->logger->debug('[IsoController:edit]::Form errors: ' . (string) $form->getErrors(true));

            // Afficher les erreurs pour debug
            foreach ($form->getErrors(true) as $error) {
                $this->logger->error('Form error: ' . $error->getMessage());
            }
        }

        $maxUploadSize = min(ini_get('upload_max_filesize'),ini_get('post_max_size'));

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
            // Supprimer le fichier physique si il existe
            if ($iso->getFilename()) {
                $file = $this->getParameter('iso_directory') . '/' . $iso->getFilename();
                if (file_exists($file)) {
                    unlink($file);
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
                $this->convertPHPSizeToBytes(ini_get('upload_max_filesize')),$this->convertPHPSizeToBytes(ini_get('post_max_size'))
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
            }
            catch (\Exception $e) {
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
        else return $this->json(['error' => 'No file uploaded'], 400);
    }

    #[Route('/api/isos/delete-temp-file', name: 'app_iso_delete_temp_file', methods: ['POST'])]
    public function deleteTempFile(Request $request): Response
    {
        $this->logger->info('Deleting temporary ISO file requested by user '.$this->getUser()->getName());
        $filename = $request->request->get('filename');

        if (!$filename) {
            return $this->json(['error' => 'No filename provided'], 400);
        }

        $filePath = $this->getParameter('iso_directory') . '/' . $filename;
        if (file_exists($filePath)) {
            $this->logger->debug('[IsoController:upload]::Try to delete '.$filename);
            unlink($filePath);
            return $this->json(['success' => true]);
        }

        return $this->json(['error' => 'File not found'], 404);
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
        // Vérification basique de l'URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return ['valid' => false, 'error' => 'Invalid URL format'];
        }

        // Vérifier que l'URL contient .iso
        if (!preg_match('/\.iso(\?|$|#)/i', $url)) {
            return ['valid' => false, 'error' => 'URL does not appear to be an ISO file'];
        }

        try {
            // Créer un contexte avec timeout et user-agent
            $context = stream_context_create([
                'http' => [
                    'method' => 'HEAD', // Utiliser HEAD pour ne pas télécharger le fichier
                    'timeout' => 30,
                    'user_agent' => 'Mozilla/5.0 (compatible; ISO-Validator/1.0)',
                    'follow_location' => true,
                    'max_redirects' => 5
                ]
            ]);

            // Effectuer la requête HEAD
            $headers = @get_headers($url, true, $context);
            
            if ($headers === false) {
                return ['valid' => false, 'error' => 'Unable to reach the URL'];
            }

            // Vérifier le code de statut
            $statusLine = $headers[0];
            if (!preg_match('/HTTP\/\d\.\d\s+200/', $statusLine)) {
                return ['valid' => false, 'error' => 'URL is not accessible (HTTP error)'];
            }

            // Extraire les informations utiles
            $fileSize = null;
            $contentType = null;
            $fileName = null;

            // Gestion des cas où les headers peuvent être des tableaux (redirections)
            $finalHeaders = [];
            foreach ($headers as $key => $value) {
                if (is_array($value)) {
                    $finalHeaders[$key] = end($value); // Prendre le dernier (après redirections)
                } else {
                    $finalHeaders[$key] = $value;
                }
            }

            // Taille du fichier
            if (isset($finalHeaders['Content-Length'])) {
                $fileSize = (int)$finalHeaders['Content-Length'];
                
                // Vérifier que la taille est raisonnable pour un ISO (entre 1MB et 10GB)
                if ($fileSize < 1024 * 1024 || $fileSize > 10 * 1024 * 1024 * 1024) {
                    return ['valid' => false, 'error' => 'File size seems unusual for an ISO file'];
                }
            }

            // Type de contenu
            if (isset($finalHeaders['Content-Type'])) {
                $contentType = $finalHeaders['Content-Type'];
                
                // Vérifier les types MIME acceptables
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
                
                // Si le type MIME n'est pas reconnu, on accepte quand même mais on avertit
                if (!$isValidMime) {
                    $this->logger->warning('ISO URL validation: Unexpected content type: ' . $contentType . ' for URL: ' . $url);
                }
            }

            // Nom du fichier depuis l'URL
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

    // Fonction utilitaire pour convertir la taille PHP en octets
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