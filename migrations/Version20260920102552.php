<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260920102552 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename ip_reputation unique index to match Doctrine naming strategy';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ip_reputation RENAME INDEX uniq_ipreputation_ip TO UNIQ_60832C09A5E3B32D');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ip_reputation RENAME INDEX uniq_60832c09a5e3b32d TO UNIQ_IpReputation_ip');
    }
}
