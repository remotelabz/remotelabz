<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220401134516 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE device ADD hypervisor_id INT DEFAULT NULL, DROP hypervisor');
        $this->addSql('ALTER TABLE device ADD CONSTRAINT FK_92FB68E4F2DF8A7 FOREIGN KEY (hypervisor_id) REFERENCES hypervisor (id)');
        $this->addSql('CREATE INDEX IDX_92FB68E4F2DF8A7 ON device (hypervisor_id)');
        $this->addSql('UPDATE device SET hypervisor_id = 3 WHERE type=\'vm\'');
        $this->addSql('UPDATE device SET hypervisor_id = 4 WHERE type=\'container\'');

    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE device DROP FOREIGN KEY FK_92FB68E4F2DF8A7');
        $this->addSql('DROP INDEX IDX_92FB68E4F2DF8A7 ON device');
        $this->addSql('ALTER TABLE device ADD hypervisor VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, DROP hypervisor_id');
        $this->addSql('UPDATE device SET hypervisor = \'qemu\' WHERE type=\'vm\'');
        $this->addSql('UPDATE device SET hypervisor = \'lxc\' WHERE type=\'container\'');
    }
}
