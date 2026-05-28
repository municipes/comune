# silfi_services

Modulo che espone endpoint REST JSON per consentire a sistemi esterni (es. Call Center) di interrogare i dati del Contact Center. Tutti gli endpoint richiedono autenticazione tramite API key.

## Posizione

Il modulo si trova in `silfi_services/` (root del progetto, non in `web/modules/custom/`). Va abilitato con:

```bash
ddev drush en silfi_services
```

## Autenticazione

Ogni richiesta deve includere l'header HTTP:

```
X-API-Key: <valore>
```

Il valore atteso è `sha1(api_key_configurata + 'SiN$W$B')`. La `api_key` è impostata in `/admin/config/services/silfi-services`.

## Endpoint

### `GET /api/v1/contactcenter/triplette`

Restituisce l'intera alberatura dei termini tassonomici `triplette` (struttura gerarchica macrostruttura > categoria > servizio).

Risposta:
```json
{
  "data": [
    { "name": "...", "tid": 1, "parent_tid": 0 },
    ...
  ],
  "pagination": {}
}
```

---

### `GET /api/v1/contactcenter/servizi`

Lista di servizi con paginazione. Filtrabili per term id tripletta.

Parametri GET:
| Param | Default | Descrizione |
|---|---|---|
| `page` | 1 | Pagina corrente |
| `limit` | 30 | Elementi per pagina |
| `tid` | — | Filtra per `field_triplette` (term ID) |

Risposta:
```json
{
  "data": [
    {
      "nid": 1, "tipo": "servizio", "title": "...",
      "descrizione_breve": "...", "created": "2024-01-01",
      "updated": "2024-01-01 00:00:00", "triplette": [...]
    }
  ],
  "pagination": { "current_page": 1, "total_pages": 5, "total_items": 100, "limit": 30 }
}
```

---

### `GET /api/v1/contactcenter/servizio/{nid}`

Dettaglio completo di un singolo servizio (bundle `servizio`). Restituisce tutti i campi inclusi paragrafi, contatti, orari, documenti, allegati media, schede collegate.

Campi principali nella risposta: `title`, `descrizione_breve`, `field_descrizione`, `field_a_chi_e_rivolto`, `field_come_fare`, `field_cosa_serve`, `field_cosa_si_ottiene`, `field_tempi_e_scadenze`, `field_costi`, `field_condizioni_di_servizio`, `field_documenti`, `field_accedi_al_servizio`, `field_punti_di_contatto` (con orari formattati), `field_allegati`, `field_schede_collegate`, `field_link_esterni`, `breadcrumb` (path triplette).

---

### `GET /api/v1/contactcenter/list/{type}`

Liste brevi per tipo. Valori validi per `{type}`:

| Tipo | Contenuto |
|---|---|
| `servizi` | Nodi `accesso_al_servizio` con `field_link` valorizzato |
| `link_documenti` | *(non ancora implementato)* |
| `link_esterni` | *(non ancora implementato)* |

---

### `GET|POST /api/v1/contactcenter/fullsearch`

Ricerca full-text tramite Search API sull'indice `servizi`. Richiede il modulo `search_api` attivo e l'indice configurato.

Parametri (GET o POST):
| Param | Default | Descrizione |
|---|---|---|
| `keywords` | — | Testo da cercare (solo alfanumerico ASCII, no caratteri speciali) |
| `page` | 1 | Pagina corrente |
| `limit` | 30 | Elementi per pagina |

Risposta uguale a `/servizi`.

## Configurazione

`/admin/config/services/silfi-services`

| Impostazione | Descrizione |
|---|---|
| `api_key` | Chiave API in chiaro (viene hashata SHA1 prima del confronto) |

## Servizi

| Service ID | Classe |
|---|---|
| `silfi_services.node_field_manager` | `ServizioNodeFieldManager` |

`ServizioNodeFieldManager` è un helper con stato: inizializzato con `initNode($nid, 'bundle')`, espone metodi per leggere campi di testo, entità referenziate, file, media con URL assoluti.
