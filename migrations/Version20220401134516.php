<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Platforms\MySQLPlatform;

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
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE device ADD hypervisor_id INT DEFAULT NULL, DROP hypervisor');
        $this->addSql('ALTER TABLE device ADD CONSTRAINT FK_92FB68E4F2DF8A7 FOREIGN KEY (hypervisor_id) REFERENCES hypervisor (id)');
        $this->addSql('CREATE INDEX IDX_92FB68E4F2DF8A7 ON device (hypervisor_id)');
        $this->addSql('UPDATE device SET hypervisor_id = 1 WHERE type=\'vm\'');
        $this->addSql('UPDATE device SET hypervisor_id = 2 WHERE type=\'container\'');
        $this->addSql('INSERT INTO operating_system (id, name, image_url, image_filename, hypervisor_id) VALUES (NULL, \'Debian cnt\', NULL, \'Debian\', \'2\')');
        $this->addSql('INSERT INTO operating_system (id, name, image_url, image_filename, hypervisor_id) VALUES (NULL, \'Alpine3.16 cnt\', NULL, \'Alpine3.16\', \'2\')');
        $this->addSql('INSERT INTO operating_system (id, name, image_url, image_filename, hypervisor_id) VALUES (NULL, \'Ubuntu20LTS cnt\', NULL, \'Ubuntu20LTS\', \'2\')');

    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE device DROP FOREIGN KEY FK_92FB68E4F2DF8A7');
        $this->addSql('DROP INDEX IDX_92FB68E4F2DF8A7 ON device');
        $this->addSql('ALTER TABLE device ADD hypervisor VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, DROP hypervisor_id');
        $this->addSql('UPDATE device SET hypervisor = \'qemu\' WHERE type=\'vm\'');
        $this->addSql('UPDATE device SET hypervisor = \'lxc\' WHERE type=\'container\'');
        $this->addSql('DELETE FROM operating_system WHERE operating_system.name=\'Ubuntu20LTS cnt\' AND operating_system.image_filename=\'Ubuntu20LTS\'');
        $this->addSql('DELETE FROM operating_system WHERE operating_system.name=\'Alpine3.16 cnt\' AND operating_system.image_filename=\'Alpine3.16\'');
        $this->addSql('DELETE FROM operating_system WHERE operating_system.name=\'Debian cnt\' AND operating_system.image_filename=\'Debian\'');

    }
}
