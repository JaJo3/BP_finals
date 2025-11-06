<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251011223057 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Clean up organizer table and handle foreign keys';
    }

    public function up(Schema $schema): void
    {
        // Drop ticket -> event foreign key
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY IF EXISTS FK_97A0ADA371F7E88B');
        
        // Drop event -> organizer foreign key
        $this->addSql('ALTER TABLE event DROP FOREIGN KEY IF EXISTS FK_3BAE0AA7876C4DDA');
        
        // Make column changes
        $this->addSql('ALTER TABLE organizer DROP COLUMN IF EXISTS file_name');
        $this->addSql('ALTER TABLE organizer DROP COLUMN IF EXISTS logo');
        
        // Recreate foreign keys
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA7876C4DDA FOREIGN KEY (organizer_id) REFERENCES organizer(id)');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA371F7E88B FOREIGN KEY (event_id) REFERENCES event(id)');
    }

    public function down(Schema $schema): void
    {
        // Drop foreign keys
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY IF EXISTS FK_97A0ADA371F7E88B');
        $this->addSql('ALTER TABLE event DROP FOREIGN KEY IF EXISTS FK_3BAE0AA7876C4DDA');
        
        // Restore columns
        $this->addSql('ALTER TABLE organizer ADD file_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE organizer ADD logo VARCHAR(255) DEFAULT NULL');
        
        // Restore foreign keys
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA7876C4DDA FOREIGN KEY (organizer_id) REFERENCES organizer(id)');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA371F7E88B FOREIGN KEY (event_id) REFERENCES event(id)');
    }
}