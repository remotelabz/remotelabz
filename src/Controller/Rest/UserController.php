<?php

namespace App\Controller\Rest;

use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Controller\Annotations\Version;

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

        $view = $this->view(null, 200);

        $user = $repository->find($id);

        if ($user != null) {
            $user->setEnabled(!$user->isEnabled());

            $em = $this->getDoctrine()->getManager();
            $em->persist($user);
            $em->flush();

            $data = array('user' => $user);

            $view->setData($data);
        }
        else {
            $view->setStatusCode(404);
        }

        return $this->handleView($view);
    }

    public function deleteAction($id)
    {
        $repository = $this->getDoctrine()->getRepository('App:User');

        $view = $this->view(null, 200);
        
        $user = $repository->find($id);

        if ($user != null) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($user);
            $em->flush();
        }
        else {
            $view->setStatusCode(404);
        }

        return $this->handleView($view);
    }
}
