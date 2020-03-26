<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200313094549 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE device_instance ADD _group_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE device_instance ADD CONSTRAINT FK_CC04E8FED0949C27 FOREIGN KEY (_group_id) REFERENCES _group (id)');
        $this->addSql('CREATE INDEX IDX_CC04E8FED0949C27 ON device_instance (_group_id)');
        $this->addSql('ALTER TABLE lab_instance ADD _group_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE lab_instance ADD CONSTRAINT FK_983C9A43D0949C27 FOREIGN KEY (_group_id) REFERENCES _group (id)');
        $this->addSql('CREATE INDEX IDX_983C9A43D0949C27 ON lab_instance (_group_id)');
        $this->addSql('ALTER TABLE network_interface_instance ADD _group_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE network_interface_instance ADD CONSTRAINT FK_245762DD0949C27 FOREIGN KEY (_group_id) REFERENCES _group (id)');
        $this->addSql('CREATE INDEX IDX_245762DD0949C27 ON network_interface_instance (_group_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE device_instance DROP FOREIGN KEY FK_CC04E8FED0949C27');
        $this->addSql('DROP INDEX IDX_CC04E8FED0949C27 ON device_instance');
        $this->addSql('ALTER TABLE device_instance DROP _group_id');
        $this->addSql('ALTER TABLE lab_instance DROP FOREIGN KEY FK_983C9A43D0949C27');
        $this->addSql('DROP INDEX IDX_983C9A43D0949C27 ON lab_instance');
        $this->addSql('ALTER TABLE lab_instance DROP _group_id');
        $this->addSql('ALTER TABLE network_interface_instance DROP FOREIGN KEY FK_245762DD0949C27');
        $this->addSql('DROP INDEX IDX_245762DD0949C27 ON network_interface_instance');
        $this->addSql('ALTER TABLE network_interface_instance DROP _group_id');
    }
}
