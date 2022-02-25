<?php

/**
 * Zkusi stahnout data z Alojz.cz (a pokud se podaří, uloží je do keše, aby ho neobtěžoval často).
 * Pokud se to nepodaří, z yr.no vygeneruje podobné.
 */

declare(strict_types=1);

namespace App\Presenters;

use Nette;
use Nette\Utils\Json;
use \App\Services\Logger;

use Nette\Utils\Strings;
use Nette\Utils\DateTime;


use \App\Services\SmartCache;
// je potreba kvuli konstantam
use Nette\Caching\Cache;

final class AlojzPresenter extends Nette\Application\UI\Presenter
{
    use Nette\SmartObject;
    
    private $alojzDownloader;
    private $yrnoDownloader;

    /** @var \App\Services\SmartCache */
    private $cache;

    /**
     * yr.no data jako objekt
     */
    private $json;

    public function __construct(\App\Services\DownloaderAlojz $alojzDownloader, \App\Services\Downloader $yrnoDownloader, \App\Services\SmartCache $cache  )
    {
        $this->alojzDownloader = $alojzDownloader;
        $this->yrnoDownloader = $yrnoDownloader;
        $this->cache = $cache;
    }


    /*
    {
   "type":"Feature",
   "geometry":{
      "type":"Point",
      "coordinates":[
         15.151,
         50.723,
         500
      ]
   },
   "properties":{
      "meta":{
         "updated_at":"2022-02-24T13:28:37Z",
         "units":{
            "air_pressure_at_sea_level":"hPa",
            "air_temperature":"celsius",
            "cloud_area_fraction":"%",
            "precipitation_amount":"mm",
            "relative_humidity":"%",
            "wind_from_direction":"degrees",
            "wind_speed":"m/s"
         }
      },
      "timeseries":[
         {
            "time":"2022-02-24T15:00:00Z",
            "data":{
               "instant":{
                  "details":{
                     "air_pressure_at_sea_level":1013.2,
                     "air_temperature":6,
                     "cloud_area_fraction":100,
                     "relative_humidity":66,
                     "wind_from_direction":166.2,
                     "wind_speed":3.6
                  }
               },
               "next_12_hours":{
                  "summary":{
                     "symbol_code":"lightrain"
                  }
               },
               "next_1_hours":{
                  "summary":{
                     "symbol_code":"cloudy"
                  },
                  "details":{
                     "precipitation_amount":0
                  }
               },
               "next_6_hours":{
                  "summary":{
                     "symbol_code":"rain"
                  },
                  "details":{
                     "precipitation_amount":1.1
                  }
               }
            }
         },
         {
            "time":"2022-02-24T16:00:00Z",
            ...
         {
            "time":"2022-03-05T12:00:00Z",
            "data":{
               "instant":{
                  "details":{
                     "air_pressure_at_sea_level":1026.3,
                     "air_temperature":1.2,
                     "cloud_area_fraction":89.1,
                     "relative_humidity":41.8,
                     "wind_from_direction":29.6,
                     "wind_speed":0.6
                  }
               },
               "next_6_hours":{
                  "summary":{
                     "symbol_code":"cloudy"
                  },
                  "details":{
                     "precipitation_amount":0
                  }
               }
            }
         },
      ]
   }
}
    */

    

    private function vytvorHodiny()
    {
        $rc = array();

        $curDay = intval( date( 'j' ) );
        $pocetHodin = 24;

        $now = (new DateTime())->modify('-1 hour')->getTimestamp();
        foreach( $this->json->properties->timeseries as $ts ) {
            $time = strtotime( $ts->time );
            $fromTime = DateTime::from( $time );
            
            $use = $time>$now;
            //D/ Logger::log( 'app', Logger::DEBUG ,  "  serie pro {$ts->time} - " . $fromTime . ' ' . ($use ? 'YES' : '' ) ); 
            if( $use ) {
                $pocetHodin--;

                $t = $ts->data->instant->details->air_temperature; 
                $r1 = $ts->data->next_1_hours->details->precipitation_amount;
                $s1 = $ts->data->next_1_hours->summary->symbol_code;
                $r6 = $ts->data->next_6_hours->details->precipitation_amount;
                $s6 = $ts->data->next_6_hours->summary->symbol_code;

                if( isset($ts->data->instant->details->fog_area_fraction) ) {
                    $f = $ts->data->instant->details->fog_area_fraction;
                } else {
                    $f = '-';
                }
                
                //D/ Logger::log( 'app', Logger::DEBUG , '  - ' . $fromTime . " temp {$t}, rain {$r1}/{$r6}, icon {$s1}/{$s6}" );

                $info = array();
                $info['hour'] = $fromTime->format('H');
                $date = $fromTime->format('j');
                $info['dnes'] = $date==$curDay ;
                $info['temp'] = $t;
                $info['rain1'] = $r1;
                $info['icon1'] = $s1;
                $info['rain6'] = $r6;
                $info['icon6'] = $s6;
                $rc[] = $info;
            }
            if( $pocetHodin==0 ) break;
        }

        return $rc;
    }

   private $iconsBourky = array( 
      'lightrainshowersandthunder',
      'lightrainandthunder',
      'rainshowersandthunder',
      'rainandthunder',
      'heavyrainshowersandthunder',
      'heavyrainandthunder',
      'lightssleetshowersandthunder',
      'lightsleetandthunder',
      'sleetshowersandthunder',
      'sleetandthunder',
      'heavysleetshowersandthunder',
      'heavysleetandthunder',
      'lightssnowshowersandthunder',
      'lightsnowandthunder',
      'snowshowersandthunder',
      'snowandthunder',
      'heavysnowshowersandthunder',
      'heavysnowandthunder' );

   private $iconsSnih = array( 
      'lightsnowshowers',
      'lightsnow',
      'lightssnowshowersandthunder',
      'lightsnowandthunder',
      'snowshowers',
      'snow',
      'heavysnowshowers',
      'heavysnow',
      'snowshowersandthunder',
      'snowandthunder',
      'heavysnowshowersandthunder',
      'heavysnowandthunder' );

   private $iconsDest = array( 
      'lightrain',
      'lightrainandthunder',
      'rain',
      'rainandthunder',
      'heavyrainshowers',
      'heavyrain',
      'heavyrainshowersandthunder',
      'heavyrainandthunder',
      'lightsleet',
      'lightsleetandthunder',
      'sleet',
      'sleetshowersandthunder',
      'sleetandthunder',
      'heavysleetshowers',
      'heavysleet',
      'heavysleetshowersandthunder',
      'heavysleetandthunder');

   private $iconsPrehanky = array( 
      'lightrainshowers',
      'lightrainshowersandthunder',
      'rainshowers',
      'rainshowersandthunder',
      'lightsleetshowers',
      'lightssleetshowersandthunder',
      'sleetshowers');

   private $iconsHezky = array( 
      'clearsky',
      'fair',
      'partlycloudy');

   private $iconsZamraceno = array( 
      'cloudy');

   private $iconsMlha = array( 
      'fog');

   private $obleceni;

   private function infoODni( $hodinovaTabulka, $hourOd, $hourDo, $dnes, $nazev )
   {
      $minTemp = 100;
      $maxTemp = -100;
      $sumSrazky = 0;
      $typSrazek = '';
      $maxPerHour = 0;

      $bourky = false;
      $snih = false;
      $dest = false;
      $prehanky = false;
      $hezky = false;
      $zamraceno = false;
      $mlha = false;

      $this->obleceni = '';

      Logger::log( 'app', Logger::DEBUG ,  "  infoODni( $hourOd, $hourDo, $dnes )" );
      foreach( $hodinovaTabulka as $hodina ) {
         if( $hodina['hour']>=$hourOd && $hodina['hour']<=$hourDo && $hodina['dnes']==$dnes ) {
            Logger::log( 'app', Logger::DEBUG ,  "    {$hodina['hour']}: {$hodina['temp']} {$hodina['rain1']} {$hodina['icon1']}"  ); 
            if( $hodina['temp'] > $maxTemp ) {
               $maxTemp = $hodina['temp'];
            }
            if( $hodina['temp'] < $minTemp ) {
               $minTemp = $hodina['temp'];
            }
            $sumSrazky += $hodina['rain1'];
            if( $hodina['rain1']>$maxPerHour ) {
               $maxPerHour = $hodina['rain1'];
            }

            $icon_split = explode ( '_' , $hodina['icon1'] );
            if( false !== array_search ( $icon_split[0] , $this->iconsBourky ) ) $bourky = true;
            if( false !== array_search ( $icon_split[0] , $this->iconsSnih ) ) $snih = true;
            if( false !== array_search ( $icon_split[0] , $this->iconsDest ) ) $dest = true;
            if( false !== array_search ( $icon_split[0] , $this->iconsPrehanky ) ) $prehanky = true;
            if( false !== array_search ( $icon_split[0] , $this->iconsHezky ) ) $hezky = true;
            if( false !== array_search ( $icon_split[0] , $this->iconsZamraceno ) ) $zamraceno = true;
            if( false !== array_search ( $icon_split[0] , $this->iconsMlha ) ) $mlha = true;
         }
      }

      if( $minTemp==100 ) {
         Logger::log( 'app', Logger::DEBUG ,  "  > asi nemam data" );
         return "???";
      }

      $minTemp = intval( $minTemp );
      $maxTemp = intval( $maxTemp );

      if( $minTemp == $maxTemp ) {
         $teplota = "{$minTemp} °C";
      } else if( $minTemp >= 0 ) {
         $teplota = "{$minTemp}-{$maxTemp} °C";
      } else {
         $teplota = "{$minTemp} až {$maxTemp} °C";
      }
      /* if( $sumSrazky==0 ) {
         $srazky = "Bez srážek";
      } */
      $pocasi1 = "";
      $pocasi2 = "";
      if( $bourky ) {
         $pocasi1 = "bouřky {$sumSrazky} mm! ";
      }
      if( $snih && $dest ) {
         $pocasi2 = ", sníh a déšť {$sumSrazky} mm";
      } else if( $snih ) {
         $pocasi2 = ", sněžení {$sumSrazky} mm";
      } else if( $dest ) {
         $pocasi2 = ", déšť {$sumSrazky} mm";
      } else if( $prehanky ) {
         if( $sumSrazky<0.3) {
            $pocasi2 = ", malé přeháňky {$sumSrazky} mm";
         } else {
            $pocasi2 = ", přeháňky {$sumSrazky} mm";
         }
      } else if( $mlha ) {
         $pocasi2 = ", mlha";
      } else if( $zamraceno ) {
         $pocasi2 = ", zamračeno";
      } else if( $hezky ) {
         $pocasi2 = ", hezky";
      } 

      if( $maxPerHour > 1.0) {
         if( $pocasi2!='' ) {
            $pocasi2 .= ", ";
         }
         $pocasi2 .= "až $maxPerHour mm srážek za hodinu!";
      } else {
         if( $pocasi2!='' ) {
            $pocasi2 .= ".";
         }
      }

      $text = $nazev . ' ' . $pocasi1 . $teplota . $pocasi2;
      
      Logger::log( 'app', Logger::DEBUG ,  "  bourky:$bourky, snih:$snih, dest:$dest, prehanky:$prehanky, hezky:$hezky, zamraceno:$zamraceno, mlha:$mlha " );
      Logger::log( 'app', Logger::DEBUG ,  "  > $text" );

      if( $minTemp<-10 ) {
         $this->obleceni = "arktickou bundu, kulicha a tlusté rukavice";
      } else if( $minTemp<-2 ) {
         $this->obleceni = "zimní bundu a rukavice";
      } else if( $minTemp<10  ) {
         $this->obleceni = "tlustou bundu";
         if( $dest || $bourky ) {
            $this->obleceni .= " a deštník";
         }
      } else if( $minTemp<15  ) {
         $this->obleceni = "mikinu";
         if( $dest || $bourky ) {
            $this->obleceni .= " a deštník";
         }
      } else if( $minTemp<20  ) {
         if( $prehanky ) {
            $this->obleceni = "mikinu";
         } else if( $dest || $bourky ) {
            $this->obleceni .= "mikinu a deštník";
         } else {
            $this->obleceni = "triko";
         } 
      } else {
         if( $prehanky ) {
            $this->obleceni = "triko";
         } else if( $dest || $bourky ) {
            $this->obleceni .= "triko a deštník";
         } else {
            $this->obleceni = "triko a kraťasy";
         } 
      }

      switch( rand(1, 3) ) {
         case 1:
            $this->obleceni = 'Vezmi si ' . $this->obleceni . '.';
            break;
         case 2:
            $this->obleceni = 'Je to na ' . $this->obleceni . '.';
            break;
         case 3:
            $this->obleceni = 'Na sebe ' . $this->obleceni . '.';
            break;
         }
         Logger::log( 'app', Logger::DEBUG ,  "  > {$this->obleceni}" );

      return $text;
    }


    /**
     * Z yr.no dat slozime stejny JSON jako Alojz.cz
     */
   private function yrnoToAlojz( $data )
   {
      Logger::log( 'app', Logger::DEBUG ,  "  yrnoToAlojz" );

      $this->json = Json::decode($data);
      $hodinovaTabulka = $this->vytvorHodiny();

      $text1 = '';
      $text2 = '';

      $curHour = intval( date( 'G' ) );
      if( $curHour<8 ) {
         // dnesek 08-20
         $text1 = $this->infoODni( $hodinovaTabulka, 8, 11, true, 'Dopoledne' );
         $text1 .= ' ';
         $text1 .= $this->infoODni( $hodinovaTabulka, 12, 20, true, 'Odpoledne' );
         $this->infoODni( $hodinovaTabulka, 8, 19, true, '-' );
         $text1 = $this->obleceni . ' ' . $text1;
         $prefer = 'day1';
      } else if( $curHour<12 ) {
         $text1 = $this->infoODni( $hodinovaTabulka, $curHour, 12, true, 'Dopoledne' );
         $text1 .= ' ';
         $text1 .= $this->infoODni( $hodinovaTabulka, 12, 20, true, 'Odpoledne' );
         $this->infoODni( $hodinovaTabulka, $curHour, 19, true, '-' );
         $text1 = $this->obleceni . ' ' . $text1;
         $prefer = 'day1';
      } else if( $curHour<15 ) {
         // dnesek od ted do 20
         $text1 = $this->infoODni( $hodinovaTabulka, $curHour, 20, true, 'Odpoledne' );
         $prefer = 'day1';
         $this->infoODni( $hodinovaTabulka, $curHour, 19, true, '-' );
         $text1 = $this->obleceni . ' ' . $text1;
      } else if( $curHour<20 ) {
         // obdobi od ted do 21
         $text1 = $this->infoODni( $hodinovaTabulka, $curHour, 21, true, 'Večer' );
         // zitrek 08-20
         $text2 = $this->infoODni( $hodinovaTabulka, 8, 11, false, 'Dopoledne' );
         $text2 .= ' ';
         $text2 .= $this->infoODni( $hodinovaTabulka, 12, 20, false, 'Odpoledne' );
         $this->infoODni( $hodinovaTabulka, $curHour, 20, false, '-' );
         $text2 = $this->obleceni . ' ' . $text2;
         $prefer = 'day2';
      } else  {
         // zitrek 08-20
         $text2 = $this->infoODni( $hodinovaTabulka, 8, 11, false, 'Dopoledne' );
         $text2 .= ' ';
         $text2 .= $this->infoODni( $hodinovaTabulka, 12, 20, false, 'Odpoledne' );
         $this->infoODni( $hodinovaTabulka, 8, 19, false, '-' );
         $text2 = $this->obleceni . ' ' . $text2;
         $prefer = 'day2';
      }

      /*
{
"code": 200,
"prefer": "day1",
"day2": null,
"day1": null,
"version": "v1",
"command": "GET",
"url_id": "/jablonec-nad-nisou"
}      
      */
      $alojz = array();
      $alojz['code'] = 200;
      $alojz['prefer'] = $prefer;
      $alojz['version'] = 'v1';
      $alojz['command'] = 'GET';
      $alojz['url_id'] = '-';

      $day1 = array();
      $day1['today_tomorrow'] = 'Dnes';
      $day1['string'] = $text1;
      $day1['created'] = date( 'Y-m-d H:i:s' );
      $alojz['day1'] = $day1;
      
      $day2 = array();
      $day2['today_tomorrow'] = 'Zítra';
      $day2['string'] = $text2;
      $day2['created'] = date( 'Y-m-d H:i:s' );
      $alojz['day2'] = $day2;

      return $alojz;

   }


    /**
     * Nemame data alojz.cz, stahneme si predpoved yr.no (s kesi)
     * a pokud nemame spocteny vysledek, spocitame ho
     */
    private function generujData( $lat, $lon, $alt ) 
    {
        // nacteme si data Yr.no

        // nesmi se pouzit vice nez 4 desetinna mista; 3 mista = +-50 metru; 2 mista = +-500 metru
        $lat = number_format( floatval($lat), 3, '.', '' );
        $lon = number_format( floatval($lon), 3, '.', '' );
        $alt = intval( $alt );
        Logger::log( 'app', Logger::INFO ,  "start {$lat} {$lon} {$alt}" );

        // tohle zavolame vzdy; zajisti nacteni souboru, pokud je potreba
        $data = $this->yrnoDownloader->getData( $lat, $lon, $alt );
        $rc = $this->yrnoToAlojz( $data );

        return $rc;
    }


    public function renderAlojz( $alojzId, $lat, $lon, $alt )
    {
        try {

            if( !is_numeric($lat) || !is_numeric($lon) || !is_numeric($alt) ) {
                throw new \Exception( 'Vsechny parametry lat,lon,alt musi byt cislo' );
            }

            $rc = array();

            // tohle zavolame vzdy; zajisti nacteni souboru, pokud je potreba
            $data = $this->alojzDownloader->getData( $alojzId );
            $json = Json::decode($data);
            if( $json->day1==null && $json->day2==null ) {
                Logger::log( 'app', Logger::INFO ,  "  day1 i day2 jsou null, data z Alojze nejsou OK" );
                $this->alojzDownloader->deleteFromCache( $alojzId );
                $rc = $this->generujData( $lat, $lon, $alt );
            } else {
                Logger::log( 'app', Logger::DEBUG ,  "  pouzivam primo data z Alojze" );
                $rc = $json; 
            }

            Logger::log( 'app', Logger::INFO ,  "OK" );            

            $response = $this->getHttpResponse();
            $response->setHeader('Cache-Control', 'no-cache');
            $response->setExpiration('1 sec'); 

            $this->sendJson($rc);

        } catch (\Nette\Application\AbortException $e ) {
            // normalni scenar pro sendJson()
            throw $e;

        } catch (\Exception $e) {
            Logger::log( 'app', Logger::ERROR,  "ERR: " . get_class($e) . ": " . $e->getMessage() );
            
            $httpResponse = $this->getHttpResponse();
            $httpResponse->setCode(Nette\Http\Response::S500_INTERNAL_SERVER_ERROR );
            $httpResponse->setContentType('text/plain', 'UTF-8');
            $response = new \Nette\Application\Responses\TextResponse("ERR {$e->getMessage()}");
            $this->sendResponse($response);
            $this->terminate();
        }
    }

}