<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Platforms\MySQLPlatform;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220318125933 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE hypervisor (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE operating_system ADD hypervisor_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE operating_system ADD CONSTRAINT FK_BCF9A7814F2DF8A7 FOREIGN KEY (hypervisor_id) REFERENCES hypervisor (id)');
        $this->addSql('CREATE INDEX IDX_BCF9A7814F2DF8A7 ON operating_system (hypervisor_id)');
        $this->addSql('INSERT INTO hypervisor (name) VALUES (\'qemu\')');
        $this->addSql('INSERT INTO hypervisor (name) VALUES (\'lxc\')');
        $this->addSql('UPDATE operating_system SET image_filename = SUBSTRING_INDEX(image_filename,\'/\',-1) WHERE image_filename LIKE \'%/%\'');
        $this->addSql('UPDATE operating_system os SET os.hypervisor_id = 1 WHERE image_filename LIKE \'%.img\'');
        $this->addSql('UPDATE operating_system os SET os.hypervisor_id = 1 WHERE image_filename IS NULL');
        $this->addSql('UPDATE operating_system os SET os.hypervisor_id = 2 WHERE image_filename NOT LIKE \'%.img\''); 

    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE operating_system DROP FOREIGN KEY FK_BCF9A7814F2DF8A7');
        $this->addSql('DROP TABLE hypervisor');
        $this->addSql('DROP INDEX IDX_BCF9A7814F2DF8A7 ON operating_system');
        $this->addSql('ALTER TABLE operating_system DROP hypervisor_id');
        $this->addSql('UPDATE operating_system SET image_filename = CONCAT(\'qemu://\',image_filename) WHERE image_filename LIKE \'%.img%\'');
        $this->addSql('UPDATE operating_system SET image_filename = CONCAT(\'lxc://\',image_filename) WHERE image_filename NOT LIKE \'%.img%\'');

    }
}
