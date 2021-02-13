# Plex Mediathek
Das Plex Mediathek Repository ließt die Plex Mediatheken aus und speichert diese mit Cover, Film/Serien-Name und Inhalt in einer HTML Box. Mit einen Button kann man je nach Anzahl der Medien die Seiten umschalten. Die HTML Tabelle ist im Formular konfigurierbar.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)

### 1. Funktionsumfang

* Ermöglicht das Auslesen der eingenen Plex Mediatheken und speichert diese in einer Variable
* Durch einen Button ist das Vor-und Zurückblättern auf den Seiten mit den Medien möglich.  
* Die HTML Tabelle kann im Konfigurationsformular nach eigenen bedürnissen angepasst werden.

### 2. Voraussetzungen

- IP-Symcon ab Version 5.5

### 3. Software-Installation

* Über das Module Control folgende URL hinzufügen:
    `https://github.com/Housemann/PlexMediathek`

### 4. Einrichten der Instanzen in IP-Symcon

- Unter "Instanz hinzufügen" kann das 'PlexMediathekUpdate'-Modul mithilfe des Schnellfilters gefunden werden.
    - Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)
__Konfigurationsseite__:

Name      | Beschreibung
--------- | ---------------------------------
Variable  | HTML Box (string)
Variable  | Mediatheken (integer)
Variable  | Seitenwechsler (integer)

### 5. Statusvariablen und Profile

Die Statusvariablen werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen. 