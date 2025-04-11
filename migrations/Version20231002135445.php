<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Platforms\MySQLPlatform;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231002135445 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE control_protocol_type_instance ADD guest_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE control_protocol_type_instance ADD CONSTRAINT FK_AA90BE399A4AA658 FOREIGN KEY (guest_id) REFERENCES invitation_code (id)');
        $this->addSql('CREATE INDEX IDX_AA90BE399A4AA658 ON control_protocol_type_instance (guest_id)');
        $this->addSql('ALTER TABLE device_instance ADD guest_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE device_instance ADD CONSTRAINT FK_CC04E8FE9A4AA658 FOREIGN KEY (guest_id) REFERENCES invitation_code (id)');
        $this->addSql('CREATE INDEX IDX_CC04E8FE9A4AA658 ON device_instance (guest_id)');
        $this->addSql('ALTER TABLE invitation_code ADD uuid VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE lab_instance ADD guest_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE lab_instance ADD CONSTRAINT FK_983C9A439A4AA658 FOREIGN KEY (guest_id) REFERENCES invitation_code (id)');
        $this->addSql('CREATE INDEX IDX_983C9A439A4AA658 ON lab_instance (guest_id)');
        $this->addSql('ALTER TABLE network_interface_instance ADD guest_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE network_interface_instance ADD CONSTRAINT FK_245762D9A4AA658 FOREIGN KEY (guest_id) REFERENCES invitation_code (id)');
        $this->addSql('CREATE INDEX IDX_245762D9A4AA658 ON network_interface_instance (guest_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE control_protocol_type_instance DROP FOREIGN KEY FK_AA90BE399A4AA658');
        $this->addSql('DROP INDEX IDX_AA90BE399A4AA658 ON control_protocol_type_instance');
        $this->addSql('ALTER TABLE control_protocol_type_instance DROP guest_id');
        $this->addSql('ALTER TABLE device_instance DROP FOREIGN KEY FK_CC04E8FE9A4AA658');
        $this->addSql('DROP INDEX IDX_CC04E8FE9A4AA658 ON device_instance');
        $this->addSql('ALTER TABLE device_instance DROP guest_id');
        $this->addSql('ALTER TABLE invitation_code DROP uuid');
        $this->addSql('ALTER TABLE lab_instance DROP FOREIGN KEY FK_983C9A439A4AA658');
        $this->addSql('DROP INDEX IDX_983C9A439A4AA658 ON lab_instance');
        $this->addSql('ALTER TABLE lab_instance DROP guest_id');
        $this->addSql('ALTER TABLE network_interface_instance DROP FOREIGN KEY FK_245762D9A4AA658');
        $this->addSql('DROP INDEX IDX_245762D9A4AA658 ON network_interface_instance');
        $this->addSql('ALTER TABLE network_interface_instance DROP guest_id');
    }
}
