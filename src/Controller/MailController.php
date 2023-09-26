<?php

namespace App\Controller;

use App\Form\MailType;
use Symfony\Component\Routing\Annotation\Route;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class MailController extends Controller
{
    private $mailer;
    public function __construct(
        MailerInterface $mailer)
    {
        $this->mailer = $mailer;
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
            $this->sendMail($mail);
        }

        return $this->render('mail/index.html.twig', ['form'=>$mailForm->createView()]);
    }

    public function sendMail($mail) {

        $emailToSend = (new Email())
            ->from('test@exemple.org')
            ->to($mail['to']);
        if ($mail['cc']) {
            $emailToSend->cc($mail['cc']);
        }
        if ($mail['cci']) {
            $emailToSend->bcc($mail['cci']);
        }
        $emailToSend->subject($mail['subject'])
            ->html('<p>'.$mail['content'].'</p>');

        $this->mailer->send($emailToSend);
    }
}