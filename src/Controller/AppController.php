<?php

namespace App\Controller;

use JMS\Serializer\SerializerBuilder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Provides common need features and custom JSON handling using JMSSerializer.
 *
 * @author Julien Hubert <julien.hubert@outlook.com>
 */
class AppController extends AbstractController
{
    final protected function json($data, int $status = 200, array $headers = [], array $context = []): JsonResponse
    {
        $serializer = SerializerBuilder::create()->build();

        $data = $serializer->serialize($data, 'json');

        $headers['Content-Type'] = 'application/json';

        return new JsonResponse($data, $status, $headers, true);
    }
}
