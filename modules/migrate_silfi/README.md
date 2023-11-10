# Migrazioni
Editare il file
migrations/d7/migrate_plus.migration.cmis_document.yml
con i dati di alfresco
Verificare mappatura del campo
  field_tipo_di_documento:
    plugin: sub_process
    source: tipo_documento
    process:
      target_id:
        plugin: static_map
        source: tipo_documento
        map:
          'Moduli': 47
          'Allegati': 55
          'Atti': 52
          # 'Delibere':
          # 'Determine':
          # 'Ordinanze':
          'Regolamenti': 51

Editare il file
migrations/d7/migrate_plus.migration.cmis_file_via_json.yml
per settare l'url per il download dei file, ricordarsi di scaricare i file json dal vecchio documentale e metterli nella cartella del modulo
modules/migrate_silfi/json/cmis
e riportare i nomi file nel suddetto file
es: urls:
    - modules/custom/migrate_silfi/json/cmis/alfresco_files.json

Editare il file
migrations/d7/migrate_plus.migration.d7_node_poi.yml
per verificare questa mappatura
  field_tipo_di_luogo:
    plugin: sub_process
    source: field_categoria
    process:
      target_id:
        plugin: static_map
        source: tid
        map:
          441: 56
          444: 318
          442: 203

Editare il file
migrations/d7/migrate_plus.migration.node_servizio.yml
e verificare la mappatura
  field_categoria_del_servizio:
    plugin: sub_process
    source: field_le_guide_tematiche
    process:
      target_id:
        plugin: static_map
        source: target_id
        map:
          244: 36
          246: 41
          398: 29
          250: 34
          243: 31
          252: 31
          245: 27
          247: 38
          251: 39
          242: 28
          248: 28
          241: 40
          249: 45

Per migrare i file privati copiarli nella cartella
/var/opt/migrate/private-files

Editare il file
migrations/d7/migrate_plus.migration.upgrade_d7_file.yml
per impostare il corretto URL del vecchio sito da migrare
