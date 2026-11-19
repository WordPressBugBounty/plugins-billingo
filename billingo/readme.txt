=== Billingo Official for WooCommerce ===
Tags: billingo.hu, billingo, woocommerce, szamlazas, magyar
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 4.3.5
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html


Hivatalos Billingo összeköttetés WooCommerce-hez.

== Description ==

= FUNKCIÓK =

A hivatalos Billingo plugin használata a Billingo előfizetési csomagokkal rendelkező felhasználók számára funkciókorlátozások nélkül elérhető.

Bővítményünk a 2.0.0-ás verziót követően már a V3-as Billingo API kapcsolatot használja, amelynek köszönhetően még jobbá tehetjük a pluginünket.

Korábbi verzió használata esetén átállási segédletünket itt találod: https://support.billingo.hu/content/797736961

*   Manuális számla kiállítás - Amennyiben szereted ellenőrizni a rendeléseid a számla kiállítás előtt, lehetőséged van a számlák kiállítására egyesével. Minden beérkező rendelésnél a megjelenik majd a számlakészítés opció, ahol módosíthatsz a beállításokon is. Rendelésenként ki is kapcsolhatod a számlázást: a rendelés oldalán bejelölve ahhoz a rendeléshez sem automatikusan, sem kézzel nem készül bizonylat.
*   Díjbekérő készítés - Pluginünk segítségével dönthetsz úgy, hogy díjbekérőt küldesz a vásárlóidnak első körben. Ebben az esetben, a függőben lévő rendelési állapotban egy díjbekérőt küldünk ki, majd a befizetést követően egy számlát tudsz kiállítani a rendelésről. A díjbekérő készítést fizetési módonként is beállíthatod.
*   Automata számlakészítés - Az automatizmusok nagyban segíthetik az adminisztratív teendőid, emellett rengeteg időt spórolhatsz. A beállítási lehetőségek között megadhatod, hogy milyen rendelési állapotban kerüljenek kiállításra a számláid. Természetesen dönthetsz úgy, hogy elsőre csak piszkozatot készítesz, így lesz egy ellenőrzési lehetőséged is
*   Automatikus sztornózási lehetőség - Abban az esetben, ha a vásárlód visszamondja a rendelését, viszont már kiállítottad a számlát, lehetőséged van a felületről sztornózást indítani. Megadhatod, hogy melyik rendelési állapotban készítsünk neked sztornó számlát.
*   Számla típus választás - Kiválaszthatod, hogy vásárlóid számára hagyományos, vagy elektronikus számlát szeretnél kiállítani a webshopodból. Természetesen bármikor módosíthatsz, ha esetleg változtatni szeretnél.
*   Termékenkénti bizonylattípus felülírás - Egy adott terméknél beállíthatod, hogy az azt tartalmazó rendelés automatikusan milyen bizonylattípussal (számla, díjbekérő, piszkozat vagy előlegszámla) készüljön el, a normál automata/kézi beállítástól függetlenül. A prioritási sorrend: 1) a rendelés oldalán kézzel kiválasztott bizonylattípus mindig elsőbbséget élvez, 2) ha nincs kézi választás, a termék-szintű felülírás érvényesül, 3) ha egyik sincs beállítva, a normál automatikus/kézi alapbeállítás szerint történik a számlázás. Ha egy rendelésben több, eltérő felülírással rendelkező termék is szerepel, a tételek sorrendjében az első felülírás érvényesül.
*   Nyelvesítési opciók - Számláid Magyar, Angol, Német, Francia, Horvát, Olasz, Román és Szlovák nyelven egyaránt kiállíthatod. Ezen felül bekapcsolhatod azt is, hogy ha valaki a weboldaladtól eltérő nyelven használja a böngészőjét, az adott nyelven kerüljön kiállításra a számla. Ehhez az opcióhoz WPML és Woocommerce Multilingual bővítményre is szükség van.
*   Megjegyzések kezelése - Van lehetőséged arra, hogy globálisan adj megjegyzést a számláidhoz, de természetesen egyesével is tudod módosítani a kiállított számlák megjegyzéseit. Ezen felül hozzáadhatod a Barion tranzakciós fizetési azonosítót és a termékeid cikkszámát hozzáadhatod a tételek megjegyzéseihez is. Külön (kapcsolható) beállítással szabályozhatod azt is, hogy a vásárló által a megrendelés során beírt megjegyzés felülírhatja-e ezt az állandó szöveget, vagy az mindig megjelenjen a számlán.
*   Adószámmal kapcsolatos funkciók - Bizonyos vásárlási esetekben előfordul, hogy az adószámot fel kell tüntetni a kiállított számlákon. Ennek a megadását a WooCommerce alapvetően nem teszi lehetővé, viszont bővítményünk segítségével a rendeléseknél bekérheted a vásárló adószámát, majd ezt a kiállított számlán könnyedén, automatikusan megjelenítjük számodra. A klasszikus pénztár oldalon kapcsolható adószám mező jelenik meg, amelynek értéke a számlára kerül; az adószám beírása után (kapcsolhatóan) a NAV-ellenőrzés eredménye látszik, és a Billingóban már szereplő partner cégneve és címe kitöltődik a még üres mezőkbe.
*   ÁFA beállítások - Nem a szokványos ÁFA beállításokat kell használnod? Esetlegesen vannak olyan tételek a termékeid között, amelyeket 0%-os ÁFA helyett AM vagy egyéb más jelöléssel helyettesítenél? Semmi gond, mert erre is tudunk megoldást nyújtani számodra. A beállításaidnál megadhatod, hogy az ÁFA felülírás a teljes termékpalettádra érvényes legyen, vagy csupán a 0%-os termékekre, ezen felül megadhatod azt is, hogy a szállítási díjakra vonatkozzon e a felülírás vagy sem. Természtesen, felsoroltuk neked azokat a választási lehetőségeket is a felületen, amellyel felülírhatod az alapértékeket. A hagyományos ÁFA kulcsok mellett az EUK jelölés is választható. Meghatározott cikkszámú termékekhez (pl. a DRS betétdíjhoz) külön ÁFA kulcs vagy jogcím (pl. TAM) is megadható, amely az általános felülíráson is felülkerekedik; a szállítási díj a számlán bruttó (a vevő által fizetett) vagy nettó összeggel szerepelhet.
*   Fizetési/szállítási díj ÁFA felülírása - Külön (kapcsolható) beállításban megadhatod, hogy a rendelés fizetési vagy szállítási díjához (fee) milyen ÁFA kulcsot rendeljünk a számlán, a WooCommerce bruttó/nettó árbeállításának figyelembevételével. A díj kulcsa kövesheti a rendelés termékeinek kulcsát is (több kulcsnál a legmagasabbat); mindkét esetben a számlán a vevő által fizetett összeg szerepel, és a díjra megadott kulcs erősebb az általános ÁFA felülírásnál.
*   Fizetési módok és kapcsolódó funkciók - A WooCommerceben telepített fizetési módok megjelennek a Billingo modulban. Minden fizetési módod mellett kiválaszthatod azt, hogy mi jelenjen meg a kiállított számlán. Tehát, ha például közvetlen banki utalással fizet valaki, beállíthatod, hogy a számlán a fizetés módja átutalás legyen. Ezen felül minden fizetési mód esetén beállíthatod azt is, hogy hozzunk e létre díjbekérőt, vagy ha az adott rendelés sikeres volt, fizetettnek jelöljük e a kiállított számlát/díjbekérőt. Tehát, ha online bankkártyával fizet a vásárlód, és sikeres a vásárlás, az automatikusan kiállított számla egyből fizetettként kerül kiállításra.
*   Fizetési státusz automatikus ellenőrzése - Banki átutalásos és egyéb, később fizetendő fizetési módoknál a bővítmény képes automatikusan (óránként) vagy kézi gombnyomásra leellenőrizni a Billingón, hogy a számla kifizetésre került-e, és ha igen, a rendelést automatikusan a fizetési módhoz beállított célállapotra váltja. A funkció fizetési módonként külön be- és kikapcsolható, a célállapot is fizetési módonként testre szabható.
*   Előlegszámla és végszámla automatikus összekapcsolása - Bekapcsolható beállítással a rendelés a számlázást aktiváló állapot elérésekor először előlegszámlát kap, majd amint a fizetési státusz-ellenőrzés eredményeként a rendelés eléri a hozzá beállított célállapotot, a bővítmény automatikusan legenerálja a végszámlát is, amely a Billingón hivatkozik az előlegszámlára, és a rendszer levonja belőle az előleg összegét. A végszámla ugyanarra a partnerre szól, mint az előlegszámla.
*   Közvetített szolgáltatás jelölése - Kapcsolható beállítás: a „Közvetített szolgáltatást tartalmaz” mező a számlán automatikusan bejelölődik, ha pozitív összegű szállítási díj szerepel rajta, ingyenes szállításnál jelöletlen marad.
*   Kuponok használata - Abban az esetben, ha webáruházadban kuponokat is használsz, a kiállított számláidon ezek a kedvezmények is meg fognak jelenni. Ehhez csupán engedélyezned kell a webshopban a kuponhasználatot, majd a Billingo bővítmény ezt automatikusan feltünteti a számláidon.
*   Számla értesítő rendszer - A vásárlók tájékoztatása mindig elsődleges szempont a rendelésekkel kapcsolatosan. Ebből nem maradhat ki a számlázás sem. Az általunk készített bővítmény segítségével automatikusan e-mailben elküldheted a felhasználóidnak a kiállított díjbekérőket és számlákat egyaránt.
*   Rendelések kezelése - A rendelésektől kiállított számlák közvetlenül a rendeléskezelőből is megnyithatók. A PDF fájl letölthető a rendelési adatlapról. Minden számla készítésénél létrehozunk egy megjegyzést a rendeléshez, amin láthatod, hogy mikor és milyen sorszámmal készült el az adott számla.
*   Manuális bizonylat-azonosító alapú ellenőrzés - Ha a Billingón már létrejött egy bizonylat egy rendeléshez, de a helyi nyilvántartásba valamiért nem került be (pl. duplikált azonosító hiba miatt), a rendelés oldalán megadhatod a Billingo bizonylat-azonosítóját, és a bővítmény (bekapcsolható funkcióként) ellenőrzi és szinkronizálja azt.
*   Multisite támogatás - Hálózatba (multisite) telepített WooCommerce esetén bekapcsolható, hogy az egyes webhelyek egyedi azonosítóval különüljenek el a Billingo felé küldött rendeléseknél, elkerülve az ütközéseket.
*   Támogatás és hibakezelés - Bővítményünk fejlesztése során törekszünk arra, hogy minden jelzést megfelelően tudjunk kezelni. Ahhoz, hogy a bejelentéseknek könnyedén utána tudjunk járni, elhelyeztünk egy hibakereső kódot a pluginünkben. Ennek a kódnak és a naplófájloknak köszönhetően, kérdés esetén azonnali segítséget tudunk nyújtani számodra kollégáinkkal.

= HASZNÁLAT =

Az összekötés a két felület között rendkívül egyszerű. Folyamatosan frissítjük felhasználói kézikönyvünkben az összekötésre vonatkozó segédletünket is, ahol lépésről lépésre mutatjuk be a bővítmény használatát.

A segédletet ide kattintva érheted el:https://support.billingo.hu/content/2996830214

Pár kiemelt pont a működéssel kapcsolatosan:
*   Telepítést követően a WooCommerce/Beállítások menüpontban a Billingo fülön add meg a fiókodból kinyert API kulcsokat, majd ments rá a felületre.
*   Állítsd be a számodra megfelelő opciókat a funkciólistában.
*   Ezt követően a rendeléseidnél megjelenik jobboldalon egy számla szerkesztésre vonatkozó felület, itt manuálisan is ki tudod állítani a számláid rendelésenként.
*   Amennyiben az automatikus számla készítést választottad, a rendelés megfelelő állapotba állítását követően (pl.: Teljesítve) kiállítjuk a számlát.
*   Bizonyos rendelésenként az opciók gombra kattintva kikapcsolhatod a számlázást és adhatsz hozzá egyéni megjegyzést is.
*   Választhatsz ki sztornó állapotot is, így, ha a megadott rendelési állapotra állítod a rendelés státuszát (Pl.: visszamondva), a sztornó számla automatikusan elkészül.

== Installation ==

1. Töltsd le a bővítményt vagy telepítsd bel a Bővítmények menüpontban
2. WooCommerce / Beállítások oldal alján megjelennek a Billingo beállítások, ezeket be kell állítani
3. Beállíátsok elmentése után lehetőség van a fizetési módok összepárosítására a billingo rendszerében megfelelővel

== Changelog ==
= 4.3.5 =
* Új funkció: „Előlegszámla” oszlop az E-mail beállítások fülön – az előlegszámla mostantól küldhető a Billingón keresztül, csatolható a WooCommerce e-mailhez (függőben lévő / feldolgozás alatti rendelés), vagy mindkettő, saját gomb- és üzenetszöveggel, a számlához, díjbekérőhöz és sztornóhoz hasonlóan
* Új funkció: számlázás kikapcsolása rendelésenként – a rendelés oldalán a Billingo dobozban kapcsolható, azonnal mentődik; kikapcsolva a rendeléshez sem automatikusan (állapotváltás, díjbekérő), sem kézzel nem készül bizonylat, a már kiállított számla sztornózható marad, a változás megjegyzésként rögzül a rendelésen
* Új funkció: „Tranzakciós díjak a rendelés termékeinek ÁFA kulcsát kapják” kapcsoló az ÁFA beállításokban – bekapcsolva a díjak (pl. utánvét díj) a rendelés termékeinek ÁFA kulcsát kapják (több kulcsnál a legmagasabbat), a vevő által fizetett összeg változatlan; erősebb a kézzel választott díj-kulcsnál
* Új funkció: „Külön ÁFA kulcs meghatározott cikkszámú termékekre” kapcsoló az ÁFA beállításokban – a megadott cikkszámú tételek (pl. DRS; vesszővel elválasztva, kis- és nagybetűre nem érzékeny, a * helyettesítő karakter használható) a kiválasztott kulcsot / jogcímet (pl. TAM) kapják az általános ÁFA felülírástól függetlenül
* Új funkció: „Közvetített szolgáltatást tartalmaz” mező automatikus jelölése (kapcsolható) - a mező be van jelölve, ha a számlán pozitív összegű szállítási díj szerepel, ingyenes szállításnál jelöletlen marad
* Új funkció: „Szállítási díj a számlán” beállítás (bruttó / nettó) az ÁFA beállításoknál, a korábbi „Szállítás díja nettóban van megadva” kapcsoló helyett; a WooCommerce a szállítás összegét mindig adó nélkül tárolja, ezért bruttó módban (alapértelmezett) a számlára a vevő által fizetett összeg (összeg + áfa) kerül, nettó módban az adó nélküli összeg nettó tételként; a szállítási kuponra is ugyanez érvényes
* Új funkció: „Adószám mező megjelenítése vásárláskor” kapcsoló – a klasszikus pénztár oldalon megjelenő adószám mező eddig nem működött (nem kapcsolódott be), és a megadott adószám nem jutott el a számlához; mostantól a partner adószámaként kerül a Billingóba (a HPOS-kompatibilis rendelés-metával mentve)
* Új funkció: „Cégadatok kitöltése adószám alapján” (kapcsolható) – az adószám beírása után a pénztár oldalon megjelenik a NAV-ellenőrzés eredménye, és a Billingóban már szereplő partner cégneve és címe kitöltődik a még üres mezőkbe (a Billingo ismeretlen adószámhoz nem ad cégadatot); a vendégek számára is elérhető végpont nonce-szal, lekérdezés-korlátozással, és csak a cégnevet és a címet adja vissza
* Kritikus hibajavítás: az áfakulcsot a modul a WooCommerce által a rendelés soraira (termék, szállítás, utánvétdíj/fee, szállítási kupon) ténylegesen felszámított adóból veszi, nem az adóosztályból és a számlázási országból találja ki; így helyes a szállítási cím szerinti adózás, az összetett adó és a több kulcsos rendelés is
* Kritikus hibajavítás: a termékszinkron már nem írja felül a Billingóban kezelt készletkezelést (is_manage), beszerzési árat, nettó egységárat, jogcímet és főkönyvi adatokat meglévő terméknél (a PUT-os frissítés korábban kikapcsolta a készletezést és lenullázta a beszerzési árat)
* Hibajavítás: ha a bizonylat összeállítása leáll (pl. kikapcsolt számlázás vagy hibás ÁFA kulcs), a „fizetettnek jelölés” beállítás már nem okoz fatal errort
* Hibajavítás: kikapcsolt „ÁFA felülírás” mellett a „Szállításra is” kapcsoló (ha korábban bekapcsolva maradt) a szállítási díjat 0%-os kulccsal állította ki, így a számláról hiányzott a szállítás ÁFA-ja; mostantól a szállítás csak akkor kap felülírt kulcsot, ha maga a felülírás is be van kapcsolva
* Hibajavítás: a „Tranzakciós díj (fee) ÁFA kulcsa” beállítással a számlán a díj nettó összege szerepelt a vevő által fizetett bruttó helyett (a számla végösszege kevesebb volt a rendelésénél); mostantól a vevő által fizetett összeg (nettó + a WooCommerce által felszámított ÁFA) kerül a számlára, csak a kulcs változik
* Hibajavítás: a Fizetési módok oldalon a „-- Válassz! --” (nincs hozzárendelés) opció a felirat szövegét mentette el értékként, ezért az érintett fizetési módú rendeléseknél a számlakészítés a Billingo „Payment Method contains invalid value” hibájával leállt; mostantól üres érték mentődik, a már elmentett érvénytelen értéket pedig a bővítmény figyelmen kívül hagyja (az automatikus leképezés, illetve a tartalék fizetési mód érvényesül)
* Hibajavítás: bekapcsolt „ÁFA felülírás” mellett a „Tranzakciós díj (fee) ÁFA kulcsa” beállítás nem érvényesült (pl. a termékekre szánt 10%-os kulcs az utánvétdíjat is 10%-ra írta, pedig 21% volt megadva); mostantól a díjra megadott kulcs erősebb az általános felülírásnál, díj-kulcs nélkül a felülírás a díjra is érvényes
* Hibajavítás: előlegszámla után a végszámla mindig az előlegszámla partnerére szól; korábban a Billingo az újonnan létrehozott partnert másodpercekig nem listázta, így gyors előleg → végszámla folyamatnál új, duplikált partner jött létre, és a Billingo elutasította az előleg felhasználását („Can not use advance(s) … has descendant(s)”)
* Hibajavítás: a „szállítási kuponként” azonosított kupon kedvezménye kétszer került a számlára (a termék-tételek „- kedvezmény” soraiban és külön sorként is); a külön sor csak akkor készül, ha a kedvezmény nincs már a termék-tételeken
* Hibajavítás: ha a WooCommerce nem rögzített adót a szállításra, a tartalék áfakulcsot a rendelés adózási címe szerint keressük (korábban a bejelentkezett admin vevőadata számított, ezért ugyanarra a rendelésre kézi és automatikus számlázásnál más kulcs jöhetett ki)
* Hibajavítás: az EU-n kívüli (harmadik országba szállított) rendelés, amelyre a WooCommerce nem számolt adót, az EUK áfakódot és az EAM jogcímet kapja a számlán (korábban az adóosztályból kitalált 27% / 10%-os áfával ment ki, holott a vevő áfa nélkül fizetett); a szállítási (adózási) cím számít, az adómentesnek jelölt nem uniós vevő is kivitel; kikapcsolt adószámításnál (kézi rendelés) a régi logika marad; az EUK kódhoz a Billingo csak az EAM jogcímet fogadja el, ezt a 0%-os felülírás és a cikkszám szerinti kulcs is így küldi
* Hibajavítás: a tranzakciós díj tartalék áfakulcsa (ha a WooCommerce nem rögzített adót) a rendelés adózási országának kulcsa, nem a kulcstábla első sora (korábban pl. egy külföldi 0%-os kulcs került a hazai díjra)
* Hibajavítás: ha a WooCommerce a rendelésen rögzített adósort (akár 0%-os kulccsal), az adókulcs nélkül maradt sor adómentesként kerül a számlára, nem az adóosztályból kitalált kulccsal
* Hibajavítás: bekapcsolt „Kedvezmény külön tételként” mellett az akciós termék kedvezménye kétszer került levonásra (25 000 Ft-os, 17 500 Ft-ra akciózott termék: a sor már az akciós áron állt, és a kedvezmény sor még egyszer levonta a különbséget, így a számla végösszege kevesebb volt a rendelésénél); mostantól a sor a normál áron szerepel, a kedvezmény sor pedig levonja a különbséget
* Hibajavítás: az utánvét díj (fee) összege kikapcsolt adószámításnál, illetve ÁFA nélkül megadott árak és adómentes (pl. AAM) ÁFA felülírás mellett újra felbruttósítódott (513 Ft helyett 651 Ft került a számlára); a díj csak akkor bruttósodik, ha a webshop valóban számol rá adót
* Hibajavítás: a „Tizedes jegyek használata számla készítésénél” kikapcsolt állapota már nem kerekíti egészre a más devizákat (pl. EUR): a 12,34 EUR korábban 12 EUR lett a számlán; az egész összegre kerekítés csak a forintra érvényes
* Hibajavítás: a számla végösszegének kerekítése („5-re / 10-re / 1-re”) csak forintnál érvényes; más devizánál (pl. EUR) egész 5 / 10 egységre kerekítette a végösszeget (a 33,15 EUR-os rendelés 35,00 EUR-val ment ki)
* Hibajavítás: kikapcsolt „Automatikus számlakészítés” mellett minden, a számlázási állapotot elérő rendelés kapott egy félrevezető „Billingo: a bizonylat nem készült el – Nincs érvényes bizonylattípus kiválasztva” megjegyzést; a szándékosan kikapcsolt automatikus számlázás mostantól csendben kimarad
* Hibajavítás: az adószám nélküli partner „Adószámmal nem rendelkezik (természetes személy)” (NO_TAX_NUMBER) típussal jön létre (külföldi: FOREIGN), így a Billingo piszkozatán a partner adózási típusa nem marad üresen; a típus csak létrehozáskor kerül be, a meglévő partnereket a keresés változatlanul megtalálja
* Hibajavítás: a rendeléslista státusz-stílusa csak a WooCommerce saját státusz-jelölőjére (mark.order-status) vonatkozik, így más bővítmények .order-status elemeit nem formázza át
* Hibajavítás: az előlegszámla utáni végszámlát a Billingo elutasította („Given Document and Advance Document VAT types do not match”), ha a rendelést a WooCommerce admin rendelés oldalán mentették, és a rendelésen olyan díj volt, amelyre a WooCommerce nem számolt adót (pl. áfamentes MPL / kezelési költség); az admin mentés az adózatlan díj adósorába is ír egy üres bejegyzést, ezt a bővítmény 27%-os kulcsnak olvasta, miközben az előlegszámlán a díj áfamentes volt; az üres adósor mostantól nem számít adókulcsnak (az explicit 0,00 összeg igen)
* Módosítás: a „Teszt mód” leírása egyértelműbb – nem teszt Billingo fiók (a számlák az API kulcshoz tartozó fiókba készülnek), és kizárólag annyit csinál, hogy a hiányzó / 2 karakternél rövidebb számlázási utcát 2 véletlenszerű karakterrel pótolja, hogy a Billingo ne utasítsa el; éles webshopon maradjon kikapcsolva
* Hibajavítás: az „egyedi meta mező” beállításban megadott adószám nem jutott el a számlához, ha a mező kulcsa aláhúzás nélkül mentődik (pl. a pénztármező-szerkesztő bővítmények a billing_adoszam-ot így mentik); a bővítmény csak az aláhúzásos változatot (_billing_adoszam) olvasta, így a partner adószám nélkül maradt; mostantól a beírt kulcs mindkét változatát megvizsgálja, az aláhúzásosat előbb, így a meglévő beállítások változatlanul működnek
* Hibajavítás: a bizonylat-modellek mezőinek utólagos beállítása (pl. az áfamentes tételek jogcíme) null vagy hiányzó értéknél csendben elmaradt
* Hibajavítás: PHP 8.4 / 8.5 kompatibilitás – a „Implicitly marking parameter as nullable is deprecated” figyelmeztetések megszüntetése (a kimenetbe kerülő figyelmeztetés az AJAX-válaszokat is megtörhette)
* Hibajavítás: az adómentes (a WooCommerce által nem adóztatott) utánvétdíj és szállítás nem kap többé szabványos áfakulcsot: a „0% ÁFA felülíráshoz tartozó jogcím” beállítás szerinti áfamentes kód kerül rá, és az összege nem nő meg a fee tévesen alkalmazott áfájával
* Hibajavítás: ha a WooCommerce által felszámított áfakulcs nem szerepel a Billingo támogatott kulcsai között (pl. 13,5%), a bizonylat generálása megáll és a rendelésen megjegyzés jelzi az okot, a korábbi csendes 0% / 27% helyett
* Hibajavítás: a szállítási tétel áfakulcsa a célország kulcsa helyett mindig 27% volt (a WooCommerce szállítási adóadatainak hibás értelmezése miatt)
* Hibajavítás: áfamentes (fordított adózású) EU-s céges rendelésnél a tételek az "EU" áfakódot és a "KBAET" jogcímet kapják, a termék adóosztályából visszaszámolt áfakulcs helyett
* Hibajavítás: a 0%-os ÁFA-felülírásnál a jogcím (pl. KBAET) már nem áfakódként, hanem jogcímként kerül a számlára
* Hibajavítás: külföldi (nem magyar országkódú) adószámos partner "Külföldi" (FOREIGN) típussal jön létre, így a Billingo elfogadja a számlát
* Hibajavítás: a szöveges áfakódok (EU, AM, TAM...) és a tizedesvesszős kulcsok (pl. 5,5%) helyes kezelése az áfakulcs-számításban
* Hibajavítás: a bizonylat-generáló hookok (állapotváltás, díjbekérő) már nem okozhatnak fatal errort a rendelés mentésekor; sikertelen generálásnál a rendelésen megjegyzés rögzíti az okot
* Hibajavítás: ha a deviza árfolyama nem határozható meg, a generálás megáll (rendelés-megjegyzéssel), nem készül hibás árfolyamú bizonylat
* Hibajavítás: díjbekérőből csak számla alakítható át; piszkozat, előlegszámla vagy újabb díjbekérő kérésekor nem lesz belőle észrevétlenül éles számla
* Hibajavítás: számla kiállítása után már nem készül utólag díjbekérő ugyanarra a rendelésre; a nulla mennyiségű tételek kimaradnak a bizonylatról
* Hibajavítás: a fizetés-ellenőrzés nem módosítja a már végállapotba került rendelést, és előlegszámla esetén is a helyes bizonylatot ellenőrzi
* Hibajavítás: a beállítások mentésekor a nem módosított bankszámla- és blokk-azonosítók megmaradnak; a számla-megjegyzés többsoros szövege sem sérül
* Hibajavítás: kézi sztornózásnál a megadott azonosító már nem írja felül a helyi nyilvántartás másik sorát; az adatbázis-frissítés után a sztornózott sor is visszaolvasható
* Hibajavítás: a változatos terméknél (variáció) a terméki bizonylattípus- és ÁFA-felülírás a szülő termék beállítását is figyelembe veszi
* Hibajavítás: a 0%-os ÁFA-felülírás kapcsoló alapértéke megegyezik a beállítások oldalán láthatóval; a kézi generálás flag-je a rendelés meta adatából (HPOS-kompatibilisen) olvasódik
* Hibajavítás: régebbi WooCommerce-ben a termékszinkron már nem omlik össze a hiányzó globális azonosító (EAN) függvény miatt
* Hibajavítás: üres vagy hibás (nem értelmezhető JSON) Billingo-válasz már nem okoz fatal errort (TypeError); a nem JSON, nem PDF válasz (pl. karbantartó oldal) hibaként jelenik meg a korábbi „sikeres, üres bizonylat” helyett
* Hibajavítás: PDF letöltés után a további API-hívások nem kapnak többé „Accept: application/pdf” fejlécet; a kapcsolat-teszt a beállításoknál megadott (még nem mentett) API-kulccsal fut, nem a korábban használttal
* Hibajavítás: az „Előlegszámla” (és minden más bizonylattípus) olvasható neve hiányzott, ezért előlegszámlára állított alapértelmezett kézi típusnál a rendelés oldala hibát dobott; az 5,5% áfakulcs tizedesvesszős megadása sem csonkolódik 5%-ra
* Hibajavítás: adóköteles szállításnál a korábbi kapcsoló kikapcsolt állapotában a szállítás az áfa összegével kevesebbel szerepelt a számlán
* Hibajavítás: az API-osztályok statikus konvertálási beállítása nem szivároghat át egy hívásról a másikra (validációs kivétel vagy hibás válasz után a következő hívás válasza üres, rossz típusú modellbe konvertálódott)
* Hibajavítás: a dokumentumtábla sémája verzió alapján, minden webhelyen (multisite alwebhelyeken is) és minden frissítés után rendbe jön; korábban hálózati aktiválásnál az alwebhelyeken hiányzott a tábla, és a Billingóban létrejött számla helyi mentése csendben elmaradt
* Hibajavítás: a Billingo API-hívások időtúllépést kaptak (kapcsolódás 5 mp, válasz 30 mp), így a Billingo kiesése nem fagyasztja le a pénztárat és az admin oldalakat
* Biztonsági megerősítés: a Billingo felé küldött kérések nem követnek átirányítást (az API-kulcs fejléc nem kerülhet másik hostra), a hibás UTF-8 karakterek helyettesítődnek a kérés törzsében
* Biztonsági javítás: a naplófájlok nem érhetők el a webről – a feltöltések alatti, kitalálhatatlan nevű mappába kerülnek (.htaccess, web.config és index.php védelemmel; nginx alatt is védett), a korábbi naplók automatikusan átkerülnek, a bővítmény törlésekor a naplók is törlődnek; a Támogatás fül nem jelenít meg védőfájlokat naplóként
* Biztonsági javítás: a dokumentumsorokba már nem kerül be a teljes API-kulcs, csak annak nem visszafejthető ujjlenyomata; a korábban tárolt kulcsok automatikusan lecserélődnek
* Módosítás: a minimálisan szükséges PHP-verzió 8.2
* Módosítás: a Számla beállítások fül áttekinthetőbb: összecsukható szekciókra tagolva (alapok, számlakészítés és automatizálás, számla nyelve, megjegyzések és tételek, adószám és vevő adatai, teszt mód és multisite); egyszerre egy szekció nyitott (a „Mindet kinyit” és „Mindet becsuk” gombbal az összes), széles felületen két oszlopban; a nyitott szekciót a böngésző megjegyzi. A jobb oszlop hosszú leírásai már nem lógnak ki a soruk alól
* Módosítás: HPOS (egyéni rendelés-táblák) kompatibilitás nyilatkozat; az eredeti árak mentésének jelzője a rendelés-meta API-t használja

== Changelog ==

= 4.3.4 =
* Új funkció: Előlegszámla és végszámla automatikus összekapcsolása (kapcsolható) - a számlázást aktiváló állapot elérésekor előlegszámlát állít ki a rendszer, majd a fizetési módhoz beállított "Fizetés ellenőrzés célállapotába" kerüléskor automatikusan legenerálja a végszámlát is, amely a Billingón hivatkozik az előlegszámlára
* Új funkció: Fizetési státusz automatikus és kézi ellenőrzése a Billingón keresztül, fizetési módonként külön be-/kikapcsolható és célállapothoz köthető, kézi "Fizetés ellenőrzése" gomb a rendelés oldalán
* Új funkció: Manuális Billingo bizonylat-azonosító alapú ellenőrzés és szinkronizálás (kapcsolható) - "duplicate vendor id" hiba esetén könnyen összepárosítható a Billingón már létrejött, de helyileg el nem mentett bizonylat
* Új funkció: ÁFA felülírás a fizetési/szállítási díjra (fee) is, a WooCommerce bruttó/nettó árbeállításának figyelembevételével (kapcsolható)
* Új funkció: EUK ÁFA kód támogatás mindkét ÁFA felülírási beállításnál
* Új funkció: Multisite támogatás - webhelyenkénti egyedi azonosító a Billingo felé küldött rendeléseknél (kapcsolható)
* Új funkció: Vásárlói megjegyzés felülírhatja az alapértelmezett megjegyzést (kapcsolható) - alapértelmezetten a boltos által beállított állandó számla-megjegyzés mindig megjelenik a számlán, akkor is, ha a vásárló saját megjegyzést írt a megrendeléshez; bekapcsolva a vásárló megjegyzése felülírja az alapértelmezett szöveget
* Új funkció: 0 Ft-os rendelések számlázásának kihagyása (kapcsolható beállítás)
* Új funkció: devizától függő számlanyelv (deviza-nyelv hozzárendelés)
* Új funkció: élő NAV adószám-ellenőrzés a checkoutnál (kapcsolható beállítás)
* Új funkció: céges vásárlás felismerése, partner adótípus automatikus beállítása adószám megadásakor
* Új funkció: termékenkénti bizonylattípus-felülírás (számla/díjbekérő/piszkozat/előlegszámla választható egy adott terméknél). Prioritási sorrend: 1) kézzel a rendelés oldalán kiválasztott típus, 2) termék-szintű felülírás, 3) normál automata/kézi alapbeállítás
* Vendor_id logika javítása, hogy sztornó után újra ki lehessen állítani a számlát a Billingo API-n keresztül
* Termékár mentés megmarad, de nem jelenik meg a rendelés részletes nézetében
* Kritikus hibajavítás: fatal error validációs hiba esetén
* Kritikus hibajavítás: fatal error a bizonylatmodellek önellenőrzésénél
* Hibajavítás: fatal error hálózati hiba esetén az API kommunikációnál
* Hibajavítás: hibás ÁFA felülírás beállítás esetén a generálás megáll admin figyelmeztetéssel, hibás számla helyett
* Biztonsági javítás: jogosultság-eszkalációs hiba az e-mail beállítások mentésénél
* Hibajavítás: díjbekérő-számla konverzió (duplikált számlák helyett)
* Hibajavítás: sztornó folyamat fatal error hiányzó adatnál
* Hibajavítás: naplófájlok webes elérhetőségének megszüntetése
* Hibajavítás: adószám-ellenőrző és legacy ID végpont URL hiba
* Több validációs és adatmodell hiba javítása
* Hibajavítás: termékszinkron lapozás (duplikált termékek 100 db felett)
* Hibajavítás: "Billingo" fül olvashatósága a WooCommerce beállítások oldalon

== Changelog ==
= 4.3.1 =
* Fees tax woo update
* WP 7.0 compatible changes

== Changelog ==
= 4.3.0 =
* Email disable bug solved
* Fee tax update
* Variable product sku to comment field

== Changelog ==
= 4.2.9 =
* Vendor_id usage logic has changed.

= 4.2.8
* New feature add shipping address to documents comment field
* Create documents on order language update

= 4.2.7
* Tax class modification
* Design update
* Logo bugfix

= 4.2.6
* Item discount update
* Add new settings Decimals
* Add new feature Test mode (address valitdation turn off in the test mode)
* Email notification fix
* NAV warning check update
* Label fix for payment depend invoice turn off
* Already cancelled invoice management

= 4.2.5
* Payment depend invoice turn off

= 4.2.4
* vat check

= 4.2.3
* Add negative fee logic
* Add fee tax check
* item discount update

= 4.2.2
* extra fees update (if vat is not set in woocommerce)
* Dataerasecode fix
* Only add the vendor ID to the invoice.

= 4.2.1
* Fees problem
* Order comment

= 4.2.0
* item discount selectable.

= 4.1.4
* Add module compatibility

= 4.0.5
* Bugfix Tax override problem

= 4.0.0.
* Új SDK és stabilitás növelés. 
* Új felhasználói felület
* 0-számlázás
* További részletek a fenti cikkben.

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
