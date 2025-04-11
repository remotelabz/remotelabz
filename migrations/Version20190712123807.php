<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Platforms\MySQLPlatform;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190712123807 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE device_instance ADD lab_id INT NOT NULL');
        $this->addSql('ALTER TABLE device_instance ADD CONSTRAINT FK_CC04E8FE628913D5 FOREIGN KEY (lab_id) REFERENCES lab (id)');
        $this->addSql('CREATE INDEX IDX_CC04E8FE628913D5 ON device_instance (lab_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE device_instance DROP FOREIGN KEY FK_CC04E8FE628913D5');
        $this->addSql('DROP INDEX IDX_CC04E8FE628913D5 ON device_instance');
        $this->addSql('ALTER TABLE device_instance DROP lab_id');
    }
}
