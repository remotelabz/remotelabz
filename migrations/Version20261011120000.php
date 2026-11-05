<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add the hidden flag on device (hidden devices are only visible to the lab
 * author and the system admins).
 */
final class Version20261011120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add device.hidden (device hidden from the other users of the lab)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE device ADD hidden TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE device DROP hidden');
    }
}
