<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251014103636 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove ticket_tier relationship from ticket table';
    }

    public function up(Schema $schema): void
    {
        // Remove only the ticket_tier relationship
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA3AF98A08E');
        $this->addSql('DROP INDEX IDX_97A0ADA3AF98A08E ON ticket');
        $this->addSql('ALTER TABLE ticket DROP ticket_tier_id');
    }

    public function down(Schema $schema): void
    {
        // Restore the ticket_tier relationship
        $this->addSql('ALTER TABLE ticket ADD ticket_tier_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA3AF98A08E FOREIGN KEY (ticket_tier_id) REFERENCES ticket_tier (id)');
        $this->addSql('CREATE INDEX IDX_97A0ADA3AF98A08E ON ticket (ticket_tier_id)');
    }
}
