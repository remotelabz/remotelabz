<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260918000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ip_reputation table for AbuseIPDB IP reputation data';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ip_reputation (id INT AUTO_INCREMENT NOT NULL, ip VARCHAR(45) NOT NULL, abuse_score INT DEFAULT NULL, total_reports INT DEFAULT NULL, country_code VARCHAR(2) DEFAULT NULL, last_checked_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_IpReputation_ip (ip), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE ip_reputation');
    }
}
