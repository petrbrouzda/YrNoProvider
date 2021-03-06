<?php

declare(strict_types=1);

namespace App\Services;

use Nette;

class Config
{
    use Nette\SmartObject;

    /**
     * Minimální délka staženého souboru
     */
    public $expectedFileSize = 5000;

    /**
     * URL sluzby yr.no
     */
    public $url = 'https://api.met.no/weatherapi/locationforecast/2.0/compact';

    /**
     * URL sluzby alojz.cz
     */
    public $alojzUrl = 'https://alojz.cz/api/v1/solution?url_id=';

    /**
     * Minimalni interval pro dotazy na stejne misto, sec.
     */
    public $minInterval = 3600;

    /**
     * Root adresar aplikace
     */
    public function getAppDir()
    {
        return substr( __DIR__, 0, strlen(__DIR__)-strlen('/app/Services/')+1 );
    }
}




