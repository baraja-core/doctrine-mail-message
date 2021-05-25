<?php

declare(strict_types=1);

namespace Baraja\DoctrineMailMessage;


use Baraja\Doctrine\ORM\DI\OrmAnnotationsExtension;
use Nette\DI\CompilerExtension;

final class DoctrineMailMessageExtension extends CompilerExtension
{
	/**
	 * @return string[]
	 */
	public static function mustBeDefinedBefore(): array
	{
		if (\class_exists('\Baraja\Doctrine\ORM\DI\OrmAnnotationsExtension')) {
			return [OrmAnnotationsExtension::class];
		}

		return [];
	}


	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();
		if (\class_exists('\Baraja\Doctrine\ORM\DI\OrmAnnotationsExtension')) {
			OrmAnnotationsExtension::addAnnotationPathToManager(
				$builder,
				'Baraja\DoctrineMailMessage',
				__DIR__ . '/Entity',
			);
		}

		if (isset($builder->parameters['tempDir']) === false) {
			throw new \RuntimeException(
				'System parameter "tempDir" is required. Please check your project configuration.',
			);
		}

		$this->createDir($builder->parameters['tempDir'] . '/emailer-attachments');

		$builder->addDefinition($this->prefix('messageEntity'))
			->setFactory(MessageEntity::class)
			->setArguments(
				[
					'attachmentBasePath' => $builder->parameters['tempDir'],
				]
			);
	}


	/**
	 * Creates a directory if it doesn't exist.
	 */
	private function createDir(string $dir, int $mode = 0_777): void
	{
		if (!is_dir($dir) && !@mkdir($dir, $mode, true) && !is_dir($dir)) { // @ - dir may already exist
			throw new \RuntimeException("Unable to create directory '$dir' with mode " . decoct($mode) . '.');
		}
	}
}
