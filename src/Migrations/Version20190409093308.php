<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190409093308 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE operating_system DROP FOREIGN KEY FK_BCF9A7814F2DF8A7');
        $this->addSql('ALTER TABLE operating_system DROP FOREIGN KEY FK_BCF9A781FDDA6450');
        $this->addSql('DROP INDEX IDX_BCF9A781FDDA6450 ON operating_system');
        $this->addSql('DROP INDEX IDX_BCF9A7814F2DF8A7 ON operating_system');
        $this->addSql('ALTER TABLE operating_system DROP hypervisor_id, DROP flavor_id, CHANGE path image VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE operating_system ADD hypervisor_id INT DEFAULT NULL, ADD flavor_id INT DEFAULT NULL, CHANGE image path VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE operating_system ADD CONSTRAINT FK_BCF9A7814F2DF8A7 FOREIGN KEY (hypervisor_id) REFERENCES hypervisor (id)');
        $this->addSql('ALTER TABLE operating_system ADD CONSTRAINT FK_BCF9A781FDDA6450 FOREIGN KEY (flavor_id) REFERENCES flavor (id)');
        $this->addSql('CREATE INDEX IDX_BCF9A781FDDA6450 ON operating_system (flavor_id)');
        $this->addSql('CREATE INDEX IDX_BCF9A7814F2DF8A7 ON operating_system (hypervisor_id)');
    }
}
