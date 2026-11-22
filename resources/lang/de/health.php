<?php

declare(strict_types=1);

return [
    'admin_resource_failed' => 'Die Administrationsressource für Elemente strukturierter Inhalte ist nicht registriert.',
    'admin_resource_label' => 'Bibliothek für strukturierte Inhalte: Element-Editor verfügbar',
    'admin_resource_passed' => 'Die Administrationsressource für Elemente strukturierter Inhalte ist registriert.',
    'admin_resource_remediation' => 'Stellen Sie sicher, dass StructuredContentLibraryServiceProvider die Administrationsressource bereitstellt.',
    'model_registry_failed' => 'StructuredContentItem ist nicht bei Capell Core registriert.',
    'model_registry_label' => 'Bibliothek für strukturierte Inhalte: Inhaltsdatensätze registriert',
    'model_registry_passed' => 'StructuredContentItem ist bei Capell Core registriert.',
    'model_registry_remediation' => 'Stellen Sie sicher, dass StructuredContentLibraryServiceProvider das Modell für strukturierte Inhalte registriert.',
    'protected_table_failed' => 'Die Tabelle structured_content_items ist nicht als geschützt registriert.',
    'protected_table_label' => 'Bibliothek für strukturierte Inhalte: Datenbanktabelle geschützt',
    'protected_table_passed' => 'Die Tabelle structured_content_items ist als geschützt registriert.',
    'protected_table_remediation' => 'Stellen Sie sicher, dass StructuredContentLibraryServiceProvider structured_content_items als geschützte Tabelle registriert.',
    'storage_table_failed' => 'Die Tabelle structured_content_items fehlt.',
    'storage_table_label' => 'Bibliothek für strukturierte Inhalte: Datenbanktabellen vorhanden',
    'storage_table_passed' => 'Die Tabelle structured_content_items ist vorhanden.',
    'storage_table_remediation' => 'Führen Sie die Capell-Migrationen aus, um die Tabelle der Bibliothek für strukturierte Inhalte zu erstellen.',
];
