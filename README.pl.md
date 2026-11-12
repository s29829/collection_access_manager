# Collection Access Manager – dokumentacja

[English](README.md) | **Polski**

Dokumentacja dla wersji wtyczki **1.06** (ResourceSpace 11).
Znacznik *(od X.YY)* oznacza wersję wtyczki, od której funkcja jest dostępna. Funkcje bez
znacznika są dostępne od wersji 1.00. Historia zmian: [CHANGELOG.md](CHANGELOG.md).

Wtyczka pokazuje w jednej tabeli, **które grupy użytkowników widzą które Featured collections**
(kategorie i kolekcje wyróżnione, dawniej „Themes”). Wiersze to drzewo kolekcji, a kolumny to
grupy. Zamiast przeklikiwać uprawnienia każdej grupy osobno, zaznaczasz pola w tabeli.

**Gdzie:** System > Plugins > Collection Access Manager > **Options**. Strona jest
dostępna tylko dla administratorów (uprawnienie `a`).


**Nazwa** *(od 1.04)*: do wersji 1.03 wtyczka nazywała się **Featured Collection Access**
(katalog `fcaccess`). Dla ResourceSpace to inna wtyczka: zainstaluj `collection_access_manager`,
aktywuj ją, a potem wyłącz i usuń `fcaccess` (strona opcji przypomina o tym, dopóki stara
wtyczka jest aktywna). Przy pierwszym użyciu wtyczka kopiuje historię wersji starej; stary
katalog `<filestore>/fcaccess` zostaje. Dostęp grup zapisuje ResourceSpace – nie zmienia się.

---

## Jak to działa

ResourceSpace zapisuje dostęp do Featured collections jako kody w uprawnieniach grupy:

| Kod | Znaczenie |
|---|---|
| `j*` | grupa widzi wszystkie Featured collections, także nowe, poza ukrytymi |
| `j<ref>` | grupa widzi pozycję najwyższego poziomu `<ref>` razem z jej zawartością |
| `-j<ref>` | pozycja `<ref>` (i wszystko pod nią) jest ukryta |
| `J` | wyszukiwanie pokazuje tylko zasoby z Featured collections, które grupa widzi |

W tabeli nie trzeba znać kodów: **pole zaznaczone oznacza, że grupa widzi pozycję**. Po zapisie
wtyczka sama wylicza kody tak, jak interpretuje je ResourceSpace:

- pozycja najwyższego poziomu: bez `j*` widoczna dostaje `j<ref>`, a z `j*` ukryta dostaje
  `-j<ref>`,
- pozycja niżej, ukryta: dostaje `-j<ref>`, także wtedy, gdy jej nadrzędna kategoria jest
  ukryta. ResourceSpace sprawdza bezpośrednie linki od góry drzewa, więc bez tego kodu ukryta
  kolekcja mogłaby być dostępna przez link.

**Pozostałe uprawnienia grupy nie są zmieniane.** Kody dla kolekcji, których już nie ma, też
zostają.

## Tabela

- **Wiersze „General”:**
  - *All featured collections, also new ones* (`j*`): nowe Featured collections są od razu
    widoczne dla grupy. Bez tego pola nowe pozycje najwyższego poziomu trzeba zaznaczać ręcznie,
  - *Search shows only resources in featured collections the group can see* (`J`).
- **Drzewo Featured collections:** kategorie z wcięciami.
  - Strzałka zwija i rozwija gałąź.
  - Obok nazwy widać liczbę pozycji w kategorii albo liczbę zasobów w kolekcji.
  - Kolumna **Code** pokazuje kod, którego dotyczy wiersz.
- **Zaznaczenie** pozycji zaznacza też jej kategorie nadrzędne, bo nie da się zobaczyć kolekcji
  bez kategorii, w której leży. **Odznaczenie** ukrywa całą gałąź pod nią.
- Pole w gałęzi, której kategoria nadrzędna jest ukryta, jest wyblakłe.
- **Niebieska ramka** oznacza zmianę jeszcze niezapisaną.
- **Grupy dziedziczące uprawnienia** po grupie nadrzędnej są tylko do odczytu (wyszarzone) i
  pokazują stan grupy nadrzędnej.
- **Select > All / None** zaznacza lub odznacza wiersz we wszystkich pokazanych grupach.

## Widok drzewa *(od 1.05)*

**View: Table / Tree** nad filtrami przełącza między tabelą a widokiem drzewa (wybór jest
zapamiętywany w przeglądarce). Widok drzewa też jest tabelą: pierwsza kolumna pokazuje Featured
collections jako **drzewo folderów** – linie poziomów, ikona folderu dla pozycji z podfolderami,
ikona kolekcji dla pozycji z zasobami, **▸** rozwija lub zwija folder – a kolejne kolumny to
**grupy użytkowników z polami wyboru** *(od 1.06; w 1.05 drzewo pokazywało zaznaczenia jednej
wybranej grupy)*, do tego **All / None** dla widocznych grup i dwa wiersze ogólne (`j*`, `J`).
Zaznaczenia są te same co w tabeli i działają według tych samych zasad (zaznaczenie zaznacza też
nadrzędne, odznaczenie ukrywa wszystko poniżej). Filtry nad tabelą działają w obu widokach: grupy
(wyszukiwanie, grupa nadrzędna, liczba kolumn, strony) i wiersze (wyszukiwanie, differing,
unsaved); **open all / close all** działa w widoku, który jest pokazany. Grupy dziedziczące
uprawnienia są tylko do odczytu. **Save changes** zapisuje zmiany z obu widoków.

## Foldery: tworzenie, zmiana nazwy, usuwanie *(od 1.05)*

W obu widokach każda pozycja ma trzy małe przyciski (wyraźniejsze po najechaniu kursorem):

- **+** – nowy folder **wewnątrz** tej pozycji (podfolder). **+ New top-level folder** nad tabelą
  tworzy folder na najwyższym poziomie. Jeśli pozycja zawiera zasoby, strona najpierw pyta: z
  podfolderem ResourceSpace pokazuje ją jako kategorię.
- **ołówek** – zmiana nazwy. Podpowiadana jest zapisana nazwa (także z tłumaczeniami, np.
  `~en:Name~pl:Nazwa`, które możesz zostawić).
- **kosz** – usunięcie. Usunąć można tylko **pustą** pozycję (bez podfolderów i bez zasobów) –
  tak samo jak `can_delete_featured_collection()` w ResourceSpace.

Foldery to Featured collections tworzone funkcjami ResourceSpace (`create_collection()`,
`save_collection()`, `delete_collection()`); ich właścicielem jest administrator, który je tworzy.
Uprawnienia grup się nie zmieniają: nowy podfolder widzą grupy, które widzą folder nadrzędny; nowy
folder najwyższego poziomu – tylko grupy z „All featured collections” (`j*`); innym grupom zaznacz
go i zapisz. Niezapisane zaznaczenia przepadają przy zmianie folderu (strona pyta wcześniej).
*Do sprawdzenia w ResourceSpace 11 (F12): tworzenie Featured collections używa tych samych wywołań
co w 10.7.*

## Filtry

**Featured collections (wiersze):**
- **Search featured collections** – nazwa lub numer; pokazuje trafienia razem z kategoriami
  nadrzędnymi (Esc czyści pole),
- **Show only: differing** – tylko wiersze, w których pokazane grupy różnią się ustawieniem,
- **Show only: unsaved** – tylko wiersze z niezapisanymi zmianami,
- **Tree: open all / close all** – rozwija lub zwija wszystkie kategorie.

**User groups (kolumny):** wyszukiwanie po nazwie, filtr grupy nadrzędnej, liczba kolumn na
stronę (10, 15, 20) i wybór strony z przyciskami « ».

## Zapisywanie, kopiowanie, historia

- **Save changes** – zapisuje zmienione grupy. Przedtem zapisuje poprzedni stan wszystkich grup
  w historii wersji. Zapis używa funkcji ResourceSpace (`save_usergroup`) i trafia do dziennika
  aktywności.
- **Discard changes** – cofa wszystkie niezapisane zmiany w tabeli.
- **Copy access** – kopiuje wszystkie zaznaczenia (także dwa wiersze „General”) z jednej grupy
  do drugiej. Zapis następuje dopiero po „Save changes”.
- **Version history:**
  - pierwsza wersja powstaje po aktywacji wtyczki, a każda następna przy każdym zapisie
    (ostatnie 50),
  - **Restore this version** przywraca kody Featured collections wszystkich grup z wybranej
    wersji (pozostałe uprawnienia zostają),
  - **Delete all history** zostawia tylko najnowszą wersję.

## Zasady korzystania

1. **Najpierw zdecyduj o `j*`.** Z `j*` grupa widzi wszystko, a w tabeli tylko ukrywasz.
   Bez `j*` grupa widzi tylko to, co zaznaczysz, a nowe pozycje najwyższego poziomu są dla
   niej ukryte, dopóki ich nie zaznaczysz.
2. **Porównując grupy, włącz „differing”.** Zobaczysz tylko wiersze, w których grupy się
   różnią.
3. **Przed zapisem zaznacz „unsaved”**, żeby sprawdzić, co się zmieni.
4. **Nowe podkategorie w ukrytej gałęzi** przy grupie z `j*` mogą być widoczne, dopóki nie
   otworzysz tej strony i nie zapiszesz grupy. Po dodaniu kategorii sprawdź tabelę.
5. **Grup dziedziczących nie zmienia się tutaj.** Zmień grupę nadrzędną albo wyłącz
   dziedziczenie na stronie grupy w ResourceSpace.
6. **Pozostałe uprawnienia grup** (np. dostęp do panelu administracyjnego) ustawia się na
   stronie grupy w ResourceSpace albo we wtyczce Permission Group Manager.

## Instalacja i pliki na serwerze

### Pobranie i instalacja

Wtyczka jest dystrybuowana jako archiwum `collection_access_manager-<wersja>.zip` na stronie wydań:
<https://github.com/s29829/collection_access_manager/releases>. Archiwum zawiera katalog `collection_access_manager/` z kodem, licencją
i dokumentacją. Poniżej `<RS_ROOT>` to katalog instalacji ResourceSpace (np.
`/var/www/resourcespace`), a `www-data` to użytkownik serwera WWW (w innych systemach np. `apache`).

**Przez przeglądarkę:** Plugin Manager > Upload a plugin > wybierz plik `.zip` > Upload (przy
aktualizacji: **Overwrite**).

**Z wiersza poleceń (CLI)** – pobranie, rozpakowanie i uprawnienia:

```bash
VER=<wersja>                     # numer wersji ze strony wydań, np. 1.00
cd /tmp
curl -fLO https://github.com/s29829/collection_access_manager/releases/download/v$VER/collection_access_manager-$VER.zip
sudo rm -rf <RS_ROOT>/plugins/collection_access_manager        # tylko przy aktualizacji (dane wtyczki są w filestore)
sudo unzip -q collection_access_manager-$VER.zip -d <RS_ROOT>/plugins/
sudo chown -R www-data:www-data <RS_ROOT>/plugins/collection_access_manager
sudo find <RS_ROOT>/plugins/collection_access_manager -type d -exec chmod 755 {} \;
sudo find <RS_ROOT>/plugins/collection_access_manager -type f -exec chmod 644 {} \;
```

**Przez git:** repozytorium <https://github.com/s29829/collection_access_manager> zawiera pełny katalog wtyczki:

```bash
sudo git clone https://github.com/s29829/collection_access_manager <RS_ROOT>/plugins/collection_access_manager
sudo chown -R www-data:www-data <RS_ROOT>/plugins/collection_access_manager
```

Aktualizacja: `sudo git -C <RS_ROOT>/plugins/collection_access_manager pull`.

Na koniec: System > Plugins, aktywuj „Collection Access Manager” i kliknij **Options**. Wtyczka sama tworzy
swój katalog danych `<filestore>/collection_access_manager` – potrzebuje tylko prawa zapisu do filestore, które
ResourceSpace i tak wymaga.

### Pliki

Wszystko, co zapisuje wtyczka, jest w `<filestore>/collection_access_manager/` *(od 1.04)*: historia wersji
w podkatalogu `data_<klucz>` (klucz wynika z `scramble_key`), pliki `v<N>_<data>_<godz>.json`
z kodami Featured collections każdej grupy. `<filestore>` to `$storagedir` ResourceSpace
(domyślnie `<RS_ROOT>/filestore`). Aktualizacja ani usunięcie kodu wtyczki nie usuwa tych plików. Wersje 1.01–1.03
(`fcaccess`) zapisywały historię w `<filestore>/fcaccess/`, wersja 1.00 w `<filestore>/fcaccess_versions_<klucz>/` – wersja 1.04 kopiuje ją automatycznie.

**Katalogi wtyczka tworzy sama** przy pierwszym użyciu (aktywacja, otwarcie strony opcji).
Potrzebuje tylko prawa zapisu do filestore, które ResourceSpace i tak wymaga.

## Reset wtyczki *(od 1.01)*

Panel jest zwinięty – kliknij tytuł, aby go rozwinąć *(od 1.05)*; po nieudanym resecie otwiera się sam.

Panel **Reset plugin** (po wpisaniu `RESET`) usuwa historię wersji z `<filestore>/collection_access_manager`.
**Dostęp grup do Featured collections nie jest zmieniany** – bieżący stan staje się znowu wersją
v1. Tej operacji nie da się cofnąć.

## Licencja

- Wtyczka jest udostępniona na licencji **PolyForm Noncommercial License 1.0.0** (plik
  [LICENSE](LICENSE)).
- **Bezpłatnie** można jej używać prywatnie i w celach niekomercyjnych, a także w organizacjach
  niekomercyjnych (edukacja, instytucje publiczne, organizacje charytatywne). Można ją zmieniać
  i udostępniać w tych celach, z zachowaniem tekstu licencji.
- **Użycie komercyjne** (firma, usługi dla klientów, płatny produkt) wymaga pisemnej zgody
  autora: <https://github.com/s29829>. Szczegóły: [LICENSE-COMMERCIAL.md](LICENSE-COMMERCIAL.md).
