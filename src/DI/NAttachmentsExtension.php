<?php

declare(strict_types=1);

namespace NAttachments\DI;

use NAttachments\AttachmentsControlFactory;
use Nette\DI\CompilerExtension;

/**
 * Nette DI Extension for automatic service registration.
 *
 * Usage in config.neon:
 *   extensions:
 *       attachments: NAttachments\DI\NAttachmentsExtension
 */
final class NAttachmentsExtension extends CompilerExtension
{
	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();

		$builder->addDefinition($this->prefix('factory'))
			->setFactory(AttachmentsControlFactory::class);
	}
}
