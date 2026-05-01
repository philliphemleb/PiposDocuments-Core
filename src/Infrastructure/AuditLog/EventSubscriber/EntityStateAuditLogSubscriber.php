<?php

declare(strict_types=1);

namespace App\Infrastructure\AuditLog\EventSubscriber;

use App\Infrastructure\AuditLog\Entity\AuditableEntityStateInterface;
use App\Infrastructure\AuditLog\Entity\EntityStateAuditLog;
use App\Infrastructure\AuditLog\Service\AuditLogContext;
use BackedEnum;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Stringable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsDoctrineListener(event: 'onFlush')]
readonly class EntityStateAuditLogSubscriber
{
    public function __construct(
        private AuditLogContext $auditLogContext,
        #[Autowire(service: 'monolog.logger.audit_log')]
        private LoggerInterface $auditLogger,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $unitOfWork = $em->getUnitOfWork();

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof AuditableEntityStateInterface) {
                continue;
            }

            $changeSet = $unitOfWork->getEntityChangeSet($entity);
            $auditableFields = $entity->getAuditableFields();

            foreach ($auditableFields as $field) {
                if (!isset($changeSet[$field])) {
                    continue;
                }

                $oldState = $this->stringifyValue($changeSet[$field][0]);
                $newState = $this->stringifyValue($changeSet[$field][1]);

                $auditLog = new EntityStateAuditLog(
                    entityType: new ReflectionClass($entity)->getShortName(),
                    entityId: $entity->id,
                    oldState: $oldState,
                    newState: $newState,
                    changedBy: $this->auditLogContext->actor ?? 'manual_change',
                    reason: $this->auditLogContext->reason ?? '',
                );

                $em->persist($auditLog);
                $unitOfWork->computeChangeSet(
                    $em->getClassMetadata(EntityStateAuditLog::class),
                    $auditLog,
                );

                $this->auditLogger->info(\sprintf(
                    '[%s] %s %s: %s -> %s (by %s, reason: %s)',
                    new ReflectionClass($entity)->getShortName(),
                    $entity->id->toRfc4122(),
                    $field,
                    $oldState,
                    $newState,
                    $this->auditLogContext->actor ?? 'manual_change',
                    $this->auditLogContext->reason ?? '',
                ));
            }
        }
    }

    private function stringifyValue(mixed $value): string
    {
        if (null === $value) {
            return 'null';
        }

        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if (\is_bool($value)) {
            return true === $value ? 'true' : 'false';
        }

        if (\is_scalar($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        return get_debug_type($value);
    }
}
