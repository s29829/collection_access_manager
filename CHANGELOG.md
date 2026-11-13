# Changelog - Collection Access Manager

Numeracja wtyczki: 1.00, 1.01, 1.02 … (+0.01 przy każdej zmianie, `tools/release.py bump`).
Wersja wtyczki w `collection_access_manager.yaml`; wersja całego repozytorium w pliku `VERSION` (zob. `CHANGELOG.md` w katalogu głównym).

## [1.07] - 2026-11-13
- Dokumentacja: instalacja na Apache (Ubuntu/Debian i rodzina RHEL) – użytkownik i grupa uruchamiające PHP (mod_php / PHP-FPM), uprawnienia, wyłączenie listowania katalogów (Options -Indexes), SELinux.

## [1.06] - 2026-11-12
- Widok drzewa: obok drzewa folderów kolumny grup użytkowników z polami wyboru (All / None, wiersze j* i J), filtry grup i wierszy działają w obu widokach; usunięty wybór jednej grupy.

## [1.05] - 2026-11-12
- Widok drzewa (przełącznik Table / Tree) ze strukturą folderów i zaznaczeniami wybranej grupy; tworzenie (także podfolderów przy każdej pozycji), zmiana nazwy i usuwanie pustych folderów (Featured collections) w obu widokach; rozwijana ramka Reset plugin.

## [1.04] - 2026-11-12
- Nowa nazwa: Collection Access Manager (katalog collection_access_manager, wcześniej Featured Collection Access / fcaccess); przy pierwszym użyciu kopiuje historię wersji starej wtyczki bez usuwania starego katalogu; strona opcji przypomina o wyłączeniu fcaccess; nowe repozytorium s29829/collection_access_manager.

## [1.03] - 2026-11-11
- Porządki w historii zmian (bez zmian w działaniu wtyczki).

## [1.02] - 2026-11-11
- Dokumentacja: instalacja przez Plugin Manager albo CLI (curl, unzip, chown) bez skryptów bash; wydania zawierają tylko archiwum zip.

## [1.01] - 2026-11-11
- Historia wersji w <filestore>/collection_access_manager/data_<klucz>/ z automatycznym przeniesieniem z <filestore>/collection_access_manager_versions_<klucz>/; poprawione tworzenie katalogu przy pierwszym zapisie; opcja „Reset plugin” (usuwa historię, dostępu grup nie zmienia).

## [1.00] - 2026-11-11
- Pierwsza wersja: tabela dostępu grup użytkowników do Featured collections (wiersze = drzewo kategorii i kolekcji, kolumny = grupy); zaznaczenie pozycji zaznacza kategorie nadrzędne, odznaczenie ukrywa całą gałąź; wiersze ogólne j* (wszystkie, także nowe) i J (wyszukiwanie tylko w dostępnych kolekcjach).
- Kody j<ref> / -j<ref> wyliczane automatycznie zgodnie z logiką ResourceSpace; pozostałe uprawnienia grup bez zmian; grupy dziedziczące tylko do odczytu; filtry (wyszukiwanie, differing, unsaved, grupy, strony kolumn), kopiowanie między grupami, historia wersji (ostatnie 50, przywracanie), zapis przez save_usergroup z wpisem w dzienniku aktywności.

