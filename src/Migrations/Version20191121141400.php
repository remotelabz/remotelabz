<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191121141400 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE _group ADD parent_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE _group ADD CONSTRAINT FK_E7F8A859727ACA70 FOREIGN KEY (parent_id) REFERENCES _group (id)');
        $this->addSql('CREATE INDEX IDX_E7F8A859727ACA70 ON _group (parent_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE _group DROP FOREIGN KEY FK_E7F8A859727ACA70');
        $this->addSql('DROP INDEX IDX_E7F8A859727ACA70 ON _group');
        $this->addSql('ALTER TABLE _group DROP parent_id');
    }
}
