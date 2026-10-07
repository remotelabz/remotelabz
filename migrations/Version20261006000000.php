<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add QEMU network card model and minimum network interface count on device.
 */
final class Version20261006000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add device.network_card_type and device.minimum_network_interfaces';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE device ADD network_card_type VARCHAR(32) DEFAULT \'e1000\' NOT NULL, ADD minimum_network_interfaces INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE device DROP network_card_type, DROP minimum_network_interfaces');
    }
}
