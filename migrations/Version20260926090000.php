<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add support for external URL practical subjects.
 */
final class Version20260926090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add url column to practical_subject';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE practical_subject ADD url VARCHAR(512) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE practical_subject DROP url');
    }
}
