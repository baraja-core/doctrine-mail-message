<?php

declare(strict_types=1);

namespace Baraja\DoctrineMailMessage;


use Baraja\Doctrine\EntityManager;
use Nette\Mail\Message;
use Nette\Utils\FileSystem;
use Nette\Utils\Strings;

final class MessageEntity
{

	/** @var EntityManager */
	private $entityManager;

	/** @var string */
	private $attachmentBasePath;

	/** @var int */
	private $defaultAttachmentDirectoryMode = 0777;


	public function __construct(string $attachmentBasePath, EntityManager $entityManager)
	{
		$this->attachmentBasePath = $attachmentBasePath;
		$this->entityManager = $entityManager;
	}


	public function toEntity(Message $message): DoctrineMessage
	{
		if (($from = ($from = $message->getFrom()) ? Helpers::formatHeader($from) : null) === null) {
			throw new \InvalidArgumentException('Possible problem: From is required.');
		}
		if (!($to = $message->getHeader('To'))) {
			throw new \InvalidArgumentException('Possible problem: Mail recipient is required.');
		}

		$primaryTo = null;
		$cc = [];
		foreach ($to as $toKey => $toValue) {
			if ($primaryTo === null) {
				$primaryTo = Helpers::formatHeader([$toKey => $toValue]);
			} else {
				$cc[] = Helpers::formatHeader([$toKey => $toValue]);
			}
		}

		$return = new DoctrineMessage(
			$from,
			$primaryTo,
			$message->getSubject() ?? Strings::truncate(
				trim(str_replace('*', '', strip_tags($message->getBody() ?: ''))), 128
			),
			$message->getHtmlBody() ?: null,
			$message->getBody() ?: null
		);

		foreach ($message->getHeader('Cc') ?? [] as $ccMail => $ccName) {
			$return->addCc(Helpers::formatHeader([$ccMail => $ccName]));
		}
		foreach ($cc as $ccItem) {
			$return->addCc($ccItem);
		}
		foreach ($message->getHeader('Bcc') ?? [] as $bccMail => $bccName) {
			$return->addBcc(Helpers::formatHeader([$bccMail => $bccName]));
		}
		foreach ($message->getHeader('Reply-To') ?? [] as $replyToMail => $replyToMail) {
			$return->addReplyTo(Helpers::formatHeader([$replyToMail => $replyToMail]));
		}

		$return->setReturnPath($message->getHeader('Return-Path'));
		$return->setPriority((int) $message->getHeader('X-Priority'));

		$this->entityManager->persist($return);
		$this->serializeAttachments($return, $message);
		$this->entityManager->flush($return);

		return $return;
	}


	public function toMessage(DoctrineMessage $message): Message
	{
		$return = (new Message)
			->setFrom($message->getFrom())
			->addTo($message->getTo())
			->setSubject($message->getSubject())
			->setHtmlBody(Helpers::processHtmlMail($message))
			->setBody($message->getTextBody())
			->setReturnPath($message->getReturnPath())
			->setPriority($message->getPriority());

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
				'Attachment base path "' . $this->attachmentBasePath . '" does not exist. '
				. 'Did you use DIC extension or create the directory manually?'
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
		if (($attachments = $message->getAttachments()) === []) {
			return;
		}

		$basePath = $this->getAttachmentsPath($entity);
		foreach ($attachments as $attachment) {
			$content = md5($body = $attachment->getBody());
			FileSystem::write($basePath . '/' . $content, $body);
			$entity->addAttachment(Helpers::getFileNameByContentDisposition((string) $attachment->getHeader('Content-Disposition')), $content, $attachment->getHeader('Content-Type'));
		}
	}


	private function unSerializeAttachments(DoctrineMessage $entity, Message $message): void
	{
		if (($attachments = $entity->getAttachments()) === []) {
			return;
		}

		$basePath = $this->getAttachmentsPath($entity);
		foreach ($attachments as $attachment) {
			if (isset($attachment['file'], $attachment['content']) === false) {
				throw new \RuntimeException('Attachment record is broken, because "' . \json_encode($attachment) . '" given.');
			}
			if (is_file($path = $basePath . '/' . $attachment['content']) === false) {
				throw new \RuntimeException('Attachment file does not exist, because path "' . $path . '" given.');
			}
			$message->addAttachment($attachment['file'], file_get_contents($path), $attachment['contentType'] ?? null);
		}
	}


	private function getAttachmentsPath(DoctrineMessage $entity, ?int $mode = null): string
	{
		if (!($id = $entity->getId())) {
			throw new \LogicException('Doctrine entity with Message must be persisted with valid scalar ID.');
		}

		FileSystem::createDir($path = $this->getAttachmentBasePath() . '/' . $id, $mode ?? $this->defaultAttachmentDirectoryMode);

		return $path;
	}
}