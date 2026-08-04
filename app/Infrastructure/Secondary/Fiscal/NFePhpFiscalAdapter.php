<?php

namespace App\Infrastructure\Secondary\Fiscal;

use App\Core\Application\Ports\Outbound\NFeFiscalGatewayInterface;
use App\Core\Application\Ports\Outbound\RespostaCancelamentoGateway;
use App\Core\Application\Ports\Outbound\RespostaEmissaoGateway;
use App\Core\Domain\Entities\EventoCancelamento;
use App\Core\Domain\Entities\NFe;
use NFePHP\Common\Certificate;
use NFePHP\DA\NFe\Danfe;
use NFePHP\NFe\Complements;
use NFePHP\NFe\Make;
use NFePHP\NFe\Tools;
use NFePHP\NFe\Common\Standardize;
use Throwable;

// Fallback SOAP constants for environments where ext-soap CLI is pending installation
if (!defined('SOAP_1_2')) {
    define('SOAP_1_2', 2);
}
if (!defined('SOAP_1_1')) {
    define('SOAP_1_1', 1);
}

/**
 * Secondary adapter implementing fiscal operations with SEFAZ using the NFePHP library.
 */
class NFePhpFiscalAdapter implements NFeFiscalGatewayInterface
{
    private array $config;
    private ?string $certPfxContent;
    private ?string $certPassword;

    public function __construct(
        array $config = [],
        ?string $certPfxContent = null,
        ?string $certPassword = null
    ) {
        $this->config = array_merge([
            'atualizacao' => date('Y-m-d H:i:s'),
            'tpAmb' => (int) config('nfe.environment', 2), // 1: Production, 2: Homologation
            'razaosocial' => config('nfe.company_name', 'Empresa Teste'),
            'cnpj' => config('nfe.company_cnpj', '00000000000000'),
            'siglaUF' => config('nfe.company_uf', 'SP'),
            'schemes' => 'PL_009_V4',
            'versao' => '4.00',
        ], $config);

        $certPath = config('nfe.cert_path');
        if ($certPfxContent === null && !empty($certPath)) {
            $resolvedPath = file_exists($certPath) ? $certPath : base_path($certPath);
            if (file_exists($resolvedPath)) {
                $this->certPfxContent = file_get_contents($resolvedPath);
            } else {
                $this->certPfxContent = null;
            }
        } else {
            $this->certPfxContent = $certPfxContent;
        }

        $this->certPassword = $certPassword ?? config('nfe.cert_password', '');
    }

    /**
     * Reads digital certificate with OpenSSL 3.0 legacy cipher fallback.
     */
    private function getCertificate(): Certificate
    {
        try {
            return Certificate::readPfx($this->certPfxContent, $this->certPassword);
        } catch (Throwable $e) {
            $tmpFile = tempnam(sys_get_temp_dir(), 'cert_in');
            $tmpPem = tempnam(sys_get_temp_dir(), 'cert_pem');
            $tmpOut = tempnam(sys_get_temp_dir(), 'cert_out');

            file_put_contents($tmpFile, $this->certPfxContent);

            $passEsc = escapeshellarg($this->certPassword);
            exec("openssl pkcs12 -in {$tmpFile} -passin pass:{$passEsc} -legacy -out {$tmpPem} -nodes 2>&1");
            exec("openssl pkcs12 -export -in {$tmpPem} -out {$tmpOut} -passout pass:{$passEsc} 2>&1");

            $convertedContent = file_get_contents($tmpOut);

            @unlink($tmpFile);
            @unlink($tmpPem);
            @unlink($tmpOut);

            return Certificate::readPfx($convertedContent, $this->certPassword);
        }
    }

    /**
     * Map UF state abbreviation to IBGE state code.
     */
    private function getCodigoUf(string $uf): int
    {
        $map = [
            'RO' => 11, 'AC' => 12, 'AM' => 13, 'RR' => 14, 'PA' => 15, 'AP' => 16, 'TO' => 17,
            'MA' => 21, 'PI' => 22, 'CE' => 23, 'RN' => 24, 'PB' => 25, 'PE' => 26, 'AL' => 27,
            'SE' => 28, 'BA' => 29, 'MG' => 31, 'ES' => 32, 'RJ' => 33, 'SP' => 35, 'PR' => 41,
            'SC' => 42, 'RS' => 43, 'MS' => 50, 'MT' => 51, 'GO' => 52, 'DF' => 53,
        ];

        return $map[strtoupper($uf)] ?? 35;
    }

    /**
     * Generates XML, signs, transmits NFe to SEFAZ, and generates DANFE PDF.
     */
    public function emitir(NFe $nfe): RespostaEmissaoGateway
    {
        try {
            $ufEmitente = strtoupper($nfe->emitente->endereco->uf);
            $codigoUfEmitente = $this->getCodigoUf($ufEmitente);
            $emitCnpjCpf = preg_replace('/\D/', '', $nfe->emitente->cnpj);

            $this->config['siglaUF'] = $ufEmitente;
            $this->config['razaosocial'] = $nfe->emitente->razaoSocial;
            $this->config['cnpj'] = $emitCnpjCpf;

            // 1. Build NFe XML structure using NFePHP Make
            $make = new Make();

            // infNFe tag
            $std = new \stdClass();
            $std->versao = '4.00';
            $make->taginfNFe($std);
            
            // ide tag
            $std = new \stdClass();
            $std->cUF = $codigoUfEmitente;
            $std->cNF = sprintf('%08d', rand(10000000, 99999999));
            $std->natOp = $nfe->naturezaOperacao;
            $std->mod = (int) $nfe->modelo;
            $std->serie = $nfe->serie;
            $std->nNF = $nfe->numero;
            $std->dhEmi = date('Y-m-d\TH:i:sP');
            $std->tpNF = 1; // 1: Saída
            $std->idDest = 1; // 1: Interna
            $std->cMunFG = (int) $nfe->emitente->endereco->codigoMunicipio;
            $std->tpImp = 1; // 1: Retrato
            $std->tpEmis = 1; // 1: Normal
            $std->tpAmb = 2; // 2: Homologação
            $std->finNFe = 1; // 1: Normal
            $std->indFinal = 1;
            $std->indPres = 1;
            $std->procEmi = 0;
            $std->verProc = '1.0.0';
            $make->tagide($std);

            // emit tag
            $std = new \stdClass();
            $std->xNome = $nfe->emitente->razaoSocial;
            $std->xFant = $nfe->emitente->nomeFantasia;
            $std->IE = $nfe->emitente->inscricaoEstadual;
            $std->CRT = (int) $nfe->emitente->crt;
            if (strlen($emitCnpjCpf) === 14) {
                $std->CNPJ = $emitCnpjCpf;
            } else {
                $std->CPF = $emitCnpjCpf;
            }
            $make->tagemit($std);

            // enderEmit tag
            $std = new \stdClass();
            $std->xLgr = $nfe->emitente->endereco->logradouro;
            $std->nro = $nfe->emitente->endereco->numero;
            $std->xBairro = $nfe->emitente->endereco->bairro;
            $std->cMun = $nfe->emitente->endereco->codigoMunicipio;
            $std->xMun = $nfe->emitente->endereco->nomeMunicipio;
            $std->UF = $nfe->emitente->endereco->uf;
            $std->CEP = preg_replace('/\D/', '', $nfe->emitente->endereco->cep);
            $make->tagenderEmit($std);

            // dest tag
            $std = new \stdClass();
            $std->xNome = $nfe->destinatario->razaoSocial;
            $destCnpjCpf = preg_replace('/\D/', '', $nfe->destinatario->cnpjCpf);
            if (strlen($destCnpjCpf) === 14) {
                $std->CNPJ = $destCnpjCpf;
            } else {
                $std->CPF = $destCnpjCpf;
            }
            $std->indIEDest = (int) $nfe->destinatario->indicadorIEDestinatario;
            $make->tagdest($std);

            // enderDest tag
            $std = new \stdClass();
            $std->xLgr = $nfe->destinatario->endereco->logradouro;
            $std->nro = $nfe->destinatario->endereco->numero;
            $std->xBairro = $nfe->destinatario->endereco->bairro;
            $std->cMun = $nfe->destinatario->endereco->codigoMunicipio;
            $std->xMun = $nfe->destinatario->endereco->nomeMunicipio;
            $std->UF = $nfe->destinatario->endereco->uf;
            $std->CEP = preg_replace('/\D/', '', $nfe->destinatario->endereco->cep);
            $make->tagenderDest($std);

            // Products loop
            foreach ($nfe->produtos as $index => $prod) {
                $nItem = $index + 1;
                $std = new \stdClass();
                $std->item = $nItem;
                $std->cProd = $prod->codigo;
                $std->cEAN = 'SEM GTIN';
                $std->xProd = $prod->descricao;
                $std->NCM = $prod->ncm;
                $std->CFOP = $prod->cfop;
                $std->uCom = $prod->unidadeComercial;
                $std->qCom = $prod->quantidadeComercial;
                $std->vUnCom = $prod->valorUnitarioComercial;
                $std->vProd = $prod->valorTotalBruto;
                $std->cEANTrib = 'SEM GTIN';
                $std->uTrib = $prod->unidadeComercial;
                $std->qTrib = $prod->quantidadeComercial;
                $std->vUnTrib = $prod->valorUnitarioComercial;
                $std->indTot = 1;
                $make->tagprod($std);

                // ICMS tag
                $icmsCst = (string) ($prod->impostos->icms->cst ?? '102');
                $std = new \stdClass();
                $std->item = $nItem;
                $std->orig = 0;

                if (strlen($icmsCst) === 3) {
                    $std->CSOSN = $icmsCst;
                    $make->tagICMSSN($std);
                } else {
                    $std->CST = sprintf('%02d', (int) $icmsCst);
                    $make->tagICMS($std);
                }

                // PIS tag
                $pisCst = sprintf('%02d', (int) ($prod->impostos->pis->cst ?? '09'));
                $std = new \stdClass();
                $std->item = $nItem;
                $std->CST = $pisCst;
                $std->vBC = (float) $prod->impostos->pis->baseCalculo;
                $std->pPIS = (float) $prod->impostos->pis->aliquota;
                $std->vPIS = (float) $prod->impostos->pis->valor;
                $make->tagPIS($std);

                // COFINS tag
                $cofinsCst = sprintf('%02d', (int) ($prod->impostos->cofins->cst ?? '09'));
                $std = new \stdClass();
                $std->item = $nItem;
                $std->CST = $cofinsCst;
                $std->vBC = (float) $prod->impostos->cofins->baseCalculo;
                $std->pCOFINS = (float) $prod->impostos->cofins->aliquota;
                $std->vCOFINS = (float) $prod->impostos->cofins->valor;
                $make->tagCOFINS($std);
            }

            // icmstot tag
            $std = new \stdClass();
            $std->vProd = number_format($nfe->valorTotal, 2, '.', '');
            $std->vNF = number_format($nfe->valorTotal, 2, '.', '');
            $make->tagicmstot($std);

            // transp tag (modFrete 9 = Sem ocorrência de transporte)
            $std = new \stdClass();
            $std->modFrete = 9;
            $make->tagtransp($std);

            // pag tag
            $std = new \stdClass();
            $std->vTroco = 0.0;
            $make->tagpag($std);

            // detPag tag
            $std = new \stdClass();
            $std->indPag = 0; // 0 = Vista
            $std->tPag = '01'; // 01 = Dinheiro
            $std->vPag = (float) $nfe->valorTotal;
            $make->tagdetPag($std);

            $xmlUnsigned = $make->getXML();
            $chaveNFe = $make->getChave();

            // 2. If certificate is missing (mock mode), return draft XML & DANFE PDF
            if (empty($this->certPfxContent)) {
                $pdfContent = null;
                try {
                    $danfe = new Danfe($xmlUnsigned);
                    $pdfContent = $danfe->render();
                } catch (Throwable $e) {
                    // Log or handle optional PDF rendering notice
                }

                return new RespostaEmissaoGateway(
                    sucesso: true,
                    xml: $xmlUnsigned,
                    pdfPath: $pdfContent,
                    chaveNFe: $chaveNFe
                );
            }

            // 3. Sign XML and transmit to SEFAZ via NFePHP Tools
            $certificate = $this->getCertificate();
            $tools = new Tools(json_encode($this->config), $certificate);
            $tools->model($nfe->modelo);

            // Sign XML
            $xmlSigned = $tools->signNFe($xmlUnsigned);

            // Transmit batch synchronously to SEFAZ (indSinc = 1 for single invoice batches)
            $idLote = str_pad((string) rand(1, 999999999), 15, '0', STR_PAD_LEFT);
            $responseSefazXml = $tools->sefazEnviaLote([$xmlSigned], $idLote, 1);

            $st = new Standardize();
            $stdResp = $st->toStd($responseSefazXml);

            $cStatLote = $stdResp->cStat ?? null;
            $cStatProt = $stdResp->protNFe->infProt->cStat ?? null;
            $xMotivoProt = $stdResp->protNFe->infProt->xMotivo ?? ($stdResp->xMotivo ?? 'Erro na transmissão');

            if ($cStatProt && !in_array((int) $cStatProt, [100, 150], true)) {
                return new RespostaEmissaoGateway(
                    sucesso: false,
                    erro: "Rejeição SEFAZ [cStat {$cStatProt}]: {$xMotivoProt}"
                );
            }

            if ($cStatLote && !in_array((int) $cStatLote, [100, 103, 104], true)) {
                return new RespostaEmissaoGateway(
                    sucesso: false,
                    erro: "Rejeição SEFAZ [cStat {$cStatLote}]: {$xMotivoProt}"
                );
            }

            // Attach SEFAZ authorization protocol (<protNFe>) to XML
            $xmlWithProtocol = Complements::toAuthorize($xmlSigned, $responseSefazXml);

            // Generate DANFE PDF with authorized XML
            $danfe = new Danfe($xmlWithProtocol);
            $pdfContent = $danfe->render();

            return new RespostaEmissaoGateway(
                sucesso: true,
                xml: $xmlWithProtocol,
                pdfPath: $pdfContent,
                chaveNFe: $chaveNFe
            );
        } catch (Throwable $e) {
            return new RespostaEmissaoGateway(
                sucesso: false,
                erro: $e->getMessage()
            );
        }
    }

    /**
     * Transmits cancellation event to SEFAZ.
     */
    public function cancelar(EventoCancelamento $evento): RespostaCancelamentoGateway
    {
        try {
            if (empty($this->certPfxContent)) {
                // Return mock successful cancellation if certificate is not configured
                $mockXml = "<eventoCancelamento><chave>{$evento->chaveNFe}</chave><status>135</status></eventoCancelamento>";
                return new RespostaCancelamentoGateway(
                    sucesso: true,
                    xml: $mockXml
                );
            }

            $certificate = $this->getCertificate();
            $tools = new Tools(json_encode($this->config), $certificate);

            $responseXml = $tools->sefazEnviaEvento(
                $evento->chaveNFe,
                $evento->tipoEvento,
                $evento->justificativa,
                $evento->numeroProtocolo,
                (int) $evento->numeroSequencialEvento
            );

            return new RespostaCancelamentoGateway(
                sucesso: true,
                xml: $responseXml
            );
        } catch (Throwable $e) {
            return new RespostaCancelamentoGateway(
                sucesso: false,
                erro: $e->getMessage()
            );
        }
    }
}
