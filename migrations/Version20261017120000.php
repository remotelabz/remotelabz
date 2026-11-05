<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Make LoginLog fully independent from User.
 *
 * Login log entries used to keep a foreign key (user_id) to the User entity.
 * This made it impossible to delete a user who had already logged in, because
 * of the foreign key constraint. The first name and last name of the user are
 * now copied directly into the login_log table and the relation (foreign key
 * and column) is removed.
 *
 * Existing logs are preserved: first_name / last_name are backfilled from the
 * related user before the relation is dropped.
 */
final class Version20261017120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'LoginLog: copy first_name/last_name from user and drop the user_id foreign key';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE login_log ADD first_name VARCHAR(255) DEFAULT NULL, ADD last_name VARCHAR(255) DEFAULT NULL');

        // Backfill the copied values from the related user for existing logs.
        $this->addSql('UPDATE login_log l INNER JOIN user u ON u.id = l.user_id SET l.first_name = u.first_name, l.last_name = u.last_name');

        // Drop the foreign key / index and the now useless column.
        $this->addSql('ALTER TABLE login_log DROP FOREIGN KEY FK_F16D9FFFA76ED395');
        $this->addSql('DROP INDEX IDX_F16D9FFFA76ED395 ON login_log');
        $this->addSql('ALTER TABLE login_log DROP user_id');
    }

    public function down(Schema $schema): void
    {
        // Recreate the relation. Logs whose user has been deleted in the
        // meantime cannot be linked again: user_id stays NULL for those.
        $this->addSql('ALTER TABLE login_log ADD user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE login_log ADD CONSTRAINT FK_F16D9FFFA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_F16D9FFFA76ED395 ON login_log (user_id)');

        $this->addSql('UPDATE login_log l INNER JOIN user u ON u.first_name = l.first_name AND u.last_name = l.last_name SET l.user_id = u.id');

        $this->addSql('ALTER TABLE login_log DROP first_name, DROP last_name');
    }
}
