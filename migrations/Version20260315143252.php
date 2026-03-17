<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260315143252 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE scheduled_action (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, action VARCHAR(16) NOT NULL, scheduled_at DATETIME NOT NULL, executed_at DATETIME DEFAULT NULL, status VARCHAR(16) NOT NULL, error_message LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, execution_report JSON DEFAULT NULL, lab_id INT NOT NULL, group_id INT DEFAULT NULL, created_by_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_EB32088BD17F50A6 (uuid), INDEX IDX_EB32088B628913D5 (lab_id), INDEX IDX_EB32088BFE54D947 (group_id), INDEX IDX_EB32088BB03A8386 (created_by_id), INDEX idx_status_scheduled_at (status, scheduled_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE scheduled_action ADD CONSTRAINT FK_EB32088B628913D5 FOREIGN KEY (lab_id) REFERENCES lab (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE scheduled_action ADD CONSTRAINT FK_EB32088BFE54D947 FOREIGN KEY (group_id) REFERENCES _group (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE scheduled_action ADD CONSTRAINT FK_EB32088BB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE lab CHANGE version version VARCHAR(10) DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE scheduled_action DROP FOREIGN KEY FK_EB32088B628913D5');
        $this->addSql('ALTER TABLE scheduled_action DROP FOREIGN KEY FK_EB32088BFE54D947');
        $this->addSql('ALTER TABLE scheduled_action DROP FOREIGN KEY FK_EB32088BB03A8386');
        $this->addSql('DROP TABLE scheduled_action');
        $this->addSql('ALTER TABLE lab CHANGE version version VARCHAR(10) DEFAULT \'1\' NOT NULL');
    }
}
