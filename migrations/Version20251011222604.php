<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251011222604 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Clean up organizer table structure';
    }

    public function up(Schema $schema): void
    {
        // Drop ticket -> event foreign key first
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA371F7E88B');
        
        // Drop event -> organizer foreign key
        $this->addSql('ALTER TABLE event DROP FOREIGN KEY FK_3BAE0AA7876C4DDA');
        
        // Remove unused columns
        $this->addSql('ALTER TABLE organizer DROP COLUMN IF EXISTS file_name');
        $this->addSql('ALTER TABLE organizer DROP COLUMN IF EXISTS logo');
        
        // Recreate event -> organizer foreign key
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA7876C4DDA FOREIGN KEY (organizer_id) REFERENCES organizer(id)');
        
        // Recreate ticket -> event foreign key
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA371F7E88B FOREIGN KEY (event_id) REFERENCES event(id)');
    }

    public function down(Schema $schema): void
    {
        // Drop foreign keys in reverse order
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA371F7E88B');
        $this->addSql('ALTER TABLE event DROP FOREIGN KEY FK_3BAE0AA7876C4DDA');
        
        // Add columns back
        $this->addSql('ALTER TABLE organizer ADD COLUMN file_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE organizer ADD COLUMN logo VARCHAR(255) DEFAULT NULL');
        
        // Recreate foreign keys in reverse order
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA7876C4DDA FOREIGN KEY (organizer_id) REFERENCES organizer(id)');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA371F7E88B FOREIGN KEY (event_id) REFERENCES event(id)');
    }
}