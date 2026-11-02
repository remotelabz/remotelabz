<?php

namespace App\Tests\Service;

use App\Entity\Lab;
use App\Entity\OperatingSystem;
use App\Entity\User;
use App\Service\Lab\LabImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * An imported lab (JSON export or EVE-NG .unl) must always belong to the user
 * who performs the import, never to another account.
 */
class LabImportAuthorTest extends WebTestCase
{
    private const JSON_LAB_NAME = 'Tmp Json Import Author Regression';
    private const EVE_LAB_NAME = 'Tmp Eve Import Author Regression';

    /**
     * The importer must use the explicitly provided author even when the
     * security token storage holds no authenticated user (regression: the lab
     * used to be attributed to whatever user the token storage happened to
     * contain).
     */
    public function testImporterUsesExplicitAuthorWithoutToken(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $importer = $container->get(LabImporter::class);

        $teacher = $this->teacher($em);

        try {
            $labId = $importer->import(json_encode([
                'name' => self::JSON_LAB_NAME,
                'virtuality' => 1,
                'devices' => [],
                'textobjects' => [],
                'pictures' => [],
                'shortDescription' => '',
                'description' => '',
            ]), $teacher);

            $lab = $em->getRepository(Lab::class)->find($labId);
            self::assertNotNull($lab);
            self::assertSame(
                $teacher->getId(),
                $lab->getAuthor()?->getId(),
                'The lab author must be the explicitly provided user.'
            );
        } finally {
            $this->deleteLab($em, self::JSON_LAB_NAME);
        }
    }

    /**
     * End-to-end JSON import through the API, authenticated with the same
     * JWT cookie the browser uses.
     */
    public function testJsonImportAuthorIsConnectedUser(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $teacher = $this->teacher($em);

        $jwt = $container->get('lexik_jwt_authentication.jwt_manager')->create($teacher);
        $client->getCookieJar()->set(new Cookie('bearer', $jwt));

        try {
            $json = json_encode([
                'name' => self::JSON_LAB_NAME,
                'virtuality' => 1,
                'devices' => [],
                'textobjects' => [],
                'pictures' => [],
                'shortDescription' => '',
                'description' => '',
            ]);

            $client->request('POST', '/api/labs/import', ['json' => $json]);
            self::assertSame(302, $client->getResponse()->getStatusCode());

            $lab = $em->getRepository(Lab::class)->findOneBy(['name' => self::JSON_LAB_NAME]);
            self::assertNotNull($lab, 'The lab must have been created.');
            self::assertSame(
                $teacher->getId(),
                $lab->getAuthor()?->getId(),
                'The lab author must be the connected user.'
            );
        } finally {
            $this->deleteLab($em, self::JSON_LAB_NAME);
        }
    }

    /**
     * End-to-end EVE-NG commit through the API.
     */
    public function testEveNgCommitAuthorIsConnectedUser(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $teacher = $this->teacher($em);

        $jwt = $container->get('lexik_jwt_authentication.jwt_manager')->create($teacher);
        $client->getCookieJar()->set(new Cookie('bearer', $jwt));

        $tmp = tempnam(sys_get_temp_dir(), 'eve-author-') . '.unl';
        file_put_contents($tmp, <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<lab name="{$this->eveLabName()}" version="1">
  <topology>
    <nodes>
      <node id="1" type="qemu" template="vios" image="tmp-author-regression.qcow2" name="R1" console="telnet" ram="512" cpu="1" qemu_arch="x86_64" left="10" top="10" icon="Router.png"/>
    </nodes>
    <networks/>
  </topology>
  <objects><textobjects/></objects>
</lab>
XML);

        try {
            $client->request('POST', '/api/labs/import/eve-ng/commit', [
                'mapping' => json_encode(['tmp-author-regression.qcow2' => ['action' => 'skip']]),
                'devices' => json_encode([]),
            ], [
                'file' => new UploadedFile($tmp, 'tmp-author-regression.unl', 'text/xml', null, true),
            ]);

            self::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

            $lab = $em->getRepository(Lab::class)->findOneBy(['name' => $this->eveLabName()]);
            self::assertNotNull($lab, 'The lab must have been created.');
            self::assertSame(
                $teacher->getId(),
                $lab->getAuthor()?->getId(),
                'The lab author must be the connected user.'
            );
        } finally {
            @unlink($tmp);
            $this->deleteLab($em, $this->eveLabName());
            $this->deleteOperatingSystem($em, 'tmp-author-regression.qcow2');
        }
    }

    private function eveLabName(): string
    {
        return self::EVE_LAB_NAME;
    }

    private function teacher(EntityManagerInterface $em): User
    {
        $teacher = $em->getRepository(User::class)->findOneBy(['email' => 'enseignant@example.org']);
        self::assertInstanceOf(User::class, $teacher);

        return $teacher;
    }

    private function deleteLab(EntityManagerInterface $em, string $name): void
    {
        $em->clear();

        $lab = $em->getRepository(Lab::class)->findOneBy(['name' => $name]);
        if (null === $lab) {
            return;
        }

        foreach ($lab->getDevices() as $device) {
            $lab->removeDevice($device);
            $em->remove($device);
        }

        $em->remove($lab);
        $em->flush();
    }

    /**
     * Removes the placeholder OperatingSystem created for an unresolved
     * EVE-NG image (the test only asserts the lab author).
     */
    private function deleteOperatingSystem(EntityManagerInterface $em, string $image): void
    {
        $em->clear();

        $repository = $em->getRepository(OperatingSystem::class);
        foreach ($repository->findBy(['imageFilename' => $image]) as $operatingSystem) {
            $em->remove($operatingSystem);
        }
        foreach ($repository->findBy(['imageUrl' => $image]) as $operatingSystem) {
            $em->remove($operatingSystem);
        }

        $em->flush();
    }
}
