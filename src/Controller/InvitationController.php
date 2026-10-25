<?php

namespace App\Controller;

use App\Repository\LabRepository;
use App\Repository\InvitationCodeRepository;
use App\Entity\InvitationCode;
use App\Form\InvitationCodeType;
use App\Form\InvitationCodeImportType;
use App\Security\ACL\LabVoter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\Post;
use FOS\RestBundle\Controller\Annotations\Put;
use FOS\RestBundle\Controller\Annotations\Patch;
use FOS\RestBundle\Controller\Annotations\Delete;
use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Controller\Annotations\Route as RestRoute;
use Symfony\Component\Validator\Constraints\Email as EmailConstraint;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Security\Http\Attribute\Security;
use Doctrine\ORM\EntityManagerInterface;


class InvitationController extends Controller
{
    /** @var LoggerInterface $logger */
    private $logger;
    /** @var LabRepository $labRepository */
    private $labRepository;
    /** @var ValidatorInterface $validator */
    private $validator;
    /** @var MailerInterface $mailer */
    private $mailer;
    /** @var InvitationCodeRepository $invitationCodeRepository */
    private $invitationCodeRepository;
    /** @var EntityManagerInterface $entityManager */
    private $entityManager;
    

    public function __construct(
        LabRepository $labRepository,
        ValidatorInterface $validator,
        LoggerInterface $logger,
        MailerInterface $mailer,
        InvitationCodeRepository $invitationCodeRepository,
        EntityManagerInterface $entityManager)
    {
        $this->labRepository = $labRepository;
        $this->validator = $validator;
        $this->logger = $logger;
        $this->mailer = $mailer;
        $this->invitationCodeRepository = $invitationCodeRepository;
        $this->entityManager = $entityManager;
    }

    
	#[Security("is_granted('ROLE_TEACHER') or is_granted('ROLE_ADMINISTRATOR')", message: "Access denied.")]
    #[Route(path: '/codes', name: 'codes')]
    public function indexAction(Request $request)
    {
        $search = $request->query->get('search', '');

        $user = $this->getUser();
        if ($user->isAdministrator()) {
            $codes = $this->invitationCodeRepository->findCodesGroupByLab(new \DateTime(), $search);
        }
        else {
            $codes = $this->invitationCodeRepository->findCodesByAuthorGroupAndLab(new \DateTime(), $search, $user);
        }

        $limit = $request->query->get('limit', 10);
        $page = $request->query->get('page', 1);
        $count = count($codes);

        try {
            $codes = array_slice($codes, $page * $limit - $limit, $limit);
        } catch (ORMException $e) {
            throw new NotFoundHttpException('Incorrect order field or sort direction', $e, $e->getCode());
        }

        return $this->render('lab/codes_management.html.twig', [
            'codes' => $codes,
            'count' => $count,
            'page' => $page,
            'limit' => $limit,
            'search' => $search
        ]);
    }

    #[Route(path: '/labs/code/{id<\d+>}', name: 'create_code_lab')]
    public function createCodeAction(Request $request, int $id, SerializerInterface $serializer)
    {

        
        $lab = $this->labRepository->find($id);
        $this->denyAccessUnlessGranted(LabVoter::EDIT_CODE, $lab);


        $invitationForm = $this->createForm(InvitationCodeType::class);
        $invitationForm->handleRequest($request);
        
        if ($request->getContentTypeFormat() === 'json') {
            $adresses = json_decode($request->getContent(), true);
            $invitationForm->submit($adresses, false);
        }

        if ($invitationForm->isSubmitted() && $invitationForm->isValid()) {
            $data = $invitationForm->getData();
            $this->checkAction($data, $lab);
            unset($invitationForm);
            $invitationForm = $this->createForm(InvitationCodeType::class);
        }

        // Import d'une liste de mails depuis un fichier CSV/TXT (même principe
        // que l'import de membres d'un groupe).
        $importForm = $this->createForm(InvitationCodeImportType::class);
        $importForm->handleRequest($request);

        if ($importForm->isSubmitted() && $importForm->isValid()) {
            $data = $importForm->getData();
            $file = $data['file'];
            $fileExtension = strtolower($file->getClientOriginalExtension());

            if (in_array($fileExtension, ['csv', 'txt'], true)) {
                $emails = $this->importEmailsFromFile($file->getPathname(), $fileExtension);

                if (count($emails) > 0) {
                    $validated = $this->checkAction(
                        ['emailAdresses' => $emails, 'duration' => $data['duration']],
                        $lab
                    );
                    if ($validated > 0) {
                        $this->addFlash(
                            'success',
                            $validated.' code(s) generated or renewed for lab '.$lab->getName().'.'
                        );
                    }
                } else {
                    $this->addFlash('warning', 'No e-mail address found in the file.');
                }
            } else {
                $this->addFlash('danger', 'This file is not accepted.');
            }

            return $this->redirectToRoute('create_code_lab', ['id' => $lab->getId()]);
        }

        $props=$serializer->serialize(
            ['lab' => $lab],
            'json',
            SerializationContext::create()->setGroups(['api_get_lab'])
        );

        return $this->render('lab/create_code_lab.html.twig', [
            'lab' => $lab,
            'form'=> $invitationForm->createView(),
            'addCodeFromFileForm'=> $importForm->createView(),
            'props' =>$props

        ]);
    }

    /**
     * Extrait les adresses e-mail d'un fichier CSV (colonne "email") ou TXT
     * (une adresse par ligne). La validation du format est faite ensuite par
     * checkAction().
     *
     * @return string[]
     */
    private function importEmailsFromFile(string $filePath, string $fileExtension): array
    {
        $emails = [];

        if ($fileExtension === 'csv') {
            $csv = array_map('str_getcsv', file($filePath, FILE_SKIP_EMPTY_LINES | FILE_IGNORE_NEW_LINES));

            if (empty($csv)) {
                $this->addFlash('danger', "File format is incorrect. CSV is empty.");
                return $emails;
            }

            $header = array_map('strtolower', array_map('trim', array_shift($csv)));
            // Retire un éventuel BOM UTF-8 ajouté par certains éditeurs (Excel "CSV UTF-8")
            if (isset($header[0])) {
                $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
            }

            if (!in_array('email', $header, true)) {
                $this->addFlash('danger', "File format is incorrect. CSV must have an 'email' column.");
                return $emails;
            }

            $emailIndex = array_search('email', $header, true);

            foreach ($csv as $line) {
                if (isset($line[$emailIndex])) {
                    $email = trim($line[$emailIndex]);
                    if (!empty($email)) {
                        $emails[] = $email;
                    }
                }
            }
        } else {
            foreach (file($filePath, FILE_SKIP_EMPTY_LINES | FILE_IGNORE_NEW_LINES) as $line) {
                $email = trim($line);
                if (!empty($email)) {
                    $emails[] = $email;
                }
            }
        }

        return $emails;
    }

    
	#[Get('api/codes/by-lab/{id<\d+>}', name: 'api_invitation_codes_by_lab')]
    public function fetchByLab(Request $request, int $id)
    {

        $lab = $this->labRepository->find($id);
        $this->denyAccessUnlessGranted(LabVoter::EDIT_CODE, $lab);
        
        $invitationCodes = $this->invitationCodeRepository->findBy(['lab'=> $lab]);

        if ('json' === $request->getRequestFormat()) {
            return $this->json($invitationCodes, 200, [], ["api_invitation_codes"]);
        }

    }

    
	#[Get('api/expiredToken/instances', name: 'api_expired_invitation_codes_instances')]
    public function fetchExpiredTokenInstances(Request $request)
    {  
        if ($_SERVER['REMOTE_ADDR'] != "127.0.0.1") {
            throw new AccessDeniedHttpException("Access denied.");
        }

        $invitationCodes = $this->invitationCodeRepository->findExpiredCodeInstances(new \DateTime());
        $guests = [];
        $deviceInstances = [];

        foreach ($invitationCodes as $invitationCode) {
            $isGuest = false;
            foreach($guests as $guest) {
                if ($guest['guest_id'] == $invitationCode['guest_id']) {
                    $isGuest = true;
                    array_push($guest['device_instances'], [
                        'device_instance_id'=> $invitationCode['device_instance_id'],
                        'device_instance_uuid'=> $invitationCode['device_instance_uuid'],
                        'hypervisor_id'=> $invitationCode['hypervisor_id']
                    ]);

                    $guests[$invitationCode['guest_id']] = $guest;
                    break;
                }
            }
            if ($isGuest == false) {
                $guests[$invitationCode["guest_id"]] = [
                    'guest_id' => $invitationCode["guest_id"],
                    'guest_uuid' => $invitationCode["guest_uuid"],
                    'code' => $invitationCode["code"],
                    'lab_instance_id' => $invitationCode["lab_instance_id"],
                    'lab_instance_uuid' => $invitationCode["lab_instance_uuid"],
                    'device_instances' => [[
                        'device_instance_id' => $invitationCode['device_instance_id'],
                        'device_instance_uuid' => $invitationCode['device_instance_uuid'],
                        'hypervisor_id' => $invitationCode['hypervisor_id']
                    ]
                ]];
                
            }
        }

        if ('json' === $request->getRequestFormat()) {
            return $this->json($guests, 200, [], []);
        }

    }

    
	#[Get('api/expiredToken', name: 'api_expired_invitation_codes')]
    public function fetchExpiredToken(Request $request)
    {
        if ($_SERVER['REMOTE_ADDR'] != "127.0.0.1") {
            throw new AccessDeniedHttpException("Access denied.");
        }

        $invitationCodes = $this->invitationCodeRepository->findExpiredCodes(new \DateTime());

        if ('json' === $request->getRequestFormat()) {
            return $this->json($invitationCodes, 200, [], []);
        }

    }

    
	#[Delete('api/codes/{uuid}', name: 'api_delete_invitation_codes', requirements: ["uuid"=>"[[:xdigit:]]{8}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{12}"])]
    public function deleteCodeAction(Request $request, string $uuid)
    {
        $invitationCode = $this->invitationCodeRepository->findBy(['uuid'=>$uuid]);

        if ($_SERVER['REMOTE_ADDR'] != "127.0.0.1") {
            $this->denyAccessUnlessGranted(LabVoter::EDIT_CODE, $invitationCode[0]->getLab());
        }

        $entityManager = $this->entityManager;
        $this->logger->info("Code for user ".$invitationCode[0]->getMail()." and lab ".$invitationCode[0]->getLab()->getName()." is deleted");
        $entityManager->remove($invitationCode[0]);
        $entityManager->flush();

        return $this->json();

    }

    public function checkAction($data, $lab) {

        $validatedEmails = [];
        $badEmails = [];
        $emailConstraint = new EmailConstraint();
        foreach($data['emailAdresses'] as $address) {
            $errors = $this->validator->validate($address, $emailConstraint);
            if (count($errors) == 0) {

                array_push($validatedEmails, $address);
            }
            else {

                array_push($badEmails, $address);
                $errorsString = (string) $errors;
                $this->logger->debug($address. ' n\'est pas valide');
            }
        }
        if (count($data['emailAdresses']) != count($validatedEmails)) {
            $this->logger->debug("Some addresses are not valid: ".print_r($badEmails, true));
            $this->addFlash('warning','Certaines adresses ne sont pas valides');
        }
        $this->sendMail($validatedEmails, $lab, $data['duration']);

        return count($validatedEmails);
    }

    public function sendMail($validatedEmails, $lab, $duration) {
        
        foreach($validatedEmails as $email) {
            $code = $this->registerCode($email, $lab, $duration);
        
            if ($code !== null) {
                $emailToSend = (new Email)
                ->from($this->getParameter('app.general.contact_mail'))
                ->to($email)
                ->subject($this->getParameter('app.general.public_address')." Access code for lab ". $lab->getName())
                ->html(
                    $this->renderView(
                        'emails/invitation.html.twig',
                        [
                            'labName' => $lab->getName(),
                            'link' => $this->generateUrl('code_login', [],  UrlGeneratorInterface::ABSOLUTE_URL),
                            'code' => $code
                        ]
                    )
                );
            
                try {
                    $this->mailer->send($emailToSend);
                    $this->logger->info("Invitation mail send to ".$email);
                } catch(TransportExceptionInterface $e) {
                    $this->logger->error("Send mail problem :". $e->getMessage());
                }
            }
            
        }
    }

    public function generateCode() {
        $n = 8;
        $possibleLetters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $code = '';

        for ($i = 0; $i < $n; $i++) {
            $index = rand(0, strlen($possibleLetters) - 1);
            $code .= $possibleLetters[$index];
        }

        return $code;
    }

    /**
     * Enregistre (ou renouvelle) le code d'invitation d'un mail pour un lab.
     *
     * Si un code existe déjà pour ce mail et ce lab, on conserve le même code
     * (l'utilisateur garde celui qu'il a déjà reçu) et on met simplement à jour
     * sa date d'expiration, même si le précédent code était expiré.
     *
     * @return string|null Le code à envoyer, ou null si l'enregistrement a échoué.
     */
    public function registerCode($email, $lab, $duration) {
        $entityManager = $this->entityManager;

        $invitationCodes = $this->invitationCodeRepository->findBy(['lab'=>$lab, 'mail'=> $email]);

        if (!$invitationCodes) {
            $invitation = new InvitationCode();
            $invitation->setCode($this->generateCode());
            $invitation->setMail($email);
            $invitation->setLab($lab);
            $invitation->setExpiryDate($duration);
            $entityManager->persist($invitation);
            $entityManager->flush();

            return $invitation->getCode();
        }

        // Un code existe déjà pour ce mail : on le réutilise et on renouvelle
        // uniquement sa date d'expiration.
        $invitation = $invitationCodes[0];
        $invitation->setExpiryDate($duration);
        $entityManager->flush();

        $this->logger->info(
            $email." already has a code for lab ".$lab->getId()."/".$lab->getName().
            ", reusing it and renewing its expiry date"
        );

        return $invitation->getCode();
    }
}
