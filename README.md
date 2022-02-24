
# YrNoProvider (v2) - proxy pro yr.no a alojz.cz

Aplikace zajišťuje dvě nezávislé funkce:
* Zprostředkovatel dat z meteoserveru yr.no pro meteostanice se slabým procesorem.
* Proxy pro zajištění spolehlivých dat ze služby alojz.cz

-----
## Zprostředkovatel dat z yr.no

Zprostředkovatel dat z meteoserveru yr.no pro meteostanice se slabým procesorem.
Načte z Yr.no velký JSON s předpovědí a ztransformuje ho na malý JSON s daty pro meteostanici:
- hodinová předpověď pro aktuálních 12 hodin
- předpověď po sekcích (dopoledne, odpoledne, večer, noc)

Vyzkoušejte si zde: https://lovecka.info/YrNoProvider1/yrno/forecast?lat=50.7230&lon=15.1514&alt=500 a do lat, lon a alt zadejte své souřadnice a nadmořskou výšku.

Aplikace řeší kešování dotazů na Yr.no i parsovaných odpovědí, aby zbytečně nezatěžovala lokální server, ani nenarazila na limity na straně yr.no. Aplikace vyplňuje User-agent dle požadavku Yr.no.

Pro stahování dat z yr.no není potřebný žádný API klíč ani nic podobného. Informace:
- omezení a terms: https://developer.yr.no/doc/TermsOfService/
- getting started: https://developer.yr.no/doc/GettingStarted/
- popis API: https://api.met.no/weatherapi/locationforecast/2.0/#!/data/get_compact
- popis ikonek: https://api.met.no/weatherapi/weathericon/2.0/documentation
- ikony ke stažení: https://github.com/nrkno/yr-weather-symbols

Chování (množství vrácených dat) lze ovlivnit parametrem **mode**:
- Při zavolání bez parametru **mode** nebo s **mode=0** aplikace vrátí jak předpověď pro sekce, tak pro jednotlivé hodiny.
- Pro **mode=1** vrátí jen předpověď pro sekce.
- Pro **mode=2** vrátí jen hodinovou předpověď.

Ukázka odpovědi:

```json
{
   "sections":[
      {
         "nazev":"dnes_dopoledne",
         "temp_min":11,
         "temp_max":11.3,
         "rain_sum":0.9,
         "rain_max":0.6,
         "clouds_min":98.4,
         "clouds_max":99.2,
         "fog":"-",
         "icon":"rain"
      },
      {
         "nazev":"dnes_odpoledne",
         "temp_min":10.6,
         "temp_max":12,
         "rain_sum":2.3,
         "rain_max":0.8,
         "clouds_min":96.1,
         "clouds_max":100,
         "fog":"-",
         "icon":"rain"
      },
      .... zkráceno ...
      {
         "nazev":"zitra_den",
         "temp_min":7.5,
         "temp_max":10.9,
         "rain_sum":2.5000000000000004,
         "rain_max":0.4,
         "clouds_min":99.2,
         "clouds_max":100,
         "fog":"-",
         "icon":"rain"
      }
   ],
   "hours":[
      {
         "hour":"10",
         "temp":11,
         "rain":0.2,
         "clouds":99.2,
         "fog":"-",
         "icon":"lightrain"
      },
      {
         "hour":"11",
         "temp":11.3,
         "rain":0.1,
         "clouds":99.2,
         "fog":"-",
         "icon":"fog"
      },
      .... zkráceno ...
      {
         "hour":"20",
         "temp":9,
         "rain":0.1,
         "clouds":100,
         "fog":"-",
         "icon":"lightrain"
      },
      {
         "hour":"21",
         "temp":8.8,
         "rain":0.1,
         "clouds":100,
         "fog":"-",
         "icon":"cloudy"
      }
   ]
}
```

Hodinová předpověď začíná vždy aktuální hodinou.

Sekce jsou:
- dnes_noc (22:00-06:00)
- dnes_dopoledne (06:00-12:00)
- dnes_odpoledne (12:00-18:00)
- dnes_vecer (18:00-22:00)
- **zitra_noc** (22:00-06:00)
- **zitra_den (06:00-21:00)**
- *zitra_dopoledne (06:00-12:00)*
- *zitra_odpoledne (12:00-18:00)*
- *zitra_vecer (18:00-22:00)*

Sekce se vrací podle denní doby. Sekce zapsané kurzívou se vrací pouze tehdy, pokud už je večer (později než 18:00); přes den se vrací sumární **zitra_den**. Sekce **zitra_noc** se vrací jen pokud probíhá "dnešní noc", tedy je mezi půlnocí a ránem.

Většina položek dat je zjevná. A ty co nejsou:
- **fog** - Procentuální pokrytí mlhou, pokud se vyskytne. Nebo "-", pokud mlha nebude.
- **clouds**, **clouds_min**, **clouds_max** - Procentuální pokrytí oblohy mraky, pokud budou. Nebo "-", pokud data nejsou.
- **rain_sum** - Srážky (v mm) za celou dobu sekce, tj. např. za celé odpoledne.
- **rain_max** - Maximální srážky za hodinu.

---
## Proxy pro Alojz.cz

Alojz.cz je skvělá služba pro "lidsky přepsanou" předpověď počasí.
Webový interface najdete zde: https://alojz.cz a webové API vypadá takto: https://alojz.cz/api/v1/solution?url_id=/jablonec-nad-nisou

Jenže poslední dobou se alojz.cz čas od času zasekne a dává "prázdná" data. Což je škoda, meteostanice pak nemá co ukázat na displeji.

Takže jsem napsal proxy, která udělá dotaz na alojz.cz a pokud dostane validní data, vrátí je tazateli.
Pokud ovšem alojz.cz nefunguje, pak si stáhne předpověď z yr.no a postaví z ní alespoň základní textovou předpověď. Není tak dobrá jako alojz.cz, ale je to lepší než prázdná displej.

**Nemusíte měnit kód** v meteostanici (v mikrokontroléru). Vrácená data **mají stejnou strukturu**, ať se vrátí z alojz.cz nebo z náhradního zdroje.

Vyzkoušejte si zde: https://lovecka.info/YrNoProvider1/alojz/alojz?alojzId=jablonec-nad-nisou&lat=50.7230&lon=15.1514&alt=500

* alojzId je ID místa používaný v alojz.cz
* lat, lon, alt jsou zeměpisné souřadnice a nadmořská výška místa pro stahování z yr.no


---
# Popis instalace

Potřebujete:

* webový server s podporou pro přepisování URL – tedy pro Apache httpd je potřeba zapnutý **mod_rewrite**
* rozumnou verzi PHP (nyní mám v provozu na 7.2)

Instalační kroky:

1) Stáhněte si celou serverovou aplikaci z githubu.

2) V adresáři vašeho webového serveru (nejčastěji něco jako /var/www/) udělejte adresář pro aplikaci, třeba "YrNoProvider". Bude tedy existovat adresář /var/www/YrNoProvider přístupný zvenčí jako https://vas-server/YrNoProvider/ .

3) V konfiguraci webserveru (zde předpokládám Apache) povolte použití vlastních souborů .htaccess v adresářích aplikace – v nastavení /etc/apache2/sites-available/vaše-site.conf pro konkrétní adresář povolte AllowOverride

Tj. pro konfiguraci ve stylu Apache 2.2:
```
<Directory /var/www/YrNoProvider/>
        AllowOverride all
        Order allow,deny
        allow from all
</Directory>
```
a ekvivalentně pro Apache 2.4:
```
<Directory /var/www/YrNoProvider/>
        AllowOverride all
        Require all granted
</Directory>
```

4) Nakopírujte obsah aplikace do vytvořeného adresáře; vznikne tedy /var/www/YrNoProvider/app ; /var/www/YrNoProvider/data; ...

5) Přidělte webové aplikaci právo zapisovat do adresářů data, log a temp! Bez toho nebude nic fungovat. Nejčastěji by mělo stačit udělat v /var/www/YrNoProvider/ něco jako:

```
sudo chown www-data:www-data data log temp
sudo chmod u+rwx data log temp
```

8) No a nyní zkuste v prohlížeči zadat https://vas-server/YrNoProvider/yrno/forecast?lat=50.7230&lon=15.1514&alt=500 a měli byste dostat data.


## Řešení problémů, ladění a úpravy

Aplikace je napsaná v Nette frameworku. Pokud Nette neznáte, **důležitá informace**: Při úpravách aplikace či nasazování nové verze je třeba **smazat adresář temp/cache/** (tedy v návodu výše /var/www/ChmiWarnings/temp/cache). V tomto adresáři si Nette ukládá předkompilované šablony, mapování databázové struktury atd. Smazáním adresáře vynutíte novou kompilaci.

Aplikace **loguje** do adresáře log/ do souboru app.YYYY-MM-DD.txt . Defaultně zapisuje chyby a základní informace o provozu; úroveň logování je možné změnit v app/Services/Logger.php v položce LOG_LEVEL.

Konfigurace aplikace je v app/Services/Config.php

Aplikace může být dle nastavení vašeho webserveru dostupná přes https nebo přes http (je jí to jedno).
