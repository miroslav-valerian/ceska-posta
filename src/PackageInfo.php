<?php

namespace Valerian\CeskaPosta;

use GuzzleHttp\Client;

class PackageInfo
{
    const TRACKING_URL = 'https://www.postaonline.cz/trackandtrace/-/zasilka/cislo?parcelNumbers=';

    /** Zásilka doručena */
    const STATUS_DELIVERED = 'delivered';
    /** adresát odmítl zásilku převzít */
    const STATUS_REJECTED = 'rejected';
    /** Zásilka vrácena odesílateli */
    const STATUS_RETURNED = 'returned';
    /** Odeslání zásilky do poštovní úložny. */
    const STATUS_LUGGAGE = 'luggage';
    /** Nedefinovaný status. */
    const STATUS_UNDEFINED = 'undefined';

    const COL_DATE = 'Datum';
    const COL_EVENT = 'Událost';
    const COL_ZIP = 'PSČ';
    const COL_PLACE_OF_EVENT = 'Místo vzniku události';

    /**
     * @var string $packageId
     */
    protected $packageId = null;

    /**
     * @var string $status
     */
    protected $status = null;

    /**
     * @var string $status
     */
    protected $stateDate = null;

    /**
     * @param string $packageId
     */
    public function __construct($packageId)
    {
      $this->packageId = $packageId;
    }

    /**
     * @return string
     */
    public function getPackageId()
    {
      return $this->packageId;
    }

    /**
     * @param string $packageId
     * @return self
     */
    public function setPackageId($packageId)
    {
      $this->packageId = $packageId;
      return $this;
    }

    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param string $status
     * @return self
     */
    public function setStatus($status)
    {
        $this->status = $status;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getStateDate()
    {
        return $this->stateDate;
    }

    /**
     * @param \DateTime $stateDate
     * @return self
     */
    public function setStateDate($stateDate)
    {
        $this->stateDate = $stateDate;
        return $this;
    }

    public function parse()
    {
        $html = $this->getHtml();
        if ($html) {
            $dom = new \simple_html_dom($html);
            $rows = $dom->find('div.item-detail-content table.datatable2 tr');
            $header = [];
            foreach ($rows as $row) {
                if(!empty($header)) break;
                foreach ($row->find('th') as $key=>$th) {
                    $header[] = trim(html_entity_decode($th->plaintext));
                }
            }
            $cells = [];
            foreach ($rows as $row) {
                $cell = [];
                foreach ($row->find('td') as $key=>$td) {
                    $cell[$header[$key]] = trim(html_entity_decode($td->plaintext));
                }
                if(!empty($cell)) {
                    $cells[] = $cell;
                }
            }

            if(empty($cells)) {
                $labelMissing = $dom->find('div#content div.errorbox div.infobox p');
                if($labelMissing) {
                    /** @var \simple_html_dom_node $error */
                    $labelMissing = $labelMissing[0];
                    if(
                        strpos(trim(html_entity_decode($labelMissing->plaintext)), 'Zásilka tohoto podacího čísla není v evidenci.') === false &&
                        strpos(trim(html_entity_decode($labelMissing->plaintext)), 'Prosím, zadejte číslo zásilky') === false
                    ) {
                        throw new NotFoundException("CP - information not found, but exists errorbox.");
                    }
                } else {
                    throw new NotFoundException("Information not found.");
                }
            } else {
                $lastStatus = $cells[0];

                if ($this->findStatus($lastStatus[self::COL_EVENT], 'Dodání zásilky')) {
                    $this->setStatus(self::STATUS_DELIVERED);
                } elseif ($this->findStatus($lastStatus[self::COL_EVENT], 'Vrácení zásilky odesílateli')) {
                    $this->setStatus(self::STATUS_RETURNED);
                } elseif ($this->findStatus($lastStatus[self::COL_EVENT], 'adresát odmítl zásilku převzít')) {
                    $this->setStatus(self::STATUS_REJECTED);
                } elseif ($this->findStatus($lastStatus[self::COL_EVENT], 'Odeslání zásilky do poštovní úložny')) {
                    $this->setStatus(self::STATUS_LUGGAGE);
                } else {
                    $this->setStatus(self::STATUS_UNDEFINED);
                }

                $lastStateDate = \DateTime::createFromFormat('j.n.Y', $lastStatus[self::COL_DATE]);
                $this->setStateDate($lastStateDate);
                return $this;

            }
        }
    }

    private function getHtml()
    {
        try {
            $client = new Client();
            $request = $client->request('GET', self::TRACKING_URL.$this->packageId);
            return $request->getBody()->getContents();
        } catch (\Exception $e) {
            throw new NotFoundException($e->getMessage());
        }
    }

    private function findStatus($source, $statusText)
    {
        if (strpos($source, $statusText) !== false) {
            return true;
        } else {
            return false;
        }
    }

}
