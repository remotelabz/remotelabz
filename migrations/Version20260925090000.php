<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add the PracticalSubject entity and the Lab <-> PracticalSubject relation.
 *
 * A practical subject is educational content (markdown or pdf) that can be
 * shared by several labs. Existing labs with a description get a dedicated
 * practical subject migrated from that description, so existing data stays
 * compatible.
 */
final class Version20260925090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add practical_subject table and lab_practical_subject relation, migrate lab descriptions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE practical_subject (id INT AUTO_INCREMENT NOT NULL, 
        author_id INT DEFAULT NULL, 
        uuid VARCHAR(255) NOT NULL, 
        name VARCHAR(255) NOT NULL, 
        description LONGTEXT DEFAULT NULL, 
        content_type VARCHAR(20) DEFAULT \'markdown\' NOT NULL, 
        pdf_filename VARCHAR(255) DEFAULT NULL, 
        created_at DATETIME NOT NULL, 
        last_updated DATETIME DEFAULT NULL, 
        INDEX IDX_PR_SUBJ_AUTHOR (author_id), PRIMARY KEY(id)
        )
        DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE lab_practical_subject (
        lab_id INT NOT NULL, 
        practical_subject_id INT NOT NULL,
        INDEX IDX_LP_SUBJ_LAB (lab_id), 
        INDEX IDX_LP_SUBJ_SUBJECT (practical_subject_id), 
        PRIMARY KEY(lab_id, practical_subject_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE practical_subject ADD CONSTRAINT FK_PR_SUBJ_AUTHOR FOREIGN KEY (author_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE lab_practical_subject ADD CONSTRAINT FK_LP_SUBJ_LAB FOREIGN KEY (lab_id) REFERENCES lab (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE lab_practical_subject ADD CONSTRAINT FK_LP_SUBJ_SUBJECT FOREIGN KEY (practical_subject_id) REFERENCES practical_subject (id) ON DELETE CASCADE');

        // Migrate existing lab descriptions into practical subjects
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, name, description, author_id FROM lab WHERE description IS NOT NULL AND TRIM(description) <> ''"
        );
        foreach ($rows as $row) {
            $uuid = sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                random_int(0, 0xffff),
                random_int(0, 0xffff),
                random_int(0, 0xffff),
                random_int(0, 0x0fff) | 0x4000,
                random_int(0, 0x3fff) | 0x8000,
                random_int(0, 0xffff),
                random_int(0, 0xffff),
                random_int(0, 0xffff)
            );
            $now = date('Y-m-d H:i:s');
            $this->connection->insert('practical_subject', [
                'uuid' => $uuid,
                'name' => $row['name'].' - Sujet',
                'description' => $row['description'],
                'content_type' => 'markdown',
                'author_id' => $row['author_id'],
                'created_at' => $now,
                'last_updated' => $now,
            ]);
            $this->connection->insert('lab_practical_subject', [
                'lab_id' => $row['id'],
                'practical_subject_id' => $this->connection->lastInsertId(),
            ]);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE practical_subject DROP FOREIGN KEY FK_PR_SUBJ_AUTHOR');
        $this->addSql('ALTER TABLE lab_practical_subject DROP FOREIGN KEY FK_LP_SUBJ_LAB');
        $this->addSql('ALTER TABLE lab_practical_subject DROP FOREIGN KEY FK_LP_SUBJ_SUBJECT');
        $this->addSql('DROP TABLE practical_subject');
        $this->addSql('DROP TABLE lab_practical_subject');
        // Migrated lab descriptions are kept in the lab.description column, nothing to restore.
    }
}
