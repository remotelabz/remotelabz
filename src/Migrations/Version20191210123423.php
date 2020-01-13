<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191210123423 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE lab_instance ADD owned_by VARCHAR(255) NOT NULL');
        $this->addSql('DROP INDEX Fname_Lname ON user');
        $this->addSql('ALTER TABLE device_instance ADD owned_by VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE network_interface_instance ADD owned_by VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE device_instance DROP owned_by');
        $this->addSql('ALTER TABLE lab_instance DROP owned_by');
        $this->addSql('ALTER TABLE network_interface_instance DROP owned_by');
        $this->addSql('CREATE INDEX Fname_Lname ON user (first_name, last_name)');
    }
}
