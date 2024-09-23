=== Official Integration for Billingo ===
Tags: billingo.hu, billingo, woocommerce, szamlazas, magyar
Requires at least: 5.3
Tested up to: 6.4.1
Stable tag: 3.6.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Hivatalos Billingo összeköttetés WooCommerce-hez.

== Description ==

= FUNKCIÓK =

A hivatalos Billingo plugin használata a Billingo előfizetési csomagokkal rendelkező felhasználók számára funkciókorlátozások nélkül elérhető.

Bővítményünk a 2.0.0-ás verziót követően már a V3-as Billingo API kapcsolatot használja, amelynek köszönhetően még jobbá tehetjük a pluginünket.

Korábbi verzió használata esetén átállási segédletünket itt találod: https://support.billingo.hu/content/797736961

*   Manuális számla kiállítás - Amennyiben szereted ellenőrizni a rendeléseid a számla kiállítás előtt, lehetőséged van a számlák kiállítására egyesével. Minden beérkező rendelésnél a megjelenik majd a számlakészítés opció, ahol módosíthatsz a beállításokon is.
*   Díjbekérő készítés - Pluginünk segítségével dönthetsz úgy, hogy díjbekérőt küldesz a vásárlóidnak első körben. Ebben az esetben, a függőben lévő rendelési állapotban egy díjbekérőt küldünk ki, majd a befizetést követően egy számlát tudsz kiállítani a rendelésről. A díjbekérő készítést fizetési módonként is beállíthatod.
*   Automata számlakészítés - Az automatizmusok nagyban segíthetik az adminisztratív teendőid, emellett rengeteg időt spórolhatsz. A beállítási lehetőségek között megadhatod, hogy milyen rendelési állapotban kerüljenek kiállításra a számláid. Természetesen dönthetsz úgy, hogy elsőre csak piszkozatot készítesz, így lesz egy ellenőrzési lehetőséged is
*   Automatikus sztornózási lehetőség - Abban az esetben, ha a vásárlód visszamondja a rendelését, viszont már kiállítottad a számlát, lehetőséged van a felületről sztornózást indítani. Megadhatod, hogy melyik rendelési állapotban készítsünk neked sztornó számlát.
*   Számla típus választás - Kiválaszthatod, hogy vásárlóid számára hagyományos, vagy elektronikus számlát szeretnél kiállítani a webshopodból. Természetesen bármikor módosíthatsz, ha esetleg változtatni szeretnél.
*   Nyelvesítési opciók - Számláid Magyar, Angol, Német, Francia, Horvát, Olasz, Román és Szlovák nyelven egyaránt kiállíthatod. Ezen felül bekapcsolhatod azt is, hogy ha valaki a weboldaladtól eltérő nyelven használja a böngészőjét, az adott nyelven kerüljön kiállításra a számla. Ehhez az opcióhoz WPML és Woocommerce Multilingual bővítményre is szükség van.
*   Megjegyzések kezelése - Van lehetőséged arra, hogy globálisan adj megjegyzést a számláidhoz, de természetesen egyesével is tudod módosítani a kiállított számlák megjegyzéseit. Ezen felül hozzáadhatod a Barion tranzakciós fizetési azonosítót és a termékeid cikkszámát hozzáadhatod a tételek megjegyzéseihez is.
*   Adószámmal kapcsolatos funkciók - Bizonyos vásárlási esetekben előfordul, hogy az adószámot fel kell tüntetni a kiállított számlákon. Ennek a megadását a WooCommerce alapvetően nem teszi lehetővé, viszont bővítményünk segítségével a rendeléseknél bekérheted a vásárló adószámát, majd ezt a kiállított számlán könnyedén, automatikusan megjelenítjük számodra.
*   ÁFA beállítások - Nem a szokványos ÁFA beállításokat kell használnod? Esetlegesen vannak olyan tételek a termékeid között, amelyeket 0%-os ÁFA helyett AM vagy egyéb más jelöléssel helyettesítenél? Semmi gond, mert erre is tudunk megoldást nyújtani számodra. A beállításaidnál megadhatod, hogy az ÁFA felülírás a teljes termékpalettádra érvényes legyen, vagy csupán a 0%-os termékekre, ezen felül megadhatod azt is, hogy a szállítási díjakra vonatkozzon e a felülírás vagy sem. Természtesen, felsoroltuk neked azokat a választási lehetőségeket is a felületen, amellyel felülírhatod az alapértékeket.
*   Fizetési módok és kapcsolódó funkciók - A WooCommerceben telepített fizetési módok megjelennek a Billingo modulban. Minden fizetési módod mellett kiválaszthatod azt, hogy mi jelenjen meg a kiállított számlán. Tehát, ha például közvetlen banki utalással fizet valaki, beállíthatod, hogy a számlán a fizetés módja átutalás legyen. Ezen felül minden fizetési mód esetén beállíthatod azt is, hogy hozzunk e létre díjbekérőt, vagy ha az adott rendelés sikeres volt, fizetettnek jelöljük e a kiállított számlát/díjbekérőt. Tehát, ha online bankkártyával fizet a vásárlód, és sikeres a vásárlás, az automatikusan kiállított számla egyből fizetettként kerül kiállításra.
*   Kuponok használata - Abban az esetben, ha webáruházadban kuponokat is használsz, a kiállított számláidon ezek a kedvezmények is meg fognak jelenni. Ehhez csupán engedélyezned kell a webshopban a kuponhasználatot, majd a Billingo bővítmény ezt automatikusan feltünteti a számláidon.
*   Számla értesítő rendszer - A vásárlók tájékoztatása mindig elsődleges szempont a rendelésekkel kapcsolatosan. Ebből nem maradhat ki a számlázás sem. Az általunk készített bővítmény segítségével automatikusan e-mailben elküldheted a felhasználóidnak a kiállított díjbekérőket és számlákat egyaránt.
*   Rendelések kezelése - A rendelésektől kiállított számlák közvetlenül a rendeléskezelőből is megnyithatók. A PDF fájl letölthető a rendelési adatlapról. Minden számla készítésénél létrehozunk egy megjegyzést a rendeléshez, amin láthatod, hogy mikor és milyen sorszámmal készült el az adott számla.
*   Támogatás és hibakezelés - Bővítményünk fejlesztése során törekszünk arra, hogy minden jelzést megfelelően tudjunk kezelni. Ahhoz, hogy a bejelentéseknek könnyedén utána tudjunk járni, elhelyeztünk egy hibakereső kódot a pluginünkben. Ennek a kódnak és a naplófájloknak köszönhetően, kérdés esetén azonnali segítséget tudunk nyújtani számodra kollégáinkkal.

= HASZNÁLAT =

Az összekötés a két felület között rendkívül egyszerű. Folyamatosan frissítjük felhasználói kézikönyvünkben az összekötésre vonatkozó segédletünket is, ahol lépésről lépésre mutatjuk be a bővítmény használatát.

A segédletet ide kattintva érheted el: https://support.billingo.hu/content/97255646

Pár kiemelt pont a működéssel kapcsolatosan:
*   Telepítést követően a WooCommerce/Beállítások menüpontban a Billingo fülön add meg a fiókodból kinyert API kulcsokat, majd ments rá a felületre.
*   Állítsd be a számodra megfelelő opciókat a funkciólistában.
*   Ezt követően a rendeléseidnél megjelenik jobboldalon egy számla szerkesztésre vonatkozó felület, itt manuálisan is ki tudod állítani a számláid rendelésenként.
*   Amennyiben az automatikus számla készítést választottad, a rendelés megfelelő állapotba állítását követően (pl.: Teljesítve) kiállítjuk a számlát.
*   Bizonyos rendelésenként az opciók gombra kattintva kikapcsolhatod a számlázást és adhatsz hozzá egyéni megjegyzést is.
*   Választhatsz ki sztornó állapotot is, így, ha a megadott rendelési állapotra állítod a rendelés státuszát (Pl.: visszamondva), a sztornó számla automatikusan elkészül.

= EGYÉB INFORMÁCIÓK =

A számla tételek és információk generálás előtt módosíthatók a wc_billingo_clientdata és wc_billingo_invoicedata filterekkel. Előbbi az ügyfél adatokat módosítja, utóbbi a számlán lévő tételeket. Ez minden esetben az éppen aktív téma functions.php fájlban történjen, hogy az esetleges plugin frissítés ne törölje ki a módosításokat!

Például:

    <?php
    // Számlanyelv változtatás
    add_filter('wc_billingo_invoicedata', 'wc_billingo_lang', 10, 2);
    function wc_billingo_lang($data, $order) {
        $data['template_lang_code'] = 'en';
        return $data;
    }

== Installation ==

1. Töltsd le a bővítményt vagy telepítsd bel a Bővítmények menüpontban
2. WooCommerce / Beállítások oldal alján megjelennek a Billingo beállítások, ezeket be kell állítani
3. Beállíátsok elmentése után lehetőség van a fizetési módok összepárosítására a billingo rendszerében megfelelővel

== Screenshots ==

1. Beállítások képernyő (WooCommerce / Beállítások)
2. Beállítások képernyő (WooCommerce / Beállítások)
3. Beállítások képernyő (WooCommerce / Beállítások)
4. Manuális számla/sztornó számla készítő meta box a rendelés részleteiben

== Changelog ==

= 3.6.2
* Fejlesztés: Adattörlőkód integrálása

= 3.6.1
* Fejlesztés: Support folyamat és hibakeresés javítása

= 3.6.0
* Fejlesztés: WooCommerce High-Performance Order Storage kompatibilitás

= 3.5.0
* Fejlesztés: Hiányzó számlatömb vagy bankszámla automatikus eltávolítása
* Fejlesztés: Kerekítés optimalizálása

= 3.4.7
* Javítés: Termék árak kerekítéséből adódó netto/bruttó eltérés hiba javítva

= 3.4.6
* Fejlesztés: Adószám kinyerés optimalizálása, vendég vásárlónál felhasználó figyelmen kívül hagyása

= 3.4.5
* Fejlesztés: Opció a manuális számlakészítésnél használt típus alapértelmezett beállítására

= 3.4.4
* Javítás: ÁFA Jogcím speciális esetben beragadt

= 3.4.3
* Fejlesztés: Szállítási adóknál az extra üres ÁFA kulcsok kihagyása.

= 3.4.2
* Fejlesztés: <0.5 sor adó számítás javítás

= 3.4.1
* Fejlesztés: PHP verzió kompatibilitás javítása

= 3.4.0
* Fejlesztés: Biztonsági frissítés

= 3.3.9
* Fejlesztés: Piszkozatnál "Küldés Billingon keresztül" opció bekapcsolható

= 3.3.8
* Fejlesztés: Mennyiségi egység felülírási lehetőség

= 3.3.7
* Fejlesztés: Frissítés közbeni Woocommerce hiány által okozott rendszerhiba megszüntetése

= 3.3.6
* Javítás: Új telepítésnél hiányzó napló mappa automatikus létrehozása

= 3.3.5
* Fejlesztés: Többszörös számlagenerálás kizárása, ha a rendelés több lapon is nyitva van

= 3.3.4 =
* Fejlesztés: Manuális számlakészítéskor korábbi díjbekérő figyelmen kívül hagyására opció (rendelés módosítás esetén szükséges)

= 3.3.3 =
* Javítás: -0% áfakulcs megoldva kedvezményeknél

= 3.3.2 =
* Javítás: jogcím mező elállítódás

= 3.3.1 =
* Fejlesztés: Javított szállító ÁFA lekérés
* Fejlesztés: Cégnévre whitespace szűrés, hogy ne fogadja el a " "-t cégnévnek

= 3.3.0 =
* Fejlesztés: Bankszámla választás opció
* Fejlesztés: "Pénzügyi teljesítést nem igényel" jelölés
* Fejlesztés: Piszkozat is lehet fizetettnek jelölt (éles számla beállításai alapján)

= 3.2.7 =
* Fejlesztés: Wordpress dátum függvényre váltás, hogy elkerüljük az időzónák miatti gondokat
* Fejlesztés: Log áthelyezve wp_content/uploads/billingo mappába, hogy ne törlődjön plugin frissítéskor

= 3.2.6 =
* Fejlesztés: WooCommerce Subscriptions email csatolmány kompatibilitás

= 3.2.5 =
* Javítás: hiányzó get_plugin_data() függvény hiba

= 3.2.4 =
* Fejlesztés: 9.5% ÁFA kulcs támogatása

= 3.2.3 =
* Javítás: Admin rendelés lista proforma ikon mutatása új rendeléseknél

= 3.2.2 =
* Fejlesztés: Termék variáns hozzáadása a termék névhez

= 3.2.1 =
* Javítás: Adatbázis tábla hiány önjavítás

= 3.2.0 =
* Fejlesztés: Manuálisan is lehet piszkotatot létrehozni
* Javítás: Automatikusan éles számla készült piszkozat helyett is

= 3.1.0 =
* Javítás: Partner tárolás API Kulcs függőség bevezetése
* Fejlesztés: Opcionális alrendelés számlázás tiltás

= 3.0.0 =
* Fejlesztés: Billingo SDK csere konfliktus mentes megoldásra
* Javítás: Adó kulcs lekérés pontosítása

= 2.6.0 =
* Fejlesztés: Létező díjbekérő esetén számlagenerálás a díjbekérő alapján

= 2.5.1 =
* Javítás: Fee adó típus

= 2.5.0 =
* Fejlesztés: Billingo API frissítve 3.0.13-ra
* Fejlesztés: ÁFA kulcsok és jogcímek felvétele

= 2.4.0 =
* Fejlesztés: Billingo API frissítve 3.0.12-re
* Javítás: Partner duplikáció

= 2.3.2 =
* Javítás: Teljesítés dátum felülíródott
* Javítás: Egyedi rendelési státuszoknál számla generálás indítás

= 2.3.1 =
* Fejlesztés: Hiányzó fordítások pótlása

= 2.3.0 =
* Javítás: E-mail küldés átdolgozva, megoldva a duplikált küldés, és a hiányzó gomb
* Javítás: Storno számla generáláskor hiba javítva

= 2.2.0 =
* Fejlesztés: Opció a szállítás megjelenítésére akkor is, ha 0 összegű
* Fejlesztés: Billingo API frissítve 3.0.10-re
* Fejlesztés: V2 számla konvertálása V3 API-s sztornózáskor
* Javítás: Fallback fizetési mód hiba megszüntetve
* Fejlesztés: Választható fallback fizetési mód
* Javítás: Nullával osztás nulla értékű díjak esetén kizárva
* Javítás: Számla link hiba az AJAX válaszban (generáláskor)

= 2.1.1 =
* Fejlesztés: Cikkszám átadás "variable product" esetén is

= 2.1.0 =
* Fejlesztés: E-mail beállítások egységesítve, díjbekérő és sztornó is küldhető automatikusan
* Fejlesztés: Kompatibilitás rendelés sorszámozó pluginokkal

= 2.0.4 =
* Fejlesztés: Javított HuCommerce compatibilitás (adószám mező használata)

= 2.0.2 =
* Módosítás: Nem egész mennyiségek engedélyezve

= 2.0.1 =
* Javítás: ÁFA felülírás hiba javítva

= 2.0.0 =
* Fejlesztés: API átírva 3-as verzióra
* Módosítás: Adószám mező és figyelmeztetés megjelenítési határ módosítás
* !!! A frissítés telepítése után újra konfigurálni kell a Billingo Plugin beállításait! Új (v3) API kulcs igénylése is szükséges (https://app.billingo.hu/api-key [Hatáskör: Olvasás, Írás]).

= 1.10.0 =
* Fejlesztés: Jobb szállító adó felismerés

= 1.9.9 =
* Javítás: Log mappa külső hozzáférés megakadályozása

= 1.9.8 =
* Javítás: Számla letöltése gomb az e-mailekben completed állapotra váltás esetén

= 1.9.7 =
* Fejlesztés: Adószám mező mindig látható (nem csak 100e felett)
* Fejlesztés: Tizedes érték engedélyezése mennyiségben
* Javítás: Fizetési mód alapértelmezés, ha nincs párosítva.
* Fejlesztés: Link a beállításokhoz a plugin lista oldalon

= 1.9.6 =
* Fejlesztés: HuCommerce plugin adószám mezőjének automatikus kezelése

= 1.9.5 =
* Fejlesztés: cím második sor átadása

= 1.9.4 =
* Javítás: Magyarország kihagyása EU/EUK felülírásból

= 1.9.3 =
* Fejlesztés: Email gomb szöveg módosítható beállításokból

= 1.9.2 =
* Javítás: E-mail gomb fordíthatóság

= 1.9.1 =
* Javítás: termék ÁFA lekérés módszer csere

= 1.9.0 =
* Fejlesztés: Sztornózási képesség, manuálisan és automatikusan
* Fejlesztés: Állítható mely rendelésállapotban jöjjön létre a számla

= 1.8.8 =
* Fejlesztés: Díjbekérő számlák fizetettre állításának külön kezelése

= 1.8.7 =
* Javítás: Kompatibilitás egy országos beállítással

= 1.8.6 =
* Fejlesztés: Cikkszám hozzáadható a termék megjegyzéshez

= 1.8.5 =
* Javítás: ÁFA % float pontatlanság
* Javítás: számla linkek új tabon nyitása
* Javítás: kupon lekérdezés hiba Woo 3.7 előtt
* Fejlesztés: prefix mentése a számlaszámmal
* Javítás: Manuális számlakészítésnél teljesítés dátuma és megjegyzés csak akkor került be, ha volt beállítva határidő is.
* Javítás: Hiányzó mennyiségi egység az extra díjaknál, pl utánvét.

= 1.8.4 =
* Frissítés: Wordpress biztonsági előírásaihoz alkalmazkodás.

= 1.8.3 =
* Javítás: Ingyenes szállítás esetén 27%-os ÁFA kulcs.

= 1.8.2 =
* API kérések kikapcsolása, amíg nincsenek megadva a kulcsok

= 1.8.1 =
* hibajavítások
* adószám mező használható a rendelésből is

= 1.8.0 =
* ÁFA kulcs javítások

= 1.7.0 =
* Kuponkedvezmények javítás

= 1.6.8 =
* hibajavítások

= 1.6.7 =
* hibajavítások

= 1.6.6 =
* official release

= 1.3.7 =
* hibajavítások

= 1.3.6 =
* hibajavítások

= 1.3.5 =
* hibajavítások

= 1.3.4 =
* hibajavítások

= 1.3.3 =
* nem készülhet duplikált dokumentum akkor sem, ha új kérés fut, de a régi még nem tért vissza
* teljesített rendelés woocommerce e-mailbe bekerült a számla letöltése gomb
* nem készülhet duplán díjbekérő a státuszok változtatásakor
* fizetési módok "Díjbekérő" opciója, ha nincs bepipálva (pl. azonnali online fizetési módoknál), nem fog díjbekérő készülni

= 1.3.2 =
* Hibajavítások

= 1.3.1 =
* Kapcsolható, hogy csak a cégnév vagy cégnév és a teljes név kerüljön megjelenésre.

= 1.3.0 =
* Vezetéknév Keresztnév felcserélése opció
* Fizetési módoknál pénzügyi teljesítést nem igényel opció
* Külön Billingo menü, optimalizálás

= 1.2.5 =
* Az áfa kulcsokat lehet párosítani

= 1.2.4 =
* Kerekítési lehetőség hozzáadása a billingo konfigurációs oldalhoz / adding rounding option to billingo config

= 1.2.3 =
* a számla a kosár devizanemében kerül kiállításra kódoptimalizálás

= 1.1.9 =
* Removed duplicated array item assignment to fix shipping unit value

= 1.1.8 =
* Shipping unit added

= 1.1.7 =
* Calculation changes: rounds removed, billingo gets brutto values to calculate the position prices

= 1.1.6 =
* Hibajavítások

= 1.1.5 =
* Hibajavítás: utánvét esetén nem készül díbekérő; szállítási költség számolási javítás (bruttó, nettó)

= 1.1.4 =
* Hibajavítások

= 1.1.3 =
* Fizetési határidő fizetési módonként

= 1.1.2 =
* Hibajavítások

= 1.1.0 =
* WooCommerce 3.0 kompatibilitás

= 1.0.4 =
* Megadható az adószám, 100e ft feletti áfatartalomnál a vásárlás oldalon megjelenik az adószám mező(opcionális) és egy figyelmeztetés, hogy kötelező megadni, ha van.
* Ha nem sikerült létrehozni számlát, akkor a rendelés megjegyzéseibe bekerül, hogy mi volt a hiba

= 1.0.3 =
* Ingyenes szállítás javítása, forrás: https://www.facebook.com/groups/wpcsoport/permalink/1415346541816505/

= 1.0.1 =
* Számlatömb ID megadható a beállításokban

= 1.0.0 =
* WordPress.org-ra feltöltött plugin első verziója

== Upgrade Notice ==

= 2.0 =
A 2.0-ás verzió egy nagyszabású frissítés. A frissítés telepítése után újra kell konfigurálni a Billingo Plugin beállításait! [Új (v3) API kulcs igénylése is szükséges](https://app.billingo.hu/api-key).
