<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251014055850 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ticket DROP description');
        $this->addSql('ALTER TABLE ticket RENAME INDEX idx_97a0ada3f4c7b3a4 TO IDX_97A0ADA3AF98A08E');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ticket ADD description LONGTEXT NOT NULL');
        $this->addSql('ALTER TABLE ticket RENAME INDEX idx_97a0ada3af98a08e TO IDX_97A0ADA3F4C7B3A4');
    }
}
