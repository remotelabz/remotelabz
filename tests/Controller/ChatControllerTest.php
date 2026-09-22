<?php

namespace App\Tests\Controller;

use App\Entity\Group;
use App\Entity\Lab;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ChatControllerTest extends WebTestCase
{
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $client;
    private ?Lab $lab = null;
    private ?Group $group = null;
    private ?User $foreignUser = null;
    private ?Group $foreignGroup = null;
    private ?Lab $foreignLab = null;

    public function setUp(): void
    {
        parent::setUp();

        $this->client = self::createClient();

        $em = $this->entityManager();

        $this->group = $em->getRepository(Group::class)->findOneBy(['name' => 'Default group']);

        $author = $em->getRepository(User::class)->findOneBy(['email' => 'root@localhost']);

        $this->lab = (new Lab())
            ->setName('Chat Test Lab')
            ->setAuthor($author)
            ->setChatEnabled(true);
        $this->lab->addGroup($this->group);

        $em->persist($this->lab);
        $em->flush();
    }

    public function tearDown(): void
    {
        if (null !== $this->lab || null !== $this->foreignLab || null !== $this->foreignUser || null !== $this->foreignGroup) {
            $em = $this->entityManager();
            $em->clear();

            // Re-attach the entities to the current EntityManager before removal
            if (null !== $this->lab) {
                $em->remove($em->getRepository(Lab::class)->find($this->lab->getId()));
            }
            if (null !== $this->foreignLab) {
                $em->remove($em->getRepository(Lab::class)->find($this->foreignLab->getId()));
            }
            if (null !== $this->foreignUser) {
                $em->remove($em->getRepository(User::class)->find($this->foreignUser->getId()));
            }
            if (null !== $this->foreignGroup) {
                $em->remove($em->getRepository(Group::class)->find($this->foreignGroup->getId()));
            }
            $em->flush();
        }

        parent::tearDown();
    }

    private function entityManager()
    {
        return self::getContainer()->get('doctrine')->getManager();
    }

    private function getRootUser(): User
    {
        return $this->entityManager()->getRepository(User::class)->findOneBy(['email' => 'root@localhost']);
    }

    /**
     * Mints a stateless JWT for the given user (no session involved).
     */
    private function getJwtToken(User $user): string
    {
        $jwtManager = self::getContainer()->get('lexik_jwt_authentication.jwt_manager');

        return $jwtManager->create($user);
    }

    public function testRoomReturnsMembersAndSetsMercureCookie()
    {
        $token = $this->getJwtToken($this->getRootUser());

        // HTTPS: the mercureAuthorization cookie is Secure (BrowserKit, like a
        // real browser, only stores Secure cookies over https).
        $this->client->request('GET',
            'https://localhost/api/chat/' . $this->lab->getUuid() . '/room',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $this->assertResponseIsSuccessful();

        $cookie = $this->client->getCookieJar()->get('mercureAuthorization', '/mercure');
        $this->assertNotNull($cookie, 'The mercureAuthorization cookie must be set for the SSE subscription.');
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('chat/lab/' . $this->lab->getUuid() . '/group/' . $this->group->getUuid(), $data['topic']);
        $this->assertSame($this->group->getName(), $data['group']['name']);

        $memberUuids = array_column($data['members'], 'uuid');
        $root = $this->entityManager()->getRepository(User::class)->findOneBy(['email' => 'root@localhost']);
        $this->assertContains($root->getUuid(), $memberUuids);
    }

    public function testSendMessageAndHistory()
    {
        $token = $this->getJwtToken($this->getRootUser());

        $this->client->request('POST',
            '/api/chat/' . $this->lab->getUuid() . '/messages',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json'],
            json_encode(['message' => 'hello world'])
        );

        $this->assertResponseStatusCodeSame(201);

        $message = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('hello world', $message['message']);
        $this->assertNotEmpty($message['id']);

        $this->client->request('GET',
            '/api/chat/' . $this->lab->getUuid() . '/messages',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );
        $this->assertResponseIsSuccessful();

        $history = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(1, $history);
        $this->assertSame('hello world', $history[0]['message']);
    }

    public function testSendMessageRejectsEmptyMessage()
    {
        $token = $this->getJwtToken($this->getRootUser());

        $this->client->request('POST',
            '/api/chat/' . $this->lab->getUuid() . '/messages',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json'],
            json_encode(['message' => '   '])
        );

        $this->assertResponseStatusCodeSame(422);
    }

    public function testPresence()
    {
        $token = $this->getJwtToken($this->getRootUser());

        $this->client->request('POST',
            '/api/chat/' . $this->lab->getUuid() . '/presence',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json'],
            json_encode(['action' => 'join'])
        );

        $this->assertResponseIsSuccessful();
    }

    public function testUnknownLabIsNotFound()
    {
        $token = $this->getJwtToken($this->getRootUser());

        $this->client->request('GET',
            '/api/chat/00000000-0000-0000-0000-000000000000/room',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );
        $this->assertResponseStatusCodeSame(404);
    }

    public function testNonMemberUserIsDenied()
    {
        $em = $this->entityManager();

        $slug = 'chat-foreign-' . bin2hex(random_bytes(4));
        $this->foreignGroup = (new Group())
            ->setName('Chat Foreign Group')
            ->setSlug($slug)
            ->setVisibility(Group::VISIBILITY_INTERNAL);
        $em->persist($this->foreignGroup);

        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $this->foreignUser = (new User())
            ->setEmail('chat-nonmember-' . bin2hex(random_bytes(4)) . '@localhost')
            ->setLastName('Non')
            ->setFirstName('Member')
            ->setEnabled(true)
            ->setRoles(['ROLE_USER']);
        $this->foreignUser->setPassword($hasher->hashPassword($this->foreignUser, 'ChatTest123!'));
        $em->persist($this->foreignUser);

        $this->foreignLab = (new Lab())->setName('Chat Foreign Lab')->setChatEnabled(true);
        $this->foreignLab->addGroup($this->foreignGroup);
        $em->persist($this->foreignLab);
        $em->flush();

        $token = $this->getJwtToken($this->foreignUser);

        $this->client->request('GET',
            '/api/chat/' . $this->foreignLab->getUuid() . '/room',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );
        $this->assertResponseStatusCodeSame(403);
    }
}
