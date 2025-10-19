<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251017201152 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user_permission table for storing granular user permissions';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('
            CREATE TABLE user_permission (
                id INT AUTO_INCREMENT NOT NULL,
                user_id INT NOT NULL,
                scope VARCHAR(50) NOT NULL,
                access VARCHAR(50) DEFAULT NULL,
                entity VARCHAR(50) DEFAULT NULL,
                action JSON DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT \'(DC2Type:datetime_immutable)\',
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT \'(DC2Type:datetime_immutable)\',
                INDEX idx_user_scope (user_id, scope),
                UNIQUE KEY uniq_user_scope_access_entity (user_id, scope, access, entity),
                PRIMARY KEY(id))
            DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user_permission ADD CONSTRAINT FK_2AF83BD7A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_permission DROP FOREIGN KEY FK_2AF83BD7A76ED395');
        $this->addSql('DROP TABLE user_permission');
    }
}
