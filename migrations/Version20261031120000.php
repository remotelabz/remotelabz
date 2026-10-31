<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * text_object.data becomes a LONGTEXT.
 *
 * The column used to be a VARCHAR(1500), which could not hold the HTML of the
 * larger EVE-NG annotations (the "txt 13" annotation of
 * `doc/PNet-Lab/VRF-BGP-Final.unl` already reaches ~2 KB). Widening the column
 * lets the EVE-NG importer store the full annotation instead of clipping it.
 */
final class Version20261031120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Widen text_object.data to LONGTEXT (EVE-NG annotations)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE text_object CHANGE data data LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE text_object CHANGE data data VARCHAR(1500) DEFAULT NULL');
    }
}
