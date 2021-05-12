<?php

/**
 * Zajisti stazeni souboru ze serveru a ulozeni do pracovniho adresare.
 * Pokud se stazeni nepodari a v adresari je stara verze, vrati alespon ji.
 */

declare(strict_types=1);

namespace App\Services;

use Nette;
use Nette\Utils\Strings;
use Nette\Utils\DateTime;

use \App\Services\Logger;

use \App\Services\SmartCache;
// je potreba kvuli konstantam
use Nette\Caching\Cache;

class Downloader 
{
    use Nette\SmartObject;

    /** @var \App\Services\Config */
    private $config;

    /** @var \App\Services\SmartCache */
    private $cache;
    
	public function __construct( \App\Services\Config $config, \App\Services\SmartCache $cache )
	{
        $this->config = $config;
        $this->cache = $cache;
	}

    private $expiresSec;

    private function download( $lat, $lon, $alt )
    {
        $url = "{$this->config->url}?lat={$lat}&lon={$lon}&altitude={$alt}";
        Logger::log( 'app', Logger::DEBUG ,  "  dwnl: stahuji $url" ); 

        $data = file_get_contents( $url, false, stream_context_create([
            'http' => [
                'protocol_version' => 1.1,
                'header'           => [
                    'Connection: close',
                    'User-agent: YrNoProvider1 petr.brouzda@gmail.com'
                ],
                "timeout" => 30,
            ],
        ]));
        
        $this->expiresSec = 3600;

        foreach( $http_response_header as $hdr ) {
            Logger::log( 'app', Logger::DEBUG ,  "  > $hdr" ); 
            if( Strings::startsWith( $hdr, 'Expires:' ) ) {
                $time = strtotime( Strings::substring( $hdr, 9 ) );
                $expires = DateTime::from( $time );
                $this->expiresSec = $time - time();
                if( $this->expiresSec < 1800 ) {
                    $this->expiresSec = 1800;
                }
                Logger::log( 'app', Logger::DEBUG ,  "  expires in {$this->expiresSec} s, " . $expires ); 
            }
        }

        return $data;
    }

    /**
     * Vraci primo obsah dat - JSON
     */
    public function getData(  $lat, $lon, $alt  )
    {
        $key = "json_{$lat}_{$lon}_{$alt}";

        $val = $this->cache->get( $key );
        if( $val==NULL ) {
            $val = $this->download( $lat, $lon, $alt );
            $this->cache->put($key, $val, [
                Cache::EXPIRE => "{$this->expiresSec} seconds"
            ]);
        } else {
            Logger::log( 'app', Logger::DEBUG ,  "  dwnl: cache hit" ); 
        }
        return $val;
    }
}