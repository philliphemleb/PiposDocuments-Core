<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260420174635 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Create email_verification_tokens table';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS email_verification_tokens (
                id UUID NOT NULL,
                user_id UUID NOT NULL,
                token VARCHAR(64) NOT NULL,
                expires_at TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL,
                created_at TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL,
                dispatched_at TIMESTAMP(6) WITHOUT TIME ZONE,
                sent_at TIMESTAMP(6) WITHOUT TIME ZONE,
                send_attempts INTEGER NOT NULL DEFAULT 0,
                PRIMARY KEY(id)
            )
            SQL);

        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_C81CA2AC5F37A13B ON email_verification_tokens (token)
            SQL);

        $this->addSql(<<<'SQL'
            CREATE INDEX IF NOT EXISTS IDX_C81CA2ACA76ED395 ON email_verification_tokens (user_id)
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE email_verification_tokens ADD CONSTRAINT FK_C81CA2ACA76ED395
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE
            SQL);
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE IF EXISTS email_verification_tokens DROP CONSTRAINT IF EXISTS FK_C81CA2ACA76ED395
            SQL);

        $this->addSql(<<<'SQL'
            DROP TABLE IF EXISTS email_verification_tokens
            SQL);
    }
}
