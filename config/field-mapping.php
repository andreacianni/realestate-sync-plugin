<?php
/**
 * RealEstate Sync Field Mapping Configuration
 * 
 * Mapping completo tra campi XML GestionaleImmobiliare e WpResidence
 */

return array(
    
    // Base Property Fields
    'base_fields' => array(
        'post_title' => 'abstract',
        'post_content' => 'description',
        'property_price' => 'price',
        'property_size' => 'mq',
        'property_address' => 'indirizzo',
        'property_city' => 'citta',
        'property_zip' => 'cap',
        'property_state' => 'provincia',
        'property_country' => 'Italy', // Default
        'property_id_gestionale' => 'id',
        'property_ref' => null, // Generated: RS-{id}
        'property_source' => 'GestionaleImmobiliare'
    ),
    
    // Property Details
    'detail_fields' => array(
        'property_bedrooms' => 'numero_camere',
        'property_bathrooms' => 'numero_bagni',
        'property_rooms' => 'numero_camere',
        'property_floors' => 'numero_piani',
        'property_floor' => 'piano',
        'property_year' => 'anno_costruzione',
        'property_energy_class' => 'classe_energetica'
    ),
    
    // Property Categories (categorie_id mapping)
    'categories' => array(
        1 => 'Casa Singola',
        2 => 'Bifamiliare', 
        11 => 'Appartamento',
        12 => 'Attico',
        18 => 'Villa',
        19 => 'Terreno',
        14 => 'Negozio',
        17 => 'Ufficio',
        8 => 'Garage',
        9 => 'Box',
        13 => 'Loft',
        15 => 'Capannone',
        16 => 'Laboratorio',
        20 => 'Rustico',
        21 => 'Castello',
        22 => 'Palazzo'
    ),
    
    // Province Mapping
    'provinces' => array(
        'TN' => 'Trento',
        'BZ' => 'Bolzano',
        'VR' => 'Verona',
        'VI' => 'Vicenza',
        'TV' => 'Treviso',
        'VE' => 'Venezia',
        'PD' => 'Padova',
        'UD' => 'Udine',
        'PN' => 'Pordenone',
        'GO' => 'Gorizia',
        'TS' => 'Trieste'
    ),
    
    // Property Features (info_inserite mapping)
    'features' => array(
        'ascensore' => 'elevator',
        'giardino' => 'garden',
        'piscina' => 'swimming-pool',
        'garage' => 'garage',
        'aria_condizionata' => 'air-conditioning',
        'riscaldamento' => 'heating',
        'balcone' => 'balcony',
        'terrazzo' => 'terrace',
        'arredato' => 'furnished',
        'allarme' => 'alarm',
        'camino' => 'fireplace',
        'mansarda' => 'attic',
        'cantina' => 'basement',
        'soffitta' => 'loft',
        'taverna' => 'tavern',
        'posto_auto' => 'parking',
        'box_auto' => 'garage-box',
        'giardino_privato' => 'private-garden',
        'terrazzo_panoramico' => 'panoramic-terrace',
        'vista_mare' => 'sea-view',
        'vista_montagna' => 'mountain-view',
        'vista_lago' => 'lake-view'
    ),
    
    // Numeric Data Fields (dati_inseriti mapping)
    'numeric_fields' => array(
        'superficie_commerciale' => 'property_size_commercial',
        'superficie_utile' => 'property_size_useful',
        'superficie_giardino' => 'property_garden_size',
        'superficie_terrazzo' => 'property_terrace_size',
        'numero_posti_auto' => 'property_parking_spaces',
        'numero_box' => 'property_garage_count',
        'numero_balconi' => 'property_balcony_count',
        'altezza_soffitti' => 'property_ceiling_height',
        'anno_ristrutturazione' => 'property_renovation_year',
        'spese_condominiali' => 'property_monthly_fees',
        'imu' => 'property_imu_tax',
        'tasi' => 'property_tasi_tax'
    ),
    
    // Location Data
    'location_fields' => array(
        'latitude' => 'property_lat',
        'longitude' => 'property_long',
        'zona' => 'property_area',
        'quartiere' => 'property_neighborhood',
        'comune' => 'property_city',
        'provincia' => 'property_county',
        'regione' => 'property_state',
        'nazione' => 'property_country'
    ),
    
    // Energy Data
    'energy_fields' => array(
        'classe_energetica' => 'property_energy_class',
        'ipe' => 'property_energy_index',
        'certificazione_energetica' => 'property_energy_certificate'
    ),
    
    // Price Fields
    'price_fields' => array(
        'prezzo_vendita' => 'property_price',
        'prezzo_affitto' => 'property_rent_price',
        'prezzo_mq' => 'property_price_per_sqm',
        'caparra' => 'property_deposit',
        'commissioni' => 'property_commission'
    ),
    
    // Custom Import Fields
    'import_fields' => array(
        'property_import_source' => 'GestionaleImmobiliare',
        'property_import_id' => 'id',
        'property_import_date' => null, // Current timestamp
        'property_import_hash' => null, // Generated hash
        'property_last_sync' => null, // Current timestamp
        'property_sync_status' => 'active'
    ),
    
    // Media Files
    'media_fields' => array(
        'gallery_images' => 'file_allegati',
        'main_image' => 'prima_foto',
        'floor_plan' => 'planimetria',
        'virtual_tour' => 'virtual_tour_url'
    ),
    
    // SEO Fields
    'seo_fields' => array(
        'seo_title' => 'meta_title',
        'seo_description' => 'meta_description',
        'seo_keywords' => 'meta_keywords'
    ),
    
    // Required Validation Fields
    'required_fields' => array(
        'id',
        'price',
        'abstract'
    ),
    
    // Field Data Types
    'field_types' => array(
        'property_price' => 'float',
        'property_rent_price' => 'float',
        'property_size' => 'integer',
        'property_bedrooms' => 'integer',
        'property_bathrooms' => 'integer',
        'property_floors' => 'integer',
        'property_floor' => 'integer',
        'property_year' => 'integer',
        'property_lat' => 'float',
        'property_long' => 'float',
        'property_parking_spaces' => 'integer'
    ),
    
    // Default Values
    'default_values' => array(
        'property_country' => 'Italy',
        'property_source' => 'GestionaleImmobiliare',
        'property_currency' => 'EUR',
        'post_status' => 'publish',
        'post_author' => 1,
        'comment_status' => 'closed',
        'ping_status' => 'closed'
    ),
    
    // Transformation Rules
    'transformations' => array(
        'price' => array(
            'type' => 'currency',
            'remove_chars' => array('€', '.', ','),
            'multiply' => 1
        ),
        'mq' => array(
            'type' => 'area',
            'min_value' => 1,
            'max_value' => 10000
        ),
        'anno_costruzione' => array(
            'type' => 'year',
            'min_value' => 1800,
            'max_value' => 2030
        )
    )
);
