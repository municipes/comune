# Migrazioni
Vanno editati i file
modules/migrate_silfi_eventi_bandi7/migrations/d7/migrate_plus.migration.d7_node_bandi.yml
settando la riga
notice_types: [2, 5, 6, 32, 793] # valore del campo field_tipo_di_contenuto
con i valori del sito da migrare
anche la mappatura su queste righe
  field_tipo_di_notizia:
    plugin: sub_process
    source: field_tipo_di_contenuto
    process:
      target_id:
        plugin: static_map
        source: target_id
        map:
          2: 32
          5: 9
          6: 21
          32: 9
          793: 9
  field_tipo_di_bando:
    plugin: sub_process
    source: field_tipo_di_contenuto
    process:
      target_id:
        plugin: static_map
        source: target_id
        map:
          5: 1002
          32: 1003
          793: 1001
        # default_value: 1001
        bypass: true
