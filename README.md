# Doctrine Mail Message

![Integrity check](https://github.com/baraja-core/doctrine-mail-message/workflows/Integrity%20check/badge.svg)

A robust PHP library that provides bidirectional conversion between Nette Mail messages and Doctrine entities. This package allows you to persist email messages in a database, enabling email queuing, logging, retry mechanisms, and comprehensive email history tracking.

## ✨ Key Features

- **Bidirectional Conversion** - Seamlessly convert `Nette\Mail\Message` to Doctrine entity and back
- **Full Email Support** - Handles all standard email fields: From, To, CC, BCC, Reply-To, Return-Path, Priority
- **Attachment Management** - Automatic serialization and storage of file attachments with content-hash based deduplication
- **Tracking Support** - Automatic injection of pair tokens for email tracking and pairing
- **Nette Integration** - First-class support for Nette Framework via DI extension
- **Type Safety** - Full PHP 8.0+ type declarations with PHPStan level 9 verification

## 🏗️ Architecture Overview

The library follows a clean, service-oriented architecture with clear separation of concerns:

```
┌─────────────────────────────────────────────────────────────────┐
│                      Your Application                           │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                       MessageEntity                             │
│  ┌─────────────────┐              ┌─────────────────────────┐  │
│  │   toEntity()    │─────────────▶│    DoctrineMessage      │  │
│  │   toMessage()   │◀─────────────│    (Doctrine Entity)    │  │
│  └─────────────────┘              └─────────────────────────┘  │
│           │                                    │                │
│           ▼                                    ▼                │
│  ┌─────────────────┐              ┌─────────────────────────┐  │
│  │     Helpers     │              │   Attachment Storage    │  │
│  │ (Header Format) │              │   (File System)         │  │
│  └─────────────────┘              └─────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                     Nette\Mail\Message                          │
│                   (Standard Mail Object)                        │
└─────────────────────────────────────────────────────────────────┘
```

## 🧩 Components

### DoctrineMessage Entity

The core Doctrine entity (`DoctrineMessage`) maps to the `core__email_message` database table and stores:

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Auto-generated primary key |
| `from` | string | Sender email address |
| `to` | string | Primary recipient |
| `subject` | string | Email subject line |
| `htmlBody` | text (nullable) | HTML content of the email |
| `textBody` | text (nullable) | Plain text content |
| `cc` | json | Array of CC recipients |
| `bcc` | json | Array of BCC recipients |
| `replyTo` | json | Array of Reply-To addresses |
| `returnPath` | string (nullable) | Return path for bounces |
| `priority` | smallint | Email priority (default: normal) |
| `attachments` | json | Attachment metadata array |

### MessageEntity Service

The main service class responsible for:

- Converting `Nette\Mail\Message` objects to `DoctrineMessage` entities via `toEntity()`
- Converting `DoctrineMessage` entities back to `Nette\Mail\Message` via `toMessage()`
- Managing attachment serialization/deserialization
- Automatic entity persistence during conversion

### Helpers

A static utility class providing:

- `formatHeader()` - Formats email headers from various array formats to string representation
- `getFileNameByContentDisposition()` - Extracts filename from Content-Disposition header
- `processHtmlMail()` - Injects tracking pair tokens into HTML email body

### DI Extension

The `DoctrineMailMessageExtension` provides automatic integration with Nette Framework:

- Registers entity paths for Doctrine ORM annotation discovery
- Creates attachment storage directory in temp path
- Registers `MessageEntity` as a service

## 📦 Installation

It's best to use [Composer](https://getcomposer.org) for installation, and you can also find the package on
[Packagist](https://packagist.org/packages/baraja-core/doctrine-mail-message) and
[GitHub](https://github.com/baraja-core/doctrine-mail-message).

To install, simply use the command:

```shell
$ composer require baraja-core/doctrine-mail-message
```

You can use the package manually by creating an instance of the internal classes, or register a DIC extension to link the services directly to the Nette Framework.

### Requirements

- PHP 8.0 or higher
- Doctrine ORM 2.7+
- Doctrine DBAL 3.2+
- Nette Framework 3.0+ (for DI integration)

## ⚙️ Configuration

### Nette Framework Integration

Register the extension in your NEON configuration file:

```neon
extensions:
    doctrineMailMessage: Baraja\DoctrineMailMessage\DoctrineMailMessageExtension
```

The extension will automatically:
1. Register the entity path for Doctrine annotations
2. Create the `emailer-attachments` directory in your temp folder
3. Register the `MessageEntity` service in the DI container

### Manual Configuration

If not using Nette Framework, create the service manually:

```php
use Baraja\DoctrineMailMessage\MessageEntity;
use Doctrine\ORM\EntityManagerInterface;

$messageEntity = new MessageEntity(
    attachmentBasePath: '/path/to/temp/emailer-attachments',
    entityManager: $entityManager, // Your EntityManagerInterface instance
    logger: $logger, // Optional PSR-3 LoggerInterface
);
```

## 🚀 Basic Usage

### Converting Mail Message to Entity

```php
use Nette\Mail\Message;
use Baraja\DoctrineMailMessage\MessageEntity;

// Create a standard Nette Mail message
$message = new Message;
$message->setFrom('sender@example.com', 'Sender Name')
    ->addTo('recipient@example.com', 'Recipient Name')
    ->setSubject('Hello World')
    ->setHtmlBody('<p>This is an <b>HTML</b> email.</p>')
    ->setBody('This is a plain text email.');

// Convert to Doctrine entity (automatically persisted)
/** @var MessageEntity $messageEntity */
$doctrineMessage = $messageEntity->toEntity($message);

// Entity is now persisted and has an ID
echo $doctrineMessage->getId(); // e.g., 42
```

### Converting Entity Back to Mail Message

```php
use Baraja\DoctrineMailMessage\DoctrineMessage;

// Load entity from database
$doctrineMessage = $entityManager->find(DoctrineMessage::class, 42);

// Convert back to Nette Mail Message
$message = $messageEntity->toMessage($doctrineMessage);

// Now you can send it via any Nette mailer
$mailer->send($message);
```

### Working with Attachments

Attachments are automatically handled during conversion:

```php
$message = new Message;
$message->setFrom('sender@example.com')
    ->addTo('recipient@example.com')
    ->setSubject('Document Attached')
    ->setHtmlBody('<p>Please find the document attached.</p>')
    ->addAttachment('document.pdf', $pdfContent, 'application/pdf');

// Attachments are serialized to filesystem
$doctrineMessage = $messageEntity->toEntity($message);

// When converting back, attachments are restored
$restoredMessage = $messageEntity->toMessage($doctrineMessage);
```

Attachment files are stored in the configured temp directory with content-hash based naming for deduplication.

### Handling CC, BCC, and Reply-To

```php
$message = new Message;
$message->setFrom('sender@example.com')
    ->addTo('primary@example.com')
    ->addCc('cc1@example.com')
    ->addCc('cc2@example.com')
    ->addBcc('bcc@example.com')
    ->addReplyTo('reply@example.com')
    ->setSubject('Multi-recipient Email');

$doctrineMessage = $messageEntity->toEntity($message);

// Access stored recipients
echo $doctrineMessage->getTo();           // primary@example.com
print_r($doctrineMessage->getCc());       // ['cc1@example.com', 'cc2@example.com']
print_r($doctrineMessage->getBcc());      // ['bcc@example.com']
print_r($doctrineMessage->getReplyTo());  // ['reply@example.com']
```

### Email Priority

```php
use Nette\Mail\Message;

$message = new Message;
$message->setPriority(Message::HIGH); // HIGH, NORMAL, or LOW

$doctrineMessage = $messageEntity->toEntity($message);
echo $doctrineMessage->getPriority(); // 1 (HIGH)
```

### Cleaning Up Attachment Storage

After successfully sending an email or when no longer needed:

```php
// Remove attachment files for a specific message
$messageEntity->invalidAttachmentStorage($doctrineMessage);
```

### Custom Attachment Directory Mode

```php
// Set custom directory permissions (default is 0777)
$messageEntity->setDefaultAttachmentDirectoryMode(0755);
```

## 🔍 Email Tracking

The library automatically injects a hidden tracking token into HTML emails when converting from entity to message:

```html
<div style="color:white;font-size:1pt" id="pair__token">42_2024-01-15</div>
```

This token contains the message ID and date, enabling:
- Email open tracking (when combined with tracking pixel)
- Pairing sent emails with responses
- Email delivery verification

## 📋 Database Schema

The entity creates the following table structure:

```sql
CREATE TABLE core__email_message (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `from` VARCHAR(255) NOT NULL,
    `to` VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    html_body LONGTEXT,
    text_body LONGTEXT,
    cc JSON NOT NULL,
    bcc JSON NOT NULL,
    reply_to JSON NOT NULL,
    return_path VARCHAR(255),
    priority SMALLINT NOT NULL DEFAULT 3,
    attachments JSON NOT NULL,
    INDEX core__email_message_subject (id, subject)
);
```

## ⚠️ Error Handling

The library provides clear error messages for common issues:

- **Missing From Address**: Triggers error in web context, throws exception in CLI
- **Missing Recipient**: Triggers warning for potential issues
- **Invalid Attachment Path**: Throws `RuntimeException` or logs via PSR-3 logger
- **Missing Temp Directory**: Throws `RuntimeException` with configuration hints

## 🧪 Testing

Run static analysis:

```shell
composer phpstan
```

## 👤 Author

**Jan Barášek**
- Website: [https://baraja.cz](https://baraja.cz)
- GitHub: [https://github.com/baraja-core](https://github.com/baraja-core)

## 📄 License

`baraja-core/doctrine-mail-message` is licensed under the MIT license. See the [LICENSE](https://github.com/baraja-core/doctrine-mail-message/blob/master/LICENSE) file for more details.
