<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use App\Repository\LabRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211014100807 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE lab_group (lab_id INT NOT NULL, group_id INT NOT NULL, INDEX IDX_A0C920D628913D5 (lab_id), INDEX IDX_A0C920DFE54D947 (group_id), PRIMARY KEY(lab_id, group_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE lab_group ADD CONSTRAINT FK_A0C920D628913D5 FOREIGN KEY (lab_id) REFERENCES lab (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE lab_group ADD CONSTRAINT FK_A0C920DFE54D947 FOREIGN KEY (group_id) REFERENCES _group (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE lab DROP FOREIGN KEY FK_61D6B1C4D0949C27');
        $this->addSql('DROP INDEX IDX_61D6B1C4D0949C27 ON lab');
        $this->addSql('ALTER TABLE lab DROP _group_id');
        $this->addSql('UPDATE lab SET banner = \'nopic.jpg\'');
    }
// Execute in shell
// sudo yarn encore prod
// sudo find /opt/remotelabz/public/uploads/lab/banner/* -type d -exec cp /opt/remotelabz/public/build/images/logo/nopic.jpg {}/nopic.jpg \;

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE lab_group');
        $this->addSql('ALTER TABLE lab ADD _group_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE lab ADD CONSTRAINT FK_61D6B1C4D0949C27 FOREIGN KEY (_group_id) REFERENCES _group (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_61D6B1C4D0949C27 ON lab (_group_id)');
    }
}
