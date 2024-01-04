<?php

namespace App\Controller;

use App\Repository\LabRepository;
use App\Repository\InvitationCodeRepository;
use App\Entity\InvitationCode;
use App\Form\InvitationCodeType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\Validator\Constraints\Email as EmailConstraint;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;

class InvitationController extends Controller
{
    /** @var LoggerInterface $logger */
    private $logger;

    public function __construct(
        LabRepository $labRepository,
        ValidatorInterface $validator,
        LoggerInterface $logger,
        MailerInterface $mailer,
        InvitationCodeRepository $invitationCodeRepository)
    {
        $this->labRepository = $labRepository;
        $this->validator = $validator;
        $this->logger = $logger;
        $this->mailer = $mailer;
        $this->invitationCodeRepository = $invitationCodeRepository;
    }

    /**
    * @Route("/labs/code/{id<\d+>}", name="create_code_lab")
    * 
    */
    public function createCodeAction(Request $request, int $id, SerializerInterface $serializer)
    {

        $lab = $this->labRepository->find($id);
        $invitationForm = $this->createForm(InvitationCodeType::class);
        $invitationForm->handleRequest($request);
        
        if ($request->getContentType() === 'json') {
            $adresses = json_decode($request->getContent(), true);
            $invitationForm->submit($adresses, false);
        }

        if ($invitationForm->isSubmitted() && $invitationForm->isValid()) {
            $data = $invitationForm->getData();
            $this->checkAction($data, $lab);
            unset($invitationForm);
            $invitationForm = $this->createForm(InvitationCodeType::class);
        }

        $props=$serializer->serialize(
            ['lab' => $lab],
            'json',
            SerializationContext::create()->setGroups(['api_get_lab'])
        );

        return $this->render('lab/create_code_lab.html.twig', [
            'lab' => $lab,
            'form'=> $invitationForm->createView(),
            'props' =>$props

        ]);
    }

    /**
    * @Rest\Get("api/codes/by-lab/{id<\d+>}", name="api_invitation_codes_by_lab")
    * 
    */
    public function fetchByLab(Request $request, int $id)
    {

        $lab = $this->labRepository->find($id);
        $invitationCodes = $this->invitationCodeRepository->findBy(['lab'=> $lab]);

        if ('json' === $request->getRequestFormat()) {
            return $this->json($invitationCodes, 200, [], ["api_invitation_codes"]);
        }

    }

    /**
    * @Rest\Get("api/expiredToken/instances", name="api_expired_invitation_codes_instances")
    * 
    */
    public function fetchExpiredTokenInstances(Request $request)
    {
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

    /**
    * @Rest\Get("api/expiredToken", name="api_expired_invitation_codes")
    * 
    */
    public function fetchExpiredToken(Request $request)
    {
        $invitationCodes = $this->invitationCodeRepository->findExpiredCodes(new \DateTime());

        if ('json' === $request->getRequestFormat()) {
            return $this->json($invitationCodes, 200, [], []);
        }

    }

    /**
    * @Rest\Delete("api/codes/{uuid}", name="api_delete_invitation_codes", requirements={"uuid"="[[:xdigit:]]{8}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{12}"})
    * 
    */
    public function deleteCodeAction(Request $request, string $uuid)
    {
        $invitationCode = $this->invitationCodeRepository->findBy(['uuid'=>$uuid]);
        $entityManager = $this->getDoctrine()->getManager();
        $this->logger->info("User ".$invitationCode[0]->getMail()." is deleted");
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
    }

    public function sendMail($validatedEmails, $lab, $duration) {
        
        foreach($validatedEmails as $email) {
            $code = $this->generateCode();
            $isCodeRegistered = $this->registerCode($email, $lab, $code, $duration);
        
            if ($isCodeRegistered == true) {
                $emailToSend = (new Email)
                ->from($this->getParameter('app.general.contact_mail'))
                ->to($email)
                ->html(
                    $this->renderView(
                        'emails/invitation.html.twig',
                        [
                            'labName' => $lab->getName(),
                            'link' => $this->generateUrl('login', [],  UrlGeneratorInterface::ABSOLUTE_URL),
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

    public function registerCode($email, $lab, $code, $duration) {
        $entityManager = $this->getDoctrine()->getManager();

        $invitationCode = $this->invitationCodeRepository->findBy(['lab'=>$lab, 'mail'=> $email]);

        if (!$invitationCode) {
            $invitation = new InvitationCode();
            $invitation->setCode($code);
            $invitation->setMail($email);
            $invitation->setLab($lab);
            $invitation->setExpiryDate(new \DateTime('@'.strtotime('+'.$duration['hour'].' hours '.$duration['minute'].' minutes')));
            $entityManager->persist($invitation);
            $entityManager->flush();

            return true;
        }
        else {
            $this->logger->error( $email. " is already registered for the lab" .$lab->getId()."/".$lab->getName());
            return false;
        }
    }
}
