<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Platforms\MySQLPlatform;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240423125956 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE booking (id INT AUTO_INCREMENT NOT NULL, author_id INT DEFAULT NULL, user_id INT DEFAULT NULL, _group_id INT DEFAULT NULL, lab_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, uuid VARCHAR(255) NOT NULL, start_date DATETIME NOT NULL, end_date DATETIME NOT NULL, INDEX IDX_E00CEDDEF675F31B (author_id), INDEX IDX_E00CEDDEA76ED395 (user_id), INDEX IDX_E00CEDDED0949C27 (_group_id), INDEX IDX_E00CEDDE628913D5 (lab_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDEF675F31B FOREIGN KEY (author_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDEA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDED0949C27 FOREIGN KEY (_group_id) REFERENCES _group (id)');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDE628913D5 FOREIGN KEY (lab_id) REFERENCES lab (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking DROP FOREIGN KEY FK_E00CEDDEF675F31B');
        $this->addSql('ALTER TABLE booking DROP FOREIGN KEY FK_E00CEDDEA76ED395');
        $this->addSql('ALTER TABLE booking DROP FOREIGN KEY FK_E00CEDDED0949C27');
        $this->addSql('ALTER TABLE booking DROP FOREIGN KEY FK_E00CEDDE628913D5');
        $this->addSql('DROP TABLE booking');
    }
}
