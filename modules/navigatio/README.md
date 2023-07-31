# Navigatĭo

![GitHub](https://img.shields.io/github/license/municipes/navigatio?style=for-the-badge)
![GitHub release (latest SemVer)](https://img.shields.io/github/v/release/municipes/navigatio?sort=semver&style=for-the-badge)
![Packagist Dependency Version](https://img.shields.io/packagist/dependency-v/municipes/navigatio/drupal/core-recommended?style=for-the-badge)
![Packagist Downloads](https://img.shields.io/packagist/dt/municipes/navigatio?style=for-the-badge)

Navigatĭo è un modulo Drupal che importa i menu utilizzati dai Comuni.

## Requisiti
- Drupal: >= 10
- Profilo Drupal: `minimal`
- Moduli contrib: `migrate_plus`, `migrate_tools`
- Moduli Municipes: `pagina`

## Installazione
Per aggiungere e installare il modulo alla tua installazione esegui:
```bash
$ composer require municipes/navigatio
$ drush -y en navigatio
```
Questo è il risultato

![Screenshot della lista delle migrazioni](docs/migrations.png "Screenshot della lista delle migrazioni")

## Uso del modulo
Puoi utilizzare il modulo:
- da interfaccia grafica;
- da CLI se hai bisogno di automatizzare l'installazione o l'aggiornamento.

In entrambi i casi, leggi la documentazione del modulo
Drupal `migrate` per maggiori dettagli.


## License

Copyright (C) 2023 https://github.com/municipes

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License version 3 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

Questo è un software libero: puoi ridistribuirlo e/o modificarlo secondo i termini della GNU General Public License versione 3 pubblicata dalla Free Software Foundation.

Questo programma è distribuito nella speranza che possa essere utile, ma SENZA ALCUNA GARANZIA; senza nemmeno la garanzia implicita di COMMERCIABILITÀ o IDONEITÀ PER UNO SCOPO PARTICOLARE. Vedere la GNU General Public License per maggiori dettagli.
