<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251211165010 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ticket_purchase (id INT AUTO_INCREMENT NOT NULL, transaction_id INT NOT NULL, ticket_id INT NOT NULL, qr_code VARCHAR(255) NOT NULL, unique_ticket_code VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_E47E2DBA2FC0CB0F (transaction_id), INDEX IDX_E47E2DBA700047D2 (ticket_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE ticket_purchase ADD CONSTRAINT FK_E47E2DBA2FC0CB0F FOREIGN KEY (transaction_id) REFERENCES transactions (id)');
        $this->addSql('ALTER TABLE ticket_purchase ADD CONSTRAINT FK_E47E2DBA700047D2 FOREIGN KEY (ticket_id) REFERENCES ticket (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ticket_purchase DROP FOREIGN KEY FK_E47E2DBA2FC0CB0F');
        $this->addSql('ALTER TABLE ticket_purchase DROP FOREIGN KEY FK_E47E2DBA700047D2');
        $this->addSql('DROP TABLE ticket_purchase');
    }
}
