<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;

use App\Controller\AppController;
use JMS\Serializer\SerializerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

/**
 * @IsGranted("ROLE_ADMINISTRATOR")
 */
class UserController extends AppController
{
    public $passwordEncoder;

    public function __construct(UserPasswordEncoderInterface $passwordEncoder)
    {
        $this->passwordEncoder = $passwordEncoder;
    }

    /**
     * @Route("/admin/users", name="users", methods={"GET", "POST"})
     */
    public function indexAction(Request $request)
    {
        $user = new User();

        $addUserForm = $this->createForm(UserType::class, $user);
        $addUserFromFileForm = $this->createFormBuilder([])
            ->add('file', FileType::class)
            ->add('submit', SubmitType::class)
            ->getForm();

        $addUserForm->handleRequest($request);
        $addUserFromFileForm->handleRequest($request);

        if ($addUserForm->isSubmitted() && $addUserForm->isValid()) {
            $user = $addUserForm->getData();
            $user->setPassword($this->passwordEncoder->encodePassword($user, $user->getPassword()));

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'User has been created.');
        } elseif ($addUserFromFileForm->isSubmitted() && $addUserFromFileForm->isValid()) {
            $file = $addUserFromFileForm->getData()['file'];

            $fileExtension = strtolower($file->getClientOriginalExtension());

            if (in_array($fileExtension, ['csv'])) {
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
     * @Route("/users", name="get_users", methods="GET")
     */
    public function cgetAction()
    {
        $repository = $this->getDoctrine()->getRepository('App:User');

        $data = $repository->findAll();

        return $this->json($data);
    }

    /**
     * @Route("/users/{id<\d+>}", name="get_user", methods="GET")
     */
    public function getAction($id)
    {
        $repository = $this->getDoctrine()->getRepository('App:User');

        $user = $repository->find($id);

        if ($user == null) {
            throw new NotFoundHttpException('This user does not exist.');
        }

        return $this->json($user);
    }

    /**
     * @Route("/users/{id<\d+>}/toggle", name="toggle_user", methods="PATCH")
     */
    public function toggleAction($id)
    {
        $repository = $this->getDoctrine()->getRepository('App:User');

        $data = [];

        $user = $repository->find($id);

        if ($user == null) {
            throw new NotFoundHttpException('This user does not exist.');
        } else {
            $user->setEnabled(!$user->isEnabled());

            $em = $this->getDoctrine()->getManager();
            $em->persist($user);
            $em->flush();

            $data['message'] = 'User has been ' . ($user->isEnabled() ? 'enabled' : 'disabled') . '.';
        }

        return $this->json($data);
    }

    /**
     * @Route("/users/{id<\d+>}", name="delete_user", methods="DELETE")
     */
    public function deleteAction($id)
    {
        $repository = $this->getDoctrine()->getRepository('App:User');
        
        $status = 200;
        $data = [];
        
        $user = $repository->find($id);

        if ($user == null) {
            $status = 404;
        } elseif ($user->hasRole('ROLE_SUPER_ADMINISTRATOR')) {
            // Prevent super admin deletion
            $status = 403;
        } else {
            $em = $this->getDoctrine()->getManager();
            $em->remove($user);
            $em->flush();

            $data['message'] = 'User has been deleted.';
        }

        return $this->json($data, $status);
    }

    /**
     * @Route("/users/me", name="get_user_current", methods="GET")
     */
    public function meAction()
    {
        $user = $this->getUser();

        return $this->json($user);
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

            if ($repository->findByEmail(trim($data[2])) == null) {
                $user->setPassword($this->passwordEncoder->encodePassword($user, \random_bytes(10)));
                $entityManager->persist($user);

                $addedUsers[$i] = $user;
            }
            
            $i++;
        }

        $entityManager->flush();

        return $addedUsers;
    }
}
