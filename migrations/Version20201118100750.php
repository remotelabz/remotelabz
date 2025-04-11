<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Utils\Uuid;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Platforms\MySQLPlatform;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201118100750 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE lab ADD _group_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE lab ADD CONSTRAINT FK_61D6B1C4D0949C27 FOREIGN KEY (_group_id) REFERENCES _group (id)');
        $this->addSql('CREATE INDEX IDX_61D6B1C4D0949C27 ON lab (_group_id)');
        $this->addSql('INSERT IGNORE INTO _group SET id = 1, parent_id = NULL, name = \'Default group\', created_at = NOW(), updated_at = NOW(), visibility = 2, picture_filename = NULL, slug = \'default-group\', description = \'The default group.\', uuid = \''.new Uuid().'\'');
        $this->addSql('INSERT IGNORE INTO user_group SET id = 1, group_id = 1, user_id = (SELECT id FROM user WHERE email = \'root@localhost\'), permissions = \'\', created_at = NOW(), role = \'owner\'');
        $this->addSql(
            'UPDATE lab
            SET _group_id = 1
            WHERE _group_id IS NULL'
        );
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE lab DROP FOREIGN KEY FK_61D6B1C4D0949C27');
        $this->addSql('DROP INDEX IDX_61D6B1C4D0949C27 ON lab');
        $this->addSql('ALTER TABLE lab DROP _group_id');
    }
}
