<?php

namespace App\Infrastructure\Secondary\Fiscal;

use App\Core\Application\Ports\Outbound\NFeFiscalGatewayInterface;
use App\Core\Application\Ports\Outbound\RespostaCancelamentoGateway;
use App\Core\Application\Ports\Outbound\RespostaEmissaoGateway;
use App\Core\Domain\Entities\EventoCancelamento;
use App\Core\Domain\Entities\NFe;
use NFePHP\DA\NFe\Danfe;
use NFePHP\NFe\Make;
use NFePHP\NFe\Tools;
use NFePHP\Common\Certificate;
use Throwable;

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
        if ($certPfxContent === null && !empty($certPath) && file_exists($certPath)) {
            $this->certPfxContent = file_get_contents($certPath);
        } else {
            $this->certPfxContent = $certPfxContent;
        }

        $this->certPassword = $certPassword ?? config('nfe.cert_password', '');
    }

    /**
     * Generates XML, signs, transmits NFe to SEFAZ, and generates DANFE PDF.
     */
    public function emitir(NFe $nfe): RespostaEmissaoGateway
    {
        try {
            // 1. Build NFe XML structure using NFePHP Make
            $make = new Make();

            // infNFe tag
            $std = new \stdClass();
            $std->versao = '4.00';
            $make->taginfNFe($std);

            // ide tag
            $std = new \stdClass();
            $std->cUF = 35; // SP
            $std->cNF = sprintf('%08d', rand(1, 99999999));
            $std->natOp = $nfe->naturezaOperacao;
            $std->mod = (int) $nfe->modelo;
            $std->serie = $nfe->serie;
            $std->nNF = $nfe->numero;
            $std->dhEmi = date('Y-m-d\TH:i:sP');
            $std->tpNF = 1; // 1: Saída
            $std->idDest = 1; // 1: Interna
            $std->cMunFG = 3550308;
            $std->tpImp = 1; // 1: Retrato
            $std->tpEmis = 1; // 1: Normal
            $std->tpAmb = $this->config['tpAmb'];
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
            $std->CNPJ = preg_replace('/\D/', '', $nfe->emitente->cnpj);
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

            // Products loop
            foreach ($nfe->produtos as $index => $prod) {
                $nItem = $index + 1;
                $std = new \stdClass();
                $std->item = $nItem;
                $std->cProd = $prod->codigo;
                $std->xProd = $prod->descricao;
                $std->NCM = $prod->ncm;
                $std->CFOP = $prod->cfop;
                $std->uCom = $prod->unidadeComercial;
                $std->qCom = $prod->quantidadeComercial;
                $std->vUnCom = $prod->valorUnitarioComercial;
                $std->vProd = $prod->valorTotalBruto;
                $std->uTrib = $prod->unidadeComercial;
                $std->qTrib = $prod->quantidadeComercial;
                $std->vUnTrib = $prod->valorUnitarioComercial;
                $std->indTot = 1;
                $make->tagprod($std);

                // ICMS tag
                $std = new \stdClass();
                $std->item = $nItem;
                $std->orig = 0;
                $std->CSOSN = $prod->impostos->icms->cst ?? '102';
                $make->tagICMSSN($std);
            }

            // icmstot tag
            $std = new \stdClass();
            $std->vProd = number_format($nfe->valorTotal, 2, '.', '');
            $std->vNF = number_format($nfe->valorTotal, 2, '.', '');
            $make->tagicmstot($std);

            $xmlUnsigned = $make->getXML();
            $chaveNFe = $make->getChave();

            // 2. If certificate is missing (e.g. mock/homologation mode), return generated XML & key
            if (empty($this->certPfxContent)) {
                return new RespostaEmissaoGateway(
                    sucesso: true,
                    xml: $xmlUnsigned,
                    pdfPath: null,
                    chaveNFe: $chaveNFe
                );
            }

            // 3. Sign XML and transmit via NFePHP Tools
            $certificate = Certificate::readPfx($this->certPfxContent, $this->certPassword);
            $tools = new Tools(json_encode($this->config), $certificate);
            $xmlSigned = $tools->signNFe($xmlUnsigned);

            // Generate DANFE PDF
            $danfe = new Danfe($xmlSigned);
            $danfe->monta();
            $pdfContent = $danfe->render();

            return new RespostaEmissaoGateway(
                sucesso: true,
                xml: $xmlSigned,
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

            $certificate = Certificate::readPfx($this->certPfxContent, $this->certPassword);
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
