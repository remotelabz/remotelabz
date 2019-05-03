<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190503093554 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE instance');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE instance (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, activity_id INT NOT NULL, network_id INT NOT NULL, user_network_id INT NOT NULL, process_name VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci, storage_path VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci, UNIQUE INDEX UNIQ_4230B1DE34128B91 (network_id), INDEX IDX_4230B1DE81C06096 (activity_id), UNIQUE INDEX UNIQ_4230B1DEA76ED395 (user_id), UNIQUE INDEX UNIQ_4230B1DE93754D7D (user_network_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE instance ADD CONSTRAINT FK_4230B1DE34128B91 FOREIGN KEY (network_id) REFERENCES network (id)');
        $this->addSql('ALTER TABLE instance ADD CONSTRAINT FK_4230B1DE81C06096 FOREIGN KEY (activity_id) REFERENCES activity (id)');
        $this->addSql('ALTER TABLE instance ADD CONSTRAINT FK_4230B1DE93754D7D FOREIGN KEY (user_network_id) REFERENCES network (id)');
        $this->addSql('ALTER TABLE instance ADD CONSTRAINT FK_4230B1DEA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }
}
