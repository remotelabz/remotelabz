<?php

namespace App\Tests\Controller;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class PracticalSubjectControllerTest extends AuthenticatedWebTestCase
{
    private array $createdLabIds = [];

    private array $createdSubjectIds = [];

    protected function tearDown(): void
    {
        foreach ($this->createdSubjectIds as $subjectId) {
            $this->client->request('DELETE', '/api/practical-subjects/'.$subjectId);
        }
        $this->createdSubjectIds = [];

        foreach ($this->createdLabIds as $labId) {
            $this->client->request('DELETE', '/api/labs/'.$labId);
        }
        $this->createdLabIds = [];

        parent::tearDown();
    }

    private function createLab(string $name): int
    {
        $this->client->request('POST', '/api/labs');
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $labId = $data['id'];
        $this->createdLabIds[] = $labId;

        $this->client->request('PUT', '/api/labs/'.$labId, [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['name' => $name]));
        $this->assertResponseIsSuccessful();

        return $labId;
    }

    private function createSubject(string $name, ?string $description = null): int
    {
        $payload = ['name' => $name];
        if (null !== $description) {
            $payload['description'] = $description;
        }

        $this->client->request('POST', '/api/practical-subjects', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->createdSubjectIds[] = $data['id'];

        return $data['id'];
    }

    public function testCreateSubject()
    {
        $subjectId = $this->createSubject('Comparaison systemd et Apache2', '## Objectif\n\nComparer **systemd** et Apache2.');

        $this->client->request('GET', '/api/practical-subjects/'.$subjectId);
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame($subjectId, $data['id']);
        $this->assertSame('Comparaison systemd et Apache2', $data['name']);
        $this->assertSame('markdown', $data['contentType']);
        $this->assertStringContainsString('systemd', $data['description']);
    }

    /**
     * @depends testCreateSubject
     */
    public function testEditSubject()
    {
        $this->client->request('POST', '/api/practical-subjects', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'name' => 'Sujet à modifier',
            'description' => 'Ancienne version',
        ]));
        $this->assertResponseIsSuccessful();
        $subjectId = json_decode($this->client->getResponse()->getContent(), true)['id'];
        $this->createdSubjectIds[] = $subjectId;

        $this->client->request('PUT', '/api/practical-subjects/'.$subjectId, [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'name' => 'Sujet modifié',
            'description' => '## Nouvelle version',
        ]));
        $this->assertResponseIsSuccessful();

        $this->client->request('GET', '/api/practical-subjects/'.$subjectId);
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Sujet modifié', $data['name']);
        $this->assertStringContainsString('Nouvelle version', $data['description']);
    }

    public function testAssociateSubjectWithLab()
    {
        $labId = $this->createLab('Ubuntu 24 - systemd');
        $subjectId = $this->createSubject('Sujet systemd');

        $this->client->request('POST', '/api/labs/'.$labId.'/practical-subjects/'.$subjectId);
        $this->assertResponseIsSuccessful();

        $this->client->request('GET', '/api/labs/'.$labId.'/practical-subjects');
        $this->assertResponseIsSuccessful();
        $subjects = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(1, $subjects);
        $this->assertSame($subjectId, $subjects[0]['id']);
    }

    public function testSameSubjectWithSeveralLabs()
    {
        $lab1Id = $this->createLab('Ubuntu 24');
        $lab2Id = $this->createLab('Ubuntu 26');
        $subjectId = $this->createSubject('Comparaison systemd et Apache2', 'Contenu identique pour les deux labs');

        $this->client->request('POST', '/api/labs/'.$lab1Id.'/practical-subjects/'.$subjectId);
        $this->assertResponseIsSuccessful();
        $this->client->request('POST', '/api/labs/'.$lab2Id.'/practical-subjects/'.$subjectId);
        $this->assertResponseIsSuccessful();

        foreach ([$lab1Id, $lab2Id] as $labId) {
            $this->client->request('GET', '/api/labs/'.$labId.'/practical-subjects');
            $this->assertResponseIsSuccessful();
            $subjects = json_decode($this->client->getResponse()->getContent(), true);
            $this->assertCount(1, $subjects);
            $this->assertSame($subjectId, $subjects[0]['id']);
        }
    }

    public function testSeveralSubjectsForOneLab()
    {
        $labId = $this->createLab('Ubuntu 24 multi-sujets');
        $subjectAId = $this->createSubject('Sujet systemd');
        $subjectBId = $this->createSubject('Sujet Apache2');

        $this->client->request('POST', '/api/labs/'.$labId.'/practical-subjects/'.$subjectAId);
        $this->assertResponseIsSuccessful();
        $this->client->request('POST', '/api/labs/'.$labId.'/practical-subjects/'.$subjectBId);
        $this->assertResponseIsSuccessful();

        $this->client->request('GET', '/api/labs/'.$labId.'/practical-subjects');
        $this->assertResponseIsSuccessful();
        $subjects = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(2, $subjects);
        $ids = array_map(fn ($subject) => $subject['id'], $subjects);
        $this->assertContains($subjectAId, $ids);
        $this->assertContains($subjectBId, $ids);
    }

    public function testUnlinkSubjectFromLab()
    {
        $labId = $this->createLab('Lab unlink');
        $subjectId = $this->createSubject('Sujet unlink');

        $this->client->request('POST', '/api/labs/'.$labId.'/practical-subjects/'.$subjectId);
        $this->assertResponseIsSuccessful();

        $this->client->request('DELETE', '/api/labs/'.$labId.'/practical-subjects/'.$subjectId);
        $this->assertResponseIsSuccessful();

        $this->client->request('GET', '/api/labs/'.$labId.'/practical-subjects');
        $this->assertResponseIsSuccessful();
        $this->assertSame([], json_decode($this->client->getResponse()->getContent(), true));
    }

    public function testMarkdownFileImport()
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'subject').'.md';
        file_put_contents($tmpFile, "## Exercice\n\nInstaller et configurer **Apache2**.\n");

        $this->client->request('POST', '/api/practical-subjects', [
            'name' => 'Sujet importé md',
        ], [
            'file' => new UploadedFile($tmpFile, 'apache2.md'),
        ]);
        $this->assertResponseIsSuccessful();
        $subjectId = json_decode($this->client->getResponse()->getContent(), true)['id'];
        $this->createdSubjectIds[] = $subjectId;
        if (is_file($tmpFile)) { unlink($tmpFile); }

        $this->client->request('GET', '/api/practical-subjects/'.$subjectId);
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Sujet importé md', $data['name']);
        $this->assertSame('markdown', $data['contentType']);
        $this->assertStringContainsString('Apache2', $data['description']);
    }

    public function testPdfFileImport()
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'subject').'.pdf';
        file_put_contents($tmpFile, "%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF");

        $this->client->request('POST', '/api/practical-subjects', [
            'name' => 'Sujet PDF',
        ], [
            'file' => new UploadedFile($tmpFile, 'sujet.pdf'),
        ]);
        $this->assertResponseIsSuccessful();
        $subjectId = json_decode($this->client->getResponse()->getContent(), true)['id'];
        $this->createdSubjectIds[] = $subjectId;

        $this->client->request('GET', '/api/practical-subjects/'.$subjectId);
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('pdf', $data['contentType']);

        $this->client->request('GET', '/api/practical-subjects/'.$subjectId.'/pdf');
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('application/pdf', $this->client->getResponse()->headers->get('Content-Type'));

        if (is_file($tmpFile)) { unlink($tmpFile); }
    }

    public function testViewMarkdownSubjectAndPrint()
    {
        $labId = $this->createLab('Lab affichage');
        $subjectId = $this->createSubject('Sujet imprimable', "## Consignes\n\n1. Créer un utilisateur **admin**\n2. Vérifier le service apache2\n");

        $this->client->request('POST', '/api/labs/'.$labId.'/practical-subjects/'.$subjectId);
        $this->assertResponseIsSuccessful();

        // The rendered subject page opens in a new tab from the lab view
        $this->client->request('GET', '/labs/'.$labId);
        $this->assertResponseIsSuccessful();
        $crawler = $this->client->getCrawler();
        $this->assertGreaterThan(0, $crawler->filter('a[target="_blank"]')->count());

        $subjectLink = $crawler->filter('a[target="_blank"]')->link();
        $this->client->click($subjectLink);
        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        // Markdown is rendered to HTML
        $this->assertStringContainsString('<strong>admin</strong>', $content);
        $this->assertStringContainsString('<ol>', $content);
        // Print support
        $this->assertStringContainsString('window.print()', $content);
        $this->assertStringContainsString('@media print', $content);
    }

    public function testEditUrlSubject()
    {
        $this->client->request('POST', '/api/practical-subjects', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'name' => 'Sujet url à modifier',
            'url' => 'https://example.org/tp/v1',
        ]));
        $this->assertResponseIsSuccessful();
        $subjectId = json_decode($this->client->getResponse()->getContent(), true)['id'];
        $this->createdSubjectIds[] = $subjectId;

        $this->client->request('PUT', '/api/practical-subjects/'.$subjectId, [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'name' => 'Sujet url modifié',
            'url' => 'https://example.org/tp/v2',
        ]));
        $this->assertResponseIsSuccessful();

        $this->client->request('GET', '/api/practical-subjects/'.$subjectId);
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Sujet url modifié', $data['name']);
        $this->assertSame('url', $data['contentType']);
        $this->assertSame('https://example.org/tp/v2', $data['url']);
    }

    public function testDeleteSubjectUnlinksLab()
    {
        $labId = $this->createLab('Lab delete subject');
        $subjectId = $this->createSubject('Sujet à supprimer');

        $this->client->request('POST', '/api/labs/'.$labId.'/practical-subjects/'.$subjectId);
        $this->assertResponseIsSuccessful();

        $this->client->request('DELETE', '/api/practical-subjects/'.$subjectId);
        $this->assertResponseIsSuccessful();
        $this->createdSubjectIds = array_values(array_diff($this->createdSubjectIds, [$subjectId]));

        $this->client->request('GET', '/api/labs/'.$labId.'/practical-subjects');
        $this->assertResponseIsSuccessful();
        $this->assertSame([], json_decode($this->client->getResponse()->getContent(), true));
    }

    public function testUrlSubjectCreation()
    {
        $this->client->request('POST', '/api/practical-subjects', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'name' => 'Sujet hébergé ailleurs',
            'url' => 'https://example.org/tp/sujet-systemd',
        ]));
        $this->assertResponseIsSuccessful();
        $subjectId = json_decode($this->client->getResponse()->getContent(), true)['id'];
        $this->createdSubjectIds[] = $subjectId;

        $this->client->request('GET', '/api/practical-subjects/'.$subjectId);
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('url', $data['contentType']);
        $this->assertSame('https://example.org/tp/sujet-systemd', $data['url']);
    }

    public function testListOnlyShowsOwnSubjects()
    {
        $ownSubjectId = $this->createSubject('Sujet du connecté');

        // Create a subject authored by another user, directly via the repository
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $otherAuthor = $entityManager->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'unittest@localhost']);
        $this->assertNotNull($otherAuthor);
        $otherSubject = new \App\Entity\PracticalSubject();
        $otherSubject->setName("Sujet de {$otherAuthor->getEmail()}");
        $otherSubject->setAuthor($otherAuthor);
        $entityManager->persist($otherSubject);
        $entityManager->flush();
        $this->createdSubjectIds[] = $otherSubject->getId();

        $this->client->request('GET', '/api/practical-subjects');
        $this->assertResponseIsSuccessful();
        $subjects = json_decode($this->client->getResponse()->getContent(), true);
        $ids = array_map(fn ($subject) => $subject['id'], $subjects);

        $this->assertContains($ownSubjectId, $ids);
        $this->assertNotContains($otherSubject->getId(), $ids);
    }

    public function testInvalidUrlRejected()
    {
        $this->client->request('POST', '/api/practical-subjects', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'name' => 'Sujet url invalide',
            'url' => 'javascript:alert(1)',
        ]));
        $this->assertResponseStatusCodeSame(400);
    }

    public function testViewUrlSubjectRedirectsToExternalUrl()
    {
        $labId = $this->createLab('Lab sujet url');

        $this->client->request('POST', '/api/practical-subjects', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'name' => 'Sujet externe',
            'url' => 'https://docs.example.org/apache2-tp',
        ]));
        $this->assertResponseIsSuccessful();
        $subjectId = json_decode($this->client->getResponse()->getContent(), true)['id'];
        $this->createdSubjectIds[] = $subjectId;

        $this->client->request('POST', '/api/labs/'.$labId.'/practical-subjects/'.$subjectId);
        $this->assertResponseIsSuccessful();

        $this->client->followRedirects(false);
        $this->client->request('GET', '/labs/'.$labId.'/subject/'.$subjectId);
        $this->assertResponseRedirects('https://docs.example.org/apache2-tp');
        $this->client->followRedirects(true);
    }

    public function testViewPdfSubjectRedirectsToPdf()
    {
        $labId = $this->createLab('Lab pdf view');

        $tmpFile = tempnam(sys_get_temp_dir(), 'subject').'.pdf';
        file_put_contents($tmpFile, "%PDF-1.4\n%%EOF");
        $this->client->request('POST', '/api/practical-subjects', [
            'name' => 'Sujet PDF lab',
        ], [
            'file' => new UploadedFile($tmpFile, 'sujet-lab.pdf'),
        ]);
        $this->assertResponseIsSuccessful();
        $subjectId = json_decode($this->client->getResponse()->getContent(), true)['id'];
        $this->createdSubjectIds[] = $subjectId;
        if (is_file($tmpFile)) { unlink($tmpFile); }

        $this->client->request('POST', '/api/labs/'.$labId.'/practical-subjects/'.$subjectId);
        $this->assertResponseIsSuccessful();

        $this->client->followRedirects(false);
        $this->client->request('GET', '/labs/'.$labId.'/subject/'.$subjectId);
        $this->assertResponseRedirects('/labs/'.$labId.'/subject/'.$subjectId.'/pdf');
        $this->client->followRedirects(true);

        $this->client->request('GET', '/labs/'.$labId.'/subject/'.$subjectId.'/pdf');
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('application/pdf', $this->client->getResponse()->headers->get('Content-Type'));
    }
}
