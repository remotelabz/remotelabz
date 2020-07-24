<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LabControllerTest extends WebTestCase
{
    use ControllerTestTrait, LabControllerTestTrait;

    public function testCreateLab()
    {
        $this->logIn();
        $data = $this->createLab();
        return $data['id'];
    }

    /**
     * @depends testCreateLab
     */
    public function testEditLab($labId)
    {
        $this->logIn();
        $this->editLab($labId, 'Edited Lab', 'This is a new description');
    }

    /**
     * @depends testCreateLab
     */
    public function testDeleteLab($labId)
    {
        $this->logIn();
        $this->deleteLab($labId);
    }
}