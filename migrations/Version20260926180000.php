<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Assign the super administrator as author of devices created
 * without an author (e.g. installation fixtures).
 */
final class Version20260926180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Set super administrator as author of devices without author';
    }

    public function up(Schema $schema): void
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id FROM user WHERE roles LIKE '%ROLE_SUPER_ADMINISTRATOR%' LIMIT 1"
        );

        if (count($rows) === 1) {
            $this->connection->executeStatement(
                'UPDATE device SET author_id = ? WHERE author_id IS NULL',
                [$rows[0]['id']]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id FROM user WHERE roles LIKE '%ROLE_SUPER_ADMINISTRATOR%' LIMIT 1"
        );

        if (count($rows) === 1) {
            $this->connection->executeStatement(
                'UPDATE device SET author_id = NULL WHERE author_id = ?',
                [$rows[0]['id']]
            );
        }
    }
}
