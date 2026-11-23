<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add the QEMU-only device options:
 *   - `qemu_agent`: expose the QEMU guest agent (virtio-serial channel);
 *   - `cpu_host`: start QEMU with `-cpu host` instead of `-cpu max`.
 *
 * Both default to 0 so existing devices keep their current behaviour.
 */
final class Version20261123120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add device.qemu_agent and device.cpu_host QEMU options';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE device
                ADD qemu_agent TINYINT(1) DEFAULT 0 NOT NULL,
                ADD cpu_host TINYINT(1) DEFAULT 0 NOT NULL'
        );

        // Preserve the current behaviour for existing QEMU devices: the worker
        // used to expose the guest agent unconditionally. New devices keep the
        // default (unchecked).
        $this->addSql(
            "UPDATE device d
                INNER JOIN hypervisor h ON h.id = d.hypervisor_id
                SET d.qemu_agent = 1
                WHERE LOWER(h.name) = 'qemu'"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE device DROP qemu_agent, DROP cpu_host');
    }
}
