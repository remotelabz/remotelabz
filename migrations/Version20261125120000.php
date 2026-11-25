<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add the QEMU-only `usb_enabled` device option.
 *
 * The worker used to add the USB stack (`-usb`, `usb-tablet`, `usb-ehci`)
 * unconditionally in `create_qemu_device()`. Existing QEMU devices are marked
 * as enabled so their behaviour does not change; new devices default to 0.
 */
final class Version20261125120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add device.usb_enabled QEMU option';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE device ADD usb_enabled TINYINT(1) DEFAULT 0 NOT NULL'
        );

        $this->addSql(
            "UPDATE device d
                INNER JOIN hypervisor h ON h.id = d.hypervisor_id
                SET d.usb_enabled = 1
                WHERE LOWER(h.name) = 'qemu'"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE device DROP usb_enabled');
    }
}
