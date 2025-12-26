<?php

namespace App\Controller;

use JMS\Serializer\SerializerInterface;
use JMS\Serializer\SerializationContext;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use App\Service\Network\NetworkManager;

/**
 * Provides custom JSON handling using JMSSerializer.
 *
 * @author Julien Hubert <julien.hubert@outlook.com>
 */
class Controller extends AbstractFOSRestController
{
    public static function getSubscribedServices(): array
    {
        return array_merge([
            'jms_serializer' => '?' . SerializerInterface::class,
        ], parent::getSubscribedServices());
    }

    /**
     * Returns a JsonResponse that uses JMSSerializer component.
     */
    protected function json($data = '', int $status = 200, array $headers = [], array $context = [], bool $json = false): JsonResponse
    {
        $serializationContext = SerializationContext::create();
        
        if (null === $data) {
            $data = '';
        }

        if (empty($data) && $status < 300) {
            $status = 204;
        }

        if (!empty($context)) {
            $serializationContext->setGroups($context);
        }

        if (!$json) {
            $data = $this->container->get('jms_serializer')->serialize($data, 'json', $serializationContext);
        }

        return new JsonResponse($data, $status, $headers, true);
    }

    #[Route(path: '/', name: 'index')]
    public function defaultAction()
    {
        return $this->render('dashboard/index.html.twig');
    }

    #[Route(path: '/admin', name: 'admin')]
    public function adminAction()
    {
        return $this->render('dashboard/admin.html.twig');
    }

    #[Route(path: '/react/{reactRouting}', name: 'index_react', defaults: ['reactRouting' => 'null'])]
    public function defaultReactAction()
    {
        return $this->render('react.html.twig');
    }

}
