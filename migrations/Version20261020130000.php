<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * device.config_data becomes a LONGTEXT.
 *
 * The column used to be a VARCHAR(255), which could not hold the startup
 * configurations decoded from an EVE-NG import (a single vios startup-config
 * already reaches ~2 KB). Widening the column lets the EVE-NG importer store
 * the full configuration of a device.
 */
final class Version20261020130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Widen device.config_data to LONGTEXT (EVE-NG startup configurations)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE device CHANGE config_data config_data LONGTEXT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE device CHANGE config_data config_data VARCHAR(255) NOT NULL');
    }
}
