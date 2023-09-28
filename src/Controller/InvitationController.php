<?php

namespace App\Controller;

use App\Repository\LabRepository;
use App\Form\InvitationCodeType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\Validator\Constraints\Email as EmailConstraint;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Psr\Log\LoggerInterface;

class InvitationController extends Controller
{
    /** @var LoggerInterface $logger */
    private $logger;

    public function __construct(
        LabRepository $labRepository,
        ValidatorInterface $validator,
        LoggerInterface $logger)
    {
        $this->labRepository = $labRepository;
        $this->validator = $validator;
        $this->logger = $logger;
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
            $this->checkAction($data);
        }

        return $this->render('lab/create_code_lab.html.twig', [
            'lab' => $lab,
            'form'=> $invitationForm->createView()
        ]);
    }

    public function checkAction($data) {

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
                $this->addFlash('warning', $address. ' n\'est pas valide');
            }
        }

        if (count($data['emailAdresses']) != count($badEmails)) {
            $this->logger->debug("Some addresses are not valid: ".print_r($badEmails, true));
            $this->addFlash('warning','Certaines adresses ne sont pas valides');
        }
    }
}
