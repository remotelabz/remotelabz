<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A lab subnet can only be reserved once.
 *
 * NetworkManager::getAvailableSubnet() picks the first free /28 of the base
 * network from a snapshot of the `network` table, and the row is only written
 * when the LabInstance is flushed. Two concurrent InstanceManager::create()
 * calls (double click, scheduled batch + HTTP request) could therefore pick
 * the very same /28, and both labs would end up sharing one subnet: on the
 * front the kernel route `<CIDR> via <worker>` is keyed by the CIDR only, so
 * one of the two labs is routed to the other's worker.
 *
 * The unique index makes the loser's INSERT fail with a duplicate key error;
 * NetworkManager then re-reads the table and allocates the next /28.
 *
 * If the migration fails on a "Duplicate entry" error, real duplicate rows
 * exist (two lab instances really share a subnet — the bug this migration
 * fixes): delete the network row of the instance that must be recreated
 * before re-running it.
 */
final class Version20261118000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add unique index on network (ip__long, netmask__long) to prevent double allocation of a lab subnet';
    }

    public function up(Schema $schema): void
    {
        // Remove orphan rows first: with the new allocation flow the network
        // row is committed as soon as the subnet is picked, so a creation
        // that dies before its own flush can leave a reserved-but-unreferenced
        // row behind. Such rows must not block the unique index.
        $this->addSql(
            'DELETE FROM network
             WHERE id NOT IN (SELECT network_id FROM lab_instance WHERE network_id IS NOT NULL)'
        );

        // If duplicates remain (both referenced by lab instances), this
        // statement fails and the migration is aborted: the duplicates must
        // be resolved by hand (see class docblock).
        $this->addSql(
            "CREATE UNIQUE INDEX uniq_network_ip_netmask ON network (ip__long, netmask__long)"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_network_ip_netmask ON network');
    }
}
