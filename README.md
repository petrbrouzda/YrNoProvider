
---
# Popis instalace

Potřebujete:

* webový server s podporou pro přepisování URL – tedy pro Apache httpd je potřeba zapnutý **mod_rewrite**
* rozumnou verzi PHP (nyní mám v provozu na 7.2)

Instalační kroky:

1) Stáhněte si celou serverovou aplikaci z githubu.

2) V adresáři vašeho webového serveru (nejčastěji něco jako /var/www/) udělejte adresář pro aplikaci, třeba "ChmiWarnings". Bude tedy existovat adresář /var/www/ChmiWarnings přístupný zvenčí jako https://vas-server/ChmiWarnings/ .

3) V konfiguraci webserveru (zde předpokládám Apache) povolte použití vlastních souborů .htaccess v adresářích aplikace – v nastavení /etc/apache2/sites-available/vaše-site.conf pro konkrétní adresář povolte AllowOverride

Tj. pro konfiguraci ve stylu Apache 2.2:
```
<Directory /var/www/ChmiWarnings/>
        AllowOverride all
        Order allow,deny
        allow from all
</Directory>
```
a ekvivalentně pro Apache 2.4:
```
<Directory /var/www/ChmiWarnings/>
        AllowOverride all
        Require all granted
</Directory>
```


4) Nakopírujte obsah podadresáře aplikace/ do vytvořeného adresáře; vznikne tedy /var/www/ChmiWarnings/app ; /var/www/ChmiWarnings/data; ...

5) Přidělte webové aplikaci právo zapisovat do adresářů data, log a temp! Bez toho nebude nic fungovat. Nejčastěji by mělo stačit udělat v /var/www/ChmiWarnings/ něco jako:

```
sudo chown www-data:www-data data log temp
sudo chmod u+rwx data log temp
```

8) No a nyní zkuste v prohlížeči zadat https://vas-server/ChmiWarnings/chmi/vystrahy/5103 a měli byste dostat data.


## Řešení problémů, ladění a úpravy

Aplikace je napsaná v Nette frameworku. Pokud Nette neznáte, **důležitá informace**: Při úpravách aplikace či nasazování nové verze je třeba **smazat adresář temp/cache/** (tedy v návodu výše /var/www/ChmiWarnings/temp/cache). V tomto adresáři si Nette ukládá předkompilované šablony, mapování databázové struktury atd. Smazáním adresáře vynutíte novou kompilaci.

Aplikace **loguje** do adresáře log/ do souboru app.YYYY-MM-DD.txt . Defaultně zapisuje jen chyby; úroveň logování je možné změnit v app/Services/Logger.php v položce LOG_LEVEL.

Konfigurace aplikace je v app/Services/Config.php

Aplikace může být dle nastavení vašeho webserveru dostupná přes https nebo přes http (je jí to jedno).
