<?php

declare(strict_types=1);

return [
    'admin_resource_failed' => 'The structured content item admin resource is not registered.',
    'admin_resource_label' => 'Structured Content Library admin resource',
    'admin_resource_passed' => 'The structured content item admin resource is registered.',
    'admin_resource_remediation' => 'Ensure StructuredContentLibraryServiceProvider contributes the admin resource.',
    'model_registry_failed' => 'StructuredContentItem is not registered with Capell Core.',
    'model_registry_label' => 'Structured Content Library model registry',
    'model_registry_passed' => 'StructuredContentItem is registered with Capell Core.',
    'model_registry_remediation' => 'Ensure StructuredContentLibraryServiceProvider registers the structured content model.',
    'protected_table_failed' => 'The structured_content_items table is not registered as protected.',
    'protected_table_label' => 'Structured Content Library protected table',
    'protected_table_passed' => 'The structured_content_items table is registered as protected.',
    'protected_table_remediation' => 'Ensure StructuredContentLibraryServiceProvider registers structured_content_items as a protected table.',
    'storage_table_failed' => 'The structured_content_items table is missing.',
    'storage_table_label' => 'Structured Content Library storage table',
    'storage_table_passed' => 'The structured_content_items table is present.',
    'storage_table_remediation' => 'Run the Capell migrations to create the structured content library table.',
];
