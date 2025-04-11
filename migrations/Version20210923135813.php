<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Platforms\MySQLPlatform;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210923135813 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE lab_device DROP FOREIGN KEY FK_9CF730DA94A4C7D4');
        $this->addSql('ALTER TABLE lab_device ADD CONSTRAINT FK_9CF730DA94A4C7D4 FOREIGN KEY (device_id) REFERENCES device (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE lab_device DROP FOREIGN KEY FK_9CF730DA94A4C7D4');
        $this->addSql('ALTER TABLE lab_device ADD CONSTRAINT FK_9CF730DA94A4C7D4 FOREIGN KEY (device_id) REFERENCES device (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
    }
}
