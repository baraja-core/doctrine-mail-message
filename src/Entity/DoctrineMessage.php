<?php

declare(strict_types=1);

namespace Baraja\DoctrineMailMessage;


use Doctrine\ORM\Mapping as ORM;
use Nette\Mail\Message;

#[ORM\Entity]
#[ORM\Table(name: 'core__email_message')]
#[ORM\Index(columns: ['id', 'subject'], name: 'core__email_message_subject')]
class DoctrineMessage
{
	#[ORM\Id]
	#[ORM\Column(type: 'integer', unique: true, options: ['unsigned' => true])]
	#[ORM\GeneratedValue]
	protected int $id;

	#[ORM\Column(name: '`from`', type: 'string')]
	private string $from;

	#[ORM\Column(name: '`to`', type: 'string')]
	private string $to;

	#[ORM\Column(type: 'string')]
	private string $subject;

	#[ORM\Column(type: 'text', nullable: true)]
	private ?string $htmlBody;

	#[ORM\Column(type: 'text', nullable: true)]
	private ?string $textBody;

	/** @var array<int, string> */
	#[ORM\Column(type: 'json')]
	private array $cc = [];

	/** @var array<int, string> */
	#[ORM\Column(type: 'json')]
	private array $bcc = [];

	/** @var array<int, string> */
	#[ORM\Column(type: 'json')]
	private array $replyTo = [];

	#[ORM\Column(type: 'string', nullable: true)]
	private ?string $returnPath = null;

	#[ORM\Column(type: 'smallint')]
	private int $priority = Message::NORMAL;

	/** @var array<int, array{file: string, content: string, contentType: string|null}> */
	#[ORM\Column(type: 'json')]
	private array $attachments = [];


	public function __construct(
		string $from,
		string $to,
		string $subject,
		?string $htmlBody = null,
		?string $textBody = null,
	) {
		$this->setFrom($from);
		$this->setTo($to);
		$this->setSubject($subject);
		$this->setHtmlBody($htmlBody);
		$this->setTextBody($textBody);
	}


	public function getId(): int
	{
		return $this->id;
	}


	public function __toString(): string
	{
		return $this->getHtmlBody() ?? $this->getTextBody() ?? '';
	}


	public function getFrom(): string
	{
		return $this->from;
	}


	public function setFrom(string $from): void
	{
		$this->from = $from;
	}


	public function getTo(): string
	{
		return $this->to;
	}


	public function setTo(string $to): void
	{
		$this->to = $to;
	}


	/** @return array<int, string> */
	public function getCc(): array
	{
		return $this->cc;
	}


	public function addCc(string $cc): void
	{
		$this->cc[] = $cc;
		$this->cc = \array_unique($this->cc);
	}


	/** @return array<int, string> */
	public function getBcc(): array
	{
		return $this->bcc;
	}


	public function addBcc(string $bcc): void
	{
		$this->bcc[] = $bcc;
		$this->bcc = \array_unique($this->bcc);
	}


	/** @return array<int, string> */
	public function getReplyTo(): array
	{
		return $this->replyTo;
	}


	public function addReplyTo(?string $replyTo): void
	{
		if ($replyTo === null) {
			return;
		}
		$this->replyTo[] = $replyTo;
		$this->replyTo = \array_unique($this->replyTo);
	}


	public function getReturnPath(): ?string
	{
		return $this->returnPath;
	}


	public function setReturnPath(?string $returnPath): void
	{
		$this->returnPath = $returnPath;
	}


	public function getPriority(): int
	{
		return $this->priority;
	}


	public function setPriority(int $priority): void
	{
		if ($priority < 0) {
			$priority = 0;
		}
		if ($priority > 100) {
			$priority = 100;
		}

		$this->priority = $priority;
	}


	public function getSubject(): string
	{
		return $this->subject;
	}


	public function setSubject(string $subject): void
	{
		$this->subject = trim($subject);
	}


	public function getHtmlBody(): ?string
	{
		return $this->htmlBody;
	}


	public function setHtmlBody(?string $htmlBody): void
	{
		$htmlBody = trim($htmlBody ?? '');
		if ($htmlBody === '') {
			$htmlBody = null;
		}
		$this->htmlBody = $htmlBody;
	}


	public function getTextBody(): ?string
	{
		return $this->textBody;
	}


	public function setTextBody(?string $textBody): void
	{
		if ($textBody !== null) {
			$textBody = implode("\n", array_map(static fn(string $line): string => trim($line), explode("\n", str_replace(["\r\n", "\r"], "\n", $textBody))));
			$textBody = trim($textBody);
			if ($textBody === '') {
				$textBody = null;
			}
		}
		$this->textBody = $textBody;
	}


	public function isAttachments(): bool
	{
		return $this->attachments !== [];
	}


	/** @return array<int, array{file: string, content: string, contentType: string|null}> */
	public function getAttachments(): array
	{
		foreach ($this->attachments as $attachment) {
			/** @phpstan-ignore-next-line */
			if (isset($attachment['file'], $attachment['content']) === false) {
				throw new \RuntimeException('Attachment record is broken, because "' . \json_encode($attachment, JSON_THROW_ON_ERROR) . '" given.');
			}
		}

		return $this->attachments;
	}


	public function addAttachment(string $file, string $contentHash, ?string $contentType = null): void
	{
		if (preg_match('/^[\da-f]{32}$/', $contentHash) !== 1) {
			throw new \InvalidArgumentException(sprintf('Content hash "%s" is not valid MD5 hash.', $contentHash));
		}

		$this->attachments[] = [
			'file' => $file,
			'content' => $contentHash,
			'contentType' => $contentType,
		];
	}
}
