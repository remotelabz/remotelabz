<?php

namespace App\Controller;

use App\Form\MailType;
use Symfony\Component\Routing\Annotation\Route;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints\Email as EmailConstraint;
use Psr\Log\LoggerInterface;

class MailController extends Controller
{
    private $mailer;
    private $validator;

    /** @var LoggerInterface $logger */
    private $logger;

    public function __construct(
        MailerInterface $mailer,
        ValidatorInterface $validator,
        LoggerInterface $logger)
    {
        $this->mailer = $mailer;
        $this->validator = $validator;
        $this->logger = $logger;
    }

    /**
     * @Route("/admin/mail/", name="admin_write_mail")
     */
    public function indexAction(Request $request) {

        $mailForm = $this->createForm(MailType::class);
        $mailForm->handleRequest($request);

        if ($request->getContentType() === 'json') {
            $mail = json_decode($request->getContent(), true);
            $mailForm->submit($mail, false);
        }

        if ($mailForm->isSubmitted() && $mailForm->isValid()) {
            $mail = $mailForm->getData();
            $this->checkAction($mail);
        }

        return $this->render('mail/index.html.twig', ['form'=>$mailForm->createView()]);
    }


    public function checkAction($mail) {

        $toAdresses = [];
        $badAdresses = [];
        $emailConstraint = new EmailConstraint();
        foreach($mail['to'] as $toAdresse) {

            $errors = $this->validator->validate($toAdresse, $emailConstraint);
            if (count($errors) == 0) {

                array_push($toAdresses, $toAdresse);
            }
            else {

                array_push($badAdresses, $toAdresse);
                $errorsString = (string) $errors;
                var_dump($errorsString);
            }
        }

        if (count($mail['to']) != count($toAdresses)) {
            $this->logger->debug("Some addresses are not valid: ".print_r($badAdresses, true));
        }

        /*$ccAdresses = [];
        foreach($mail['cc'] as $ccAdresse) {

            $errors = $this->validator->validate($ccAdresse, $emailConstraint);
            if (count($errors) == 0) {

                array_push($ccAdresses, $ccAdresse);
            }
        }

        $bccAdresses = [];
        foreach($mail['cci'] as $bccAdresse) {

            $errors = $this->validator->validate($bccAdresse, $emailConstraint);
            if (count($errors) == 0) {

                array_push($bccAdresses, $bccAdresse);
            }
        }*/

        $checkedEmail = [
            'to' => $toAdresses,
            /*'cc' => $ccAdresses,
            'cci' => $bccAdresses,*/
            'subject' => $mail['subject'],
            'content' => $mail['content']
        ];

        $this->sendMail($checkedEmail);
        
    }

    public function sendMail($mail) {

        //var_dump($mail['to']);exit;
        $emailToSend = (new Email())
            ->from('test@exemple.org')
            ->to(...$mail['to']);
        /*if ($mail['cc']) {
            $emailToSend->cc($mail['cc']);
        }
        if ($mail['cci']) {
            $emailToSend->bcc($mail['cci']);
        }*/
        $emailToSend->subject($mail['subject'])
            ->html('<p>'.$mail['content'].'</p>');

        $this->mailer->send($emailToSend);
    }
}