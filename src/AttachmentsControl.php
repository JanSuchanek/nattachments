<?php

declare(strict_types=1);

namespace NAttachments;

use Nette\Application\UI\Control;
use Nette\Http\FileUpload;

/**
 * Universal file attachments component with upload, delete, reorder, rename, download.
 * Entity-agnostic — communicates via callback arrays.
 *
 * Usage:
 *   $ctrl = $this->attachmentsFactory->create('page', $pageId, $wwwDir.'/uploads/pages/', '/uploads/pages/');
 *   $ctrl->onLoadAttachments[] = fn() => $this->pageRepo->findAttachments($pageId);
 *   $ctrl->onSaveAttachment[] = fn($data) => $this->pageRepo->addAttachment($pageId, $data);
 *   ...
 */
final class AttachmentsControl extends Control
{
	/** Max file size in bytes (10 MB) */
	private const MAX_FILE_SIZE = 10 * 1024 * 1024;

	/**
	 * Callback: () => array<object{id, filename, originalName, displayName, filesize, position}>
	 * @var list<callable(): array>
	 */
	public array $onLoadAttachments = [];

	/**
	 * Callback: (array{filename: string, originalName: string, filesize: int}) => void
	 * @var list<callable(array): void>
	 */
	public array $onSaveAttachment = [];

	/**
	 * Callback: (int $fileId) => ?object{filename: string}  — return attachment or null
	 * @var list<callable(int): ?object>
	 */
	public array $onGetAttachment = [];

	/**
	 * Callback: (int $fileId) => void
	 * @var list<callable(int): void>
	 */
	public array $onDeleteAttachment = [];

	/**
	 * Callback: (array<int> $orderedIds) => void
	 * @var list<callable(array): void>
	 */
	public array $onReorderAttachments = [];

	/**
	 * Callback: (int $fileId, string $displayName) => void
	 * @var list<callable(int, string): void>
	 */
	public array $onRenameAttachment = [];


	/**
	 * @param string $entityType  Identifier for the entity (e.g. 'page', 'product')
	 * @param int    $entityId    Entity primary key
	 * @param string $uploadDir   Absolute filesystem path for uploads (e.g. /var/www/uploads/pages/123/)
	 * @param string $webPath     Web-accessible path prefix (e.g. /uploads/pages/123/)
	 */
	public function __construct(
		private readonly string $entityType,
		private readonly int $entityId,
		private readonly string $uploadDir,
		private readonly string $webPath,
	) {
	}


	public function render(): void
	{
		$attachments = [];
		foreach ($this->onLoadAttachments as $cb) {
			$attachments = $cb();
		}

		$template = $this->template;
		$template->setFile(__DIR__ . '/../templates/attachments.latte');
		$template->attachments = $attachments;
		$template->entityType = $this->entityType;
		$template->entityId = $this->entityId;
		$template->webPath = $this->webPath;
		$template->render();
	}


	/**
	 * AJAX or standard file upload.
	 */
	public function handleUpload(): void
	{
		if (!is_dir($this->uploadDir)) {
			mkdir($this->uploadDir, 0755, true);
		}

		$httpRequest = $this->getPresenter()->getHttpRequest();
		$allFiles = $httpRequest->getFiles();

		$fileUploads = [];
		array_walk_recursive($allFiles, function ($item) use (&$fileUploads): void {
			if ($item instanceof FileUpload && $item->isOk()) {
				$fileUploads[] = $item;
			}
		});

		$uploaded = 0;
		foreach ($fileUploads as $file) {
			if ($file->getSize() > self::MAX_FILE_SIZE) {
				continue;
			}

			$origName = $file->getSanitizedName();
			$safeName = time() . '_' . bin2hex(random_bytes(4)) . '_' . $origName;
			$file->move($this->uploadDir . $safeName);

			foreach ($this->onSaveAttachment as $cb) {
				$cb([
					'filename' => $safeName,
					'originalName' => $origName,
					'filesize' => $file->getSize(),
				]);
			}

			$uploaded++;
		}

		if ($uploaded > 0) {
			$this->presenter->flashMessage("Nahráno $uploaded soubor(ů)", 'success');
		}

		if ($this->presenter->isAjax()) {
			$this->redrawControl('attachmentsList');
			$this->presenter->redrawControl('flashes');
		} else {
			$this->redirect('this');
		}
	}


	/**
	 * Delete attachment file + DB record.
	 */
	public function handleDelete(int $fileId): void
	{
		// Get attachment info to delete the physical file
		$att = null;
		foreach ($this->onGetAttachment as $cb) {
			$att = $cb($fileId);
		}

		if ($att) {
			$path = $this->uploadDir . $att->filename;
			if (file_exists($path)) {
				unlink($path);
			}

			foreach ($this->onDeleteAttachment as $cb) {
				$cb($fileId);
			}

			$this->presenter->flashMessage('Soubor smazán', 'success');
		}

		if ($this->presenter->isAjax()) {
			$this->redrawControl('attachmentsList');
			$this->presenter->redrawControl('flashes');
		} else {
			$this->redirect('this');
		}
	}


	/**
	 * Reorder attachments via drag & drop (AJAX).
	 */
	public function handleReorder(): void
	{
		$httpRequest = $this->getPresenter()->getHttpRequest();
		$orderJson = $httpRequest->getPost('order');

		if (is_string($orderJson)) {
			$order = json_decode($orderJson, true);
			if (is_array($order)) {
				foreach ($this->onReorderAttachments as $cb) {
					$cb($order);
				}
			}
		}

		$this->presenter->sendJson(['status' => 'ok']);
	}


	/**
	 * Inline rename via AJAX (blur/Enter save).
	 */
	public function handleRename(int $fileId): void
	{
		$httpRequest = $this->getPresenter()->getHttpRequest();
		$name = trim((string) $httpRequest->getPost('display_name'));

		if ($name !== '' && $fileId > 0) {
			foreach ($this->onRenameAttachment as $cb) {
				$cb($fileId, $name);
			}
		}

		$this->presenter->sendJson(['status' => 'ok']);
	}


	/**
	 * Download / serve a file inline.
	 */
	public function handleDownload(int $fileId): void
	{
		$att = null;
		foreach ($this->onGetAttachment as $cb) {
			$att = $cb($fileId);
		}

		if (!$att) {
			$this->presenter->error('Soubor nenalezen', 404);
		}

		$path = $this->uploadDir . $att->filename;
		if (!file_exists($path)) {
			$this->presenter->error('Soubor nenalezen', 404);
		}

		$response = $this->presenter->getHttpResponse();
		$response->setContentType(mime_content_type($path) ?: 'application/octet-stream');
		$response->setHeader('Content-Disposition', 'inline; filename="' . ($att->originalName ?? $att->filename) . '"');
		$response->setHeader('Content-Length', (string) filesize($path));
		$response->setHeader('Cache-Control', 'max-age=86400, public');

		readfile($path);
		exit;
	}
}
