<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\AddUserType;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class UserController extends AbstractController implements WebController
{
    public $passwordEncoder;

    public function __construct(UserPasswordEncoderInterface $passwordEncoder)
    {
        $this->passwordEncoder = $passwordEncoder;
    }

    /**
     * @Route("/admin/users", name="users")
     * 
     * @param $request The request
     * 
     */
    public function index(Request $request)
    {
        $user = new User();

        $addUserForm = $this->createForm(AddUserType::class, $user);
        $addUserFromFileForm = $this->createFormBuilder([])
            ->add('file', FileType::class)
            ->add('submit', SubmitType::class)
            ->getForm();

        $addUserForm->handleRequest($request);
        $addUserFromFileForm->handleRequest($request);

        if ($addUserForm->isSubmitted() && $addUserForm->isValid())
        {
            $user = $addUserForm->getData();
            $user->setPassword( $this->passwordEncoder->encodePassword($user, $user->getPassword()) );

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'User has been created.');
        }
        else if ($addUserFromFileForm->isSubmitted() && $addUserFromFileForm->isValid())
        {
            $file = $addUserFromFileForm->getData()['file'];

            $fileExtension = strtolower($file->getClientOriginalExtension());

            if (in_array($fileExtension, ['csv']))
            {
                $fileSocket = fopen($file, 'r');

                $addedUsers = [];

                switch ($fileExtension) {
                    case 'csv':
                        $addedUsers = $this->createUserFromCSV($fileSocket);
                        break;
                }

                if (count($addedUsers) > 0) {
                    $this->addFlash('success', 'Utilisateur(s) créé(s).');
                } else {
                    $this->addFlash('warning', 'Aucun utilisateur créé. Veuillez vérifier que les utilisateurs spécifiés dans le fichier n\'existent pas déjà.');
                }
                
                fclose($fileSocket);
            } else {
                $this->addFlash('danger', "Ce type de fichier n'est pas accepté.");
            }
        }

        return $this->render('user/index.html.twig', [
            'addUserForm' => $addUserForm->createView(),
            'addUserFromFileForm' => $addUserFromFileForm->createView(),
        ]);
    }

    /**
     * Format du tableau :
     * Nom,Prénom,Mail
     * @return The number of elements added
     */
    public function createUserFromCSV($file)
    {
        $line = array();
        $i = 0;
        $addedUsers = array();

        $repository = $this->getDoctrine()->getRepository('App:User');
        $entityManager = $this->getDoctrine()->getManager();
        
        while ($line[$i] = fgets($file, 4096)) {
            $line[$i] = str_replace('"', '', $line[$i]);

            $data = array();
            $data = explode(",", $line[$i]);

            $user = new User();

            $user->setLastName($data[0]);
            $user->setFirstName($data[1]);
            $user->setEmail(trim($data[2])); // trim newline because this is the last field

            if ( $repository->findByEmail(trim($data[2])) == null ) 
            {
                $user->setPassword( $this->passwordEncoder->encodePassword($user, \random_bytes(10)) );
                $entityManager->persist($user);

                $addedUsers[$i] = $user;
            }
            
            $i++;
        }

        $entityManager->flush();

        return $addedUsers;
    }
}
