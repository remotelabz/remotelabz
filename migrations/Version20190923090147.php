<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Platforms\MySQLPlatform;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190923090147 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE device_instance ADD lab_instance_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE device_instance ADD CONSTRAINT FK_CC04E8FEB05CE826 FOREIGN KEY (lab_instance_id) REFERENCES lab_instance (id)');
        $this->addSql('CREATE INDEX IDX_CC04E8FEB05CE826 ON device_instance (lab_instance_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE device_instance DROP FOREIGN KEY FK_CC04E8FEB05CE826');
        $this->addSql('DROP INDEX IDX_CC04E8FEB05CE826 ON device_instance');
        $this->addSql('ALTER TABLE device_instance DROP lab_instance_id');
    }
}
