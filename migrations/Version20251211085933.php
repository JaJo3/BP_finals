<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251211085933 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE event ADD created_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA7B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_3BAE0AA7B03A8386 ON event (created_by_id)');
        $this->addSql('ALTER TABLE organizer ADD created_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE organizer ADD CONSTRAINT FK_99D47173B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_99D47173B03A8386 ON organizer (created_by_id)');
        $this->addSql('ALTER TABLE ticket ADD created_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA3B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_97A0ADA3B03A8386 ON ticket (created_by_id)');
        $this->addSql('ALTER TABLE transactions ADD created_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT FK_EAA81A4CB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_EAA81A4CB03A8386 ON transactions (created_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA3B03A8386');
        $this->addSql('DROP INDEX IDX_97A0ADA3B03A8386 ON ticket');
        $this->addSql('ALTER TABLE ticket DROP created_by_id');
        $this->addSql('ALTER TABLE organizer DROP FOREIGN KEY FK_99D47173B03A8386');
        $this->addSql('DROP INDEX IDX_99D47173B03A8386 ON organizer');
        $this->addSql('ALTER TABLE organizer DROP created_by_id');
        $this->addSql('ALTER TABLE transactions DROP FOREIGN KEY FK_EAA81A4CB03A8386');
        $this->addSql('DROP INDEX IDX_EAA81A4CB03A8386 ON transactions');
        $this->addSql('ALTER TABLE transactions DROP created_by_id');
        $this->addSql('ALTER TABLE event DROP FOREIGN KEY FK_3BAE0AA7B03A8386');
        $this->addSql('DROP INDEX IDX_3BAE0AA7B03A8386 ON event');
        $this->addSql('ALTER TABLE event DROP created_by_id');
    }
}
