<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20181219151234 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE connexion DROP FOREIGN KEY FK_936BF99C45F33077');
        $this->addSql('ALTER TABLE connexion DROP FOREIGN KEY FK_936BF99C57469F99');
        $this->addSql('DROP INDEX IDX_936BF99C57469F99 ON connexion');
        $this->addSql('DROP INDEX IDX_936BF99C45F33077 ON connexion');
        $this->addSql('ALTER TABLE connexion DROP device1_id, DROP device2_id');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE connexion ADD device1_id INT DEFAULT NULL, ADD device2_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE connexion ADD CONSTRAINT FK_936BF99C45F33077 FOREIGN KEY (device2_id) REFERENCES device (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE connexion ADD CONSTRAINT FK_936BF99C57469F99 FOREIGN KEY (device1_id) REFERENCES device (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_936BF99C57469F99 ON connexion (device1_id)');
        $this->addSql('CREATE INDEX IDX_936BF99C45F33077 ON connexion (device2_id)');
    }
}
