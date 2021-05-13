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

        // https://api.met.no/weatherapi/weathericon/2.0/documentation
        // https://hjelp.yr.no/hc/en-us/articles/203786121-Weather-symbols-on-Yr
        /*
prioritizace:

clearsky	1	Clear sky	
fair	2	Fair	            // polojasno
partlycloudy	3	Partly cloudy	
cloudy	4	Cloudy	

fog	15	Fog	

lightrainshowers	40	Light rain showers	
lightrain	46	Light rain	
lightrainshowersandthunder	24	Light rain showers and thunder	
lightrainandthunder	30	Light rain and thunder	

rainshowers	5	Rain showers	
rain	9	Rain	
rainshowersandthunder	6	Rain showers and thunder	
rainandthunder	22	Rain and thunder	

heavyrainshowers	41	Heavy rain showers	
heavyrain	10	Heavy rain	
heavyrainshowersandthunder	25	Heavy rain showers and thunder	
heavyrainandthunder	11	Heavy rain and thunder	

lightsleetshowers	42	Light sleet showers	
lightsleet	47	Light sleet	
lightssleetshowersandthunder	26	Light sleet showers and thunder	
lightsleetandthunder	31	Light sleet and thunder	

sleetshowers	7	Sleet showers	
sleet	12	Sleet	                // dest se snehem
sleetshowersandthunder	20	Sleet showers and thunder	
sleetandthunder	23	Sleet and thunder	

heavysleetshowers	43	Heavy sleet showers	
heavysleet	48	Heavy sleet	
heavysleetshowersandthunder	27	Heavy sleet showers and thunder	
heavysleetandthunder	32	Heavy sleet and thunder	

lightsnowshowers	44	Light snow showers	
lightsnow	49	Light snow	
lightssnowshowersandthunder	28	Light snow showers and thunder	
lightsnowandthunder	33	Light snow and thunder	

snowshowers	8	Snow showers	
snow	13	Snow	
snowshowersandthunder	21	Snow showers and thunder        
snowandthunder	14	Snow and thunder	

heavysnowshowers	45	Heavy snow showers	
heavysnow	50	Heavy snow	
heavysnowshowersandthunder	29	Heavy snow showers and thunder	
heavysnowandthunder	34	Heavy snow and thunder	
        */

    private $icons = array( 
        'clearsky',
        'fair',
        'partlycloudy',
        'cloudy',
        'fog',
        'lightrainshowers',
        'lightrain',
        'lightrainshowersandthunder',
        'lightrainandthunder',
        'rainshowers',
        'rain',
        'rainshowersandthunder',
        'rainandthunder',
        'heavyrainshowers',
        'heavyrain',
        'heavyrainshowersandthunder',
        'heavyrainandthunder',
        'lightsleetshowers',
        'lightsleet',
        'lightssleetshowersandthunder',
        'lightsleetandthunder',
        'sleetshowers',
        'sleet',
        'sleetshowersandthunder',
        'sleetandthunder',
        'heavysleetshowers',
        'heavysleet',
        'heavysleetshowersandthunder',
        'heavysleetandthunder',
        'lightsnowshowers',
        'lightsnow',
        'lightssnowshowersandthunder',
        'lightsnowandthunder',
        'snowshowers',
        'snow',
        'snowshowersandthunder',
        'snowandthunder',
        'heavysnowshowers',
        'heavysnow',
        'heavysnowshowersandthunder',
        'heavysnowandthunder' );

    private function najdiNejdulezitejsiIkonu( $symbols )
    {
        $rc = 'clearsky';
        $prevIndex = 0;

        foreach( array_keys($symbols) as $icon ) {
            $i = array_search ( $icon , $this->icons );
            if( $i>$prevIndex ) {
                $rc = $this->icons[$i];
                $prevIndex = $i;
            }
        }

        return $rc;
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
        $minCloud = 101;
        $maxCloud = -101;
        $minFog = 101;
        $maxFog = -101;
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
                if( isset($ts->data->instant->details->cloud_area_fraction) ) {
                    $c = $ts->data->instant->details->cloud_area_fraction;
                    if( $c > $maxCloud ) { $maxCloud = $c; }
                    if( $c < $minCloud ) { $minCloud = $c; }
                } else {
                    $c = '-';
                }
                $s = $ts->data->next_1_hours->summary->symbol_code;
                if( isset($symbols[$s]) ) {
                    $symbols[$s]++;
                } else {
                    $symbols[$s] = 1;
                }
                if( isset($ts->data->instant->details->fog_area_fraction) ) {
                    $f = $ts->data->instant->details->fog_area_fraction;
                    if( $f > $maxFog ) { $maxFog = $f; }
                    if( $f < $minFog ) { $minFog = $f; }
                } else {
                    $f = '-';
                }
                
                Logger::log( 'app', Logger::DEBUG , '    ' . $fromTime . " temp {$t}, rain {$r}, cloud {$c}, icon {$s}" );
            }
        }
        $info = array();
        $info['nazev'] = $nazev;
        $info['temp_min'] = $minTemp;
        $info['temp_max'] = $maxTemp;
        $info['rain_sum'] = $sumRain;
        $info['rain_max'] = $maxHourRain;
        $info['clouds_min'] = ($minCloud != 101) ? $minCloud : '-';
        $info['clouds_max'] = ($maxCloud != -101) ? $maxCloud : '-';
        $info['fog'] = ($maxFog != -101) ? $maxFog : '-';

        // vytvoreni ikony - experimental, chybi snih a mlha
        /*
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
        */

        $info['icon'] = $this->najdiNejdulezitejsiIkonu( $symbols ); 

        Logger::log( 'app', Logger::INFO , "  {$nazev}: {$info['icon']}; temp {$minTemp}..{$maxTemp}; rain {$sumRain} tot, {$maxHourRain}/hr; clouds {$minCloud}..{$maxCloud}; fog {$minFog}..{$maxFog}" );

        return $info;
    }


    private function vytvorSekce()
    {
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


    public function parse( $data, $odhackuj )
    {
        Debugger::enable();

        if( $odhackuj ) {
            $this->odhackuj = true; 
            // aby fungoval iconv
            setlocale(LC_ALL, 'czech'); // záleží na použitém systému
        } 

        $this->json = Json::decode($data);
        
        $rc = array();
        // sekce - dopoledne, odpoledne, vecer
        $rc['sections'] = $this->vytvorSekce();
        // hodinove predpovedi pro nejblizsich N hodin
        //TODO
        return $rc;
    }

}