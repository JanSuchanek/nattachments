<?php

declare(strict_types=1);

namespace NAttachments;

/**
 * Factory for creating AttachmentsControl instances.
 * Register as a DI service.
 */
final class AttachmentsControlFactory
{
	public function create(
		string $entityType,
		int $entityId,
		string $uploadDir,
		string $webPath,
	): AttachmentsControl {
		return new AttachmentsControl($entityType, $entityId, $uploadDir, $webPath);
	}
}
