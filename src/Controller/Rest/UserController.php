<?php

namespace App\Controller\Rest;

use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Controller\Annotations\Version;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

// To see how routes are automatically generated in FOSRestController, refer to :
// https://symfony.com/doc/master/bundles/FOSRestBundle/5-automatic-route-generation_single-restful-controller.html
class UserController extends FOSRestController implements ClassResourceInterface
{
    public function cgetAction()
    {
        $repository = $this->getDoctrine()->getRepository('App:User');

        $users = $repository->findAll();

        $data = array('users' => $users);

        $view = $this->view($data, 200);

        return $this->handleView($view);
    }

    public function toggleAction($id)
    {
        $repository = $this->getDoctrine()->getRepository('App:User');

        $code = 200;
        $data = [];

        $user = $repository->find($id);

        if ($user != null) {
            $user->setEnabled(!$user->isEnabled());

            $em = $this->getDoctrine()->getManager();
            $em->persist($user);
            $em->flush();

            $data['message'] = 'User has been ' . ($user->isEnabled() ? 'enabled' : 'disabled') . '.';
        }
        else {
            $code = 404;
        }

        $view = $this->view($data, $code);

        return $this->handleView($view);
    }

    /**
     * @IsGranted("ROLE_ADMINISTRATOR")
     */
    public function deleteAction($id)
    {
        $repository = $this->getDoctrine()->getRepository('App:User');
        
        $code = 200;
        $data = [];
        
        $user = $repository->find($id);

        if ($user == null) {
            $code = 404;
        }
        // Prevent super admin deletion
        else if ($user->hasRole('ROLE_SUPER_ADMINISTRATOR')) {
            $code = 403;
        }
        else {
            $em = $this->getDoctrine()->getManager();
            $em->remove($user);
            $em->flush();

            $data['message'] = 'User has been deleted.';
        }

        $view = $this->view($data, $code);

        return $this->handleView($view);
    }

    public function meAction()
    {
        $user = $this->getUser();

        $view = $this->view($user, 200);

        return $this->handleView($view);
    }
}
