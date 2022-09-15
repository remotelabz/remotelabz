<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220915154452 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE control_protocol ADD device_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE control_protocol ADD CONSTRAINT FK_17004C0C94A4C7D4 FOREIGN KEY (device_id) REFERENCES device (id)');
        $this->addSql('CREATE INDEX IDX_17004C0C94A4C7D4 ON control_protocol (device_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE control_protocol DROP FOREIGN KEY FK_17004C0C94A4C7D4');
        $this->addSql('DROP INDEX IDX_17004C0C94A4C7D4 ON control_protocol');
        $this->addSql('ALTER TABLE control_protocol DROP device_id');
    }
}
