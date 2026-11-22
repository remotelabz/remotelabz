<?php

namespace App\Tests\Controller;

use App\Entity\Device;
use App\Entity\Lab;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * The sandbox only lists labs and device templates the current user is
 * allowed to modify. Showing another author's lab used to lead to an
 * access-denied redirect when clicking "Modify".
 */
class DeviceSandboxControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private ?User $teacher = null;
    private ?User $otherTeacher = null;
    private ?Lab $ownLab = null;
    private ?Lab $foreignLab = null;
    private ?Device $sharedDevice = null;
    private ?Device $foreignDevice = null;

    public function setUp(): void
    {
        parent::setUp();
        $this->client = self::createClient();
    }

    public function tearDown(): void
    {
        $em = $this->entityManager();
        $em->clear();

        foreach ([$this->ownLab, $this->foreignLab] as $lab) {
            if (null !== $lab) {
                $managed = $em->getRepository(Lab::class)->find($lab->getId());
                if (null !== $managed) {
                    $em->remove($managed);
                }
            }
        }

        foreach ([$this->sharedDevice, $this->foreignDevice] as $device) {
            if (null !== $device) {
                $managed = $em->getRepository(Device::class)->find($device->getId());
                if (null !== $managed) {
                    $em->remove($managed);
                }
            }
        }

        foreach ([$this->teacher, $this->otherTeacher] as $user) {
            if (null !== $user) {
                $managed = $em->getRepository(User::class)->find($user->getId());
                if (null !== $managed) {
                    $em->remove($managed);
                }
            }
        }

        $em->flush();

        $this->teacher = null;
        $this->otherTeacher = null;
        $this->ownLab = null;
        $this->foreignLab = null;
        $this->sharedDevice = null;
        $this->foreignDevice = null;

        parent::tearDown();
    }

    public function testOnlyListsLabsAndDevicesTheUserMayModify(): void
    {
        $em = $this->entityManager();
        $root = $em->getRepository(User::class)->findOneBy(['email' => 'root@localhost']);
        self::assertNotNull($root, 'The root user fixture is required.');

        $suffix = bin2hex(random_bytes(4));

        $this->teacher = $this->createUser('sandbox-teacher-' . $suffix . '@localhost');
        $this->otherTeacher = $this->createUser('sandbox-other-' . $suffix . '@localhost');

        $this->ownLab = (new Lab())
            ->setName('Sandbox Own Lab ' . $suffix)
            ->setAuthor($this->teacher)
            ->setIsTemplate(true);
        $this->foreignLab = (new Lab())
            ->setName('Sandbox Foreign Lab ' . $suffix)
            ->setAuthor($root)
            ->setIsTemplate(true);

        $this->sharedDevice = $this->createDevice('Sandbox Shared Device ' . $suffix, $root);
        $this->foreignDevice = $this->createDevice('Sandbox Foreign Device ' . $suffix, $this->otherTeacher);

        $em->persist($this->ownLab);
        $em->persist($this->foreignLab);
        $em->persist($this->sharedDevice);
        $em->persist($this->foreignDevice);
        $em->flush();

        $this->client->loginUser($this->teacher, 'default');
        $this->client->request('GET', '/admin/sandbox');

        $this->assertResponseIsSuccessful();
        $content = $this->client->getResponse()->getContent();

        // The teacher may modify their own template.
        $this->assertStringContainsString($this->ownLab->getName(), $content);
        // Administrator-owned device templates are deliberately shared.
        $this->assertStringContainsString($this->sharedDevice->getName(), $content);

        // Another author's templates must not be advertised: clicking Modify
        // on them would be denied by LabVoter::EDIT.
        $this->assertStringNotContainsString($this->foreignLab->getName(), $content);
        $this->assertStringNotContainsString($this->foreignDevice->getName(), $content);
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get('doctrine')->getManager();
    }

    private function createUser(string $email): User
    {
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $user = (new User())
            ->setEmail($email)
            ->setLastName('Sandbox')
            ->setFirstName('Teacher')
            ->setEnabled(true)
            ->setRoles(['ROLE_TEACHER']);
        $user->setPassword($hasher->hashPassword($user, 'SandboxTest123!'));

        $em = $this->entityManager();
        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function createDevice(string $name, User $author): Device
    {
        return (new Device())
            ->setName($name)
            ->setType('vm')
            ->setVirtuality(1)
            ->setNbCpu(1)
            ->setIsTemplate(true)
            ->setAuthor($author);
    }
}
