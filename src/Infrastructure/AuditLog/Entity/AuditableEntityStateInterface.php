<?php

declare(strict_types=1);

namespace App\Infrastructure\AuditLog\Entity;

use Symfony\Component\Uid\Uuid;

interface AuditableEntityStateInterface
{
    public Uuid $id { get; }

    /**
     * @return list<string>
     */
    public function getAuditableFields(): array;
}
