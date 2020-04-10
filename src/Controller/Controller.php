<?php

namespace App\Controller;

use JMS\Serializer\SerializerInterface;
use JMS\Serializer\SerializationContext;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use FOS\RestBundle\Controller\AbstractFOSRestController;

/**
 * Provides custom JSON handling using JMSSerializer.
 *
 * @author Julien Hubert <julien.hubert@outlook.com>
 */
class Controller extends AbstractFOSRestController
{
    public static function getSubscribedServices()
    {
        return array_merge([
            'jms_serializer' => '?' . SerializerInterface::class,
        ], parent::getSubscribedServices());
    }

    /**
     * Returns a JsonResponse that uses JMSSerializer component.
     */
    protected function json($data = '', int $status = 200, array $headers = [], array $context = []): JsonResponse
    {
        $serializationContext = SerializationContext::create();

        if (empty($data) && $status < 300) {
            $status = 204;
        }

        if (!empty($context)) {
            $serializationContext->setGroups($context);
        }

        $data = $this->container->get('jms_serializer')->serialize($data, 'json', $serializationContext);

        return new JsonResponse($data, $status, $headers, true);
    }

    /**
     * @Route("/", name="index")
     */
    public function defaultAction()
    {
        return $this->redirectToRoute('labs');
    }
}
