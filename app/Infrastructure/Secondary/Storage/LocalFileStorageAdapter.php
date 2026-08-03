<?php

namespace App\Infrastructure\Secondary\Storage;

use App\Core\Application\Ports\Outbound\FileStorageServiceInterface;
use Illuminate\Support\Facades\Storage;

/**
 * Secondary adapter implementing local storage service using Laravel Storage Facade.
 */
class LocalFileStorageAdapter implements FileStorageServiceInterface
{
    private string $disk;

    public function __construct(string $disk = 'local')
    {
        $this->disk = $disk;
    }

    /**
     * Stores an NFe XML file in the local storage disk.
     */
    public function salvarXml(string $chaveNFe, string $conteudoXml): string
    {
        $path = "nfe/xml/{$chaveNFe}.xml";
        Storage::disk($this->disk)->put($path, $conteudoXml);

        return $path;
    }

    /**
     * Stores an NFe PDF (DANFE) file in the local storage disk.
     */
    public function salvarPdf(string $chaveNFe, string $conteudoPdf): string
    {
        $path = "nfe/pdf/{$chaveNFe}.pdf";
        Storage::disk($this->disk)->put($path, $conteudoPdf);

        return $path;
    }
}
