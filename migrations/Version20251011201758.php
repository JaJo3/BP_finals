<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251011201758 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove unused logo and file_name columns';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organizer DROP COLUMN file_name, DROP COLUMN logo');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organizer ADD COLUMN file_name VARCHAR(255) DEFAULT NULL, ADD COLUMN logo VARCHAR(255) DEFAULT NULL');
    }
}