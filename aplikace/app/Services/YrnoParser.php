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


    private $json;
    

    private function najdiSerie( $odDnes, $odHod, $doDnes, $doHod, $nazev )
    {
        Logger::log( 'app', Logger::DEBUG ,  '  hledam od ' . ($odDnes ? 'dnes' : 'zitra') . " $odHod do " . ($doDnes ? 'dnes' : 'zitra') . " $doHod" ); 

        $fromLimit = new Nette\Utils\DateTime();
        if( !$odDnes ) {
            $fromLimit->modify( '+1 day' );
        }
        $fromLimit->setTime( $odHod, 0, 0 );
        $fromT = $fromLimit->getTimestamp() - 10;

        $toLimit = new Nette\Utils\DateTime();
        if( !$doDnes ) {
            $toLimit->modify( '+1 day' );
        }
        $toLimit->setTime( $doHod, 0, 0 );
        $toT = $toLimit->getTimestamp() + 10;

        $minTemp = +100;
        $maxTemp = -100;
        $sumRain = 0;
        $maxHourRain = 0;
        $minCloud = 100;
        $maxCloud = 0;
        $symbols = array();

        foreach( $this->json->properties->timeseries as $ts ) {
            $time = strtotime( $ts->time );
            $fromTime = DateTime::from( $time );
            $use = $time>$fromT && $time<$toT ;
            // Logger::log( 'app', Logger::DEBUG ,  "serie pro {$ts->time} - " . $fromTime . ' ' . ($use ? 'YES' : '' ) ); 
            if( $use ) {
                $t = $ts->data->instant->details->air_temperature; 
                if( $t > $maxTemp ) { $maxTemp = $t; }
                if( $t < $minTemp ) { $minTemp = $t; }
                $r = $ts->data->next_1_hours->details->precipitation_amount;
                if( $r > $maxHourRain ) { $maxHourRain = $r; }
                $sumRain += $r;
                $c = $ts->data->instant->details->cloud_area_fraction;
                if( $c > $maxCloud ) { $maxCloud = $c; }
                if( $c < $minCloud ) { $minCloud = $c; }
                $s = $ts->data->next_1_hours->summary->symbol_code;
                if( isset($symbols[$s]) ) {
                    $symbols[$s]++;
                } else {
                    $symbols[$s] = 1;
                }
                Logger::log( 'app', Logger::DEBUG , '    ' . $fromTime . " temp {$t}, rain {$r}, cloud {$c}, icon {$s}" );
            }
        }
        Logger::log( 'app', Logger::DEBUG , "  {$nazev}: temp {$minTemp}..{$maxTemp}; rain {$sumRain} tot, {$maxHourRain}/hr; clouds {$minCloud}..{$maxCloud}" );

        $info = array();
        $info['nazev'] = $nazev;
        $info['temp_min'] = $minTemp;
        $info['temp_max'] = $maxTemp;
        $info['rain_sum'] = $sumRain;
        $info['rain_max'] = $maxHourRain;
        $info['clouds_min'] = $minCloud;
        $info['clouds_max'] = $maxCloud;

        // vytvoreni ikony
        if( $maxHourRain > 3 ) {
            $icon = 'heavyrain';
        } else if( $maxHourRain > 1 ) {
            $icon = 'rain';
        } else if( $maxHourRain > 0.1 ) {
            $icon = 'lightrain';
        } else if( $maxCloud < 30 ) {
            $icon = 'sun';
        } else if( $maxCloud < 70 ) {
            $icon = 'partlycloudy';
        } else {
            $icon = 'cloudy';
        }
        $info['icon-my'] = $icon;
        arsort($symbols);
        $info['icon-yr'] = array_keys($symbols)[0];

        return $info;
    }


    public function parse( $data, $odhackuj )
    {
        Debugger::enable();

        if( $odhackuj ) {
            $this->odhackuj = true; 
            // aby fungoval iconv
            setlocale(LC_ALL, 'czech'); // záleží na použitém systému
        } 

        $this->json = Json::decode($data);
        // bdump( $this->json );

        /*
        $now = time();
        foreach( $this->json->properties->timeseries as $ts ) {
            // bdump( $ts );
            $time = strtotime( $ts->time );
            $fromTime = DateTime::from( $time );
            $toTime = $fromTime->modifyClone('+1 hour');
            $expired = $toTime->getTimestamp() < $now;
            Logger::log( 'app', Logger::DEBUG ,  "serie pro {$ts->time} - " . $fromTime . ' ' . ($expired ? 'expired' : '' ) ); 
        }
        */

        $rc = array();

        

        $curHour = intval( date( 'G' ) );
        if( $curHour <= 3 ) {
            // noc: 00-05 
            $rc[] = $this->najdiSerie( true, 0, true, 5, 'dnes_noc' );
            // dopoledne: 06-11 
            $rc[] = $this->najdiSerie( true, 6, true, 11, 'dnes_dopoledne' );
            // odpoledne: 12-17
            $rc[] = $this->najdiSerie( true, 12, true, 17, 'dnes_odpoledne' );
            // vecer: 18-21
            $rc[] = $this->najdiSerie( true, 18, true, 21, 'dnes_vecer' );
            // noc: 22-05
            $rc[] = $this->najdiSerie( true, 22, false, 5, 'zitra_noc' );
            // zitra: +1d 6-20 min max dest
            $rc[] = $this->najdiSerie( false, 6, false, 20, 'zitra_den' );
        } else if( $curHour <= 11 ) {
            // dopoledne: 06-11 
            $rc[] = $this->najdiSerie( true, 6, true, 11, 'dnes_dopoledne' );
            // odpoledne: 12-17
            $rc[] = $this->najdiSerie( true, 12, true, 17, 'dnes_odpoledne' );
            // vecer: 18-21
            $rc[] = $this->najdiSerie( true, 18, true, 21, 'dnes_vecer' );
            // noc: 22-05
            $rc[] = $this->najdiSerie( true, 22, false, 5, 'dnes_noc' );
            // zitra: +1d 6-20 min max dest
            $rc[] = $this->najdiSerie( false, 6, false, 20, 'zitra_den' );
        } else if( $curHour <= 17 ) {
            // odpoledne: 12-17
            $rc[] = $this->najdiSerie( true, 12, true, 17, 'dnes_odpoledne' );
            // vecer: 18-21
            $rc[] = $this->najdiSerie( true, 18, true, 21, 'dnes_vecer' );
            // noc: 22-05
            $rc[] = $this->najdiSerie( true, 22, false, 5, 'dnes_noc' );
            // zitra: +1d 6-20 min max dest
            $rc[] = $this->najdiSerie( false, 6, false, 20, 'zitra_den' );
        } else if( $curHour <= 21 ) {
            // vecer: 18-21
            $rc[] = $this->najdiSerie( true, 18, true, 21, 'dnes_vecer' );
            // noc: 22-05
            $rc[] = $this->najdiSerie( true, 22, false, 5, 'dnes_noc' );
            // zitra dopoledne: +1d 06-11
            $rc[] = $this->najdiSerie( false, 6, false, 11, 'zitra_dopoledne' );
            // zitra odpoledne: +1d 12-17
            $rc[] = $this->najdiSerie( false, 12, false, 17, 'zitra_odpoledne' );
            // zitra vecer: +1d 18-21
            $rc[] = $this->najdiSerie( false, 18, false, 21, 'zitra_vecer' );
        } else {
            // noc: 22-05
            $rc[] = $this->najdiSerie( true, 22, false, 5, 'dnes_noc' );
            // zitra dopoledne: +1d 06-11
            $rc[] = $this->najdiSerie( false, 6, false, 11, 'zitra_dopoledne' );
            // zitra odpoledne: +1d 12-17
            $rc[] = $this->najdiSerie( false, 12, false, 17, 'zitra_odpoledne' );
            // zitra vecer: +1d 18-21
            $rc[] = $this->najdiSerie( false, 18, false, 21, 'zitra_vecer' );
        }

        return $rc;
    }

}