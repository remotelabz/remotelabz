<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Platforms\MySQLPlatform;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191108145710 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE activity DROP shared, DROP supervised, DROP access_type');
        $this->addSql('ALTER TABLE device DROP FOREIGN KEY FK_92FB68E34A97425');
        $this->addSql('ALTER TABLE device ADD CONSTRAINT FK_92FB68E34A97425 FOREIGN KEY (editor_data_id) REFERENCES editor_data (id)');
        $this->addSql('ALTER TABLE network_interface_instance CHANGE remote_port remote_port INT DEFAULT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE activity ADD shared TINYINT(1) NOT NULL, ADD supervised TINYINT(1) NOT NULL, ADD access_type VARCHAR(3) NOT NULL COLLATE utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE device DROP FOREIGN KEY FK_92FB68E34A97425');
        $this->addSql('ALTER TABLE device ADD CONSTRAINT FK_92FB68E34A97425 FOREIGN KEY (editor_data_id) REFERENCES editor_data (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE network_interface_instance CHANGE remote_port remote_port INT NOT NULL');
    }
}
