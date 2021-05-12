<?php

/**
 * Projde stazene XML a sestavi z nej JSON data pro zadane parametry.
 */

declare(strict_types=1);

namespace App\Services;

use Nette;
use Nette\Utils\DateTime;
use Nette\Utils\Strings;
use Nette\Utils\Json;
use Tracy\Debugger;

use \App\Services\Logger;

class YrnoParser 
{
    use Nette\SmartObject;

	public function __construct(  )
	{
	}

    private $odhackuj = false;
    
    /**
     * Odstrani hacky a carky, pokud je to pozadovane.
     */
    private function textCnv( $text ) 
    {
        return !$this->odhackuj ? $text : iconv("utf-8", "us-ascii//TRANSLIT", $text );
    }

    private $dny = [ ' ', 'po', 'út', 'st', 'čt', 'pá', 'so', 'ne' ];

    private function hezkeDatum( $date )
    {
        $today = new Nette\Utils\DateTime();
        $dateT = $date->format('Y-m-d');

        if( strcmp( $today->format('Y-m-d') , $dateT)==0 ) {
            return "dnes " . $date->format('H:i');
        }

        if( strcmp( $today->modifyClone('+1 day')->format('Y-m-d') , $dateT)==0 ) {
            return "zítra " . $date->format('H:i');
        }

        return $this->dny[$date->format('N')] . ' ' . $date->format( 'j.n. H:i' );
    }

    

    public function parse( $data, $odhackuj )
    {
        Debugger::enable();

        if( $odhackuj ) {
            $this->odhackuj = true; 
            // aby fungoval iconv
            setlocale(LC_ALL, 'czech'); // záleží na použitém systému
        } 

        $json = Json::decode($data);
        // bdump( $json );
        $now = time();
        foreach( $json->properties->timeseries as $ts ) {
            // bdump( $ts );
            $time = strtotime( $ts->time );
            $fromTime = DateTime::from( $time );
            $toTime = $fromTime->modifyClone('+1 hour');
            $expired = $toTime->getTimestamp() < $now;
            Logger::log( 'app', Logger::DEBUG ,  "serie pro {$ts->time} - " . $fromTime . ' ' . ($expired ? 'expired' : '' ) ); 
        }

        $rc = array();
        return $rc;
    }

}