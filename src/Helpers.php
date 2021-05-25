<?php

declare(strict_types=1);

namespace Baraja\DoctrineMailMessage;


final class Helpers
{
	/** @throws \Error */
	public function __construct()
	{
		throw new \Error('Class ' . static::class . ' is static and cannot be instantiated.');
	}


	public static function getFileNameByContentDisposition(string $haystack): string
	{
		$haystack = trim((string) preg_replace('/\s+/', '', $haystack));
		if ($haystack === '') {
			throw new \InvalidArgumentException('Attachment file name is empty.');
		}
		$return = trim((string) preg_replace('/^.*filename="([^"]+?)(\.[^".]+)?"/', '$1$2', $haystack));
		if ($return === '') {
			throw new \InvalidArgumentException(
				'Header "Content-Disposition" is invalid, '
				. 'because string "' . $haystack . '" does not match required filename format.',
			);
		}

		return $return;
	}


	/**
	 * @param mixed[]|null $header
	 */
	public static function formatHeader(?array $header): string
	{
		if ($header === null) {
			return '';
		}
		foreach ($header as $mail => $name) {
			if ($mail !== null) {
				return $name === null
					? trim((string) $mail)
					: trim((string) $name) . ' <' . trim((string) $mail) . '>';
			}
		}

		return '';
	}


	public static function processHtmlMail(DoctrineMessage $entity): string
	{
		$pairHtml = '<div style="color:white;font-size:1pt" id="pair__token">'
			. htmlspecialchars($entity->getId() . '_' . date('Y-m-d'), ENT_QUOTES)
			. '</div>';

		$body = (string) ($entity->getHtmlBody() ?? $entity->getTextBody());
		if (str_contains($body, '</body>')) {
			return str_replace('</body>', $pairHtml . '</body>', $body);
		}

		return $body . $pairHtml;
	}
}
