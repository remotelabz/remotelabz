<?php

namespace App\Tests\Controller;

class LabControllerTest extends AuthenticatedWebTestCase
{
    public function testCreateLab()
    {
        $this->client->request('POST', '/api/labs');
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);

        return $data['id'];
    }

    /**
     * @depends testCreateLab
     */
    public function testEditLab($labId)
    {
        $tmp['name'] = 'Edited Lab';
        $tmp['description'] = 'This is a new description';
        $data = json_encode($tmp);

        $this->client->request('PUT',
            '/api/labs/'.$labId,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $data
        );

        $this->assertResponseIsSuccessful();
    }

    /**
     * @depends testCreateLab
     */
    public function testAddDeviceToLab($labId)
    {
        $device = [
            'name' => 'test-device',
            'operatingSystem' => 1,
            'networkInterfaces' => [],
            'flavor' => 1,
            'isTemplate' => 0,
        ];

        $this->client->request('POST',
            '/api/labs/'.$labId.'/devices',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($device)
        );

        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->client->request('DELETE', '/api/devices/'.$data['id']);

        $this->assertResponseIsSuccessful();
    }

    public function testCloneLab()
    {
        $this->client->request('POST', '/api/labs');
        $this->assertResponseIsSuccessful();
        $source = json_decode($this->client->getResponse()->getContent(), true);
        $sourceId = $source['id'];

        $this->client->request('PUT', '/api/labs/'.$sourceId, [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'name' => 'Clone source lab',
            'description' => "## Sujet d'origine",
        ]));
        $this->assertResponseIsSuccessful();

        // Link a practical subject to the source lab
        $this->client->request('POST', '/api/practical-subjects', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'name' => 'Sujet partagé',
            'description' => 'Contenu partagé',
        ]));
        $this->assertResponseIsSuccessful();
        $subjectId = json_decode($this->client->getResponse()->getContent(), true)['id'];
        $this->client->request('POST', '/api/labs/'.$sourceId.'/practical-subjects/'.$subjectId);
        $this->assertResponseIsSuccessful();

        $this->client->request('POST', '/api/labs/'.$sourceId.'/createcopy/', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['name' => 'Clone dest lab']));
        $this->assertResponseIsSuccessful();
        $clone = json_decode($this->client->getResponse()->getContent(), true);
        $cloneId = $clone['id'];

        // The clone is a different lab with its own identity
        $this->assertNotSame($sourceId, $cloneId);
        $this->assertNotSame($source['uuid'], $clone['uuid']);

        $this->client->request('GET', '/api/labs/'.$cloneId);
        $this->assertResponseIsSuccessful();
        $cloneData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Clone dest lab', $cloneData['name']);
        $this->assertSame("## Sujet d'origine", $cloneData['description']);
        // The practical subject is shared between the two labs, not duplicated
        $this->assertCount(1, $cloneData['practicalSubjects']);
        $this->assertSame($subjectId, $cloneData['practicalSubjects'][0]['id']);

        // The clone is independent from the original lab
        $this->client->request('PUT', '/api/labs/'.$cloneId, [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'name' => 'Clone modifié',
            'description' => 'Description du clone',
        ]));
        $this->assertResponseIsSuccessful();

        $this->client->request('GET', '/api/labs/'.$sourceId);
        $this->assertResponseIsSuccessful();
        $sourceData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Clone source lab', $sourceData['name']);
        $this->assertSame("## Sujet d'origine", $sourceData['description']);

        // Cleanup
        $this->client->request('DELETE', '/api/labs/'.$cloneId);
        $this->assertResponseIsSuccessful();
        $this->client->request('DELETE', '/api/practical-subjects/'.$subjectId);
        $this->assertResponseIsSuccessful();
        $this->client->request('DELETE', '/api/labs/'.$sourceId);
        $this->assertResponseIsSuccessful();
    }

    /**
     * @depends testCreateLab
     */
    public function testDeleteLab($labId)
    {
        $this->client->followRedirects();
        $crawler = $this->client->request('GET', '/admin/labs/'.$labId.'/delete');
        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $crawler->filter('.flash-notice.alert-success')->count());

        // api side
        $this->client->request('POST', '/api/labs');
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->client->request('DELETE', '/api/labs/'.$data['id']);
        $this->assertResponseIsSuccessful();
    }
}
