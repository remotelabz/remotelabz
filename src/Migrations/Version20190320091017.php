<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190320091017 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE lab ADD user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE lab ADD CONSTRAINT FK_61D6B1C4A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_61D6B1C4A76ED395 ON lab (user_id)');
        $this->addSql('ALTER TABLE network_interface DROP FOREIGN KEY FK_B3518C3494A4C7D4');
        $this->addSql('ALTER TABLE network_interface ADD CONSTRAINT FK_B3518C3494A4C7D4 FOREIGN KEY (device_id) REFERENCES device (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE lab DROP FOREIGN KEY FK_61D6B1C4A76ED395');
        $this->addSql('DROP INDEX IDX_61D6B1C4A76ED395 ON lab');
        $this->addSql('ALTER TABLE lab DROP user_id');
        $this->addSql('ALTER TABLE network_interface DROP FOREIGN KEY FK_B3518C3494A4C7D4');
        $this->addSql('ALTER TABLE network_interface ADD CONSTRAINT FK_B3518C3494A4C7D4 FOREIGN KEY (device_id) REFERENCES device (id)');
    }
}
