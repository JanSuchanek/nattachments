# NAttachments

File attachment system for Nette Framework — upload, manage, and associate files with Doctrine entities.

## Features

- 📎 **File Uploads** — Drag & drop with preview
- 🖼️ **Image Thumbnails** — Auto-generated previews
- 🔗 **Entity Association** — Link files to any Doctrine entity
- 🗑️ **CRUD** — Rename, reorder, delete with AJAX
- ⚙️ **DI Extension** — Auto-registers upload services

## Installation

```bash
composer require jansuchanek/nattachments
```

## Configuration

```neon
extensions:
    attachments: NAttachments\DI\NAttachmentsExtension

attachments:
    uploadDir: %wwwDir%/uploads
```

## Usage

In your presenter:

```php
#[Inject]
public AttachmentControlFactory $attachmentFactory;

protected function createComponentAttachments(): AttachmentControl
{
    return $this->attachmentFactory->create($this->entity);
}
```

In your Latte template:

```latte
{control attachments}
```

## Requirements

- PHP >= 8.2
- Nette Application ^3.2
- Doctrine ORM ^3.0

## License

MIT
