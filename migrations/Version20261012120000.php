<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Labs are Internet-authorized by default: every existing lab is flagged
 * is_internet_authorized = true and new labs inherit the default.
 */
final class Version20261012120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Set lab.is_internet_authorized to true by default (all existing labs)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE lab SET is_internet_authorized = 1');
        $this->addSql("ALTER TABLE lab ALTER is_internet_authorized SET DEFAULT 1");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE lab ALTER is_internet_authorized SET DEFAULT 0");
    }
}
