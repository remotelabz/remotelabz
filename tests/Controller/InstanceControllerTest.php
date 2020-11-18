<?php

namespace App\Tests\Controller;

use App\Entity\Lab;
use App\Entity\User;
use App\Entity\LabInstance;
use Psr\Log\LoggerInterface;
use App\Entity\DeviceInstance;
use App\Instance\InstanceState;
use Remotelabz\Message\Message\InstanceStateMessage;
use Doctrine\ORM\EntityManagerInterface;
use App\MessageHandler\InstanceStateMessageHandler;

class InstanceControllerTest extends AuthenticatedWebTestCase
{
    /** @var string */
    private $labUuid;

    /** @var string */
    private $userUuid;

    /** @var EntityManagerInterface */
    private $entityManager;

    /** @var InstanceStateMessageHandler */
    private $handler;

    /** @var LoggerInterface */
    private $logger;

    public function setUp(): void
    {
        parent::setUp();
        $this->entityManager = static::bootKernel()->getContainer()->get('doctrine')->getManager();

        /** @var LoggerInterface */
        $this->logger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->handler = new InstanceStateMessageHandler(
            $this->entityManager->getRepository(DeviceInstance::class),
            $this->entityManager->getRepository(LabInstance::class),
            $this->entityManager,
            $this->logger
        );
    }

    public function testCreateLabInstance()
    {
        /** @var User */
        $user = $this->entityManager
            ->getRepository(User::class)
            ->find(1);

        $this->assertInstanceOf(User::class, $user);

        $this->userUuid = $user->getUuid();

        /** @var Lab */
        $lab = $this->entityManager
            ->getRepository(Lab::class)
            ->find(1);

        $this->assertInstanceOf(Lab::class, $lab);

        $this->labUuid = $lab->getUuid();

        $this->client->request(
            'POST',
            '/api/instances/create',
            [
                'lab' => $this->labUuid,
                'instancier' => $this->userUuid,
                'instancierType' => 'user',
            ]
        );

        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $labInstance = $this->entityManager
            ->getRepository(LabInstance::class)
            ->find($data['id']);

        return $labInstance;
    }

    // TODO test start and stop requests
    // /**
    //  * @depends testCreateLabInstance
    //  * @param LabInstance $labInstance
    //  */
    // public function testStartDeviceInstance($labInstance)
    // {
    //     $this->client->request('GET', '/api/instances/start/by-uuid/' . $labInstance->getDeviceInstances()->first()->getUuid());
    //     $this->assertResponseIsSuccessful();

    //     $message = new InstanceStateMessage(InstanceStateMessage::TYPE_LAB, $labInstance->getUuid(), InstanceState::STARTED);

    //     $this->handler->__invoke($message);

    //     return $labInstance;
    // }

    // /**
    //  * @depends testStartLabInstance
    //  * @param LabInstance $labInstance
    //  */
    // public function testStopDeviceInstance($labInstance)
    // {
    //     $this->client->request('GET', '/api/instances/stop/by-uuid/' . $labInstance->getDeviceInstances()->first()->getUuid());
    //     $this->assertResponseIsSuccessful();

    //     $message = new InstanceStateMessage(InstanceStateMessage::TYPE_LAB, $labInstance->getUuid(), InstanceState::STOPPED);

    //     $this->handler->__invoke($message);

    //     return $labInstance;
    // }

    /**
     * @depends testCreateLabInstance
     *
     * @param LabInstance $labInstance
     */
    public function testDeleteLabInstance($labInstance)
    {
        $this->client->request('DELETE', '/api/instances/'.$labInstance->getUuid());
        $this->assertResponseIsSuccessful();

        $message = new InstanceStateMessage(InstanceStateMessage::TYPE_LAB, $labInstance->getUuid(), InstanceState::DELETED);

        $this->handler->__invoke($message);
    }
}
