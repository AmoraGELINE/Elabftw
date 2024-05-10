<?php

/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

declare(strict_types=1);

namespace Elabftw\Make;

use Elabftw\Elabftw\DisplayParams;
use Elabftw\Elabftw\Tools;
use Elabftw\Enums\EntityType;
use Elabftw\Interfaces\MpdfProviderInterface;
use Elabftw\Models\Config;
use Elabftw\Models\Users;
use Elabftw\Traits\TwigTrait;
use Symfony\Component\HttpFoundation\Request;

/**
 * Make a PDF from several experiments or db items showing only minimal info with QR codes
 */
class MakeQrPdf extends AbstractMakePdf
{
    use TwigTrait;

    public function __construct(protected Users $requester, MpdfProviderInterface $mpdfProvider, protected EntityType $entityType, private array $idArr)
    {
        parent::__construct(
            mpdfProvider: $mpdfProvider,
            includeChangelog: false
        );
    }

    /**
     * Get the name of the generated file
     */
    public function getFileName(): string
    {
        return 'qr-codes.elabftw.pdf';
    }

    public function getFileContent(): string
    {
        $renderArr = array(
            'css' => $this->getCss(),
            'entityArr' => $this->readAll(),
            'useCjk' => $this->requester->userData['cjk_fonts'],
        );
        $Config = Config::getConfig();
        $html = $this->getTwig((bool) $Config->configArr['debug'])->render('qr-pdf.html', $renderArr);
        $this->mpdf->WriteHTML(html_entity_decode($html, ENT_HTML5, 'UTF-8'));
        $output = $this->mpdf->OutputBinaryData();
        $this->contentSize = strlen($output);
        return $output;
    }

    /**
     * Get all the entity data from the id array
     */
    private function readAll(): array
    {
        $DisplayParams = new DisplayParams($this->requester, Request::createFromGlobals(), $this->entityType);
        $DisplayParams->limit = 9001;
        $entity = $this->entityType->toInstance($this->requester);
        $entity->idFilter = Tools::getIdFilterSql($this->idArr);
        $entityArr = $entity->readShow($DisplayParams, true);
        $siteUrl = Config::fromEnv('SITE_URL');
        foreach ($entityArr as &$entity) {
            $entity['url'] = sprintf('%s/%s.php?mode=view&id=%d', $siteUrl, $entity['page'], $entity['id']);
        }
        return $entityArr;
    }
}
