<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Platforms\MySQLPlatform;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190910135157 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE lab DROP FOREIGN KEY FK_61D6B1C4A76ED395');
        $this->addSql('DROP INDEX IDX_61D6B1C4A76ED395 ON lab');
        $this->addSql('ALTER TABLE lab DROP user_id');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE lab ADD user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE lab ADD CONSTRAINT FK_61D6B1C4A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_61D6B1C4A76ED395 ON lab (user_id)');
    }
}
