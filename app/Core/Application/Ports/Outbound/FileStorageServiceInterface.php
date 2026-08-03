<?php

namespace App\Core\Application\Ports\Outbound;

/**
 * Port interface for file storage operations (XML, PDF documents).
 */
interface FileStorageServiceInterface
{
    /**
     * Stores an XML file and returns its stored relative path or URI.
     */
    public function salvarXml(string $chaveNFe, string $conteudoXml): string;

    /**
     * Stores a PDF file and returns its stored relative path or URI.
     */
    public function salvarPdf(string $chaveNFe, string $conteudoPdf): string;
}
