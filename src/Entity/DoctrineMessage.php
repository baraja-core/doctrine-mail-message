<?php

declare(strict_types=1);

namespace Baraja\DoctrineMailMessage;


use Baraja\Doctrine\UUID\UuidIdentifier;
use Doctrine\ORM\Mapping as ORM;
use Nette\Mail\Message;

/**
 * @ORM\Entity()
 * @ORM\Table(name="core__email_message")
 */
class DoctrineMessage
{
	use UuidIdentifier;

	/**
	 * @var string
	 * @ORM\Column(type="string", name="`from`")
	 */
	private $from;

	/**
	 * @var string
	 * @ORM\Column(type="string", name="`to`")
	 */
	private $to;

	/**
	 * @var string
	 * @ORM\Column(type="string")
	 */
	private $subject;

	/**
	 * @var string|null
	 * @ORM\Column(type="text", nullable=true)
	 */
	private $htmlBody;

	/**
	 * @var string|null
	 * @ORM\Column(type="text", nullable=true)
	 */
	private $textBody;

	/**
	 * @var string[]
	 * @ORM\Column(type="json_array")
	 */
	private $cc = [];

	/**
	 * @var string[]
	 * @ORM\Column(type="json_array")
	 */
	private $bcc = [];

	/**
	 * @var string[]
	 * @ORM\Column(type="json_array")
	 */
	private $replyTo = [];

	/**
	 * @var string|null
	 * @ORM\Column(type="string", nullable=true)
	 */
	private $returnPath;

	/**
	 * @var int
	 * @ORM\Column(type="smallint")
	 */
	private $priority = Message::NORMAL;

	/**
	 * Format:
	 * [ {"file": "hello.txt", "content": "hash", "contentType": "text/plain"}, ... ]
	 *
	 * @var string[][]|null[][]
	 * @ORM\Column(type="json_array")
	 */
	private $attachments = [];


	public function __construct(string $from, string $to, string $subject, ?string $htmlBody = null, ?string $textBody = null)
	{
		$this->from = $from;
		$this->to = $to;
		$this->subject = $subject;
		$this->htmlBody = $htmlBody;
		$this->textBody = $textBody;
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


	/**
	 * @return string[]
	 */
	public function getCc(): array
	{
		return $this->cc;
	}


	public function addCc(string $cc): void
	{
		$this->cc[] = $cc;
		$this->cc = \array_unique($this->cc);
	}


	/**
	 * @return string[]
	 */
	public function getBcc(): array
	{
		return $this->bcc;
	}


	public function addBcc(string $bcc): void
	{
		$this->bcc[] = $bcc;
		$this->bcc = \array_unique($this->bcc);
	}


	/**
	 * @return string[]
	 */
	public function getReplyTo(): array
	{
		return $this->replyTo;
	}


	public function addReplyTo(?string $replyTo): void
	{
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
		$this->htmlBody = $htmlBody;
	}


	public function getTextBody(): ?string
	{
		return $this->textBody;
	}


	public function setTextBody(?string $textBody): void
	{
		$this->textBody = $textBody;
	}


	public function isAttachments(): bool
	{
		return $this->attachments !== [];
	}


	/**
	 * @return string[][]|null[][]
	 */
	public function getAttachments(): array
	{
		return $this->attachments;
	}


	public function addAttachment(string $file, string $contentHash, ?string $contentType = null): void
	{
		if (!preg_match('/^[\da-f]{32}$/', $contentHash)) {
			throw new \InvalidArgumentException('Content hash "' . $contentHash . '" is not valid MD5 hash.');
		}

		$this->attachments[] = [
			'file' => $file,
			'content' => $contentHash,
			'contentType' => $contentType,
		];
	}
}
