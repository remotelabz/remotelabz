<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Platforms\MySQLPlatform;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191021074434 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE editor_data (id INT AUTO_INCREMENT NOT NULL, x INT NOT NULL, y INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE device ADD editor_data_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE device ADD CONSTRAINT FK_92FB68E34A97425 FOREIGN KEY (editor_data_id) REFERENCES editor_data (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_92FB68E34A97425 ON device (editor_data_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE device DROP FOREIGN KEY FK_92FB68E34A97425');
        $this->addSql('DROP TABLE editor_data');
        $this->addSql('DROP INDEX UNIQ_92FB68E34A97425 ON device');
        $this->addSql('ALTER TABLE device DROP editor_data_id');
    }
}
