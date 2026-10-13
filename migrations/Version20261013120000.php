<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Track the date of the last state change of a device instance, so the
 * reconcile watchdog can detect devices stuck in "starting" (worker never
 * replied to the ACTION_START message).
 */
final class Version20261013120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add device_instance.state_changed_at (last state change, used to reconcile devices stuck in starting)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE device_instance ADD state_changed_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE device_instance DROP state_changed_at');
    }
}
