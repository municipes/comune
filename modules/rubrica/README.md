# Rubrica

Provides a staff directory (rubrica) for Drupal sites, exposing an AJAX
search form and a REST API endpoint. The module is part of the
`comune_silfi` distribution and targets municipal websites built on the
Municipes framework.

## Features

- Staff directory with search by first name, last name, and office unit.
- Full-text search via Search API as an alternative to field-based search.
- REST API endpoint returning all published *persona* and
  *unita_organizzativa* nodes in JSON format.
- Optional call-centre mode: the REST endpoint can also return unpublished
  *persona* nodes whose Content Moderation state is `solo_contact_center`.
- Twig templates for rendering individual person and office unit cards.
- Search API processor to exclude irrelevant nodes from the search index.

## Requirements

- Drupal 9.5 or 10.x
- [Basic Auth](https://www.drupal.org/docs/core-modules-and-themes/core-modules/basic-authentication-module) (Drupal core)
- [RESTful Web Services](https://www.drupal.org/docs/core-modules-and-themes/core-modules/restful-web-services-module) (Drupal core)
- [REST UI](https://www.drupal.org/project/restui)
- [Search API](https://www.drupal.org/project/search_api) (optional, required for full-text search)
- Content types: `persona`, `unita_organizzativa`, `incarico`,
  `punto_di_contatto`
- Workflow `persona_solo_contact_center` (provided by `comune_silfi`) for
  call-centre mode

## Installation

This module ships as part of the `comune_silfi` distribution and is
enabled automatically. If you need to enable it manually:

```
drush en rubrica
drush cache:rebuild
```

The REST resource `rubrica_resource` is activated by the configuration in
`config/install/rest.resource.rubrica_resource.yml` and requires no
additional configuration steps.

## Configuration

### Search form

The search form is available at `/rubrica/search` and requires the
`access content` permission.

The office search can also be started from a link: `?office=NNN`, where
`NNN` is the node ID of an *unita_organizzativa*, preselects the office
and renders its results on page load. Results of a search by name use it
to make each organisational unit clickable. An ID that is not a
published, viewable *unita_organizzativa* is ignored and the page behaves
like a plain visit. Political bodies (council, cabinet, committees) are
not listed in the select — it filters on
`field_tipo_di_organizzazione` — but are still reachable this way, and
the select gains the matching option for that request only.

### REST endpoint

| Property       | Value                              |
|----------------|------------------------------------|
| URL            | `/rest/rubrica/api/v1/get/all`     |
| Method         | GET                                |
| Format         | JSON (`?_format=json`)             |
| Authentication | Basic Auth                         |
| Permission     | `restful get rubrica_resource`     |

**Standard request** — returns all published *persona* and
*unita_organizzativa* nodes:

```
GET /rest/rubrica/api/v1/get/all?_format=json
```

**Call-centre mode** — also includes unpublished *persona* nodes whose
Content Moderation state is `solo_contact_center`:

```
GET /rest/rubrica/api/v1/get/all?_format=json&callcenter=true
```

**Opening hours** — every contact entry (`contatti`, and `pocs` in
call-centre mode) carries an additional `orari` key when the underlying
*punto_di_contatto* node has an `office_hours` field (`field_orari`)
filled in. The key is omitted entirely otherwise, so consumers that do
not handle it see an unchanged payload. Hours are normalised to one
entry per time slot:

```json
"orari": [
  { "giorno": 1, "dalle": "08:00", "alle": "13:00", "nota": "" },
  { "giorno": 2, "dalle": "14:30", "alle": "18:00", "nota": "estate esclusa" }
]
```

`giorno` follows the date_api convention used by office_hours
(0 = Sunday … 6 = Saturday). Rows that office_hours stores as *exception
dates* carry a `data` key in `Y-m-d` format instead of `giorno`. The
`office_hours` module is an optional dependency: where it is not
installed the field does not exist and no `orari` key is ever emitted.

**Organisational units of a person** — each *persona* entry carries a
`uo` map of the organisational units the person belongs to. It is built
from the `incarico` nodes linked to the person and collects both:

- the unit the *incarico* is attached to (`field_unita_organizzativa`);
- the unit the person is directly in charge of
  (`field_responsabile_struttura`), which for managers, *elevate
  qualificazioni* and heads of service is often the only link to a unit.

Units of the second kind are appended after the first ones and only when
not already present, so existing entries never move. Every entry exposes
`id` (the unit node ID), `name` and `indirizzo`. In call-centre mode the
map is keyed by unit node ID; in standard mode by *incarico* node ID,
with the prefix `resp-` plus the unit node ID for units taken from
`field_responsabile_struttura`.

### REST UI

The endpoint can be inspected and toggled at
**Administration › Configuration › Web services › REST**
(`/admin/config/services/rest`).

### Permissions

| Permission                        | Description                          |
|-----------------------------------|--------------------------------------|
| `administer rubrica configuration`| Administer rubrica configuration     |
| `restful get rubrica_resource`    | Access the rubrica REST endpoint     |

## Search API index

The module ships with a Search API index configuration
(`search_api.index.rubrica`) and a custom processor
`SearchApiExcludeItemsFromIndex` that removes from the index:

- *persona* nodes not linked to any *incarico* of type 411;
- *unita_organizzativa* nodes whose `field_tipo_di_organizzazione` is
  neither 303 nor 304.

## Architecture

```
rubrica/
├── config/install/
│   ├── rest.resource.rubrica_resource.yml   # REST resource activation
│   └── search_api.index.rubrica.yml         # Search API index
├── src/
│   ├── Form/SearchForm.php                  # AJAX search form
│   ├── Helper/
│   │   ├── FullSearch.php                   # Search API query helper
│   │   ├── RicercaPersonaUo.php             # Entity query helper
│   │   └── TemplateBuilder.php              # Data serialisation helper
│   └── Plugin/
│       ├── rest/resource/RubricaResource.php
│       └── search_api/processor/SearchApiExcludeItemsFromIndex.php
├── templates/
│   ├── persona.html.twig
│   ├── rubrica-item.html.twig
│   └── uo.html.twig
├── rubrica.info.yml
├── rubrica.module                           # hook_theme()
├── rubrica.permissions.yml
├── rubrica.routing.yml
└── rubrica.services.yml
```

### Services

| Service ID                  | Class                | Description                        |
|-----------------------------|----------------------|------------------------------------|
| `rubrica.ricercapersonauo`  | `RicercaPersonaUo`   | Entity queries for the search form |
| `rubrica.fullsearch`        | `FullSearch`         | Full-text Search API queries       |
| `rubrica.templatebuilder`   | `TemplateBuilder`    | Node → array serialisation         |

## Maintainers

This module is maintained as part of the
[Municipes](https://github.com/municipes) project.
