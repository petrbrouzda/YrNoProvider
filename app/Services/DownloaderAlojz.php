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

class DownloaderAlojz 
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

    private function download( $alojzId )
    {
        if( substr($alojzId,0,1)!='/' ) {
            $alojzId = '/' . $alojzId;
        }
        $url = "{$this->config->alojzUrl}{$alojzId}";
        $ua = "YrNoProvider; {$_SERVER['SERVER_NAME']}; github.com/petrbrouzda/YrNoProvider";

        Logger::log( 'app', Logger::DEBUG ,  "  dwnl: stahuji $url [$ua]" ); 

        $data = file_get_contents( $url, false, stream_context_create([
            'http' => [
                'protocol_version' => 1.1,
                'header'           => [
                    'Connection: close',
                    "User-agent: $ua"
                ],
                "timeout" => 30,
            ],
        ]));
        
        $this->expiresSec = 7200;

        foreach( $http_response_header as $hdr ) {
            Logger::log( 'app', Logger::DEBUG ,  "  > $hdr" ); 
            if( Strings::startsWith( $hdr, 'Expires:' ) ) {
                $time = strtotime( Strings::substring( $hdr, 9 ) );
                $expires = DateTime::from( $time );
                $this->expiresSec = $time - time();
                if( $this->expiresSec < $this->config->minInterval ) {
                    $this->expiresSec = $this->config->minInterval;
                }
                Logger::log( 'app', Logger::DEBUG ,  "  expires in {$this->expiresSec} s, " . $expires ); 
            }
        }

        return $data;
    }

    private function getKey(  $alojzId  ) 
    {
        return "alojz_{$alojzId}";
    }

    /**
     * Vraci primo obsah dat - JSON
     */
    public function getData( $alojzId  )
    {
        $key = $this->getKey($alojzId);

        $val = $this->cache->get( $key );
        if( $val==NULL ) {
            $val = $this->download( $alojzId );
            $this->cache->put($key, $val, [
                Cache::EXPIRE => "{$this->expiresSec} seconds"
            ]);
        } else {
            Logger::log( 'app', Logger::DEBUG ,  "  dwnl: cache hit" ); 
        }
        return $val;
    }

    public function deleteFromCache( $alojzId )
    {
        $key = $this->getKey($alojzId);
        $this->cache->remove( $key );

    }
}