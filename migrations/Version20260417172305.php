<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260417172305 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Create users, user_locks, banned_identifiers, and entity_state_audit_logs tables';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS users (
                id UUID NOT NULL,
                email VARCHAR(255) NOT NULL,
                role VARCHAR(255) NOT NULL,
                status VARCHAR(255) NOT NULL,
                created_at TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
            SQL);

        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_1483A5E9E7927C74 ON users (email)
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS user_locks (
                id UUID NOT NULL,
                user_id UUID NOT NULL,
                locked_at TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL,
                valid_until TIMESTAMP(6) WITHOUT TIME ZONE DEFAULT NULL,
                reason VARCHAR(255) NOT NULL,
                locked_by_id UUID NOT NULL,
                created_at TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
            SQL);

        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_1EF396CFA76ED395 ON user_locks (user_id)
            SQL);

        $this->addSql(<<<'SQL'
            CREATE INDEX IF NOT EXISTS IDX_1EF396CF7A88E00 ON user_locks (locked_by_id)
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE user_locks ADD CONSTRAINT FK_1EF396CFA76ED395
                FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE user_locks ADD CONSTRAINT FK_1EF396CF7A88E00
                FOREIGN KEY (locked_by_id) REFERENCES users (id) NOT DEFERRABLE
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS banned_identifiers (
                id UUID NOT NULL,
                email VARCHAR(255) NOT NULL,
                banned_at TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL,
                reason VARCHAR(255) NOT NULL,
                created_at TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
            SQL);

        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_928B9ABAE7927C74 ON banned_identifiers (email)
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS entity_state_audit_logs (
                id UUID NOT NULL,
                entity_type VARCHAR(255) NOT NULL,
                entity_id UUID NOT NULL,
                old_state VARCHAR(255) NOT NULL,
                new_state VARCHAR(255) NOT NULL,
                changed_by VARCHAR(255) NOT NULL,
                changed_at TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL,
                reason VARCHAR(255) NOT NULL,
                created_at TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
            SQL);

        $this->addSql(<<<'SQL'
            CREATE INDEX IF NOT EXISTS IDX_ENTITY_STATE_AUDIT_LOG_ENTITY ON entity_state_audit_logs (entity_type, entity_id)
            SQL);
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE IF EXISTS user_locks DROP CONSTRAINT IF EXISTS FK_1EF396CFA76ED395
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE IF EXISTS user_locks DROP CONSTRAINT IF EXISTS FK_1EF396CF7A88E00
            SQL);

        $this->addSql(<<<'SQL'
            DROP TABLE IF EXISTS entity_state_audit_logs
            SQL);

        $this->addSql(<<<'SQL'
            DROP TABLE IF EXISTS banned_identifiers
            SQL);

        $this->addSql(<<<'SQL'
            DROP TABLE IF EXISTS user_locks
            SQL);

        $this->addSql(<<<'SQL'
            DROP TABLE IF EXISTS users
            SQL);
    }
}
