<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240320153156 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE device DROP delay, DROP count, DROP postfix, DROP status, DROP ethernet');
        $this->addSql('ALTER TABLE lab DROP tasks, DROP version, DROP scripttimeout');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE device ADD delay INT DEFAULT 0 NOT NULL, ADD count INT DEFAULT NULL, ADD postfix INT DEFAULT NULL, ADD status INT DEFAULT 0 NOT NULL, ADD ethernet INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE lab ADD tasks LONGTEXT DEFAULT NULL, ADD version VARCHAR(10) DEFAULT \'1\' NOT NULL, ADD scripttimeout INT DEFAULT 300 NOT NULL');
    }
}
