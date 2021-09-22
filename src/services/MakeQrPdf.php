<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Services;

use Elabftw\Elabftw\DisplayParams;
use Elabftw\Elabftw\Tools;
use Elabftw\Interfaces\FileMakerInterface;
use Elabftw\Interfaces\MpdfProviderInterface;
use Elabftw\Models\AbstractEntity;
use Elabftw\Models\Config;
use Elabftw\Traits\TwigTrait;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\Request;

/**
 * Make a PDF from several experiments or db items showing only minimal info with QR codes
 */
class MakeQrPdf extends AbstractMake implements FileMakerInterface
{
    use TwigTrait;

    // the input ids but in an array
    private array $idArr = array();

    // The mpdf object which contains all information for the multi entiy PDF file
    private Mpdf $mpdf;

    /**
     * Give me an id list and a type, I make multi entity PDF for you
     *
     * @param string $idList 4 8 15 16 23 42
     */
    public function __construct(MpdfProviderInterface $mpdfProvider, AbstractEntity $entity, string $idList)
    {
        parent::__construct($entity);

        $this->idArr = explode(' ', $idList);
        $this->mpdf = $mpdfProvider->getInstance();
    }

    public function getContentType(): string
    {
        return 'application/pdf';
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
        );
        $html = $this->getTwig(Config::getConfig())->render('qr-pdf.html', $renderArr);
        $this->mpdf->WriteHTML($html);
        return $this->mpdf->Output('', 'S');
    }

    /**
     * Return the url of the item or experiment
     */
    private function getIdUrl(string $id): string
    {
        $Request = Request::createFromGlobals();
        $url = Tools::getUrl($Request) . '/' . $this->Entity->page . '.php';

        return $url . '?mode=view&id=' . $id;
    }

    /**
     * Get all the entity data from the id array
     */
    private function readAll(): array
    {
        $DisplayParams = new DisplayParams();
        $DisplayParams->limit = 9001;
        $this->Entity->idFilter = Tools::getIdFilterSql($this->idArr);
        $entityArr = $this->Entity->readShow($DisplayParams, true);
        foreach ($entityArr as &$entity) {
            $entity['url'] = $this->getIdUrl($entity['id']);
        }
        return $entityArr;
    }
}
