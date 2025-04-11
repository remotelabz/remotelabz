<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Platforms\MySQLPlatform;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220109170619 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
         // this up() migration is auto-generated, please modify it to your needs
         $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

         $this->addSql('UPDATE operating_system SET image_filename = CONCAT(\'qemu://\',image_filename) WHERE image_filename LIKE \'%.img%\'');
         $this->addSql('UPDATE operating_system SET image_filename = CONCAT(\'lxc://\',image_filename) WHERE image_filename NOT LIKE \'%.img%\'');
         $this->addSql('INSERT INTO operating_system (name,image_url,image_filename) VALUES (\'Migration\',NULL,\'lxc://Migration\')');

    }

    public function down(Schema $schema) : void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('UPDATE operating_system SET image_filename = SUBSTRING_INDEX(image_filename,\'/\',-1) WHERE image_filename LIKE \'%/%\'');
    }
}
