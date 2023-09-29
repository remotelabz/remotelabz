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
    public function createCodeAction(Request $request, int $id)
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
        }

        return $this->render('lab/create_code_lab.html.twig', [
            'lab' => $lab,
            'form'=> $invitationForm->createView()
        ]);
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
        $this->sendMail($validatedEmails, $lab);
    }

    public function sendMail($validatedEmails, $lab) {
        
        foreach($validatedEmails as $email) {
            $code = $this->generateCode();
            $isCodeRegistered = $this->registerCode($email, $lab, $code);
        
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

    public function registerCode($email, $lab, $code) {
        $entityManager = $this->getDoctrine()->getManager();

        $invitationCode = $this->invitationCodeRepository->findBy(['lab'=>$lab, 'mail'=> $email]);

        if (!$invitationCode) {
            $invitation = new InvitationCode();
            $invitation->setCode($code);
            $invitation->setMail($email);
            $invitation->setLab($lab);
            $invitation->setExpiryDate(new \DateTime('@'.strtotime('+4 hours')));
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
