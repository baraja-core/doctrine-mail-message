<?php

declare(strict_types=1);

namespace Baraja\DoctrineMailMessage;


use Baraja\Url\Url;
use Doctrine\ORM\EntityManagerInterface;
use Nette\Mail\Message;
use Nette\Utils\FileSystem;
use Nette\Utils\Strings;

final class MessageEntity
{
	private int $defaultAttachmentDirectoryMode = 0_777;


	public function __construct(
		private string $attachmentBasePath,
		private EntityManagerInterface $entityManager
	) {
	}


	public function toEntity(Message $message): DoctrineMessage
	{
		$from = $message->getFrom();
		if ($from !== null) {
			$from = Helpers::formatHeader($from);
		} else {
			if (PHP_SAPI === 'cli') {
				throw new \InvalidArgumentException('Possible problem: From is required.');
			}
			trigger_error('Possible problem: From is required.');
			$from = 'admin@' . Url::get()->getNetteUrl()->getDomain();
		}

		/** @var array<string, string|null>|null $to */
		$to = $message->getHeader('To');
		if ($to === null || $to === []) {
			trigger_error('Possible problem: Mail recipient is required.');
		}

		$primaryTo = null;
		$cc = [];
		foreach ($to ?? [] as $toKey => $toValue) {
			assert($toValue === null || is_string($toValue));
			if ($primaryTo === null) {
				$primaryTo = Helpers::formatHeader([$toKey => $toValue]);
			} else {
				$cc[] = Helpers::formatHeader([$toKey => $toValue]);
			}
		}
		if ($primaryTo === null) {
			throw new \InvalidArgumentException('Primary To does not exist.');
		}

		$return = new DoctrineMessage(
			from: $from,
			to: $primaryTo,
			subject: $message->getSubject() ?? Strings::truncate(
				trim(str_replace('*', '', strip_tags($message->getBody()))),
				128,
			),
			htmlBody: $message->getHtmlBody(),
			textBody: $message->getBody(),
		);

		/** @var array<string, string|null> $ccHeader */
		$ccHeader = $message->getHeader('Cc') ?? [];
		foreach ($ccHeader as $ccMail => $ccName) {
			$return->addCc(Helpers::formatHeader([$ccMail => $ccName]));
		}

		foreach ($cc as $ccItem) {
			$return->addCc($ccItem);
		}

		/** @var array<string, string|null> $bccHeader */
		$bccHeader = $message->getHeader('Bcc') ?? [];
		foreach ($bccHeader as $bccMail => $bccName) {
			$return->addBcc(Helpers::formatHeader([$bccMail => $bccName]));
		}

		/** @var array<string, string|null> $replyToHeader */
		$replyToHeader = $message->getHeader('Reply-To') ?? [];
		foreach ($replyToHeader as $replyToMail => $replyToName) {
			$return->addReplyTo(Helpers::formatHeader([$replyToMail => $replyToName]));
		}

		$returnPath = $message->getHeader('Return-Path');
		$priority = $message->getHeader('X-Priority') ?? 3;
		assert($returnPath === null || is_string($returnPath));
		assert(is_scalar($priority));
		$return->setReturnPath($returnPath);
		$return->setPriority((int) $priority);

		$this->entityManager->persist($return);
		$this->entityManager->getUnitOfWork()->commit($return);
		if ($message->getAttachments() !== []) {
			$this->serializeAttachments($return, $message);
			$this->entityManager->getUnitOfWork()->commit($return);
		}

		return $return;
	}


	public function toMessage(DoctrineMessage $message): Message
	{
		$return = (new Message)
			->setFrom($message->getFrom())
			->addTo($message->getTo())
			->setSubject($message->getSubject())
			->setHtmlBody(Helpers::processHtmlMail($message))
			->setPriority($message->getPriority());

		$textBody = $message->getTextBody();
		if ($textBody !== null) {
			$return->setBody($textBody);
		}
		$returnPath = $message->getReturnPath();
		if ($returnPath !== null) {
			$return->setReturnPath($returnPath);
		}
		foreach ($message->getCc() as $cc) {
			$return->addCc($cc);
		}
		foreach ($message->getBcc() as $bcc) {
			$return->addBcc($bcc);
		}
		foreach ($message->getReplyTo() as $replyTo) {
			$return->addReplyTo($replyTo);
		}

		$this->unSerializeAttachments($message, $return);

		return $return;
	}


	public function getAttachmentBasePath(): string
	{
		if (\is_dir($this->attachmentBasePath) === false) {
			throw new \RuntimeException(
				sprintf('Attachment base path "%s" does not exist. ', $this->attachmentBasePath)
				. 'Did you use DIC extension or create the directory manually?',
			);
		}

		return $this->attachmentBasePath;
	}


	public function invalidAttachmentStorage(DoctrineMessage $entity): void
	{
		FileSystem::delete($this->getAttachmentsPath($entity));
	}


	public function setDefaultAttachmentDirectoryMode(int $mode): void
	{
		$this->defaultAttachmentDirectoryMode = $mode;
	}


	private function serializeAttachments(DoctrineMessage $entity, Message $message): void
	{
		$attachments = $message->getAttachments();
		if ($attachments === []) {
			return;
		}

		$basePath = $this->getAttachmentsPath($entity);
		foreach ($attachments as $attachment) {
			$body = $attachment->getBody();
			$content = md5($body);
			FileSystem::write(sprintf('%s/%s', $basePath, $content), $body);
			$contentDisposition = $attachment->getHeader('Content-Disposition');
			$contentType = $attachment->getHeader('Content-Type');
			assert($contentDisposition === null || is_string($contentDisposition));
			assert($contentType === null || is_string($contentType));
			$entity->addAttachment(
				file: Helpers::getFileNameByContentDisposition((string) $contentDisposition),
				contentHash: $content,
				contentType: $contentType,
			);
		}
	}


	private function unSerializeAttachments(DoctrineMessage $entity, Message $message): void
	{
		$attachments = $entity->getAttachments();
		if ($attachments === []) {
			return;
		}

		$basePath = $this->getAttachmentsPath($entity);
		foreach ($attachments as $attachment) {
			$path = sprintf('%s/%s', $basePath, $attachment['content']);
			if (is_file($path) === false) {
				throw new \RuntimeException(sprintf('Attachment file does not exist, because path "%s" given.', $path));
			}
			$message->addAttachment(
				$attachment['file'],
				(string) file_get_contents($path),
				$attachment['contentType'] ?? null,
			);
		}
	}


	private function getAttachmentsPath(DoctrineMessage $entity, ?int $mode = null): string
	{
		$path = $this->getAttachmentBasePath() . '/' . $entity->getId();
		FileSystem::createDir($path, $mode ?? $this->defaultAttachmentDirectoryMode);

		return $path;
	}
}
