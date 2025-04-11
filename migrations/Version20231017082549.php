<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Platforms\MySQLPlatform;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231017082549 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE picture DROP FOREIGN KEY FK_16DB4F89628913D5');
        $this->addSql('ALTER TABLE picture ADD CONSTRAINT FK_16DB4F89628913D5 FOREIGN KEY (lab_id) REFERENCES lab (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE text_object DROP FOREIGN KEY FK_BD21321F628913D5');
        $this->addSql('ALTER TABLE text_object ADD CONSTRAINT FK_BD21321F628913D5 FOREIGN KEY (lab_id) REFERENCES lab (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE picture DROP FOREIGN KEY FK_16DB4F89628913D5');
        $this->addSql('ALTER TABLE picture ADD CONSTRAINT FK_16DB4F89628913D5 FOREIGN KEY (lab_id) REFERENCES lab (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('ALTER TABLE text_object DROP FOREIGN KEY FK_BD21321F628913D5');
        $this->addSql('ALTER TABLE text_object ADD CONSTRAINT FK_BD21321F628913D5 FOREIGN KEY (lab_id) REFERENCES lab (id) ON UPDATE NO ACTION ON DELETE SET NULL');
    }
}
