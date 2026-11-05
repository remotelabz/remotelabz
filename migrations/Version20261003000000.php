<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Store the date of the last reputation alert sent for an IP, so an alert is
 * not sent again for the same IP before the cooldown expires.
 */
final class Version20261003000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add last_alerted_at column to ip_reputation (reputation alert cooldown)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ip_reputation ADD last_alerted_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ip_reputation DROP COLUMN last_alerted_at');
    }
}
