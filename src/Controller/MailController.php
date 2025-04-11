<?php

namespace App\Controller;

use App\Form\MailType;
use Symfony\Component\Routing\Annotation\Route;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\Post;
use FOS\RestBundle\Controller\Annotations\Put;
use FOS\RestBundle\Controller\Annotations\Patch;
use FOS\RestBundle\Controller\Annotations\Delete;
use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Controller\Annotations\Route as RestRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints\Email as EmailConstraint;
use Psr\Log\LoggerInterface;
use App\Repository\UserRepository;

class MailController extends Controller
{
    private $mailer;
    private $validator;

    /** @var LoggerInterface $logger */
    private $logger;

    public function __construct(
        MailerInterface $mailer,
        ValidatorInterface $validator,
        LoggerInterface $logger
        )
    {
        $this->mailer = $mailer;
        $this->validator = $validator;
        $this->logger = $logger;
    }

    #[Route(path: '/admin/mail/', name: 'admin_write_mail')]
    public function indexAction(Request $request, UserRepository $userRepository) {

        $mailForm = $this->createForm(MailType::class);
        $mailForm->handleRequest($request);

        if ($request->getContentType() === 'json') {
            $mail = json_decode($request->getContent(), true);
            $mailForm->submit($mail, false);
        }

        if ($mailForm->isSubmitted() && $mailForm->isValid()) {
            $mail = $mailForm->getData();
            $this->checkAction($mail, $userRepository);
        }

        return $this->render('mail/index.html.twig', ['form'=>$mailForm->createView()]);
    }

    public function checkAction($mail, UserRepository $userRepository) {

        $bccAdresses = [];
        $badAdresses = [];
        $emailConstraint = new EmailConstraint();

        if(count($mail['to']) == 1 && $mail['to'][0] == "All users") {

            $bccAdresses = [];
            $users = $userRepository->findAll();

            foreach($users as $user){

                $errors = $this->validator->validate($user->getEmail(), $emailConstraint);
                if (count($errors) == 0) {

                    array_push($bccAdresses, $user->getEmail());
                }
                else {

                    array_push($badAdresses, $user->getEmail());
                    $errorsString = (string) $errors;
                }
            }

            if (count($users) != count($bccAdresses)) {
                $this->logger->debug("Some addresses are not valid: ".print_r($badAdresses, true));
                $this->addFlash('warning','Certaines adresses de sont pas valides');
            }

        }
        else {
            foreach($mail['to'] as $bccAdresse) {

                $errors = $this->validator->validate($bccAdresse, $emailConstraint);
                if (count($errors) == 0) {
    
                    array_push($bccAdresses, $bccAdresse);
                }
                else {
    
                    array_push($badAdresses, $bccAdresse);
                    $errorsString = (string) $errors;
                }
            }
    
            if (count($mail['to']) != count($bccAdresses)) {
                $this->logger->debug("Some addresses are not valid: ".print_r($badAdresses, true));
                $this->addFlash('warning','Certaines adresses de sont pas valides');
            }
        }
        
        $checkedEmail = [
            'to' => [$this->getParameter('app.general.contact_mail')],
            'cci' => $bccAdresses,
            'subject' => $mail['subject'],
            'content' => $mail['content']
        ];

        $this->sendMail($checkedEmail);
        
    }

    public function sendMail($mail) {

        $emailToSend = (new Email())
            ->from($this->getParameter('app.general.contact_mail'))
            ->to(...$mail['to'])
            ->bcc(...$mail['cci']);

        $emailToSend->subject($mail['subject'])
            ->html('<p>'.$mail['content'].'</p>');

        $this->mailer->send($emailToSend);
        $this->addFlash('success','L\'email a été envoyé');
    }
}