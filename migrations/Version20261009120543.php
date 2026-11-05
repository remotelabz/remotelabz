<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add the lab_share rule table and drop the unused lab.shared boolean.
 */
final class Version20261009120543 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create lab_share (shared labs rules) and drop lab.shared';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE lab_share (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME DEFAULT NULL, lab_id INT NOT NULL, shared_with_id INT NOT NULL, group_id INT NOT NULL, created_by_id INT DEFAULT NULL, UNIQUE INDEX uniq_share (lab_id, shared_with_id, group_id), INDEX IDX_88CA4B92628913D5 (lab_id), INDEX IDX_88CA4B92D14FE63F (shared_with_id), INDEX IDX_88CA4B92FE54D947 (group_id), INDEX IDX_88CA4B92B03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE lab_share ADD CONSTRAINT FK_88CA4B92628913D5 FOREIGN KEY (lab_id) REFERENCES lab (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE lab_share ADD CONSTRAINT FK_88CA4B92D14FE63F FOREIGN KEY (shared_with_id) REFERENCES lab (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE lab_share ADD CONSTRAINT FK_88CA4B92FE54D947 FOREIGN KEY (group_id) REFERENCES _group (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE lab_share ADD CONSTRAINT FK_88CA4B92B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE lab DROP shared');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE lab_share DROP FOREIGN KEY FK_88CA4B92628913D5');
        $this->addSql('ALTER TABLE lab_share DROP FOREIGN KEY FK_88CA4B92D14FE63F');
        $this->addSql('ALTER TABLE lab_share DROP FOREIGN KEY FK_88CA4B92FE54D947');
        $this->addSql('ALTER TABLE lab_share DROP FOREIGN KEY FK_88CA4B92B03A8386');
        $this->addSql('DROP TABLE lab_share');
        $this->addSql('ALTER TABLE lab ADD shared TINYINT NOT NULL');
    }
}
