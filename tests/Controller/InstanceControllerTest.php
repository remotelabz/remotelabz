<?php

namespace App\Tests\Controller;

use App\Entity\Lab;
use App\Entity\User;
use App\Entity\LabInstance;
use App\Instance\InstanceState;
use App\Service\Worker\WorkerManager;
use Remotelabz\Message\Message\InstanceStateMessage;
use Doctrine\ORM\EntityManagerInterface;
use App\MessageHandler\InstanceStateMessageHandler;

class InstanceControllerTest extends AuthenticatedWebTestCase
{
    /** @var string */
    private $userUuid;

    /** @var Lab|null */
    private $lab;

    /** @var EntityManagerInterface */
    private $entityManager;

    /** @var InstanceStateMessageHandler */
    private $handler;

    public function setUp(): void
    {
        parent::setUp();
        $this->entityManager = static::getContainer()->get('doctrine')->getManager();

        // Le handler est recupere depuis le conteneur : sa liste d'arguments a
        // beaucoup evolue, la construire a la main dans le test n'est pas fiable.
        $this->handler = static::getContainer()->get(InstanceStateMessageHandler::class);
    }

    public function tearDown(): void
    {
        // Lab dedie au test : on le supprime meme si le test a echoue en cours
        // de route. Les LabInstance partent d'abord (pas de cascade depuis Lab).
        if (null !== $this->lab) {
            $em = static::getContainer()->get('doctrine')->getManager();

            foreach ($em->getRepository(LabInstance::class)->findBy(['lab' => $this->lab->getId()]) as $labInstance) {
                $em->remove($labInstance);
            }

            $lab = $em->getRepository(Lab::class)->find($this->lab->getId());
            if (null !== $lab) {
                $em->remove($lab);
            }
            $em->flush();

            $this->lab = null;
        }

        parent::tearDown();
    }

    public function testCreateLabInstance()
    {
        // Test de bout en bout : sans worker declare disponible,
        // InstanceManager::create() refuse la creation (400).
        if (!static::getContainer()->get(WorkerManager::class)->hasAvailableWorker()) {
            $this->markTestSkipped('No worker flagged as available: skipping the instance lifecycle smoke test.');
        }

        /** @var User */
        $user = $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['email' => 'root@localhost']);

        $this->assertInstanceOf(User::class, $user);

        $this->userUuid = $user->getUuid();

        // La base ne contient pas forcement de lab "id 1" : on cree un lab
        // jetable dont l'auteur est l'utilisateur connecte (droit "see").
        $this->lab = (new Lab())
            ->setName('Instance Controller Test Lab')
            ->setAuthor($user);
        $this->entityManager->persist($this->lab);
        $this->entityManager->flush();

        $this->client->request(
            'POST',
            '/api/instances/create',
            [
                'lab' => $this->lab->getUuid(),
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
